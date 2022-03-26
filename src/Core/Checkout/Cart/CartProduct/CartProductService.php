<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartProduct;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Checkout\Cart\Exception\DynamicProductsInCartDataCollectionMissingException;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadService;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartProductService
{
    protected Connection $connection;

    protected DynamicProductService $dynamicProductService;

    public function __construct(Connection $connection, DynamicProductService $dynamicProductService)
    {
        $this->connection = $connection;
        $this->dynamicProductService = $dynamicProductService;
    }

    /**
     * @param array<CartProduct> $cartProducts
     * @throws Exception
     */
    public function saveCartProducts(array $cartProducts)
    {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'INSERT INTO ec_cart_product (id, unique_id, token, line_item_id, product_id, sub_product_id, sub_product_quantity, line_item_quantity, line_item_type, created_at)
            values (:id, :unique_id, :token, :line_item_id, :product_id, :sub_product_id, :sub_product_quantity, :line_item_quantity, :line_item_type, :created_at);'
            )
        );

        foreach ($cartProducts as $cartProduct) {
            $query->execute([
                'id' => Uuid::randomBytes(),
                'unique_id' => Uuid::fromHexToBytes($cartProduct->getUniqueId()),
                'token' => $cartProduct->getToken(),
                'line_item_id' => $cartProduct->getLineItemId(),
                'product_id' => Uuid::fromHexToBytes($cartProduct->getProductId()),
                'sub_product_id' => Uuid::fromHexToBytes($cartProduct->getSubProductId()),
                'sub_product_quantity' => $cartProduct->getSubProductQuantity(),
                'line_item_quantity' => $cartProduct->getLineItemQuantity(),
                'line_item_type' => $cartProduct->getLineItemType(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public function removeCartProductsByTokenAndType(string $token, string $type)
    {
        $this->connection->executeStatement(
            "delete from ec_cart_product where `token` = :token and line_item_type = :type;",
            ['token' => $token, 'type' => $type]
        );
    }

    /**
     * @throws Exception
     */
    public function removeCartProductsByToken(string $token)
    {
        $this->connection->executeStatement(
            "delete from ec_cart_product where `token` = :token;",
            ['token' => $token]
        );
    }

    /**
     * @throws Exception
     */
    public function removeCartProductsByLineItemIds(array $lineItemIds, string $token)
    {
        $this->connection->executeStatement(
            "delete from ec_cart_product where line_item_id in (:ids) and token = :token;",
            ['ids' => $lineItemIds, 'token' => $token],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    /**
     * @throws Exception
     */
    public function removeCartProductsByLineItem(string $lineItemId, string $token)
    {
        $this->connection->executeStatement(
            "delete from ec_cart_product where line_item_id = :line_item_id and token = :token;",
            ['line_item_id' => Uuid::fromHexToBytes($lineItemId), 'token' => $token]
        );
    }

    /**
     * @param LineItem $lineItem
     * @param CartDataCollection $data
     * @param string $type
     * @return array
     * @throws DynamicProductsInCartDataCollectionMissingException
     */
    public function buildCartProductsFromPayload(
        LineItem $lineItem,
        CartDataCollection $data,
        string $type
    ): array {
        /** @var DynamicProductEntity[] $product */
        $products = $this->dynamicProductService->getFromCartDataByLineItemId($lineItem->getId(), $data);
        if (!$products) {
            throw new DynamicProductsInCartDataCollectionMissingException($lineItem);
        }

        /** @var CartProduct[] $cartProducts */
        $cartProducts = [];

        foreach ($products as $product) {
            $key = PayloadService::getPayloadKey($product->getId());
            if (!$data->has($key)) {
                continue;
            }
            $payload = $data->get($key);

            $cartProducts = array_merge($cartProducts, $this->createCartProductsFromPayload($lineItem, $product, $payload, $type));
        }
        return $cartProducts;
    }

    /**
     * @param LineItem $lineItem
     * @param DynamicProductEntity $dynamicProductEntity
     * @param array $payload
     * @param string $type
     * @return array
     */
    private function createCartProductsFromPayload(
        LineItem $lineItem,
        DynamicProductEntity $dynamicProductEntity,
        array $payload,
        string $type
    ): array {
        $cartProducts = [];
        foreach ($payload as $row) {
            $subProductId = Uuid::fromBytesToHex($row['product_id']);

            $cartProducts[] = new CartProduct(
                $dynamicProductEntity->getId(),
                $dynamicProductEntity->getToken(),
                $dynamicProductEntity->getLineItemId(),
                $dynamicProductEntity->getProductId(),
                $subProductId,
                (int)$row['quantity'] * $lineItem->getQuantity(),
                $lineItem->getQuantity(),
                $type
            );
        }
        return $cartProducts;
    }

    /**
     * @param array $dynamicProductIds
     * @throws Exception
     */
    public function removeCartProductsByNotInIds(array $dynamicProductIds)
    {
        $dynamicProductIds = array_map(function (string $id) {
            return Uuid::fromHexToBytes($id);
        }, $dynamicProductIds);

        $this->connection->executeStatement("DELETE FROM ec_cart_product WHERE unique_id not in (:ids)",
            ['ids' => $dynamicProductIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    /**
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function getCartProductsByToken(string $token): array {
        $sql = "SELECT * FROM ec_cart_product WHERE token = :token";
        return $this->connection->fetchAllAssociative($sql, ['token' => $token]);
    }
}
