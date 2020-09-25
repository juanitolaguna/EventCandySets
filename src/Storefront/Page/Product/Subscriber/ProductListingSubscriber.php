<?php declare(strict_types=1);

namespace EventCandy\Sets\Storefront\Page\Product\Subscriber;

use EventCandy\Sets\Core\Content\Product\Aggregate\ProductProductEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface
     */
    private $productProductRepository;

    /**
     * @param EntityRepositoryInterface $productProductRepository
     */

    public function __construct(EntityRepositoryInterface $productProductRepository)
    {
        $this->productProductRepository = $productProductRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => 'enrichCriteria',
            'sales_channel.product.loaded' => 'salesChannelProductLoaded',
        ];
    }

    public function enrichCriteria(ProductListingCriteriaEvent $event) {
        $criteria = $event->getCriteria();
        $criteria->addAssociation('products');
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
        $productId = $product->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('setProductId', $productId));
        $criteria->addAssociation('product.price');


        $result = $this->productProductRepository->search($criteria, $context->getContext());



        $mapped = new EntityCollection();
        /** @var ProductProductEntity $pp */
        foreach ($result as $pp) {
            $mapped->add($pp->getProduct());
        }

        // ovveride simple price
        $product->setCalculatedPrice();

//        $mapped = $result->map(function (ProductProductEntity $product){
//                 return $product->getProduct();
//        });

//        $prices = $result->map(function(ProductProductEntity $pp) {
//            return $pp->getProduct();
//        });

        $product->addExtension('set-products', $mapped);



//        $product->setCalculatedPrice(new CalculatedPrice(3,3,3,3));

//        $prices = $this->map(function     (CalculatedPrice $price) {
//            return $price->getUnitPrice();
//        });

//        throw new ErrorException(strval(array_sum($prices)) );

//        return array_sum($prices);


//        $price = $result->getPrices()->sum();
//        $product->setPrice($result->getPrices()->sum());

        // no Impact
//        $product->setCalculatedPrice(new CalculatedPrice(
//            99, 99,  new CalculatedTaxCollection([]), $context->buildTaxRules($product->getTaxId())));

//        $psroduct->getPrice(

//        $product->setName('This is a Set Product');
//                $product->setTranslated(['name' => 'This is a Set Product']);

//        $translations = $product->getTranslated();
//        $translations['name'] = 'this is a set products';
//        $product->setTranslated($translations);
    }
}
