<?php
declare(strict_types=1);

namespace EventCandy\Sets\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1637051141DynamicProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1637051141;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `ec_dynamic_product` (
            `id` BINARY(16) NOT NULL,
            `token` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `line_item_id` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `is_new` TINYINT(1) NULL DEFAULT \'0\',
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.ec_dynamic_product.token` FOREIGN KEY (`token`) REFERENCES `cart` (`token`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_dynamic_product.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
