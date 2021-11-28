<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use EventCandy\Sets\Core\Checkout\Cart\CartProduct\CartProductService;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadService;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductGateway;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCartCollector implements CartDataCollectorInterface
{

    public const TYPE = 'setproduct';

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var DynamicProductService
     */
    private $dynamicProductService;

    /**
     * @var DynamicProductGateway
     */
    private $dynamicProductGateway;

    /**
     * @var LineItemPriceService
     */
    private $lineItemPriceService;

    /**
     * @var PayloadService
     */
    private $payloadService;

    /**
     * @var CartProductService
     */
    private $cartProductService;


    /**
     * @param CartPersisterInterface $cartPersister
     * @param DynamicProductService $dynamicProductService
     * @param DynamicProductGateway $dynamicProductGateway
     * @param LineItemPriceService $lineItemPriceService
     * @param PayloadService $payloadService
     * @param CartProductService $cartProductService
     */
    public function __construct(
        CartPersisterInterface $cartPersister,
        DynamicProductService $dynamicProductService,
        DynamicProductGateway $dynamicProductGateway,
        LineItemPriceService $lineItemPriceService,
        PayloadService $payloadService,
        CartProductService $cartProductService
    ) {
        $this->cartPersister = $cartPersister;
        $this->dynamicProductService = $dynamicProductService;
        $this->dynamicProductGateway = $dynamicProductGateway;
        $this->lineItemPriceService = $lineItemPriceService;
        $this->payloadService = $payloadService;
        $this->cartProductService = $cartProductService;
    }


    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {

        $lineItemsChanged = $this->getNotCompleted($data, $original->getLineItems()->getElements(), $original->isModified());
        if (count($lineItemsChanged) === 0) {
            return;
        }

        //Utils::log('collectSets');

        $lineItems = $original->getLineItems()->filterFlatByType(self::TYPE);
        $this->createCartIfNotExists($context, $original);

        foreach ($lineItems as $lineItem) {
            $this->dynamicProductService->removeDynamicProductsByLineItemId($lineItem->getId(), $context->getToken());
            //$this->cartProductService->removeCartProductsByLineItem($lineItem->getId(), $context->getToken());
        }
        $this->cartProductService->removeCartProductsByTokenAndType($context->getToken(), self::TYPE);
        $data->clear();

        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection($lineItems, $context->getToken());
        $dynamicProductIds = $this->dynamicProductService->getDynamicProductIdsFromCollection($dynamicProducts);
        $this->dynamicProductService->saveDynamicProductsToDb($dynamicProducts);

        $dynamicProductCollection = $this->dynamicProductGateway->get($dynamicProductIds, $context, false);
        $this->dynamicProductService->addDynamicProductsToCartDataByLineItemId($dynamicProductCollection, $data);

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $this->payloadService->loadPayloadDataForLineItem($lineItem, $data, $context);
            $cartProducts = $this->cartProductService->buildCartProductsFromPayload($lineItem, $data, self::TYPE);
            $this->cartProductService->saveCartProducts($cartProducts);
            $this->dynamicProductService->removeDynamicProductsFromCartDataByLineItemId($lineItem->getId(), $data);
        }


        // repeat it again but with correct stock
        $dynamicProductCollection = $this->dynamicProductGateway->get($dynamicProductIds, $context);
        $this->dynamicProductService->addDynamicProductsToCartDataByLineItemId($dynamicProductCollection, $data);

        foreach ($lineItems as $lineItem) {
            $this->enrichLineItem($lineItem, $data, $context);

            $payloadItem = $this->payloadService->buildPayloadObject($lineItem, $data);
            $payloadAssociative = $this->payloadService->makePayloadDataAssociative($payloadItem, self::TYPE);
            $lineItem->setPayload($payloadAssociative);
        }


//        $this->dynamicProductService->removeDynamicProductsByNotInIds($dynamicProductIds);
//        $this->cartProductService->removeCartProductsByNotInIds($dynamicProductIds);
    }

    private function enrichLineItem(
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context
    ) {
        /** @var DynamicProductEntity $product */
        $dynamicProduct = $this->dynamicProductService->getFromCartDataByLineItemId($lineItem->getId(), $data)[0];
        $product = $dynamicProduct->getProduct();

        $lineItem->setLabel($product->getTranslation('name'));

        if ($product->getCover()) {
            $lineItem->setCover($product->getCover()->getMedia());
        }

        $deliveryTime = null;
        if ($product->getDeliveryTime() !== null) {
            $deliveryTime = DeliveryTime::createFromEntity($product->getDeliveryTime());
        }
        //Utils::log(print_r($product->getAvailableStock(), true));

        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                (int)$product->getAvailableStock(),
                (float)$product->getWeight(),
                $product->getShippingFree(),
                $product->getRestockTime(),
                $deliveryTime,
                $product->getHeight(),
                $product->getWidth(),
                $product->getLength()
            )
        );

        if ($lineItem->getPriceDefinition() == null) {
            $qtyDefinition = $this->lineItemPriceService->buildQuantityPriceDefinition($lineItem, $data, $context);
            $lineItem->setPriceDefinition($qtyDefinition);
        }

        $quantityInformation = (new QuantityInformation())
            ->setMinPurchase($product->getMinPurchase() ?? 1)
            ->setMaxPurchase($product->getAvailableStock())
            ->setPurchaseSteps($product->getPurchaseSteps() ?? 1);
        $lineItem->setQuantityInformation($quantityInformation);

        $payload = [
            'isCloseout' => $product->getIsCloseout(),
            'customFields' => $product->getCustomFields(),
            'createdAt' => $product->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'releaseDate' => $product->getReleaseDate() ? $product->getReleaseDate()->format(
                Defaults::STORAGE_DATE_TIME_FORMAT
            ) : null,
            'isNew' => false,
            'markAsTopseller' => $product->getMarkAsTopseller(),
            'purchasePrices' => null,
            'productNumber' => $product->getProductNumber(),
            'manufacturerId' => $product->getManufacturerId(),
            'taxId' => $product->getTaxId(),
            'tagIds' => $product->getTagIds(),
            'categoryIds' => $product->getCategoryTree(),
            'propertyIds' => $product->getPropertyIds(),
            'optionIds' => $product->getOptionIds(),
            'options' => $product->getVariation(),
        ];

        $lineItem->replacePayload($payload);
    }

    private function getNotCompleted(CartDataCollection $data, array $lineItems, bool $cartModified): array
    {
        $newLineItems = [];

        $areModified = array_filter($lineItems, function (LineItem $lineItem) {
            //return $lineItem->isModified();
        });

        // If one Item is modified recalculate all.
        if (count($areModified) > 0) {
            return $lineItems;
        }

        // No items modified but one deleted
        if ($cartModified) {
            return $lineItems;
        }

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $key = DynamicProductService::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItem->getId();

            // check if some data is missing (label, price, cover)
            if (!$this->isComplete($lineItem)) {
                $newLineItems[] = $lineItem;
                continue;
            }

            // data already fetched?
            if ($data->has($key)) {
                continue;
            }
            $lineItems[] = $lineItem;
        }

        return $newLineItems;
    }

    private function isComplete(LineItem $lineItem): bool
    {
        return $lineItem->getPriceDefinition() !== null
            && $lineItem->getLabel() !== null
            && $lineItem->getDeliveryInformation() !== null
            && $lineItem->getQuantityInformation() !== null;
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

}