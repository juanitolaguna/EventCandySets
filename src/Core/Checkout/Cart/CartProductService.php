<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class CartProductService
{

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
     * @param CartProduct[] $cartProducts
     */
    public function saveCartProducts(array $cartProducts)
    {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'INSERT INTO ec_cart_product (id, token, line_item_id, product_id, sub_product_id, sub_product_quantity, line_item_quantity)
            values (:id, :token, :line_item_id, :product_id, :sub_product_id, :sub_product_quantity, :line_item_quantity);'
            )
        );

        foreach ($cartProducts as $cartProduct) {
            $query->execute([
                'id' => Uuid::randomBytes(),
                'token' => $cartProduct->getToken(),
                'line_item_id' => Uuid::fromHexToBytes($cartProduct->getLineItemId()),
                'product_id' => Uuid::fromHexToBytes($cartProduct->getProductId()),
                'sub_product_id' => Uuid::fromHexToBytes($cartProduct->getSubProductId()),
                'sub_product_quantity' => $cartProduct->getSubProductQuantity(),
                'line_item_quantity' => $cartProduct->getLineItemQuantity()
            ]);
        }
    }

    /**
     * @param string $token
     * @throws \Doctrine\DBAL\Exception
     */
    public function removeCartProductsByToken(string $token)
    {
        $this->connection->executeStatement(
            "delete from ec_cart_product where `token` = :token;",
            ['token' => $token]);
    }

    /**
     * @param string $lineItemId
     * @throws \Doctrine\DBAL\Exception
     */
    public function removeCartProductsByLineItem(string $lineItemId)
    {
        $this->connection->executeStatement(
            "delete from ec_cart_product cp where cp.line_item_id = :line_item_id;",
            ['line_item_id' => $lineItemId]
        );
    }


}