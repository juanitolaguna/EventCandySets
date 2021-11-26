<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Checkout\Cart\Exception\PayloadMissingInCartDataException;
use EventCandy\Sets\Core\Checkout\Cart\Exception\ProductsMissingInPayloadObjectException;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayloadService
{
    public const LINE_ITEM_PAYLOAD_ID = 'line_item_payload_id-';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var DynamicProductService
     */
    private $dynamicProductService;


    /**
     * @param Connection $connection
     * @param DynamicProductService $dynamicProductService
     */
    public function __construct(
        Connection $connection,
        DynamicProductService $dynamicProductService
    ) {
        $this->connection = $connection;
        $this->dynamicProductService = $dynamicProductService;
    }

    /**
     * Fetches the required data to build the subproducts payload and saves it to the CartDataCollection.
     * For Performance gain same method is used to fetch data to build the CartProduct collection.
     * Hence 'subproducts' are needed for 2 Different DataStructures but are fetched only once from Db.
     * @throws Exception
     */
    public function loadPayloadDataForLineItem(
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context
    ): void {
        $sqlSetProducts = "SELECT
            	dp.id AS dynamicProductId,
            	pp.product_id,
            	pp.quantity,
            	pt.name,
            	p.product_number,
            	IFNULL(p.weight, 0.0) AS weight
            FROM
            	ec_dynamic_product AS dp
            	LEFT JOIN ec_product_product pp ON dp.product_id = pp.set_product_id
            	LEFT JOIN product_translation pt ON pp.product_id = pt.product_id
            	LEFT JOIN product p ON pp.product_id = p.id
            	LEFT JOIN product_translation mainProductTranslation ON pp.set_product_id = mainProductTranslation.product_id
            WHERE
            	dp.id = :dynamicProductId
            	AND pt.language_id = :languageId";


        /** @var DynamicProductEntity[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService
            ->getFromCartDataByLineItemId($lineItem->getId(), $data);

        foreach ($dynamicProducts as $dynamicProduct) {
            $key = self::getPayloadKey($dynamicProduct->getId());
            $result = $this->connection->fetchAllAssociative(
                $sqlSetProducts,
                [
                    'dynamicProductId' => Uuid::fromHexToBytes($dynamicProduct->getId()),
                    'languageId' => Uuid::fromHexToBytes($context->getContext()->getLanguageId())
                ]
            );
            $data->set($key, $result);
        }
    }

    /**
     * The Idea is to resolve one data structure to multiple representations
     * as associative array, string and maybe serialize and deserialize it for StockUpdater usage.
     * @param array $payloadData
     * @return PayloadLineItem
     */
    public function buildPayloadObject(LineItem $lineItem, CartDataCollection $data): PayloadLineItem
    {
        /** @var DynamicProductEntity $product */
        $products = $this->dynamicProductService->getFromCartDataByLineItemId($lineItem->getId(), $data);

        $label = $lineItem->getLabel() ?? '';
        $quantity = $lineItem->getQuantity();

        $payloadLineItem = new PayloadLineItem(
            $label,
            $quantity
        );

        foreach ($products as $dynamicProduct) {
            $payload = $data->get(PayloadService::getPayloadKey($dynamicProduct->getId()));
            if (!$payload) {
                throw new PayloadMissingInCartDataException();
            }

            $product = $dynamicProduct->getProduct();

            $payloadProducts = $this->getSubProductsFromPayload($payload);

            $payloadLineItemProduct = new PayloadLineItemProduct(
                $product->getProductNumber(),
                $product->getId(),
                $product->getTranslation('name'),
                $product->getWeight() ?? 0.0,
                $lineItem->getQuantity(),
                $payloadProducts ?? []
            );
            $payloadLineItem->addProduct($payloadLineItemProduct);
        }

        return $payloadLineItem;
    }


    /**
     * @param PayloadLineItem $payloadLineItem
     * @return array
     */
    public function makePayloadDataAssociative(PayloadLineItem $payloadLineItem, string $payloadKey): array
    {
        $this->validatePayloadObject($payloadLineItem);
        $products = $this->makePayloadDataAssociativeRecursive($payloadLineItem->getProducts());

        return [
            $payloadKey => [
                'products' => $products,
                'total_weight' => $payloadLineItem->getTotalWeight(),
            ]
        ];
    }

    /**
     * @param PayloadLineItem $payloadLineItem
     * @return string
     */
    public function makePayloadDataString(PayloadLineItem $payloadLineItem): string
    {
        $this->validatePayloadObject($payloadLineItem);
        // ToDo...makePayloadDataString
        return '--';
    }

    /**
     * @param string $dynamicProductId
     * @return string
     */
    public static function getPayloadKey(string $dynamicProductId)
    {
        return self::LINE_ITEM_PAYLOAD_ID . $dynamicProductId;
    }

    /**
     * @param PayloadLineItem $lineItem
     * @throws ProductsMissingInPayloadObjectException
     */
    private function validatePayloadObject(PayloadLineItem $lineItem): void
    {
        if (count($lineItem->getProducts()) === 0) {
            throw new ProductsMissingInPayloadObjectException();
        }
    }

    /**
     * @param array $payload
     * @return PayloadLineItemProduct[]
     */
    private function getSubProductsFromPayload(array $payload): array
    {
        /** @var PayloadLineItemProduct[] $payloadLineItemProducts */
        $payloadLineItemProducts = [];
        foreach ($payload as $row) {
            $productId = Uuid::fromBytesToHex($row['product_id']);
            $payloadProduct = new PayloadLineItemProduct(
                $row['product_number'],
                $productId,
                $row['name'],
                (float) $row['weight'],
                (int) $row['quantity'],
                []
            );
            $payloadLineItemProducts[] = $payloadProduct;
        }
        return $payloadLineItemProducts;
    }

    /**
     * @param PayloadLineItemProduct $products
     * @return array
     */
    private function makePayloadDataAssociativeRecursive(array $products): array
    {
        $productsAssociative = [];
        /** @var PayloadLineItemProduct $product */
        foreach ($products as $product) {
            if (count($product->getProducts()) > 0) {
                $children = $this->makePayloadDataAssociativeRecursive($product->getProducts());
            }
            $productsAssociative[] = [
                'product_id' => $product->getProductId(),
                'product_number' => $product->getProductNumber(),
                'product_name' => $product->getName(),
                'quantity' => $product->getQuantity(),
                'weight' => $product->getWeight(),
                'products' => $children ?? ''
            ];
        }
        return $productsAssociative;
    }
}