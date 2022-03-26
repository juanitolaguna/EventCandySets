<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadRepository;

use Doctrine\DBAL\Connection;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadService;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService\DynamicProductServiceInterface;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayloadRepository implements PayloadRepositoryInterface
{
    private Connection $connection;

    private DynamicProductServiceInterface $dynamicProductService;

    public function __construct(Connection $connection, DynamicProductServiceInterface $dynamicProductService)
    {
        $this->connection = $connection;
        $this->dynamicProductService = $dynamicProductService;
    }

    /**
     * @inheritDoc
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
            $result = $this->connection->fetchAllAssociative(
                $sqlSetProducts,
                [
                    'dynamicProductId' => Uuid::fromHexToBytes($dynamicProduct->getId()),
                    'languageId' => Uuid::fromHexToBytes($context->getContext()->getLanguageId()),
                    'mainProductId' => Uuid::fromHexToBytes($dynamicProduct->getProductId())
                ]
            );

            $key = PayloadService::getPayloadKey($dynamicProduct->getId());
            $data->set($key, $result);
        }
    }
}