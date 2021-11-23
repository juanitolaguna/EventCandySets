<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;

use EventCandy\Sets\Test\Utils\ToDoTestBehaviour;
use Monolog\Test\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;

class CartProcessorTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use ToDoTestBehaviour;

    public function testItSavesDataToCartProductTable() {
        $this->todo();
    }

    public function testItSavesCorrectQuantityToCartProductTable() {
        $this->todo();
    }

    public function testItShowsCorrectAvailableStockInCart() {
        $this->todo();
    }

    public function testItShowsCorrectAvailableStockInSalesChannelIfSharedProductsInCart() {
        $this->todo();
    }
}