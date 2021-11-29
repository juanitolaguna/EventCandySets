<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class DynamicProductService
{

    public const DYNAMIC_PRODUCT_LINE_ITEM_ID = 'dynamic_product_line_item_id-';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    /**
     * This method should be overwritten by an extending class if LineItem contains more than one product.
     * @param LineItem[] $lineItems
     * @param string $token
     * @return DynamicProduct[]
     */
    public function createDynamicProductCollection(array $lineItems, string $token): array
    {
        $collection = [];
        foreach ($lineItems as $lineItem) {
            $id = Uuid::randomHex();
            $collection[] = new DynamicProduct(
                $id,
                $token,
                $lineItem->getReferencedId(),
                $lineItem->getId()
            );
        }
        return $collection;
    }

    /**
     * @param DynamicProduct[] $dynamicProducts
     * @return string[]
     */
    public function getDynamicProductIdsFromCollection(array $dynamicProducts): array
    {
        return array_map(function (DynamicProduct $product) {
            return $product->getId();
        }, $dynamicProducts);
    }

    /**
     * @param DynamicProduct[] $dynamicProducts
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function saveDynamicProductsToDb(array $dynamicProducts, $isNew = false)
    {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'INSERT INTO ec_dynamic_product (id, token, product_id, line_item_id, is_new) 
                    values (:id, :token, :product_id, :line_item_id, :is_new);'
            )
        );


        foreach ($dynamicProducts as $product) {
            $query->execute([
                'id' => Uuid::fromHexToBytes($product->getId()),
                'token' => $product->getToken(),
                'product_id' => Uuid::fromHexToBytes($product->getProductId()),
                'line_item_id' => $product->getLineItemId(),
                'is_new' => $isNew ? 1 : 0
            ]);
        }
    }


    /**
     * @param string $token
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

    public function resetNewFlag(string $token):void {
        $sql = "UPDATE ec_dynamic_product SET is_new = 0 WHERE token = :token";

        $this->connection->executeStatement($sql, [
            'token' => $token
        ]);
    }

    /**
     * @param array $lineItemIds
     * @param string $token
     * @throws Exception
     */
    public function removeDynamicProductsByLineItemIds(array $lineItemIds, string $token): void
    {
        $this->connection->executeStatement(
            "DELETE FROM ec_dynamic_product WHERE line_item_id IN (:ids) and token = :token;",
            ['ids' => $lineItemIds, 'token' => $token],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    public function preparedlineItemsInCart(array $lineItemIds, string $token)
    {
        $sql = "SELECT
                	count(*) AS total
                FROM (
                	SELECT
                		line_item_id
                	FROM
                		ec_dynamic_product
                	WHERE
                		line_item_id in (:ids)
                		AND token = :token
                	GROUP BY
                		line_item_id) group1;";

        $result = $this->connection->fetchAssociative(
            $sql,
            ['ids' => $lineItemIds, 'token' => $token],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        return (int)$result['total'];
    }

    /**
     * @param array $dynamicProductIds
     * @throws Exception
     */
    public function removeDynamicProductsByNotInIds(array $dynamicProductIds): void
    {
        $dynamicProductIds = array_map(function (string $id) {
            return Uuid::fromHexToBytes($id);
        }, $dynamicProductIds);

        $this->connection->executeStatement("DELETE FROM ec_dynamic_product WHERE id not in (:ids)",
            ['ids' => $dynamicProductIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    /**
     * @param string $lineItemId
     * @param string $token
     * @throws Exception
     */
    public function removeDynamicProductsByLineItemId(string $lineItemId, string $token): void
    {
        $this->connection->executeStatement(
            "DELETE FROM ec_dynamic_product WHERE ec_dynamic_product.line_item_id = :id AND token = :token",
            ['id' => $lineItemId, 'token' => $token],
        );
    }


    /**
     * Creates DynamicProductEntity[] array, that can be accessed
     * with the lineItemId and saves it to the CartDataCollection
     * @param DynamicProductCollection $dynamicProductCollection
     * @param CartDataCollection $data
     */
    public function addDynamicProductsToCartDataByLineItemId(
        DynamicProductCollection $dynamicProductCollection,
        CartDataCollection $data
    ) {
        foreach ($dynamicProductCollection as $product) {
            $lineItemId = $product->getLineItemId();
            $key = self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;

            if ($data->has($key)) {
                $products = $data->get($key);
                $products[] = $product;
                $data->set($key, $products);
            } else {
                $data->set($key, [$product]);
            }
        }
    }

    /**
     * @param string $lineItemId
     * @param CartDataCollection $data
     * @return DynamicProductEntity[]
     */
    public function getFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): ?array
    {
        $key = self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;
        return $data->get($key);
    }

    public function removeDynamicProductsFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data)
    {
        $key = self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;
        $data->remove($key);
    }


}