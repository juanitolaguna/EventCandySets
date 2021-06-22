<?php declare(strict_types=1);

namespace EventCandy\Sets\Storefront\Page\Product\Subscriber;

use EventCandy\Sets\Core\Checkout\Cart\SubProductQuantityInCartReducerInterface;
use EventCandy\Sets\Core\Content\Product\Aggregate\ProductProductEntity;
use EventCandyCandyBags\Utils;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    private static $count = 0;


    /**
     * ProductListingSubscriber constructor.
     * @param EntityRepositoryInterface $productProductRepository
     * @param CartPersisterInterface $persister
     * @param SubProductQuantityInCartReducerInterface[] $cartReducer
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EntityRepositoryInterface $productProductRepository, CartPersisterInterface $persister, iterable $cartReducer, EventDispatcherInterface $eventDispatcher)
    {
        $this->productProductRepository = $productProductRepository;
        $this->persister = $persister;
        $this->cartReducer = $cartReducer;
        $this->eventDispatcher = $eventDispatcher;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.product.loaded' => 'salesChannelProductLoaded'
        ];
    }

    public function salesChannelProductLoaded(SalesChannelEntityLoadedEvent $event)
    {

        /** @var SalesChannelProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $keyIsTrue = array_key_exists('ec_is_set', $product->getCustomFields())
                && $product->getCustomFields()['ec_is_set'];
            if ($keyIsTrue) {
                $this->enrichProduct($product, $event->getSalesChannelContext());
            }
        }


    }

    private function enrichProduct(SalesChannelProductEntity $product, SalesChannelContext $context)
    {
        // get related products
        $productId = $product->getId();
        $accQuantity = $this->getAvailableStock($productId, $context);


        $product->setAvailableStock((int)$accQuantity);


        // set calculated purchase quantity gen min(uservalue)
        $maxPurchase = $product->getMaxPurchase();
        if ($maxPurchase !== null) {
            $min = $maxPurchase < $accQuantity ? $maxPurchase : $accQuantity;
            $product->setCalculatedMaxPurchase($min);
        } else {
            $product->setCalculatedMaxPurchase((int)$accQuantity);
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


    /**
     * method used in other classes
     * 'setProduct' = mainProduct
     * get list
     * @param string $mainProduct
     * @param SalesChannelContext $context
     * @param bool $includeCart
     * @return int
     */
    public function getAvailableStock(string $mainProduct, SalesChannelContext $context, bool $includeCart = true): int
    {
        // load cart
        try {
            $cart = $this->persister->load($context->getToken(), $context);
            $hasLineItems = $cart->getLineItems()->count();
        } catch (CartTokenNotFoundException $e) {
            $hasLineItems = false;
        }


        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('setProductId', $mainProduct));

        if ($includeCart && $hasLineItems) {
            $criteria->addAssociation('product.masterProductsJoinTable');
        } else {
            $criteria->addAssociation('product');
        }

        $result = $this->productProductRepository->search($criteria, $context->getContext());

        if ($result->getTotal() === 0) {
            return 0;
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
    private function getSubProductQuantityInCart(ProductProductEntity $pp, string $mainProduct, Cart $cart, SalesChannelContext $context): int
    {
        $subProductQuantityInCart = 0;

        //each masterProduct related to subProduct
        /** @var ProductProductEntity $relatedMainProduct */
        foreach ($pp->getProduct()->get('masterProductsJoinTable') as $relatedMainProduct) {
            $relatedMainId = $relatedMainProduct->get('setProductId');
            $subProductQuantity = $relatedMainProduct->get('quantity');
            foreach ($this->cartReducer as $reducer) {
                $subProductQuantityInCart += $reducer->reduce($cart, $relatedMainId, $mainProduct, $subProductQuantity, $context);
            }
        }

        return $subProductQuantityInCart;
    }

}
