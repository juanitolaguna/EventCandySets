<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;


use Doctrine\DBAL\Connection;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductStruct;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductGateway;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use EventCandy\Sets\Test\Utils\ValidDataTrait;
use Monolog\Test\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

class DynamicProductServiceTest extends TestCase
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

    public function testItCreatesDynamicProductCollectionFromLineItems()
    {
        $cart = Generator::createCart();
        $lineItems = $cart->getLineItems()->getElements();

        /** @var DynamicProductStruct[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection($lineItems, $cart->getToken());

        $dynamicProductIds = $this->dynamicProductService->getDynamicProductIdsFromCollection($dynamicProducts);

        self::assertContainsOnlyInstancesOf(DynamicProductStruct::class, $dynamicProducts);
        foreach ($dynamicProductIds as $id) {
            self::assertIsString($id, "The elements in the dynamicProductIds array have a wrong datatype\n");
        }
    }

    public function testItSavesDynamicProductCollectionToDb()
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

        /** @var DynamicProductStruct[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection($lineItems, $cart->getToken());
        $this->dynamicProductService->saveDynamicProductsToDb($dynamicProducts);

        $sql = "select count(*) from ec_dynamic_product where token = :token";

        $result = $this->connection->fetchOne($sql, [
            'token' => $context->getToken()
        ]);

        self::assertEquals(2, $result);

        $this->dynamicProductService->deleteDynamicProductsByToken($context->getToken());
        $result = $this->connection->fetchOne($sql, [
            'token' => $context->getToken()
        ]);

        self::assertEquals(0, $result);
    }

    public function testItSavesDynamicProductsToCartDataCollectionByLineItemId()
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

        /** @var DynamicProductStruct[] $dynamicProducts */
        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection($lineItems, $cart->getToken());
        $this->dynamicProductService->saveDynamicProductsToDb($dynamicProducts);

        $dynamicProductIds = $this->dynamicProductService->getDynamicProductIdsFromCollection($dynamicProducts);

        $result = $this->dynamicProductGateway->get($dynamicProductIds, $context->getContext());

        $cartDataCollection = new CartDataCollection();
        $this->dynamicProductService->addDynamicProductsToCartDataByLineItemId($result, $cartDataCollection);

        $lineItemKey1 = DynamicProductService::DYNAMIC_PRODUCT_LINE_ITEM_ID . $this->ids->get('li1');
        $lineItemKey2 = DynamicProductService::DYNAMIC_PRODUCT_LINE_ITEM_ID . $this->ids->get('li2');


        self::assertTrue($cartDataCollection->has($lineItemKey1));
        self::assertTrue($cartDataCollection->has($lineItemKey2));

        self::assertInstanceOf(DynamicProductEntity::class, $cartDataCollection->get($lineItemKey1)[0]);
        self::assertInstanceOf(DynamicProductEntity::class, $cartDataCollection->get($lineItemKey2)[0]);

        $productArray = $this->dynamicProductService->getFromCartDataByLineItemId($this->ids->get('li1'), $cartDataCollection);
        self::assertInstanceOf(DynamicProductEntity::class, $productArray[0]);
    }
}