<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use ErrorException;
use EventCandy\LabelMe\Core\Checkout\Cart\EclmCartProcessor;
use EventCandy\Sets\Core\Checkout\Cart\SetProductCartProcessor;
use EventCandy\Sets\Utils;
use Hoa\Exception\Error;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StockUpdater implements EventSubscriberInterface
{

    /**
     * @var string
     */
    private $type;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var StockUpdaterRelatedProducts
     */
    private $relatedProducts;

    /**
     * @var array
     */
    private $quantityBefore;

    private $orderStateNotDoneOrCanceled = false;

    public const AVAILABLE_STOCK = 'available_stock';
    public const STOCK = 'stock';


    public function __construct(
        Connection $connection,
        ProductDefinition $definition,
        CacheClearer $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        StockUpdaterRelatedProducts $relatedProducts,
        string $type
    )
    {
        $this->connection = $connection;
        $this->definition = $definition;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->relatedProducts = $relatedProducts;
        $this->type = $type;
    }

    /**
     * Returns a list of custom business events to listen where the product maybe changed
     */
    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
            StateMachineTransitionEvent::class => 'stateChanged',
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'lineItemWritten',
            OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT => 'lineItemWritten',

            EntityWrittenContainerEvent::class => [
                ['onProductStockUpdateAfter', -1000]
            ],

            PreWriteValidationEvent::class => [
                ['onProductStockUpdateBefore', 0],
                ['beforeOrderDeletion', 0]
            ]
        ];
    }

    public function beforeOrderDeletion(PreWriteValidationEvent $event): void
    {
        //execute only once, since same class is instantiated twice;
        if ($this->type !== SetProductCartProcessor::TYPE) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            //is executed for each line item in order: not good!
            if ($command instanceof DeleteCommand && $command->getDefinition() instanceof OrderLineItemDefinition) {
                $this->checkOrderStateNotDoneOrCanceled($command, $event);
                Utils::log($this->orderStateNotDoneOrCanceled ? 'true' : 'false');
            }
        }
    }

    /**
     * SubProduct available_stock recalculation on manual stock update;
     * To avoid extra calculations get the stock quantity
     * before stock was updated by the main Stock Updater
     *
     * Contains Delete Logic
     * @param EntityWrittenContainerEvent $event
     */
    public function onProductStockUpdateBefore(PreWriteValidationEvent $event): void
    {
        //execute only once, since same class is instantiated twice;
        if ($this->type !== SetProductCartProcessor::TYPE) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            // check quantity before update
            if ($command instanceof UpdateCommand && array_key_exists('stock', $command->getPayload())) {
                $id = $command->getPrimaryKey()['id'];
                $sql = 'select stock, available_stock from product
                where product.id = :id
                and product.version_id = :version';
                $quantity = $this->connection->fetchArray(
                    $sql,
                    [
                        'id' => $id,
                        'version' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
                    ]
                );
                $this->quantityBefore = $quantity;
            }
        }
    }

    /**
     * SubProduct available_stock recalculation on manual stock update;
     * Get new stock, compare with old stock, recalculate available stock, based on the diference
     * rather than on open orders.
     *
     * Performance decision:
     * - No pure sql join with open orders possible -> subproducts are stored in JSON Payload field.
     * - iteraring with php rather slow & error prone.
     *
     * @param EntityWrittenContainerEvent $event
     */
    public function onProductStockUpdateAfter(EntityWrittenContainerEvent $event): void
    {

        Utils::log(print_r($this->quantityBefore, true));

        //execute only once, since same class is instantiated twice;
        if ($this->type !== SetProductCartProcessor::TYPE) {
            return;
        }

        $ids = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);
        $ids = array_filter(array_keys(array_flip($ids)));
        if (empty($ids) || count($ids) > 1) {
            return;
        }

        $byteId = Uuid::fromHexToBytesList($ids)[0];

        //check if product is a subproduct
        if (!$this->checkIfSubProduct($byteId, $event)) {
            // override SwStockUpdater, include custom line item types
            $this->updateAvailableStock([$ids[0]], $event->getContext());
            return;
        }

        // get new stock
        $sql = 'select stock, available_stock from product
                where product.id = :id
                and product.version_id = :version';

        $quantityAfter = $this->connection->fetchArray(
            $sql,
            [
                'id' => $byteId,
                'version' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
            ]
        );

        Utils::log('quantityBefore: ' . print_r($this->quantityBefore, true));
        Utils::log('quantityAfter: ' . print_r($quantityAfter, true));

        // recalculate available_stock of subproduct
        $stockDifference = $quantityAfter[0] - $this->quantityBefore[0];
        $availableStock = $this->quantityBefore[1] + $stockDifference;

        Utils::log('new available stock: ' . $availableStock);

        $updateSql = 'UPDATE product SET available_stock = :availableStock
                      WHERE id = :id AND version_id = :version;';

        RetryableQuery::retryable(function () use ($updateSql, $event, $byteId, $availableStock): void {
            $this->connection->executeUpdate(
                $updateSql,
                [
                    'availableStock' => $availableStock,
                    'id' => $byteId,
                    'version' => Uuid::fromHexToBytes($event->getContext()->getVersionId())
                ]
            );
        });

        $this->quantityBefore = [];
    }


    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
        Utils::log('');
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (!$command instanceof ChangeSetAware) {
                continue;
            }
            /** @var ChangeSetAware|InsertCommand|UpdateCommand $command */
            if ($command->getDefinition()->getEntityName() !== OrderLineItemDefinition::ENTITY_NAME) {
                continue;
            }
            if ($command instanceof DeleteCommand) {
                $command->requestChangeSet();
                continue;
            }
            if ($command->hasField('referenced_id') || $command->hasField('product_id') || $command->hasField('quantity')) {
                $command->requestChangeSet();
                continue;
            }
        }
    }

    /**
     * If the product of an order item changed, the stocks of the old product and the new product must be updated.
     * [order deleted]
     */
    public function lineItemWritten(EntityWrittenEvent $event): void
    {
        $ids = [];
        $toDelete = [];


        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $changeSet = $result->getChangeSet();
            if (!$changeSet) {
                continue;
            }

            $type = $changeSet->getBefore('type');

            if ($type !== $this->type) {
                continue;
            }

            if (!$changeSet->hasChanged('referenced_id') && !$changeSet->hasChanged('quantity')) {
                continue;
            }

            // Select orders to delete explicitly
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                $toDelete[] = [
                    'payload' => $result->getChangeSet()
                ];
            }

            $ids[] = $changeSet->getBefore('referenced_id');
            $ids[] = $changeSet->getAfter('referenced_id');
        }


        $ids = array_filter(array_unique($ids));
        if (empty($ids)) {
            return;
        }


        foreach ($toDelete as $delete) {
            // cancel update if order is not open
            if (!$this->orderStateNotDoneOrCanceled) {
                return;
            }
            Utils::log('getBefore on delete: ' . print_r($delete['payload']->getBefore(null), true));
            $this->relatedProducts->updateStockOnStateChange([$delete['payload']->getBefore(null)], +1, self::AVAILABLE_STOCK);
        }
        $this->orderStateNotDoneOrCanceled = false;

        $this->update($ids, $event->getContext());
        $this->clearCache($ids);

    }

    /**
     * Change Stock on order movements, open, done, cancel, reopen.
     * LineItem type check partly in sub methods [done, reopen], partly in this method [cancel]
     * @param StateMachineTransitionEvent $event
     * @throws \ErrorException
     */
    public function stateChanged(StateMachineTransitionEvent $event): void
    {

        Utils::log('start');
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            Utils::log('decreaseStock');
            $this->decreaseStock($event);
            return;
        }

        if ($event->getFromPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            Utils::log('increaseStock');
            $this->increaseStock($event);
            return;
        }

        // increase available stock on cancel
        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            // type checked here
            $products = $this->getProductsOfOrder($event->getEntityId());

            if (empty($products)) {
                return;
            }

            $ids = array_column($products, 'referenced_id');

            $this->updateAvailableStock($ids, $event->getContext());
            $this->updateAvailableFlag($ids, $event->getContext());

            //increase available stock
            $this->relatedProducts->updateStockOnStateChange($products, +1, self::AVAILABLE_STOCK);
            $this->clearCache($ids);
            return;
        }

        //decrease available stock on reopen
        if ($event->getFromPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            // type checked here
            $products = $this->getProductsOfOrder($event->getEntityId());

            if (empty($products)) {
                return;
            }

            $ids = array_column($products, 'referenced_id');

            $this->updateAvailableStock($ids, $event->getContext());
            $this->updateAvailableFlag($ids, $event->getContext());

            // decrease available stock
            $this->relatedProducts->updateStockOnStateChange($products, -1, self::AVAILABLE_STOCK);
            $this->clearCache($ids);
            return;
        }
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {

        $refIds = [];
        $ids = [];

        /** @var LineItem $lineItem */
        foreach ($event->getOrder()->getLineItems() as $lineItem) {
            // Same type check as in SW StockUpdater
            if ($lineItem->getType() !== $this->type) {
                continue;
            }

            Utils::log($this->type . ' stock updates wil be executed');

            $refId = $lineItem->getReferencedId();
            $refIds[] = $refId;

            // get lineItem ids and the referenced products for advanced stock calculation
            $ids[] = [
                'id' => $lineItem->getId(),
                'referencedId' => $refId
            ];
        }

        // missing line in SW Stock updater - hence update() is invoked with empty array.
        if (empty($ids)) {
            return;
        }

        $this->relatedProducts->updateRelatedProductsOnOrderPlaced($ids, $event->getContext());
        $this->update($refIds, $event->getContext());
        $this->clearCache($refIds);
    }


    /**
     * Update available_stock
     * @param array $ids
     * @param Context $context
     */
    public function update(array $ids, Context $context): void
    {
        Utils::log($this->type);
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->updateAvailableStock($ids, $context);
        $this->updateAvailableFlag($ids, $context);
    }

    private function increaseStock(StateMachineTransitionEvent $event): void
    {
        // type is filtered here
        $products = $this->getProductsOfOrder($event->getEntityId());

        if (empty($products)) {
            return;
        }

        $ids = array_column($products, 'referenced_id');

        Utils::log(print_r($products, true));

        $this->updateStock($products, +1);

        $this->relatedProducts->updateStockOnStateChange($products, +1, self::STOCK);

        $this->clearCache($ids);
    }

    private function decreaseStock(StateMachineTransitionEvent $event): void
    {
        // type is filtered here
        $products = $this->getProductsOfOrder($event->getEntityId());
        if (empty($products)) {
            return;
        }

        $ids = array_column($products, 'referenced_id');

        Utils::log(print_r($products, true));

        $this->updateStock($products, -1);

        $this->relatedProducts->updateStockOnStateChange($products, -1, self::STOCK);

        $this->clearCache($ids);
    }

    private function updateAvailableStock(array $ids, Context $context): void
    {
        $ids = array_filter(array_keys(array_flip($ids)));

        if (empty($ids)) {
            return;
        }

        Utils::log($this->type);

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = '
            UPDATE product SET available_stock = stock - (SELECT IFNULL(SUM(order_line_item.quantity), 0)
            FROM order_line_item
            INNER JOIN `order`
                ON `order`.id = order_line_item.order_id
                AND `order`.version_id = order_line_item.order_version_id
            INNER JOIN state_machine_state
                ON state_machine_state.id = `order`.state_id
                AND state_machine_state.technical_name NOT IN (:states)
            WHERE LOWER(order_line_item.referenced_id) = LOWER(HEX(product.id))
                AND order_line_item.type in (:types)
                AND order_line_item.version_id = :version
            )WHERE product.id IN (:ids) AND product.version_id = :version;';


        RetryableQuery::retryable(function () use ($sql, $bytes, $context): void {
            $this->connection->executeUpdate(
                $sql,
                [
                    'types' => [LineItem::PRODUCT_LINE_ITEM_TYPE, EclmCartProcessor::TYPE, SetProductCartProcessor::TYPE],
                    'version' => Uuid::fromHexToBytes($context->getVersionId()),
                    'states' => [OrderStates::STATE_COMPLETED, OrderStates::STATE_CANCELLED],
                    'ids' => $bytes,
                ],
                [
                    'ids' => Connection::PARAM_STR_ARRAY,
                    'states' => Connection::PARAM_STR_ARRAY,
                    'types' => Connection::PARAM_STR_ARRAY
                ]
            );
        });
    }

    private function updateAvailableFlag(array $ids, Context $context): void
    {
        $ids = array_filter(array_keys(array_flip($ids)));

        if (empty($ids)) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = '
            UPDATE product
            LEFT JOIN product parent
                ON parent.id = product.parent_id
                AND parent.version_id = product.version_id

            SET product.available = IFNULL((
                IFNULL(product.is_closeout, parent.is_closeout) * product.available_stock
                >=
                IFNULL(product.is_closeout, parent.is_closeout) * IFNULL(product.min_purchase, parent.min_purchase)
            ), 0)
            WHERE product.id IN (:ids)
            AND product.version_id = :version
        ';

        RetryableQuery::retryable(function () use ($sql, $context, $bytes): void {
            $this->connection->executeUpdate(
                $sql,
                ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });
    }

    private function updateStock(array $products, int $multiplier): void
    {
        $query = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET stock = stock + :quantity WHERE id = :id AND version_id = :version')
        );

        foreach ($products as $product) {
            $query->execute([
                'quantity' => (int)$product['quantity'] * $multiplier,
                'id' => Uuid::fromHexToBytes($product['referenced_id']),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }
    }

    private function getProductsOfOrder(string $orderId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['referenced_id', 'quantity', 'payload']);
        $query->from('order_line_item');
        $query->andWhere('type = :type');
        $query->andWhere('order_id = :id');
        $query->andWhere('version_id = :version');
        $query->setParameter('id', Uuid::fromHexToBytes($orderId));
        $query->setParameter('version', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('type', $this->type);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function clearCache(array $ids): void
    {
        Utils::log(print_r($ids, true));
        $tags = [];
        foreach ($ids as $id) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->definition->getEntityName());
        }

        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'id');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'available');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'availableStock');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'stock');

        $this->cache->invalidateTags($tags);
    }

    /**
     * @param $bytes
     * @param EntityWrittenContainerEvent $event
     */
    private function checkIfSubProduct($id, EntityWrittenContainerEvent $event): bool
    {
        $isSubProductSql = 'select count(*) from ec_product_product as pp
                            where pp.product_id = :id and pp.product_version_id = :version';
        $isSubProduct = $this->connection->fetchArray(
            $isSubProductSql,
            [
                'id' => $id,
                'version' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
            ]
        );

        Utils::log(print_r($isSubProduct, true));

        return $isSubProduct[0] > 0;

    }

    /**
     * @param $command
     * @param $event
     * @return string
     */
    private function checkOrderStateNotDoneOrCanceled($command, $event): void
    {
        $sql = 'select count(*) from order_line_item
                    inner join `order` on order.id = order_line_item.order_id
                    inner join 	state_machine_state on state_machine_state.id = `order`.state_id
                    and state_machine_state.technical_name not in (:states)
                    where order_line_item.id = :id and order_line_item.version_id = :version';

        $result = $this->connection->fetchArray(
            $sql,
            [
                'id' => $command->getPrimaryKey()['id'],
                'version' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
                'states' => [OrderStates::STATE_COMPLETED, OrderStates::STATE_CANCELLED],
            ],
            [
                'states' => Connection::PARAM_STR_ARRAY,
            ]
        );

        if ($result[0] === '1') {
            $this->orderStateNotDoneOrCanceled = true;
        } else {
            $this->orderStateNotDoneOrCanceled = false;
        }
    }


}
