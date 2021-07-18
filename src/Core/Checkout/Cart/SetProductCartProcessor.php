<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use EventCandy\LabelMe\Core\Checkout\Cart\EclmCartProcessor;
use EventCandyCandyBags\Core\Checkout\Cart\CandyBagsCartProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Exception\MissingLineItemPriceException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Cart\ProductFeatureBuilder;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\Cart\ProductNotFoundError;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Content\Product\Cart\ProductStockReachedError;
use Shopware\Core\Content\Product\Cart\PurchaseStepsError;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const TYPE = 'setproduct';

    public const CUSTOM_PRICE = 'customPrice';

    public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    public const ALLOW_PRODUCT_LABEL_OVERWRITES = 'allowProductLabelOverwrites';

    public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    public const SKIP_PRODUCT_STOCK_VALIDATION = 'skipProductStockValidation';

    /**
     * @var ProductGatewayInterface
     */
    private $productGateway;

    /**
     * @var ProductPriceDefinitionBuilderInterface
     */
    private $priceDefinitionBuilder;

    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    /**
     * @var ProductFeatureBuilder
     */
    private $featureBuilder;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProductGatewayInterface $productGateway,
        QuantityPriceCalculator $calculator,
        ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder,
        ProductFeatureBuilder $featureBuilder,
        Connection $connection,
        LoggerInterface $logger
    )
    {
        $this->productGateway = $productGateway;
        $this->priceDefinitionBuilder = $priceDefinitionBuilder;
        $this->calculator = $calculator;
        $this->featureBuilder = $featureBuilder;
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void
    {
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(self::TYPE);

        // find products in original cart which requires data from gateway
        $ids = $this->getNotCompleted($data, $lineItems);

        if (!empty($ids)) {
            // fetch missing data over gateway
            $context->addExtension('processorType', new ArrayStruct(['processorType' => self::TYPE]));
            $products = $this->productGateway->get($ids, $context);

            // add products to data collection
            foreach ($products as $product) {
                $data->set('product-' . $product->getId(), $product);
            }
        }

        foreach ($lineItems as $lineItem) {
            // enrich all products in original cart
            $this->enrich($original, $lineItem, $data, $context, $behavior);
            $this->addRelatedProductsToPayload($lineItem, $context);
        }

        $this->featureBuilder->prepare($lineItems, $data, $context);
    }

    /**
     * #dup - @param LineItem $lineItem
     * @param SalesChannelContext $context
     * @link EclmCartProcessor
     * #dup - @link CandyBagsCartProcessor
     */
    private function addRelatedProductsToPayload(LineItem $lineItem, SalesChannelContext $context)
    {
        /*
        * Base Query
        * $sqlSetProducts = 'select product_id, product_version_id, quantity from ec_product_product as pp where pp.set_product_id = :id;';

         * Do not do this. This function is triggered on checkout & if product quantity had changed to <= 0
         * it wil not throw an error because it still thinks that there is enough...
                if ($lineItem->getPayloadValue(self::TYPE) !== null && !$lineItem->isModified()) {
                    return;
                }
        */


        $sqlSetProducts = 'select
                            	pp.product_version_id,
                            	pp.product_id,
                            	pp.quantity,
                            	pt.name,
                            	p.product_number
                            from
                            	ec_product_product as pp
                            	left join product_translation pt on pp.product_id = pt.product_id
                            	left join product p on pp.product_id = p.id
                            where
                            	pp.set_product_id = :id
                            	and pt.language_id = :languageId';

        $rows = $this->connection->fetchAll(
            $sqlSetProducts,
            [
                'id' => Uuid::fromHexToBytes($lineItem->getReferencedId()),
                'languageId' => Uuid::fromHexToBytes($context->getContext()->getLanguageId())
            ]
        );

        $setProducts = [];
        $lineItemSubProducts = "";

        foreach ($rows as $row) {
            $setProducts[] = [
                'product_number' => $row['product_number'],
                'name' => $row['name'],
                'product_id' => Uuid::fromBytesToHex($row['product_id']),
                'product_version_id' => Uuid::fromBytesToHex($row['product_version_id']),
                'quantity' => $row['quantity']
            ];

            // Sub Products line für fljnk
            $lineItemSubProducts .= "- {$row['product_number']} - {$row['name']} - {$row['quantity']}x \n";

        }

        $lineItem->setPayload([self::TYPE => $setProducts]);
        // format setProducts as a string‚
        $lineItem->setPayload(['line_item_sub_products' => $lineItemSubProducts]);
    }

    /**
     * @throws MissingLineItemPriceException
     */
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void
    {

        // handle all products which stored in root level
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(self::TYPE);


        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $definition = $lineItem->getPriceDefinition();

            if (!$definition instanceof QuantityPriceDefinition) {
                throw new MissingLineItemPriceException($lineItem->getId());
            }

            if ($behavior->hasPermission(self::SKIP_PRODUCT_STOCK_VALIDATION)) {
                $definition->setQuantity($lineItem->getQuantity());
                $lineItem->setPrice($this->calculator->calculate($definition, $context));
                $toCalculate->add($lineItem);

                continue;
            }

            /** @var SalesChannelProductEntity $product */
            $product = $data->get('product-' . $lineItem->getReferencedId());

            // container products can not be bought
            if ($product->getChildCount() > 0) {
                $original->remove($lineItem->getId());

                continue;
            }

            if ($lineItem->getQuantity() < $product->getMinPurchase()) {
                $lineItem->setQuantity($product->getMinPurchase());
                $definition->setQuantity($product->getMinPurchase());
            }

            $available = $product->getCalculatedMaxPurchase() ?? $lineItem->getQuantity();

            if ($available <= 0 || $available < $product->getMinPurchase()) {
                $original->remove($lineItem->getId());
                $toCalculate->addErrors(
                    new ProductOutOfStockError($product->getId(), (string)$product->getTranslation('name'))
                );
                continue;
            }

            if ($available < $lineItem->getQuantity()) {
                $lineItem->setQuantity($available);
                $definition->setQuantity($available);
                //ToDo: prüfen....
                $lineItem->setPayloadValue('fixed-quantity', microtime(true));
            }


            $fixedQuantity = $this->fixQuantity($product->getMinPurchase() ?? 1, $lineItem->getQuantity(), $product->getPurchaseSteps() ?? 1);
            if ($lineItem->getQuantity() !== $fixedQuantity) {
                $lineItem->setQuantity($fixedQuantity);
                $definition->setQuantity($fixedQuantity);
                $toCalculate->addErrors(
                    new PurchaseStepsError($product->getId(), (string)$product->getTranslation('name'), $fixedQuantity)
                );
            }

            $lineItem->setPrice($this->calculator->calculate($definition, $context));


            if ((microtime(true) - $lineItem->getPayloadValue('fixed-quantity')) < 5) {
                $toCalculate->addErrors(
                    new ProductStockReachedError($product->getId(), (string)$product->getTranslation('name'), $available)
                );
            }
            $toCalculate->add($lineItem);
        }


        $this->featureBuilder->add($lineItems, $data, $context);
    }

    private function enrich(
        Cart $cart,
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void
    {
        $id = $lineItem->getReferencedId();

        $key = 'product-' . $id;

        $product = $data->get($key);

        if (!$product instanceof SalesChannelProductEntity) {
            $cart->addErrors(new ProductNotFoundError($lineItem->getLabel() ?: $lineItem->getId()));
            $cart->getLineItems()->remove($lineItem->getId());

            return;
        }

        // already enriched and not modified? Skip
        if ($this->isComplete($lineItem) && !$lineItem->isModified()) {
            return;
        }

        $label = trim($lineItem->getLabel() ?? '');
        // set the label if its empty or the context does not have the permission to overwrite it
        if ($label === '' || !$behavior->hasPermission(self::ALLOW_PRODUCT_LABEL_OVERWRITES)) {
            $lineItem->setLabel($product->getTranslation('name'));
        }

        if ($product->getCover()) {
            $lineItem->setCover($product->getCover()->getMedia());
        }

        $deliveryTime = null;
        if ($product->getDeliveryTime() !== null) {
            $deliveryTime = DeliveryTime::createFromEntity($product->getDeliveryTime());
        }

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

        //Check if the price has to be updated
        if ($this->shouldPriceBeRecalculated($lineItem, $behavior)) {
            //In Case keep original Price of Product
            $prices = $this->priceDefinitionBuilder->build($product, $context, $lineItem->getQuantity());

            $lineItem->setPriceDefinition($prices->getQuantityPrice());
        }

        $quantityInformation = new QuantityInformation();

        $quantityInformation->setMinPurchase(
            $product->getMinPurchase() ?? 1
        );

        $quantityInformation->setMaxPurchase(
            $product->getCalculatedMaxPurchase()
        );

        $quantityInformation->setPurchaseSteps(
            $product->getPurchaseSteps() ?? 1
        );

        $lineItem->setQuantityInformation($quantityInformation);

        $purchasePrices = null;

//        ToDo: activate on 6.4.0.0
//        $purchasePricesCollection = $product->getPurchasePrices();
//        if ($purchasePricesCollection !== null) {
//            $purchasePrices = $purchasePricesCollection->getCurrencyPrice(Defaults::CURRENCY);
//        }

        $payload = [
            'isCloseout' => $product->getIsCloseout(),
            'customFields' => $product->getCustomFields(),
            'createdAt' => $product->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'releaseDate' => $product->getReleaseDate() ? $product->getReleaseDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'isNew' => $product->isNew(),
            'markAsTopseller' => $product->getMarkAsTopseller(),
            // @deprecated tag:v6.4.0 - purchasePrice Will be removed in 6.4.0
            'purchasePrice' => $purchasePrices ? $purchasePrices->getGross() : null,
            'purchasePrices' => $purchasePrices ? json_encode($purchasePrices) : null,
            'productNumber' => $product->getProductNumber(),
            'manufacturerId' => $product->getManufacturerId(),
            'taxId' => $product->getTaxId(),
            'tagIds' => $product->getTagIds(),
            'categoryIds' => $product->getCategoryTree(),
            'propertyIds' => $product->getPropertyIds(),
            'optionIds' => $product->getOptionIds(),
            'options' => $this->getOptions($product),
        ];

        $payload['options'] = $product->getVariation();

        $lineItem->replacePayload($payload);
    }

    private function getNotCompleted(CartDataCollection $data, array $lineItems): array
    {
        $ids = [];

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $id = $lineItem->getReferencedId();

            $key = 'product-' . $id;

            // data already fetched?
            if ($data->has($key)) {
                continue;
            }

            // user change line item quantity or price?
            if ($lineItem->isModified()) {
                $ids[] = $id;

                continue;
            }

            // already enriched?
            if ($this->isComplete($lineItem)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    private function isComplete(LineItem $lineItem): bool
    {
        return $lineItem->getPriceDefinition() !== null
            && $lineItem->getLabel() !== null
            && $lineItem->getCover() !== null
            && $lineItem->getDescription() !== null
            && $lineItem->getDeliveryInformation() !== null
            && $lineItem->getQuantityInformation() !== null;
    }

    private function shouldPriceBeRecalculated(LineItem $lineItem, CartBehavior $behavior): bool
    {
        if ($lineItem->getPriceDefinition() !== null
            && $lineItem->hasExtension(self::CUSTOM_PRICE)
            && $behavior->hasPermission(self::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null
            && $behavior->hasPermission(self::SKIP_PRODUCT_RECALCULATION)) {
            return false;
        }

        return true;
    }

    private function getOptions(SalesChannelProductEntity $product): array
    {
        $options = [];

        if (!$product->getOptions()) {
            return $options;
        }

        foreach ($product->getOptions() as $option) {
            if (!$option->getGroup()) {
                continue;
            }

            $options[] = [
                'group' => $option->getGroup()->getTranslation('name'),
                'option' => $option->getTranslation('name'),
            ];
        }

        return $options;
    }

    private function fixQuantity(int $min, int $current, int $steps): int
    {
        return (int)(floor(($current - $min) / $steps) * $steps + $min);
    }
}
