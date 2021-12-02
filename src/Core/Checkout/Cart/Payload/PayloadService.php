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
        $sqlSetProducts = "
                SELECT
                	dp.id AS dynamicProductId,
                	IFNULL(pp.product_id, mainProduct.id) as product_id,
                	IFNULL(pp.quantity, 1) as quantity, 
                	IFNULL(pt.name, mainProductTranslation.name) as name,
                	IFNULL(p.product_number, mainProduct.product_number) as product_number,
                	IFNULL(p.weight, IF(pp.product_id IS NULL, IFNULL(mainProduct.weight, 0.0), 0.0)) AS weight
                FROM
                	ec_dynamic_product AS dp
                	LEFT JOIN ec_product_product pp ON dp.product_id = pp.set_product_id
                	LEFT JOIN product_translation pt ON pp.product_id = pt.product_id
                	LEFT JOIN product p ON pp.product_id = p.id
                	LEFT JOIN product_translation mainProductTranslation ON :mainProductId = mainProductTranslation.product_id
                	LEFT JOIN product mainProduct ON :mainProductId = mainProduct.id
                WHERE
                	dp.id = :dynamicProductId AND mainProductTranslation.language_id = :languageId;";


        /** @var DynamicProductEntity[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService
            ->getFromCartDataByLineItemId($lineItem->getId(), $data);

        foreach ($dynamicProducts as $dynamicProduct) {
            $key = self::getPayloadKey($dynamicProduct->getId());
            $result = $this->connection->fetchAllAssociative(
                $sqlSetProducts,
                [
                    'dynamicProductId' => Uuid::fromHexToBytes($dynamicProduct->getId()),
                    'languageId' => Uuid::fromHexToBytes($context->getContext()->getLanguageId()),
                    'mainProductId' => Uuid::fromHexToBytes($dynamicProduct->getProductId())
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
        /** @var DynamicProductEntity[] $products */
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

            $payloadProducts = $this->getSubProductsFromPayload($payload, $dynamicProduct);
            //Utils::log(print_r($payloadProducts, true));

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
        $products = $this->makePayloadDataAssociativeIterate($payloadLineItem->getProducts());
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
    private function getSubProductsFromPayload(array $payload, DynamicProductEntity $dynamicProductEntity): array
    {
        /** @var PayloadLineItemProduct[] $payloadLineItemProducts */
        $payloadLineItemProducts = [];
        foreach ($payload as $row) {
            $productId = Uuid::fromBytesToHex($row['product_id']);

            /** This case occurs only with normal products because of
             * IFNULL(pp.product_id, mainProduct.id) as product_id,
             * @link PayloadService::loadPayloadDataForLineItem()
             */
            if ($productId === $dynamicProductEntity->getProductId()) {
                continue;
            }
            $payloadProduct = new PayloadLineItemProduct(
                $row['product_number'],
                $productId,
                $row['name'],
                (float)$row['weight'],
                (int)$row['quantity'],
                []
            );
            //Utils::log(print_r($payloadProduct, true));
            $payloadLineItemProducts[] = $payloadProduct;
        }
        return $payloadLineItemProducts;
    }

    /**
     * @param PayloadLineItemProduct $products
     * @return array
     */
    private function makePayloadDataAssociativeIterate(array $products): array
    {
        $productsAssociative = [];
        /** @var PayloadLineItemProduct $product */
        foreach ($products as $product) {
            if (count($product->getProducts()) > 0) {
                /** @var PayloadLineItemProduct $child */

                foreach ($product->getProducts() as $child) {
                    $children[] = [
                        'product_id' => $child->getProductId(),
                        'product_number' => $child->getProductNumber(),
                        'product_name' => $child->getName(),
                        'quantity' => $child->getQuantity(),
                        'weight' => $child->getWeight(),
                        'products' => ''
                    ];
                }
            }

            $productsAssociative[] = [
                'product_id' => $product->getProductId(),
                'product_number' => $product->getProductNumber(),
                'product_name' => $product->getName(),
                'quantity' => $product->getQuantity(),
                'weight' => $product->getWeight(),
                'products' => $children ?? ''
            ];

            $children = null;
        }
        return $productsAssociative;
    }

    public function removePayloadDataByLineItemId(CartDataCollection $data, string $lineItemId)
    {
        /** @var DynamicProductEntity[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService
            ->getFromCartDataByLineItemId($lineItemId, $data);

        foreach ($dynamicProducts as $dynamicProduct) {
            $key = self::getPayloadKey($dynamicProduct->getId());
            if ($data->has($key)) {
                $data->remove($key);
            }
        }
    }
}