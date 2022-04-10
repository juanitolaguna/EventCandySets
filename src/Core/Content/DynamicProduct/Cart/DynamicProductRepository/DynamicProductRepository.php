<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductStruct;
use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection\DynamicProductStructCollectionInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class DynamicProductRepository implements DynamicProductRepositoryInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function saveDynamicProductsToDb(
        DynamicProductStructCollectionInterface $dynamicProducts,
        $isNew = false
    ): void {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'INSERT INTO ec_dynamic_product (id, token, product_id, line_item_id, is_new, created_at)
                    values (:id, :token, :product_id, :line_item_id, :is_new, :created_at);'
            )
        );

        foreach ($dynamicProducts as $product) {
            $query->execute([
                'id' => Uuid::fromHexToBytes($product->getId()),
                'token' => $product->getToken(),
                'product_id' => Uuid::fromHexToBytes($product->getProductId()),
                'line_item_id' => $product->getLineItemId(),
                'is_new' => $isNew ? 1 : 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public function removeDynamicProductsByToken(string $token, bool $excludeNew = false): void
    {
        if ($excludeNew) {
            $sql = "DELETE FROM ec_dynamic_product WHERE token = :token and is_new != 1";
        } else {
            $sql = "DELETE FROM ec_dynamic_product WHERE token = :token";
        }

        $this->connection->executeStatement($sql, [
            'token' => $token
        ]);
    }

    public function resetNewFlag(string $token): void
    {
        $sql = "UPDATE ec_dynamic_product SET is_new = 0 WHERE token = :token";

        $this->connection->executeStatement($sql, [
            'token' => $token
        ]);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getDynamicProductIds(string $token, array $lineItems): array
    {
        $sql = "SELECT id FROM ec_dynamic_product WHERE token = :token AND line_item_id IN (:ids)";

        $bytesIds = array_map(function (LineItem $lineItem) {
            return Uuid::fromHexToBytes($lineItem->getId());
        }, $lineItems);

        return $this->connection->fetchFirstColumn(
            $sql,
            [
                'ids' => $bytesIds,
                'token' => $token
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );
    }
}