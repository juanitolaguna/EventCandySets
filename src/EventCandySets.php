<?php declare(strict_types=1);

namespace EventCandy\Sets;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class EventCandySets extends Plugin
{

    /**
     * @var CustomFieldService
     */
    private $customFieldService;

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->customFieldService->createCustomFields();
    }




    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
        if ($context->keepUserData()) {
            return;
        }
        $this->customFieldService->deleteCustomFields();
        $connection = $this->container->get(Connection::class);
        $connection->executeUpdate('DROP TABLE IF EXISTS `ec_set_product`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `ec_product_product`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `ec_set`');
    }


    /**
     * @required
     * @param CustomFieldService $customFieldService
     */
    public function setCustomFieldService(CustomFieldService $customFieldService): void
    {
        $this->customFieldService = $customFieldService;
    }



}
