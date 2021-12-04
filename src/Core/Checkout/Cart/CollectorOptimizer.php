<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use Enqueue\Util\UUID;
use EventCandy\Sets\Core\Checkout\Cart\CartProduct\CartProductService;
use EventCandy\Sets\Core\Checkout\Cart\CollectorOptimizer\CollectorOptimizerInterface;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CollectorOptimizer implements CartDataCollectorInterface
{

    public const LINE_ITEMS_OVER_CONTEXT = 'line-items_over-context';

    private $counter = 0;

    /**
     * @var DynamicProductService
     */
    private $dynamicProductService;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var CartProductService
     */
    private $cartProductService;

    /**
     * @var CollectorOptimizerInterface[]
     */
    private $optimizers;

    /**
     * @param DynamicProductService $dynamicProductService
     * @param CartPersisterInterface $cartPersister
     * @param CartProductService $cartProductService
     * @param CollectorOptimizerInterface[] $optimizers
     */
    public function __construct(
        DynamicProductService $dynamicProductService,
        CartPersisterInterface $cartPersister,
        CartProductService $cartProductService,
        iterable $optimizers
    ) {
        $this->dynamicProductService = $dynamicProductService;
        $this->cartPersister = $cartPersister;
        $this->cartProductService = $cartProductService;
        $this->optimizers = $optimizers;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // Ansatz: teste ob verschiedene Anzahl im Cart & In DB
        //$lineItemsNotMissing = $this->getLineItemsNotMissing($original, $context);

        // Todo: Beim hinzufügen gibt es noch Probleme - beim ersten mal ist das neue lineItem noch nicht dabei
        // Man kann nicht mehr reinpacken als es gibt, und komischerweise wenn man es versucht, springt der limit an.
        // aber die anzeige vom produkt im warenkorb wird nicht herunetergesetzt.


        $changed = $this->cartOrLineItemsChanged(
            $data,
            $original->getLineItems()->getElements(),
            $original->isModified()
        );
        if (!$changed) {
            return;
        }

        /** execute only once per process cycle @link Processor::process() */
        if ($this->counter > 0 ) {
            return;
        }
        $this->counter++;

        //Utils::log('optimizer');
        $this->createCartIfNotExists($context, $original);

        $lineItemIds = array_map(function (LineItem $lineItem) {
            return $lineItem->getId();
        }, $original->getLineItems()->getElements());

//        $this->dynamicProductService->removeDynamicProductsByLineItemIds($lineItemIds, $context->getToken());
//        $this->cartProductService->removeCartProductsByLineItemIds($lineItemIds, $context->getToken());



        // removeDynamicProductsByToken but exclude new
        $this->dynamicProductService->removeDynamicProductsByToken($context->getToken(), true);
        $this->cartProductService->removeCartProductsByToken($context->getToken());

        foreach ($this->optimizers as $optimizer) {
            $optimizer->saveDynamicProductsBeforeCollect($data, $original, $context, $behavior);
        }

        //Todo: reset dynamic product flags
        $this->dynamicProductService->resetNewFlag($context->getToken());

        // Danach entfernt der jeweilige collector products, aber nur seine eigenen (nach lineItemId), die er dann wieder erstellt
        // daher ist die Berechnung richtig.
        // Mann kan diesen process optimieren und die bereits vorhandenen ids in der CartDataCollection im optimizer als collection vorspeichern.
        // Dann verhindert man das Löschen / Erstellen der Dynamic Products und Cart Products. Dass muss aber alles getestet werden
    }

    /**
     * @param array $lineItems
     * @param SalesChannelContext $context
     */
    public static function addNewLineItemsToContextForCorrectCalculation(array $lineItems, SalesChannelContext $context) {
        $context->addExtension(self::LINE_ITEMS_OVER_CONTEXT, new ArrayStruct($lineItems));
    }


    /**
     * @param SalesChannelContext $context
     * @param Cart $original
     */
    private function createCartIfNotExists(SalesChannelContext $context, Cart $original): void
    {
        try {
            $this->cartPersister->load($context->getToken(), $context);
        } catch (CartTokenNotFoundException $exception) {
            $this->cartPersister->save($original, $context);
        }
    }

    private function cartOrLineItemsChanged(CartDataCollection $data, array $lineItems, bool $cartModified): bool
    {
        $includesCreditLineItem = array_filter($lineItems, function (LineItem $lineItem) {
            return $lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE;
        });

        if($includesCreditLineItem) {
            return false;
        }

        $areModified = array_filter($lineItems, function (LineItem $lineItem) {
            return $lineItem->isModified();
        });

        // If one Item is modified recalculate all.
        if (count($areModified) > 0) {
            return true;
        }

        // No items modified but one deleted
        if ($cartModified) {
            return true;
        }

        return false;
    }

    /**
     * @param Cart $original
     * @param SalesChannelContext $context
     * @return bool
     */
    private function getLineItemsNotMissing(Cart $original, SalesChannelContext $context): bool
    {
        $lineItemIds = $original->getLineItems()->fmap(
            function (LineItem $lineItem) {
                return $lineItem->getId();
            }
        );
        $count = $this->dynamicProductService->preparedlineItemsInCart($lineItemIds, $context->getToken());
        return count($lineItemIds) === (int)$count;
    }

}