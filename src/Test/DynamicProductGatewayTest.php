<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;

use Doctrine\DBAL\Connection;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProduct;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductGateway;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Test\Utils\ValidDataTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

class DynamicProductGatewayTest extends TestCase
{


    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CartRuleHelperTrait;
    use ValidDataTrait;

    /**
     * @var DynamicProductService
     */
    protected $dynamicProductService;

    /**
     * @var CartPersister
     */
    protected $cartPersister;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var TestDataCollection
     */
    protected $ids;

    /**
     * @var DynamicProductGateway
     */
    protected $dynamicProductGateway;


    protected function setUp(): void
    {
        parent::setUp();
        $this->dynamicProductService = $this->getContainer()->get(DynamicProductService::class);
        $this->cartPersister = $this->getContainer()->get(CartPersister::class);
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->ids = new TestDataCollection();

        $this->dynamicProductGateway = $this->getContainer()->get(DynamicProductGateway::class);
    }


    public function testItFetchesDynamicProductEntities()
    {
        $products = ['vk1', 'vk2'];
        $this->createAndSaveProducts($this->ids, $products);
        $lineItems = ['li1', 'li2'];
        $lineItems = $this->createLineItems($this->ids, $products, $lineItems);
        $cart = $this->createCart($lineItems);

        $context = $this->createSalesChannelContext();
        $cart->setToken($context->getToken());
        $this->cartPersister->save($cart, $context);
        $lineItems = $cart->getLineItems()->getElements();

        /** @var DynamicProduct[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection($lineItems, $cart->getToken());
        $this->dynamicProductService->saveDynamicProductsToDb($dynamicProducts);

        $dynamicProductIds = $this->dynamicProductService->getDynamicProductIdsFromCollection($dynamicProducts);

        $result = $this->dynamicProductGateway->get($dynamicProductIds, $context->getContext());
        $dynamicProducts = $result->getElements();

        self::assertInstanceOf(DynamicProductCollection::class, $result);
        self::assertContainsOnlyInstancesOf(DynamicProductEntity::class, $dynamicProducts);
        self::assertCount(2, $dynamicProducts);
    }
}