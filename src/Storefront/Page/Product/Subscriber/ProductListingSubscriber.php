<?php
declare(strict_types=1);

namespace EventCandy\Sets\Storefront\Page\Product\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use ErrorException;
use EventCandy\Sets\Core\Checkout\Cart\SubProductQuantityInCartReducerInterface;
use EventCandy\Sets\Core\Content\Product\Aggregate\ProductProductEntity;
use EventCandy\Sets\Core\SetProductLoadedEvent;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * Class ProductListingSubscriber
 * @package EventCandy\Sets\Storefront\Page\Product\Subscriber
 * Calculates stock before product is loaded in Storefront.
 */
class ProductListingSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface
     */
    private $productProductRepository;

    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @var SubProductQuantityInCartReducerInterface[]
     */
    private $cartReducer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;


    /**
     * ProductListingSubscriber constructor.
     * @param EntityRepositoryInterface $productProductRepository
     * @param CartPersisterInterface $persister
     * @param SubProductQuantityInCartReducerInterface[] $cartReducer
     * @param EventDispatcherInterface $eventDispatcher
     * @param Connection $connection
     */
    public function __construct(
        EntityRepositoryInterface $productProductRepository,
        CartPersisterInterface $persister,
        iterable $cartReducer,
        EventDispatcherInterface $eventDispatcher,
        Connection $connection
    ) {
        $this->productProductRepository = $productProductRepository;
        $this->persister = $persister;
        $this->cartReducer = $cartReducer;
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.product.loaded' => 'salesChannelProductLoaded',
            SetProductLoadedEvent::class => 'setProductLoaded'
        ];
    }

    /**
     * If SalesChannelRepository is not an option,
     * for example, loading inactive products
     * @param SetProductLoadedEvent $event
     */
    public function setProductLoaded(SetProductLoadedEvent $event)
    {
        /** @var SalesChannelProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $keyIsTrue = array_key_exists('ec_is_set', $product->getCustomFields())
                && $product->getCustomFields()['ec_is_set'];
            if ($keyIsTrue) {
                $this->enrichProduct($product, $event->getContext());
            } else {
                $this->enrichProduct($product, $event->getContext(), true);
            }
        }
    }

    public function salesChannelProductLoaded(SalesChannelEntityLoadedEvent $event)
    {
        /** @var SalesChannelProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $keyIsTrue = array_key_exists('ec_is_set', $product->getCustomFields())
                && $product->getCustomFields()['ec_is_set'];
            if ($keyIsTrue) {
                $this->enrichProduct($product, $event->getSalesChannelContext());
            } else {
                $this->enrichProduct($product, $event->getSalesChannelContext(), true);
            }
        }
    }

    private function enrichProduct(ProductEntity $product, SalesChannelContext $context, $isNormalProduct = false)
    {
        // get related products
        $productId = $product->getId();

        $cart = $this->persister->load($context->getToken(), $context);
        $hasLineItems = $cart->getLineItems()->count();

        if (!$hasLineItems) {
            $accQuantity = $this->getAvailableStockWithSQL($productId, $context->getContext());
        } else {
            $accQuantity = $this->getAvailableStockWithCart($productId, $context);
        }
//        $accQuantity = $this->getAvailableStock($productId, $context, true, $isNormalProduct,
//            $product->getAvailableStock());

        $product->setAvailableStock((int)$accQuantity);
        $this->setAvailability($product, $accQuantity);
    }

    /**
     * @param ProductEntity $product
     * @param int $accQuantity
     */
    private function setAvailability(ProductEntity $product, int $accQuantity): void
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

    private function getAvailableStockWithSQL(string $productId, Context $context)
    {
        $sql = "select
                	floor(min(stock)) as stock,
                	floor(min(available_stock)) as available_stock
                from (
                	select
                		(subProduct.stock / pp.quantity) as stock,
                		(subProduct.available_stock / pp.quantity) as available_stock,
                		pp.quantity
                	from
                		product as subProduct
                		inner join ec_product_product as pp on subProduct.id = pp.product_id and subProduct.version_id = pp.product_version_id
                	where
                		pp.set_product_id = :productId and pp.product_version_id = :version) as calculated;";
        try {
            $result = $this->connection->fetchAssociative($sql, [
                'productId' => Uuid::fromHexToBytes($productId),
                'version' => Uuid::fromHexToBytes($context->getVersionId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }
        return (int)$result['available_stock'];
    }

    private function getAvailableStockWithCart(string $productId, SalesChannelContext $context)
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
                		pp.set_product_id = :product_id
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
                		cp.token = :token
                		AND cp.product_id != :product_id) AS subProducts
                	INNER JOIN product AS p ON subProduct = p.id
                GROUP BY
                	subProduct) as subproductsGrouped;";

        try {
            $result = $this->connection->fetchAssociative($sql, [
                'token' => $context->getToken(),
                'product_id' => Uuid::fromHexToBytes($productId)
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }

        return (int)$result['available_stock'];
    }



    //===============================OLD==============================//

    /**
     * method used in other classes
     * 'setProduct' = mainProduct
     * get list
     * @param string $mainProduct
     * @param SalesChannelContext $context
     * @param bool $includeCart
     * @param bool $isNormalProduct
     * @param int $availableStock
     * @return int
     */
    public function getAvailableStock(
        string $mainProduct,
        SalesChannelContext $context,
        bool $includeCart = true,
        bool $isNormalProduct = false,
        int $availableStock = 0
    ): int {
        // load cart
        try {
            $cart = $this->persister->load($context->getToken(), $context);
            $hasLineItems = $cart->getLineItems()->count();
        } catch (CartTokenNotFoundException $e) {
            $hasLineItems = false;
        }

        if (!$hasLineItems) {
            if ($isNormalProduct) {
                return $availableStock;
            }

            return $this->getAvailableStockWithSQL($mainProduct, $context->getContext());
        }


        $criteria = new Criteria();
        if ($isNormalProduct) {
            $criteria->addFilter(new EqualsFilter('productId', $mainProduct));
        } else {
            $criteria->addFilter(new EqualsFilter('setProductId', $mainProduct));
        }

        if ($includeCart && $hasLineItems) {
            $criteria->addAssociation('product.masterProductsJoinTable');
        } else {
            $criteria->addAssociation('product');
        }

        $result = $this->productProductRepository->search($criteria, $context->getContext());

        if ($result->getTotal() === 0) {
            return $availableStock;
        }


        $accQuantity = PHP_INT_MAX;
        // calculate min available stock
        // each subProduct of mainProduct
        /** @var ProductProductEntity $pp */
        foreach ($result as $pp) {
            $subProductQuantityInCart = 0;
            if ($hasLineItems && $includeCart) {
                $subProductQuantityInCart = $this->getSubProductQuantityInCart($pp, $mainProduct, $cart, $context);
            }

            $availableStock = $pp->getProduct()->getAvailableStock() - $subProductQuantityInCart;
            $quantity = $pp->getQuantity() < 1 ? 1 : $pp->getQuantity();

            $realQuantity = (int)floor($availableStock / $quantity);
            $accQuantity = $realQuantity < $accQuantity ? $realQuantity : $accQuantity;
        }
        return $accQuantity;
    }

    /**
     * @param ProductProductEntity $pp
     * @param string $mainProduct
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return int
     */
    private function getSubProductQuantityInCart(
        ProductProductEntity $pp,
        string $mainProduct,
        Cart $cart,
        SalesChannelContext $context
    ): int {
        $subProductQuantityInCart = 0;
        //each masterProduct related to subProduct
        /** @var ProductProductEntity $relatedMainProduct */
        foreach ($pp->getProduct()->get('masterProductsJoinTable') as $relatedMainProduct) {
            $relatedMainId = $relatedMainProduct->get('setProductId');
            $subProductQuantity = $relatedMainProduct->get('quantity');
            foreach ($this->cartReducer as $reducer) {
                $subProductQuantityInCart += $reducer->reduce(
                    $cart,
                    $relatedMainId,
                    $mainProduct,
                    $subProductQuantity,
                    $context
                );
            }
        }

        return $subProductQuantityInCart;
    }
}
