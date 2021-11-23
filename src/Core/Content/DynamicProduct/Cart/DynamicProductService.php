<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Checkout\Cart\Cart;
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
     * @throws Exception
     */
    public function saveDynamicProductsToDb(array $dynamicProducts)
    {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'INSERT INTO ec_dynamic_product (id, token, product_id, line_item_id) 
                    values (:id, :token, :product_id, :line_item_id);'
            )
        );

        foreach ($dynamicProducts as $product) {
            $query->execute([
                'id' => Uuid::fromHexToBytes($product->getId()),
                'token' => $product->getToken(),
                'product_id' => Uuid::fromHexToBytes($product->getProductId()),
                'line_item_id' => $product->getLineItemId()
            ]);
        }
    }

    /**
     * @param string $token
     * @throws Exception
     */
    public function removeDynamicProductsByToken(string $token): void
    {
        $this->connection->executeStatement("DELETE FROM ec_dynamic_product WHERE token = :token", [
            'token' => $token
        ]);
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
    public function getFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): array
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