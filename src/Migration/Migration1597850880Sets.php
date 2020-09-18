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

        $connection->executeUpdate('CREATE TABLE IF NOT EXISTS `ec_set` (
            `id` BINARY(16) NOT NULL,
            `name` VARCHAR(255) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $connection->executeUpdate('CREATE TABLE IF NOT EXISTS `ec_set_product` (
            `set_id` BINARY(16) NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `product_version_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`set_id`,`product_id`,`product_version_id`),
            CONSTRAINT `fk.ec_set_product.set_id` FOREIGN KEY (`set_id`)
                REFERENCES `ec_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_set_product.product_id` FOREIGN KEY (`product_id`,`product_version_id`)
                REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $connection->executeUpdate('CREATE TABLE IF NOT EXISTS `ec_product_product` (
            `id` BINARY(16) NOT NULL,
            `set_product_id` BINARY(16) NOT NULL,
            `set_product_version_id` BINARY(16) NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `product_version_id` BINARY(16) NOT NULL,
            `quantity` INTEGER NOT NULL DEFAULT 1,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.ec_product_product.set_product_id` FOREIGN KEY (`set_product_id`,`set_product_version_id`) REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.ec_product_product.product_id` FOREIGN KEY (`product_id`,`product_version_id`) REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

//        PRIMARY KEY (`id`,`set_product_id`,`product_id`,`set_product_version_id`,`product_version_id`),
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
