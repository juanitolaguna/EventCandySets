<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\TestData;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class CartProcessorTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use IntegrationTestBehaviour;


    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;


    /**
     * @var EntityRepositoryInterface
     */
    private $setProductRepository;





    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        //$this->setProductRepository = $this->getContainer()->get('ec_product_product.repository');
    }

    public function testItWorks()
    {
        $ids = new TestDataCollection();

        $this->getContainer()->get('category.repository')
            ->create([
                ['id' => $ids->create('cat-1'), 'name' => 'test'],
                ['id' => $ids->create('cat-2'), 'name' => 'test'],
                ['id' => $ids->create('cat-3'), 'name' => 'test'],
            ], Context::createDefaultContext());


        $products = ['lw1', 'lw2', 'lw3', 'vk1'];

        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $ids->create($product),
                'productNumber' => $ids->get($product),
                'name' => $product,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
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

        echo print_r( $this->getContainer()->getParameter('kernel.project_dir'), true);

        $this->connectSubProducts($ids, $products);

        $criteria = new Criteria([$ids->get('vk1')]);
        $criteria->addAssociation('products');

        /** @var ProductEntity $result */
        $result = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        //echo print_r($result, true);
    }

    private function connectSubProducts(TestDataCollection $ids, array $products) {
        $data = [];

        $quantity = 10;

        foreach ($products as $product) {
            if ($product === 'vk1') {
                continue;
            }

            $data[] = [
                'id' => Uuid::randomHex(),
                'setProductId' => $ids->get('vk1'),
                'productId' => $ids->get($product),
                'quantity' => $quantity += 10
            ];
        }

        $this->setProductRepository->create($data, Context::createDefaultContext());


    }
}



