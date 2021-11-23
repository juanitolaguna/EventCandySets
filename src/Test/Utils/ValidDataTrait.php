<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test\Utils;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestDataCollection;

trait ValidDataTrait
{

    /**
     * @param TestDataCollection $ids
     * @param array $products
     * @return TestDataCollection
     */
    private function createAndSaveProducts(TestDataCollection $ids, array $products): void
    {
        $mainProductStock = 100;
        $subProductMinStock = 5;

        $this->getContainer()->get('category.repository')
            ->create([
                ['id' => $ids->create('cat-1'), 'name' => 'test'],
                ['id' => $ids->create('cat-2'), 'name' => 'test'],
                ['id' => $ids->create('cat-3'), 'name' => 'test'],
            ], Context::createDefaultContext());

        $data = [];
        $stock = $subProductMinStock;
        foreach ($products as $product) {
            $data[] = [
                'id' => $ids->create($product),
                'productNumber' => $ids->get($product),
                'name' => $product,
                'stock' => $product === 'vk1' ? $mainProductStock : $stock++,
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

        $this->getContainer()->get('product.repository')->create($data, Context::createDefaultContext());
    }

    private function createLineItems(TestDataCollection $ids, array $products, array $lineItems): LineItemCollection
    {
        if (count($products) !== count($lineItems)) {
            throw new \ErrorException('Product and LineItem Arrays must have the same length');
        }

        $lineItemCollection = new LineItemCollection();

        $unitPrice = 10;
        $totalPrice = 270;
        $quantity = 27;
        $productNumber = 0;

        foreach ($lineItems as $lineItem) {
            $lineItemCollection->add(
                (new LineItem($ids->create($lineItem), 'setproduct', $this->ids->get($products[$productNumber++]), 27))
                    ->setPrice(
                        new CalculatedPrice(
                            $unitPrice++,
                            $totalPrice++,
                            new CalculatedTaxCollection(),
                            new TaxRuleCollection(),
                            $quantity++
                        )
                    )
            );
        }
        return $lineItemCollection;
    }
}