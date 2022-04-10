<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use ErrorException;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Core\Event\DynamicProductLoadedEvent;
use EventCandy\Sets\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Class ProductListingSubscriber
 * @package EventCandy\Sets\Storefront\Page\Product\Subscriber
 * Calculates stock before product is loaded in Storefront.
 */
class DynamicProductSubscriber implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            DynamicProductLoadedEvent::class => 'dynamicProductLoaded'
        ];
    }

    /**
     * DynamicProductLoaded event is thrown by DynamicProductGateway.
     * It fetches DynamicProductEntities for the CartCollector.
     * DynamicProductStruct is a wrapper around possibly 2 equal Products, with a unique id
     * to resolve the self reference problem when calculating available stock for setproducts dynamically
     * considering warehouse(subproducts) in stock & warehouse(subproducts) in cart.
     */
    public function dynamicProductLoaded(DynamicProductLoadedEvent $event): void
    {
        if (!$event->isCalculateStock()) {
            return;
        }

        $dynamicProducts = $event->getEntities();
        /** @var SalesChannelContext $context */
        $context = $event->getContext();

        /** @var DynamicProductEntity $dynamicProduct */
        foreach ($dynamicProducts as $dynamicProduct) {
            if (!$this->isSetProduct($dynamicProduct->getProduct())) {
                continue;
            }
            $stock = $this->calculateStock($context->getContext(), $dynamicProduct);

            $product = $dynamicProduct->getProduct();
            $availableStock = (int)$stock['available_stock'];
            $product->setAvailableStock($availableStock);
            $product->setStock((int)$stock['stock']);
            $this->setAvailability($product, $availableStock);

            $dynamicProduct->setProduct($product);
        }
    }

    private function isSetProduct(ProductEntity $productEntity): bool
    {
        return array_key_exists('ec_is_set', $productEntity->getCustomFields())
            && $productEntity->getCustomFields()['ec_is_set'];
    }


    private function calculateStock(Context $context, DynamicProductEntity $dynamicProduct): array
    {
        $sql = $this->dynamicProductSql();
        try {
            $result = $this->connection->fetchAssociative($sql, [
                'token' => $dynamicProduct->getToken(),
                'mainProductId' => Uuid::fromHexToBytes($dynamicProduct->getProductId()),
                'uniqueId' => Uuid::fromHexToBytes($dynamicProduct->getId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }
        return $result;
    }


    public static function setAvailability(ProductEntity $product, int $accQuantity): void
    {
        // set calculated purchase quantity gen min(uservalue)

        $maxPurchase = $product->getMaxPurchase();
        if ($product instanceof SalesChannelProductEntity) {
            if ($maxPurchase !== null) {
                $min = $maxPurchase < $accQuantity ? $maxPurchase : $accQuantity;
                $product->setCalculatedMaxPurchase($min);
            } else {
                $product->setCalculatedMaxPurchase((int)$accQuantity);
            }
        }
        $minPurchase = $product->getMinPurchase() !== null ? $product->getMinPurchase() : 1;

        //set flags based on quantity
        if ($accQuantity < $minPurchase) {
            $product->setAvailable(false);
            $product->setIsCloseout(true);
        } else {
            $product->setAvailable(true);
            $product->setIsCloseout(false);
        }
    }


    private function dynamicProductSql()
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
                	(p.stock - sum(subProducts.quantity)) / sum(subProducts.quantityPP) AS calculatedStock,
                    (p.available_stock - sum(subProducts.quantity)) / sum(subProducts.quantityPP) AS calculatedAvailableStock
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
                		sum(cp.sub_product_quantity) as quantity,
                		0 as quantityPP
                	FROM
                		ec_cart_product cp
                	WHERE
                		cp.token collate utf8mb4_unicode_ci = :token
                		AND cp.unique_id != :uniqueId group by sub_product_id) AS subProducts
                	INNER JOIN product AS p ON subProduct = p.id
                GROUP BY subProduct) as subproductsGrouped;";
        return $sql;
    }


}
