<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing\ProductStockIndexerTest;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StockUpdater implements EventSubscriberInterface
{


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
     * @var EntityRepositoryInterface
     */
    private $orderLineItemProductRepository;


    /**
     * @var LineItemStockUpdaterFunctionsInterface[]
     */
    private $stockUpdaterFunctionsSupplier;


    /**
     * @var array
     */
    private $productIds = [];


    /**
     * StockUpdater
     * supports:
     * + stock updates on orderPlaced (checkout)
     * + stock updates for all state changes
     * + stock updates for order deletion
     * + stock updates on product stock updates
     * ToDo: StockUpdater... @param Connection $connection
     * - stock updates on lineItem change
     * - stock updates on lineItem quantity change
     * - stock updates on adding new LineItem
     * - stock updates on lineItem removal
     *
     * @param ProductDefinition $definition
     * @param CacheClearer $cache
     * @param EntityCacheKeyGenerator $cacheKeyGenerator
     * @param iterable $stockUpdaterFunctionsSupplier
     * @param EntityRepositoryInterface $orderLineItemProductRepository
     * @link ProductStockIndexerTest
     */
    public function __construct(
        Connection $connection,
        ProductDefinition $definition,
        CacheClearer $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        iterable $stockUpdaterFunctionsSupplier,
        EntityRepositoryInterface $orderLineItemProductRepository
    )
    {
        $this->connection = $connection;
        $this->definition = $definition;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->stockUpdaterFunctionsSupplier = $stockUpdaterFunctionsSupplier;
        $this->orderLineItemProductRepository = $orderLineItemProductRepository;
    }

    /**
     * Returns a list of custom business events to listen where the product maybe changed
     */
    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => ['orderPlaced', -1000],
            StateMachineTransitionEvent::class => ['stateChanged', 1000],
            EntityWrittenContainerEvent::class => ['onProductStockUpdateAfter', -1000],
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => ['lineItemWritten', 1000],
            OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT => ['lineItemWritten', 1000],
            PreWriteValidationEvent::class => 'preWrite'
        ];
    }

    public function preWrite(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            //fetch all related OrderProducts before deletion
            if ($command->getDefinition()->getClass() == OrderDefinition::class && $command instanceof DeleteCommand) {
                $id = $command->getPrimaryKey()['id'];
                if ($id) {
                    $id = Uuid::fromBytesToHex($id);
                    $this->productIds = $this->productIdsOnly($this->getAllProductsOfOrder($id));
                }
                continue;
            }
        }
    }


    /**
     * If the product of an order item changed, the stocks of the old product and the new product must be updated.
     * @param EntityWrittenEvent $event
     * Stops SW StockUpdater lineItemWritten method from execution
     */
    public function lineItemWritten(EntityWrittenEvent $event): void
    {
        //Add Supported Types
        $types = [];
        $types[] = LineItem::PRODUCT_LINE_ITEM_TYPE;
        foreach ($this->stockUpdaterFunctionsSupplier as $supplier) {
            $types[] = $supplier->getLineItemType();
        }

        $ids = $this->productIds;

        foreach ($event->getWriteResults() as $result) {

            if ($result->hasPayload('referencedId')) {
                $ids[] = $result->getProperty('referencedId');
            }

            if ($result->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $changeSet = $result->getChangeSet();
            if (!$changeSet) {
                continue;
            }


            $type = $changeSet->getBefore('type');
            if (!in_array($type, $types)) {
                continue;
            }

            if (!$changeSet->hasChanged('referenced_id') && !$changeSet->hasChanged('quantity')) {
                continue;
            }

            $ids[] = $changeSet->getBefore('referenced_id');
            $ids[] = $changeSet->getAfter('referenced_id');
        }

        $ids = array_merge($ids, $this->productIds);
        $ids = array_filter(array_unique($ids));


        if (empty($ids)) {
            return;
        }

        $this->update($ids, $event->getContext());
        $this->clearCache($ids);
        $this->productIds = [];
        $event->stopPropagation();
    }


    /**
     * (Stops SW StockUpdater stateChanged event from executing)
     * @param StateMachineTransitionEvent $event
     */
    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        // Do not execute stock calculation in SW StockUpdater
        $event->stopPropagation();

        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            $this->decreaseStock($event);
            return;
        }

        if ($event->getFromPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            $this->increaseStock($event);
            return;
        }

        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED || $event->getFromPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            $products = $this->productIdsOnly(
                $this->getALlProductsOfOrder($event->getEntityId())
            );
            $this->updateAvailableStockAndSales($products, $event->getContext());
            $this->updateAvailableFlag($products, $event->getContext());
            $this->clearCache($products);
            return;
        }
    }

    private function increaseStock(StateMachineTransitionEvent $event): void
    {
        $products = $this->getAllProductsOfOrder($event->getEntityId());

        $ids = $this->productIdsOnly($products);

        $this->updateStock($products, +1);

        $this->updateAvailableStockAndSales($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());

        $this->clearCache($ids);
    }

    private function decreaseStock(StateMachineTransitionEvent $event): void
    {
        $products = $this->getAllProductsOfOrder($event->getEntityId());

        $ids = $this->productIdsOnly($products);

        $this->updateStock($products, -1);

        $this->updateAvailableStockAndSales($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());

        $this->clearCache($ids);
    }


    private function updateStock(array $products, int $multiplier): void
    {
        $query = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET stock = stock + :quantity WHERE id = :id AND version_id = :version')
        );

        foreach ($products as $product) {
            $query->execute([
                'quantity' => (int)$product['quantity'] * $multiplier,
                'id' => $product['product_id'],
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }
    }


    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $orderLineItemProducts = [];
        foreach ($this->stockUpdaterFunctionsSupplier as $supplier) {
            foreach ($event->getOrder()->getLineItems() as $lineItem) {
                // each supplier handles its own type
                if ($lineItem->getType() !== $supplier->getLineItemType()) {
                    continue;
                }
                // extract all Products & SubProducts from LineItem
                // to an write ready array for OrderLineItemProduct
                $orderLineItemProductsNew = $supplier->createOrderLineItemProducts($lineItem, $event);
                $orderLineItemProducts = array_merge($orderLineItemProducts, $orderLineItemProductsNew);
            }
        }

        if (count($orderLineItemProducts) > 0) {
            $this->orderLineItemProductRepository->create($orderLineItemProducts, $event->getContext());
        }

        // get all Products For Order
        $productIds = $this->productIdsOnly(
            $this->getAllProductsOfOrder($event->getOrder()->getId())
        );

        $this->update($productIds, $event->getContext());
        $this->clearCache($productIds);
    }

    public function getAllProductsOfOrder(string $orderId): array
    {
        $sql = "select product_id, sum(quantity) as quantity from 
                    (select product_id, quantity 
                    from ec_order_line_item_product where order_id = :id and order_version_id = :versionId
                    union all
                    select product_id, quantity 
                    from order_line_item where order_id = :id and order_version_id = :versionId 
                    and product_id is not null and type = :type) as all_tables
                    group by product_id";
        return $this->connection->fetchAll(
            $sql,
            [
                'id' => Uuid::fromHexToBytes($orderId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE

            ]
        );
    }

    private function productIdsOnly($rows): array
    {
        $rows = array_column($rows, 'product_id');

        $rows = array_filter(array_keys(array_flip($rows)));
        return array_map(function ($row) {
            return Uuid::fromBytesToHex($row);
        }, $rows);
    }


    /**
     * Update available_stock
     * @param array $ids
     * @param Context $context
     */
    public function update(array $ids, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        $this->updateAvailableStockAndSales($ids, $context);
        $this->updateAvailableFlag($ids, $context);
    }


    private function updateAvailableStockAndSales(array $ids, Context $context = null): void
    {
        if (empty($ids)) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = "SELECT all_tables.product_id, 
                SUM(all_tables.open_quantity) as open_quantity, 
                SUM(all_tables.sales_quantity) as sales_quantity
                    FROM(SELECT LOWER(HEX(ec_order_line_item_product.product_id)) as product_id,
                        IFNULL(SUM(IF(state_machine_state.technical_name = :completed_state, 0, ec_order_line_item_product.quantity)),0) as open_quantity,
                        IFNULL(SUM(IF(state_machine_state.technical_name = :completed_state, ec_order_line_item_product.quantity, 0)),0) as sales_quantity
                    FROM ec_order_line_item_product
                        INNER JOIN `order`
                            ON `order`.id = ec_order_line_item_product.order_id
                            AND `order`.version_id = ec_order_line_item_product.order_version_id
                        INNER JOIN state_machine_state
                            ON state_machine_state.id = `order`.state_id
                            AND state_machine_state.technical_name <> :cancelled_state
                    
                    WHERE ec_order_line_item_product.product_id IN (:ids)
                    AND ec_order_line_item_product.order_version_id = :versionId
                    GROUP BY product_id
        
                    UNION ALL
        
                    SELECT
		            LOWER(HEX(order_line_item.product_id)) AS product_id,
		            IFNULL(SUM( IF(state_machine_state.technical_name = :completed_state, 0, order_line_item.quantity)), 0) AS open_quantity,
		            IFNULL(SUM( IF(state_machine_state.technical_name = :completed_state, order_line_item.quantity, 0)), 0) AS sales_quantity
	                FROM
	                	order_line_item
	                	INNER JOIN `order` ON `order`.id = order_line_item.order_id AND `order`.version_id = order_line_item.order_version_id
	                	INNER JOIN state_machine_state ON state_machine_state.id = `order`.state_id AND state_machine_state.technical_name <> :cancelled_state
	                WHERE
	        	        order_line_item.product_id IN (:ids) AND order_line_item.product_id IS NOT NULL AND order_line_item. `type` = :product_type
                        AND order_line_item.order_version_id = :versionId
	                GROUP BY product_id) AS all_tables GROUP BY product_id;";


        $rows = $this->connection->fetchAll(
            $sql,
            [
                'completed_state' => OrderStates::STATE_COMPLETED,
                'cancelled_state' => OrderStates::STATE_CANCELLED,
                'ids' => $bytes,
                'product_type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'versionId' => Uuid::fromHexToBytes($context->getVersionId())
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY
            ]
        );


        $fallback = array_column($rows, 'product_id');
        $fallback = array_diff($ids, $fallback);

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET available_stock = stock - :open_quantity, sales = :sales_quantity, updated_at = :now WHERE id = :id')
        );

        foreach ($fallback as $id) {
            $update->execute([
                'id' => Uuid::fromHexToBytes((string)$id),
                'open_quantity' => 0,
                'sales_quantity' => 0,
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        foreach ($rows as $row) {
            $update->execute([
                'id' => Uuid::fromHexToBytes($row['product_id']),
                'open_quantity' => $row['open_quantity'],
                'sales_quantity' => $row['sales_quantity'],
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

    }

    private function updateAvailableFlag(array $ids, Context $context): void
    {
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
            AND product.version_id = :version;';

        RetryableQuery::retryable(function () use ($sql, $context, $bytes): void {
            $this->connection->executeUpdate(
                $sql,
                [
                    'ids' => $bytes,
                    'version' => Uuid::fromHexToBytes($context->getVersionId())
                ],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });
    }

    public function onProductStockUpdateAfter(EntityWrittenContainerEvent $event)
    {
        $ids = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);
        $this->updateAvailableStockAndSales($ids, $event->getContext());
        $this->updateAvailableFlag($ids, $event->getContext());
        $this->clearCache($ids);
    }

    private function clearCache(array $ids): void
    {
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
}
