<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadRepository;

use Doctrine\DBAL\Connection;
use EventCandy\Sets\Core\Checkout\Cart\Collections\DynamicProductPayloadCollection\DynamicProductPayloadCollection;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadService;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadStruct;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadStructCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayloadRepository implements PayloadRepositoryInterface
{
    private Connection $connection;

    private const DYNAMIC_PRODUCT_ID = 'dynamic_product_id';
    private const PRODUCT_ID = 'product_id';
    private const QUANTITY = 'quantity';
    private const NAME = 'name';
    private const PRODUCT_NUMBER = 'product_number';
    private const WEIGHT = 'weight';




    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     * [string $dynamicProductId, array<mixed $dynamicProductData>]
     */
    public function loadPayloadDataForLineItem(
        LineItem $lineItem,
        DynamicProductCollection $dynamicProducts,
        DynamicProductPayloadCollection $data,
        SalesChannelContext $context
    ): void {
        $sqlSetProducts = "
                SELECT
                	dp.id AS dynamic_product_id,
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


        foreach ($dynamicProducts as $dynamicProduct) {
            $result = $this->connection->fetchAllAssociative(
                $sqlSetProducts,
                [
                    'dynamicProductId' => Uuid::fromHexToBytes($dynamicProduct->getId()),
                    'languageId' => Uuid::fromHexToBytes($context->getContext()->getLanguageId()),
                    'mainProductId' => Uuid::fromHexToBytes($dynamicProduct->getProductId())
                ]
            );

            $result = $this->toCollection($result);

            $data->set($dynamicProduct->getId(), $result);
        }
    }

    private function toCollection(array $result): PayloadStructCollection
    {
        $collection = new PayloadStructCollection();

        foreach ($result as $row) {
           $payload =  new PayloadStruct(
                Uuid::fromBytesToHex($row[self::DYNAMIC_PRODUCT_ID]),
                Uuid::fromBytesToHex($row[self::PRODUCT_ID]),
                $row[self::QUANTITY],
                $row[self::NAME],
                $row[self::PRODUCT_NUMBER],
                $row[self::WEIGHT]
            );

           $collection->add($payload);
        }
        return $collection;
    }
}