<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use ErrorException;
use EventCandy\Sets\Core\Event\BoolStruct;
use EventCandy\Sets\Core\Event\ProductLoadedEvent;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelProductSubscriber implements EventSubscriberInterface
{

    public const SKIP_UNIQUE_ID = 'skip-unique-id';

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
            'sales_channel.product.loaded' => 'salesChannelProductLoaded',
            ProductLoadedEvent::class => 'productLoaded'
        ];
    }

    public function productLoaded(ProductLoadedEvent $event)
    {
        $context = $event->getContext();
        $products = $event->getEntities();
        $this->recalculateStock($context, $products->getElements());
    }

    /**
     * @param SalesChannelEntityLoadedEvent $event
     * @throws ErrorException
     */
    public function salesChannelProductLoaded(SalesChannelEntityLoadedEvent $event)
    {
        $context = $event->getSalesChannelContext();
        $products = $event->getEntities();
        $this->recalculateStock($context, $products);
    }

    /**
     * @param SalesChannelContext $context
     * @param array $products
     * @throws ErrorException
     */
    private function recalculateStock(SalesChannelContext $context, array $products): void
    {
        if ($this->cartHasLineItems($context)) {
            $this->calculateStockWithCart($products, $context);
        } else {
            $this->calculateStockWithoutCart($products, $context);
        }
    }

    /**
     * @param array $products
     * @param SalesChannelContext $context
     * @throws ErrorException
     */
    private function calculateStockWithCart(array $products, SalesChannelContext $context): void
    {
        /** @var ProductEntity $salesChannelProduct */
        foreach ($products as $product) {
            if (!$this->isSetProduct($product)) {
                continue;
            }
            $stock = $this->getStockWithCart($context, $product);
            $product->setAvailableStock((int)$stock['available_stock']);
            $product->setStock((int)$stock['stock']);
            DynamicProductSubscriber::setAvailability($product, (int)$stock['available_stock']);
        }
    }

    private function calculateStockWithoutCart(array $products, SalesChannelContext $context): void
    {
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            if (!$this->isSetProduct($product)) {
                continue;
            }
            $stock = $this->getStockWithoutCart($context, $product);
            $product->setAvailableStock((int)$stock['available_stock']);
            $product->setStock((int)$stock['stock']);
            DynamicProductSubscriber::setAvailability($product, (int)$stock['available_stock']);
        }
    }

    /**
     * @param SalesChannelEntityLoadedEvent $event
     * @param SalesChannelProductEntity $product
     * @return array
     * @throws ErrorException
     */
    private function getStockWithCart(SalesChannelContext $context, ProductEntity $product): array
    {
        $sql = $this->sqlWithCart();

        /** @var BoolStruct $uniqueId */
        $skipUniqueId = $product->getExtension(self::SKIP_UNIQUE_ID) ?? new BoolStruct(false);

        try {
            $result = $this->connection->fetchAssociative($sql, [
                'token' => $context->getToken(),
                'mainProductId' => Uuid::fromHexToBytes($product->getId()),
                'uniqueId' => $skipUniqueId->getValue() ? Uuid::randomBytes() : Uuid::fromHexToBytes($product->getId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }
        return $result;
    }

    private function getStockWithoutCart(
        SalesChannelContext $context,
        ProductEntity $salesChannelProduct
    ) {
        $sql = $this->sqlWithoutCart();
        try {
            $result = $this->connection->fetchAssociative($sql, [
                'productId' => Uuid::fromHexToBytes($salesChannelProduct->getId()),
                'version' => Uuid::fromHexToBytes($context->getVersionId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }
        return $result;
    }


    private function isSetProduct(ProductEntity $productEntity): bool
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
        $sql = "SELECT floor(min(calculatedStock)) AS stock,
                       floor(min(calculatedAvailableStock)) AS available_stock
                FROM
                  (SELECT token,
                          subProducts.subProduct,
                          sum(subProducts.quantity) AS quantity,
                          sum(subProducts.quantityPP) AS quantityProProduct,
                          p.stock,
                          p.available_stock,
                          ((p.stock / subProducts.quantityPP) - sum(subProducts.quantity)) AS calculatedStock,
                          ((p.available_stock / subProducts.quantityPP) - sum(subProducts.quantity)) AS calculatedAvailableStock
                   FROM
                     (SELECT '---' AS token,
                             '+++' AS line_item_id,
                             pp.product_id AS subProduct,
                             0 AS quantity,
                             pp.quantity AS quantityPP
                      FROM product AS p
                      INNER JOIN ec_product_product AS pp ON p.id = pp.product_id
                      WHERE pp.set_product_id = :mainProductId
                      UNION SELECT cp.token,
                                   cp.line_item_id,
                                   cp.sub_product_id AS subProduct,
                                   sum(cp.sub_product_quantity) AS quantity,
                                   0 AS quantityPP
                      FROM ec_cart_product cp
                      WHERE cp.token COLLATE utf8mb4_unicode_ci = :token
                      and cp.line_item_id != :uniqueId
                      GROUP BY sub_product_id) AS subProducts
                   INNER JOIN product AS p ON subProduct = p.id
                   GROUP BY subProduct) AS subproductsGrouped;";
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