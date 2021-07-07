<?php declare(strict_types=1);

namespace EventCandy\Sets\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1625093065OrderLineItemProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625093065;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('CREATE TABLE IF NOT EXISTS `ec_order_line_item_product` (
            `id` BINARY(16) NOT NULL,
            `parent_id` BINARY(16) NULL,
            `product_id` BINARY(16) NOT NULL,
            `product_version_id` BINARY(16) NOT NULL,
            `order_id` BINARY(16) NOT NULL,
            `order_version_id` BINARY(16) NOT NULL,
            `order_line_item_id` BINARY(16) NOT NULL,
            `order_line_item_version_id` BINARY(16) NOT NULL,
            `quantity` INTEGER NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.ec_order_line_item_product.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `ec_order_line_item_product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_order_line_item_product.product_id` FOREIGN KEY (`product_id`,`product_version_id`) REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_order_line_item_product.order_id` FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_order_line_item_product.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`) REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
