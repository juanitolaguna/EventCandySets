<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use ErrorException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class StockUpdaterRelatedProducts
{
    public const TYPE = 'setproduct';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * StockUpdaterRelatedProducts constructor.
     * @param Connection $connection
     * @param LoggerInterface $logger
     */
    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }


    public function updateRelatedProductsOnOrderPlaced(array $ids, Context $context)
    {

        $bytesIds = Uuid::fromHexToBytesList($ids);

        foreach ($bytesIds as $id) {
            $sqlSetProducts = 'select product_id, product_version_id, quantity from ec_product_product as pp
                    where pp.set_product_id = :id;';

            $rows = $this->connection->fetchAll(
                $sqlSetProducts,
                ['id' => $id]
            );

            $setQuantity = 'select quantity from order_line_item
                WHERE LOWER(order_line_item.referenced_id) = LOWER(HEX(:productId))
                AND order_line_item.type = :orderType
                AND order_line_item.version_id = :version;';


            $quantity = $this->connection->fetchArray(
                $setQuantity,
                [
                    'productId' => $id,
                    'orderType' => self::TYPE,
                    'version' => Uuid::fromHexToBytes($context->getVersionId())
                ]
            );

            $this->logger->log(100, 'updateRelatedProductsOnOrderPlaced:rows ' . print_r($rows, TRUE)) ;
            $this->logger->log(100, 'updateRelatedProductsOnOrderPlaced:quantity ' . print_r(intval($quantity[0]), TRUE)) ;

            $this->updateRelatedProductsAvailableStockInnerLoop($rows, intval($quantity[0]));
        }
    }

    private function updateRelatedProductsAvailableStockInnerLoop(array $rows, int $mainProductQuantity)
    {
        $sqlUpdateProducts = 'UPDATE product SET available_stock = (available_stock - (:quantity * :mainProductQuantity))
                                 WHERE product.id = (:productId) AND product.version_id = :productVersionId;
                             ';

        foreach ($rows as $row) {
            // Todo
            $productId = $row['product_id'];
            $productVersionId = $row['product_version_id'];
            $quantity = $row['quantity'];

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



        foreach ($lineItems as $lineItem) {
            $payload = json_decode($lineItem['payload'], true);
            $lineItemQuantity = $lineItem['quantity'];
            $lineItemSetProductId = $lineItem['referenced_id'];

            foreach ($payload[self::TYPE] as $subProduct) {
                $quantity = $subProduct['quantity'];
                $product_id = $subProduct['product_id'];
                $product_version_id = $subProduct['product_version_id'];

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
    }

}
