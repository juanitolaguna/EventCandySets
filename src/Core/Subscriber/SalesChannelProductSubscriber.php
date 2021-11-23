<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use ErrorException;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelProductSubscriber implements EventSubscriberInterface
{

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @param Connection $connection
     * @param CartPersisterInterface $persister
     */
    public function __construct(Connection $connection, CartPersisterInterface $persister)
    {
        $this->connection = $connection;
        $this->persister = $persister;
    }


    public static function getSubscribedEvents()
    {
        return [
            'sales_channel.product.loaded' => 'salesChannelProductLoaded'
        ];
    }

    public function salesChannelProductLoaded(SalesChannelEntityLoadedEvent $event)
    {
        $context = $event->getSalesChannelContext();
        if ($this->cartHasLineItems($context)) {
            $this->calculateStockWithCart($event);
        } else {
            $this->calculateStockWithoutCart($event);
        }
    }

    private function calculateStockWithCart(SalesChannelEntityLoadedEvent $event): void
    {
        /** @var SalesChannelProductEntity $salesChannelProduct */
        foreach ($event->getEntities() as $salesChannelProduct) {
            if (!$this->isSetProduct($salesChannelProduct)) {
                continue;
            }
            $stock = $this->getStockWithCart($event, $salesChannelProduct);
            $salesChannelProduct->setAvailableStock((int)$stock['available_stock']);
            $salesChannelProduct->setStock((int)$stock['stock']);
            DynamicProductSubscriber::setAvailability($salesChannelProduct, (int)$stock['available_stock']);
        }
    }

    private function calculateStockWithoutCart(SalesChannelEntityLoadedEvent $event): void
    {
        /** @var SalesChannelProductEntity $salesChannelProduct */
        foreach ($event->getEntities() as $salesChannelProduct) {
            if (!$this->isSetProduct($salesChannelProduct)) {
                continue;
            }
            $stock = $this->getStockWithoutCart($event, $salesChannelProduct);
            $salesChannelProduct->setAvailableStock((int)$stock['available_stock']);
            $salesChannelProduct->setStock((int)$stock['stock']);
            DynamicProductSubscriber::setAvailability($salesChannelProduct, (int)$stock['available_stock']);
        }
    }

    /**
     * @param SalesChannelEntityLoadedEvent $event
     * @param SalesChannelProductEntity $product
     * @return array
     * @throws ErrorException
     */
    private function getStockWithCart(SalesChannelEntityLoadedEvent $event, SalesChannelProductEntity $product): array
    {
        $sql = $this->sqlWithCart();
        try {
            $result = $this->connection->fetchAssociative($sql, [
                'token' => $event->getSalesChannelContext()->getToken(),
                'mainProductId' => Uuid::fromHexToBytes($product->getId()),
                'uniqueId' => Uuid::fromHexToBytes($product->getId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }
        return $result;
    }

    private function getStockWithoutCart(
        SalesChannelEntityLoadedEvent $event,
        SalesChannelProductEntity $salesChannelProduct
    ) {
        $sql = $this->sqlWithoutCart();
        try {
            $result = $this->connection->fetchAssociative($sql, [
                'productId' => Uuid::fromHexToBytes($salesChannelProduct->getId()),
                'version' => Uuid::fromHexToBytes($event->getSalesChannelContext()->getVersionId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }
        return $result;
    }


    private function isSetProduct(SalesChannelProductEntity $productEntity): bool
    {
        return array_key_exists('ec_is_set', $productEntity->getCustomFields())
            && $productEntity->getCustomFields()['ec_is_set'];
    }

    private function cartHasLineItems(SalesChannelContext $context): bool
    {
        try {
            $cart = $this->persister->load($context->getToken(), $context);
            $hasLineItems = (bool)$cart->getLineItems()->count();
        } catch (CartTokenNotFoundException $e) {
            $hasLineItems = false;
        }
        return $hasLineItems;
    }

    /**
     * There is a slight difference between the DynamicCartProductSubscriber query an this one.
     * Here we are excluding the product it self by cp.product_id != :uniqueId
     * In the DynamicCartProductSubscriber the DynamicProduct exclude itself by cp.unique_id != :uniqueId
     * where uniqueId equals dynamicProductId
     * @return string
     */
    private function sqlWithCart()
    {
        $sql = "SELECT
                	floor(min(calculatedStock)) AS stock,
                	floor(min(calculatedAvailableStock)) AS available_stock
                	FROM
                (SELECT
                	token,
                	subProducts.subProduct,
                	sum(subProducts.quantity) as quantity,
                	sum(subProducts.quantityPP) as quantityProProduct,
                	p.stock,
                	p.available_stock,
                	((p.stock / subProducts.quantityPP) - sum(subProducts.quantity)) as calculatedStock,
                	((p.available_stock / subProducts.quantityPP) - sum(subProducts.quantity)) as calculatedAvailableStock
                FROM (
                	SELECT
                		'---' as token,
                		'+++' as line_item_id,
                		pp.product_id AS subProduct,
                		0 as quantity,
                		pp.quantity as quantityPP
                	FROM
                		product AS p
                		INNER JOIN ec_product_product AS pp ON p.id = pp.product_id
                	WHERE
                		pp.set_product_id = :mainProductId
                	UNION
                	SELECT
                		cp.token,
                		cp.line_item_id,
                		cp.sub_product_id AS subProduct,
                		cp.sub_product_quantity as quantity,
                		0 as quantityPP
                	FROM
                		ec_cart_product cp
                	WHERE
                		cp.token collate utf8mb4_unicode_ci = :token
                		AND cp.product_id != :uniqueId) AS subProducts
                	INNER JOIN product AS p ON subProduct = p.id
                GROUP BY subProduct) as subproductsGrouped;";
        return $sql;
    }

    private function sqlWithoutCart(): string
    {
        $sql = "SELECT
                	floor(min(stock)) AS stock,
                	floor(min(available_stock)) AS available_stock
                FROM (
                	SELECT
                		(subProduct.stock / pp.quantity) AS stock,
                		(subProduct.available_stock / pp.quantity) AS available_stock,
                		pp.quantity
                	FROM
                		product AS subProduct
                		INNER JOIN ec_product_product AS pp ON subProduct.id = pp.product_id
                			AND subProduct.version_id = pp.product_version_id
                	WHERE
                		pp.set_product_id = :productId
                		AND pp.product_version_id = :version) AS calculated;";
        return $sql;
    }


}