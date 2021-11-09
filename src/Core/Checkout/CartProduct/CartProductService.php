<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartProduct;

use Doctrine\DBAL\Connection;

class CartProductService {

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function addLineItems() {
        
    }


}