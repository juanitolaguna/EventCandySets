<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemPriceService
{
    /**
     * @var DynamicProductService
     */
    protected $dynamicProductService;

    /**
     * @param DynamicProductService $dynamicProductService
     */
    public function __construct(DynamicProductService $dynamicProductService)
    {
        $this->dynamicProductService = $dynamicProductService;
    }


    public function buildQuantityPriceDefinition(
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context,
        string $taxId = null
    ): QuantityPriceDefinition {
        $taxId = $taxId ?? $this->getProductWithHighestTaxRate($lineItem, $data)->getTaxId();
        $taxRules = $context->buildTaxRules($taxId);
        $currencyPrice = $this->getProductCurrencyPrice($lineItem, $data, $context);

        return new QuantityPriceDefinition(
            $currencyPrice,
            $taxRules,
            $lineItem->getQuantity()
        );
    }

    private function getProductWithHighestTaxRate(LineItem $lineItem, CartDataCollection $data)
    {
        /** @var DynamicProductEntity[] $dynamicProducts */
        $products = $this->dynamicProductService->getFromCartDataByLineItemId($lineItem->getId(), $data);

        /** @var ProductEntity $productWithHighestTax */
        $productWithHighestTax = array_reduce(
            $products,
            function (DynamicProductEntity $p1, DynamicProductEntity $p2) {
                $p1 = $p1->getProduct();
                $p2 = $p2->getProduct();
                $taxRate1 = $p1->getTax()->getTaxRate();
                $taxRate2 = $p2->getTax()->getTaxRate();
                return $taxRate1 > $taxRate2 ? $p1 : $p2;
            },
            $products[0]
        );
        return $productWithHighestTax;
    }

    private function getProductCurrencyPrice(
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context
    ): float {
        /** @var DynamicProductEntity[] $dynamicProducts */
        $products = $this->dynamicProductService->getFromCartDataByLineItemId($lineItem->getId(), $data);
        $currencyId = $context->getCurrency()->getId();

        /** @var float $calculatedPrice */
        $calculatedPrice = array_reduce(
            $products,
            function (float $price, DynamicProductEntity $product) use ($currencyId, $context) {
                $product = $product->getProduct();
                $priceClass = $product->getPrice()->getCurrencyPrice($currencyId);
                $priceNetOrGross = $this->netOrGross($priceClass, $context);
                $recalculated = $this->recalculateCurrencyIfNeeded($priceNetOrGross, $priceClass, $context);
                $price += $recalculated;
                return $price;
            },
            0.0
        );
        return $calculatedPrice;
    }

    private function netOrGross(Price $price, SalesChannelContext $context): float
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $price->getGross() ?? 0.0;
        } else {
            return $price->getNet() ?? 0.0;
        }
    }

    private function recalculateCurrencyIfNeeded(float $value, Price $price, SalesChannelContext $context): float
    {
        if ($price->getCurrencyId() !== $context->getCurrency()->getId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }
        return $value;
    }
}