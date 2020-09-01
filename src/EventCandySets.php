<?php declare(strict_types=1);

namespace EventCandy\Sets;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class EventCandySets extends Plugin
{
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        $connection->executeUpdate('DROP TABLE IF EXISTS `ec_set_product`');
//        $connection->executeUpdate('ALTER TABLE `product` DROP COLUMN `is_set`');
    }
}
