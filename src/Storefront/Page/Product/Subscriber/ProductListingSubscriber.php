<?php declare(strict_types=1);

namespace EventCandy\Sets\Storefront\Page\Product\Subscriber;

use EventCandy\Sets\Core\Content\Product\Aggregate\ProductProductEntity;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var ProductPriceDefinitionBuilderInterface
     */
    private $priceDefinitionBuilder;

    /**
     * ProductListingSubscriber constructor.
     * @param EntityRepositoryInterface $productProductRepository
     * @param QuantityPriceCalculator $priceCalculator
     * @param ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder
     */
    public function __construct(EntityRepositoryInterface $productProductRepository, QuantityPriceCalculator $priceCalculator, ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder)
    {
        $this->productProductRepository = $productProductRepository;
        $this->priceCalculator = $priceCalculator;
        $this->priceDefinitionBuilder = $priceDefinitionBuilder;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.product.loaded' => 'salesChannelProductLoaded',
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
        $productId = $product->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('setProductId', $productId));
        $criteria->addAssociation('product');

        $result = $this->productProductRepository->search($criteria, $context->getContext());


        $calculatedPrices = [];
        /** @var ProductProductEntity $pp */
        foreach ($result as $pp) {
            $prices = $this->priceDefinitionBuilder->build($pp->getProduct(), $context);
            $calculatedPrice = $this->priceCalculator->calculate($prices->getPrice(), $context);
            $calculatedPrices[] = [
                'quantity' => $pp->getQuantity(),
                'calculatedPrice' => $calculatedPrice
            ];
        }

        $calculatedTaxes = $product->getCalculatedPrice()->getCalculatedTaxes();
        $calculatedTaxRules = $product->getCalculatedPrice()->getTaxRules();

        $unitPrice = $this->sumColumn("getUnitPrice", $calculatedPrices);
        $totalPrice = $this->sumColumn("getTotalPrice", $calculatedPrices);

        //set simple price
        // custom field remains in product json after uninstall -> check needed.
        if ($unitPrice !== 0) {
            $product->setCalculatedPrice(new CalculatedPrice(
                $unitPrice,
                $totalPrice,
                $calculatedTaxes,
                $calculatedTaxRules
            ));
        }


    }

    private function sumColumn(string $columnNameMethod, array $array)
    {
        /** @var CalculatedPrice $price */
        return array_reduce($array, function (float $acc, array $price) use ($columnNameMethod) {
            $acc += call_user_func([$price['calculatedPrice'], $columnNameMethod]) * $price['quantity'];
            return $acc;
        }, 0);
    }


}
