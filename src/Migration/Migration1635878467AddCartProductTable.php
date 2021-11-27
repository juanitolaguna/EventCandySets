<?php
declare(strict_types=1);

namespace EventCandy\Sets\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1635878467AddCartProductTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1635878467;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS `ec_cart_product` (
                `id` BINARY(16) NOT NULL,
                `unique_id` BINARY(16) NOT NULL,
                `token` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                `line_item_id` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `sub_product_id` BINARY(16) NOT NULL,
                `sub_product_quantity` INTEGER NOT NULL,
                `line_item_quantity` INTEGER NOT NULL,
                `line_item_type` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.ec_cart_product.token` FOREIGN KEY (`token`) REFERENCES `cart` (`token`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
