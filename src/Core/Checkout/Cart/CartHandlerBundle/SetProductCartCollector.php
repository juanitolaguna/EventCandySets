<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartCollectorInterface;
use EventCandy\Sets\Core\Checkout\Cart\CartHandlerBundle\SetProductCartOptimizer;
use EventCandy\Sets\Core\Checkout\Cart\CartProduct\CartProductService;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadRepository\PayloadRepository;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadRepository\PayloadRepositoryInterface;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadService;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductGateway;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepository\DynamicProductRepositoryInterface;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCartCollector implements AggregateCartCollectorInterface
{

    public const TYPE = 'setproduct';

    private CartPersisterInterface $cartPersister;

    private DynamicProductService $dynamicProductService;

    private DynamicProductRepositoryInterface $dynamicProductRepository;

    private DynamicProductGateway $dynamicProductGateway;

    private LineItemPriceService $lineItemPriceService;

    private PayloadService $payloadService;

    private PayloadRepositoryInterface $payloadRepository;

    private CartProductService $cartProductService;

    public function __construct(
        CartPersisterInterface $cartPersister,
        DynamicProductService $dynamicProductService,
        DynamicProductRepositoryInterface $dynamicProductRepository,
        DynamicProductGateway $dynamicProductGateway,
        LineItemPriceService $lineItemPriceService,
        PayloadService $payloadService,
        PayloadRepositoryInterface $payloadRepository,
        CartProductService $cartProductService
    ) {
        $this->cartPersister = $cartPersister;
        $this->dynamicProductService = $dynamicProductService;
        $this->dynamicProductRepository = $dynamicProductRepository;
        $this->dynamicProductGateway = $dynamicProductGateway;
        $this->lineItemPriceService = $lineItemPriceService;
        $this->payloadService = $payloadService;
        $this->payloadRepository = $payloadRepository;
        $this->cartProductService = $cartProductService;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {

        $lineItems = $original->getLineItems()->filterFlatByType(self::TYPE);
        $dynamicProductIds = $this->dynamicProductRepository->getDynamicProductIds($original->getToken(), $lineItems);


        // ToDo: ↓
        $dynamicProductCollection = $this->dynamicProductGateway->get($dynamicProductIds, $context, false);
        $this->dynamicProductService->addDynamicProductsToCartDataByLineItemId($dynamicProductCollection, $data);

        foreach ($lineItems as $lineItem) {
            $this->payloadRepository->loadPayloadDataForLineItem($lineItem, $data, $context);
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

}