<?php

declare(strict_types=1);

namespace EventCandy\Sets\Decorators;


use EventCandy\Sets\Core\Content\Product\DataAbstractionLayer\LineItemStockUpdaterFunctionsInterface;
use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Notifications\NotificationService;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Parcel\ParcelCustomsInformation;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\Parcel\ParcelItemCustomsInformation;
use Pickware\ShippingBundle\ParcelHydration\ParcelHydrator;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;
use Shopware\Core\Checkout\Document\DocumentEntity as ShopwareDocumentEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * Class PickwareDhlParcelHydratorDecorator
 * @package EventCandy\Sets\Decorators
 * Decorates the PickwareDHL ParcelHydrator to make it compatible with Set Products.
 */
class PickwareDhlParcelHydratorDecorator extends ParcelHydrator
{

    /**
     * @var ?ParcelHydrator
     */
    private $parcelHydrator;

    /**
     * @var ContextFactory
     */
    private $contextFactory;

    /**
     * @var EntityManager
     */
    private $entityManager;


    private NotificationService $notificationService;

    /**
     * @var LineItemStockUpdaterFunctionsInterface[]
     */
    private $stockUpdaterFunctionsSupplier;

    /**
     * @var array
     */
    private $supportedTypes = [];

    /**
     * ParcelHydratorDecorator constructor.
     * @param ?ParcelHydrator $parcelHydrator
     * @param EntityManager $entityManager
     * @param ContextFactory $contextFactory
     * @param iterable $stockUpdaterFunctionsSupplier
     */
    public function __construct(
        ?ParcelHydrator $parcelHydrator,
        EntityManager $entityManager,
        ContextFactory $contextFactory,
        NotificationService $notificationService,
        iterable $stockUpdaterFunctionsSupplier
    ) {
        parent::__construct($entityManager, $contextFactory, $notificationService);
        $this->parcelHydrator = $parcelHydrator;
        $this->entityManager = $entityManager;
        $this->contextFactory = $contextFactory;
        $this->notificationService = $notificationService;
        $this->stockUpdaterFunctionsSupplier = $stockUpdaterFunctionsSupplier;
    }

    public function hydrateParcelFromOrder(string $orderId, Context $context): Parcel
    {
        $orderContext = $this->contextFactory->deriveOrderContext($orderId, $context);
        $orderContext->setConsiderInheritance(true);
        /** @var OrderEntity $order */
        $order = $this->entityManager->findByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $orderContext,
            [
                'currency',
                'documents.documentType',
                'lineItems.lineItemProducts.product.masterProducts'
            ]
        );

        $parcel = new Parcel();
        $parcel->setCustomerReference($order->getOrderNumber());

        $customsInformation = new ParcelCustomsInformation($parcel);
        $currencyCode = $order->getCurrency()->getIsoCode();
        $shippingCosts = new MoneyValue($order->getShippingTotal(), new Currency($currencyCode));
        $customsInformation->addFee(ParcelCustomsInformation::FEE_TYPE_SHIPPING_COSTS, $shippingCosts);

        $invoices = $order->getDocuments()->filter(
            function (ShopwareDocumentEntity $document) {
                return $document->getDocumentType()->getTechnicalName() === InvoiceGenerator::INVOICE;
            }
        );
        $invoiceNumbers = $invoices->map(function (ShopwareDocumentEntity $document) {
            return $document->getConfig()['documentNumber'];
        });
        $customsInformation->setInvoiceNumbers(array_values($invoiceNumbers));


        $this->setSupportedTypes();
        foreach ($order->getLineItems() as $orderLineItem) {
            if (!in_array($orderLineItem->getType(), $this->supportedTypes, true)) {
                continue;
            }


            $parcelItem = new ParcelItem($orderLineItem->getQuantity());

            $parcel->addItem($parcelItem);

            $itemCustomsInformation = new ParcelItemCustomsInformation($parcelItem);
            $itemCustomsInformation->setCustomsValue(
                new MoneyValue(
                    $orderLineItem->getUnitPrice(),
                    new Currency($currencyCode)
                )
            );

            $parcelItem->setName($orderLineItem->getLabel());

            $type = $orderLineItem->getType();
            $calculatedWeight = $orderLineItem->getPayload()[$type];

            if ($calculatedWeight["total_weight"]) {
                $weight = round($calculatedWeight["total_weight"] / $orderLineItem->getQuantity(), 4);
                $parcelItem->setUnitWeight(new Weight($weight, 'kg'));
            } else {
                $parcelItem->setUnitWeight(null);
            }

            $description = $orderLineItem->getLabel();

            $itemCustomsInformation->setDescription($description);
        }
        return $this->mergeParcelItemsWithInner($parcel, $orderId, $context);
    }

    private function setSupportedTypes(): void
    {
        foreach ($this->stockUpdaterFunctionsSupplier as $supplier) {
            $this->supportedTypes[] = $supplier->getLineItemType();
        }
    }

    private function mergeParcelItemsWithInner(Parcel $parcel, string $orderId, Context $context): Parcel
    {
        $parentItems = $this->parcelHydrator->hydrateParcelFromOrder($orderId, $context);
        foreach ($parentItems->getItems() as $parcelItem) {
            $parcel->addItem($parcelItem);
        }

        return $parcel;
    }
}
