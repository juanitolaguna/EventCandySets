<?php declare(strict_types=1);

namespace EventCandy\Sets\Storefront\Page\Product\Subscriber;

use ErrorException;
use EventCandy\Sets\Core\Content\Product\Aggregate\ProductProductEntity;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Subscriber is deactivated in Services because no price calculation needed.
 * Class ProductListingSubscriber
 * @package EventCandy\Sets\Storefront\Page\Product\Subscriber
 * Calculates price before product is loaded in Storefront.
 */
class ProductListingSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface
     */
    private $productProductRepository;


    /**
     * ProductListingSubscriber constructor.
     * @param EntityRepositoryInterface $productProductRepository
     */
    public function __construct(EntityRepositoryInterface $productProductRepository)
    {
        $this->productProductRepository = $productProductRepository;
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
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('setProductId', $productId));
        $criteria->addAssociation('product');
        $result = $this->productProductRepository->search($criteria, $context->getContext());

        // calculate starter value
        /** @var ProductProductEntity $first */
        $first = $result->first();
        $quantity = $first->getQuantity() < 1 ? 1 : $first->getQuantity();
        $availableStock = $first->getProduct()->getAvailableStock();
        $accQuantity = (int) floor($availableStock / $quantity);

        // calculate min available stock
        /** @var ProductProductEntity $pp */
        foreach ($result as $pp) {
            $quantity = $pp->getQuantity() < 1 ? 1 : $pp->getQuantity();
            $availableStock = $pp->getProduct()->getAvailableStock();
            $realQuantity = (int) floor($availableStock / $quantity);

            $accQuantity = $realQuantity < $accQuantity ? $realQuantity : $accQuantity;
        }


        $product->setAvailableStock((int) $accQuantity);

        // set calculated purchase quantity gen min(uservalue)
        $maxPurchase = $product->getMaxPurchase();
        if ($maxPurchase !== null) {
            $min = $maxPurchase < $accQuantity ? $maxPurchase : $accQuantity;
            $product->setCalculatedMaxPurchase($min);
        } else {
            $product->setCalculatedMaxPurchase((int) $accQuantity);
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

}
