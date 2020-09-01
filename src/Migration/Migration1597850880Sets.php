<?php declare(strict_types=1);

namespace EventCandy\Sets\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597850880Sets extends MigrationStep
{

    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1597850880;
    }

    public function update(Connection $connection): void
    {
//        $connection->executeUpdate('ALTER TABLE `product` ADD COLUMN `is_set` TINYINT(1) NULL DEFAULT \'0\'');

        /** ToDo: Add product version Id for both sides.. set_product & product */
        $connection->executeUpdate('CREATE TABLE IF NOT EXISTS `ec_set_product` (
            `id` BINARY(16) NOT NULL,
            `set_product_id` BINARY(16) NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `quantity` INT(11) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`, `set_product_id`, `product_id`),
            KEY `fk.ec_set_product.set_product_id` (`set_product_id`),
            KEY `fk.ec_set_product.product_id` (`product_id`),
            CONSTRAINT `fk.ec_set_product.set_product_id` FOREIGN KEY (`set_product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_set_product.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->updateInheritance($connection, 'product', 'products');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
