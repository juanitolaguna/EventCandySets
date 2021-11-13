<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group store-api
 */
class SetProductTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use SalesChannelApiTestBehaviour;


    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;


    /**
     * @var EntityRepositoryInterface
     */
    private $setProductRepository;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var string[]
     */
    private $products;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var Connection
     */
    private $connection;

    public const MAIN_PRODUCT_STOCK = 100;

    public const SUB_PRODUCT_MIN_STOCK = 5;


    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->setProductRepository = $this->getContainer()->get('ec_product_product.repository');

        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->products = ['lw1', 'lw2', 'lw3', 'vk1'];
        $this->createProducts($this->ids, $this->products);
        $this->addSubProducts($this->ids, $this->products);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel')
        ]);
        $this->setVisibilities();
    }

    public function testAvailableStockDiffersInDbAndRepository()
    {
        $vkId = $this->ids->get('vk1');
        $result = $this->connection
            ->fetchOne("select available_stock from product where product.id = :id", [
                'id' => Uuid::fromHexToBytes($vkId)
            ]);
        self::assertEquals(self::MAIN_PRODUCT_STOCK, $result);

        // Now load over the repo should be SUB_PRODUCT_MIN_STOCK
        $criteria = new Criteria([$vkId]);
        /** @var ProductEntity $result */
        $result = $this->productRepository->search($criteria, Context::createDefaultContext())->first();
        self::assertEquals(self::SUB_PRODUCT_MIN_STOCK, $result->getAvailableStock());
    }

    public function testOneCanAddSubProductsToProduct()
    {
        $criteria = new Criteria([$this->ids->get('vk1')]);
        $criteria->addAssociation('products');

        /** @var ProductEntity $result */
        $result = $this->productRepository->search($criteria, Context::createDefaultContext())->first();
        /** @var ProductCollection $products */
        $products = $result->get('products');
        self::assertInstanceOf(ProductCollection::class, $products);
        self::assertEquals(3, $products->count());
    }


    public function testItShowsCorrectCalculatedStockInSalesChannel()
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/product/' . $this->ids->get('vk1')
            );

        $content = \json_decode($this->browser->getResponse()->getContent(), true);
        self::assertEquals(5, $content['product']['availableStock']);
    }


    /**
     * @param TestDataCollection $ids
     * @param array $products
     */
    private function createProducts(TestDataCollection $ids, array $products): void
    {
        $this->getContainer()->get('category.repository')
            ->create([
                ['id' => $ids->create('cat-1'), 'name' => 'test'],
                ['id' => $ids->create('cat-2'), 'name' => 'test'],
                ['id' => $ids->create('cat-3'), 'name' => 'test'],
            ], Context::createDefaultContext());

        $data = [];
        $stock = self::SUB_PRODUCT_MIN_STOCK;
        foreach ($products as $product) {
            $data[] = [
                'id' => $ids->create($product),
                'productNumber' => $ids->get($product),
                'name' => $product,
                'stock' => $product === 'vk1' ? self::MAIN_PRODUCT_STOCK : $stock++,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'active' => true,
                'customFields' => ['ec_is_set' => $product === 'vk1'],
                'tax' => [
                    'id' => $ids->create('tax'),
                    'name' => 'test',
                    'taxRate' => 15,
                ],
                'categories' => [
                    ['id' => $ids->create('cat-1')],
                    ['id' => $ids->create('cat-2')],
                    ['id' => $ids->create('cat-3')],
                ]
            ];
        }

        $this->productRepository->create($data, Context::createDefaultContext());
    }

    /**
     * @param TestDataCollection $ids
     * @param array $products
     */
    private function addSubProducts(TestDataCollection $ids, array $products)
    {
        $data = [];

        $quantity = 10;

        foreach ($products as $product) {
            if ($product === 'vk1') {
                continue;
            }
            $quantity = 1;
            $data[] = [
                'id' => Uuid::randomHex(),
                'setProductId' => $ids->get('vk1'),
                'productId' => $ids->get($product),
                'quantity' => $quantity
            ];
        }
        $this->setProductRepository->create($data, Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $update = [
            [
                'id' => $this->ids->get('vk1'),
                'visibilities' => [
                    [
                        'salesChannelId' => $this->ids->get('sales-channel'),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL
                    ],
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')
            ->update($update, $this->ids->context);
    }
}



