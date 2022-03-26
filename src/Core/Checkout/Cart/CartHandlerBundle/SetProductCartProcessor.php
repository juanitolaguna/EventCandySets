<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandlerBundle;

use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\MissingLineItemPriceException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Content\Product\Cart\ProductStockReachedError;
use Shopware\Core\Content\Product\Cart\PurchaseStepsError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCartProcessor implements AggregateCartProcessorInterface
{

    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    /**
     * @param QuantityPriceCalculator $calculator
     */
    public function __construct(QuantityPriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }


    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(SetProductCartCollector::TYPE);

        if (count($lineItems) === 0) {
            return;
        }

        foreach ($lineItems as $lineItem) {

            $definition = $lineItem->getPriceDefinition();
            if (!$definition instanceof QuantityPriceDefinition) {
                throw new MissingLineItemPriceException($lineItem->getId());
            }
            $definition->setQuantity($lineItem->getQuantity());

            $minPurchase = 1;
            $steps = 1;
            $available = $lineItem->getQuantity();

            if ($lineItem->getQuantityInformation() !== null) {
                $minPurchase = $lineItem->getQuantityInformation()->getMinPurchase();
                $available = $lineItem->getQuantityInformation()->getMaxPurchase();
                $steps = $lineItem->getQuantityInformation()->getPurchaseSteps() ?? 1;
            }

            if ($lineItem->getQuantity() < $minPurchase) {
                $lineItem->setQuantity($minPurchase);
                $definition->setQuantity($minPurchase);
            }

            if ($available <= 0 || $available < $minPurchase) {
                $original->remove($lineItem->getId());
                $toCalculate->addErrors(
                    new ProductOutOfStockError((string) $lineItem->getReferencedId(), (string) $lineItem->getLabel())
                );
                continue;
            }

            if ($available < $lineItem->getQuantity()) {
                $lineItem->setQuantity($available);
                $definition->setQuantity($available);
                $toCalculate->addErrors(
                    new ProductStockReachedError((string) $lineItem->getReferencedId(), (string) $lineItem->getLabel(), $available)
                );
            }

            $fixedQuantity = $this->fixQuantity($minPurchase, $lineItem->getQuantity(), $steps);
            if ($lineItem->getQuantity() !== $fixedQuantity) {
                $lineItem->setQuantity($fixedQuantity);
                $definition->setQuantity($fixedQuantity);
                $toCalculate->addErrors(
                    new PurchaseStepsError((string) $lineItem->getReferencedId(), (string) $lineItem->getLabel(), $fixedQuantity)
                );
            }

            $lineItem->setPrice($this->calculator->calculate($definition, $context));

            $toCalculate->add($lineItem);
        }
    }

    private function fixQuantity(int $min, int $current, int $steps): int
    {
        return (int) (floor(($current - $min) / $steps) * $steps + $min);
    }
}