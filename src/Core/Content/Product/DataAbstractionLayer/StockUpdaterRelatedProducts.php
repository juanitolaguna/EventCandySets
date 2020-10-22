<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use ErrorException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class StockUpdaterRelatedProducts
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
     * @var LoggerInterface
     */
    private $logger;

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
     * StockUpdaterRelatedProducts constructor.
     * @param Connection $connection
     * @param LoggerInterface $logger
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        ProductDefinition $definition,
        CacheClearer $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        string $type
    )
    {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->definition = $definition;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->type = $type;
    }


    public function updateRelatedProductsOnOrderPlaced(array $ids, Context $context)
    {

//        $bytesIds = Uuid::fromHexToBytesList($refIds);

        foreach ($ids as $id) {
            $sqlSetProducts = 'select product_id, product_version_id, quantity from ec_product_product as pp
                    where pp.set_product_id = :id;';

            $rows = $this->connection->fetchAll(
                $sqlSetProducts,
                ['id' => Uuid::fromHexToBytes($id['referencedId'])]
            );

            $quantityQuery = 'select quantity from order_line_item
                WHERE order_line_item.id = :id
                AND order_line_item.type = :orderType
                AND order_line_item.version_id = :version;';

            $this->logger->log(100, 'updateRelatedProductsOnOrderPlaced:ids ' . print_r($id, TRUE));
            $this->logger->log(100, 'updateRelatedProductsOnOrderPlaced:type ' . print_r($this->type, TRUE));

            $quantity = $this->connection->fetchArray(
                $quantityQuery,
                [
                    'id' => Uuid::fromHexToBytes($id['id']),
                    'version' => Uuid::fromHexToBytes($context->getVersionId()),
                    'orderType' => $this->type
                ]
            );

            $this->logger->log(100, 'updateRelatedProductsOnOrderPlaced:rows ' . print_r($rows, TRUE));
            $this->logger->log(100, 'updateRelatedProductsOnOrderPlaced:quantity ' . print_r($quantity, TRUE));

            $this->updateRelatedProductsAvailableStockInnerLoop($rows, intval($quantity[0]));
        }
    }

    private function updateRelatedProductsAvailableStockInnerLoop(array $rows, int $mainProductQuantity)
    {
        $sqlUpdateProducts = 'UPDATE product SET available_stock = (available_stock - (:quantity * :mainProductQuantity))
                                 WHERE product.id = (:productId) AND product.version_id = :productVersionId;
                             ';

        $productIds = [];

        foreach ($rows as $row) {
            // Todo
            $productId = $row['product_id'];
            $productVersionId = $row['product_version_id'];
            $quantity = $row['quantity'];

            // collect for cache clear
            $productIds[] = $productId;

            RetryableQuery::retryable(function () use ($sqlUpdateProducts, $productId, $productVersionId, $quantity, $mainProductQuantity): void {
                $this->connection->executeUpdate(
                    $sqlUpdateProducts,
                    [
                        'productId' => $productId,
                        'productVersionId' => $productVersionId,
                        'quantity' => $quantity,
                        'mainProductQuantity' => $mainProductQuantity
                    ]
                );
            });
        }

//        $this->clearCache($productIds);
    }

    public function updateStockOnStateChange(array $lineItems, int $multiplier, string $stockType)
    {
        if ($stockType == 'available_stock') {
            $sqlUpdateProducts = 'UPDATE product SET available_stock = (available_stock + (:quantity))
                                 WHERE product.id = (:productId) AND product.version_id = :productVersionId;';
        } elseif ($stockType == 'stock') {
            $sqlUpdateProducts = 'UPDATE product SET stock = (stock + (:quantity))
                                 WHERE product.id = (:productId) AND product.version_id = :productVersionId;';
        } else {
            throw new ErrorException('Wrong table type: ' . $stockType);
        }

        $productIds = [];

        foreach ($lineItems as $lineItem) {
            $payload = json_decode($lineItem['payload'], true);
            $lineItemQuantity = $lineItem['quantity'];
            $lineItemSetProductId = $lineItem['referenced_id'];


            foreach ($payload[$this->type] as $subProduct) {
                $quantity = $subProduct['quantity'];
                $product_id = $subProduct['product_id'];
                $product_version_id = $subProduct['product_version_id'];

                // collect for cache
                $productIds[] = $product_id;

                RetryableQuery::retryable(function () use ($sqlUpdateProducts, $product_id, $product_version_id, $quantity, $lineItemQuantity, $multiplier): void {
                    $this->connection->executeUpdate(
                        $sqlUpdateProducts,
                        [
                            'productId' => Uuid::fromHexToBytes($product_id),
                            'productVersionId' => Uuid::fromHexToBytes($product_version_id),
                            'quantity' => $quantity * $lineItemQuantity * $multiplier,
                        ]
                    );
                });
            }
        }

        $this->clearCache($productIds);
    }

    private function clearCache(array $ids): void
    {
        $tags = [];
        $hexIds = Uuid::fromBytesToHexList($ids);
        foreach ($hexIds as $id) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->definition->getEntityName());
        }

        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'id');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'available');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'availableStock');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'stock');

//        $this->logger->log(100, 'clear-cache: ' .print_r($tags, true));
        $this->cache->invalidateTags($tags);
    }

}
