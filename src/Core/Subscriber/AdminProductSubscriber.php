<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use ErrorException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdminProductSubscriber implements EventSubscriberInterface
{

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    public static function getSubscribedEvents()
    {
        return [
            'product.loaded' => 'onProductLoaded'
        ];
    }

    public function onProductLoaded(EntityLoadedEvent $event)
    {

        // Execude only if not a SalesChannel Product
        if(!empty($event->getEntities())) {
            $type = $event->getEntities()[0];
        } else {
            return;
        }

        if (get_class($type) !== "Shopware\Core\Content\Product\ProductEntity") {
            return;
        }

        /** @var ProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $keyIsTrue = array_key_exists('ec_is_set', $product->getCustomFields() ?? [] ) && $product->getCustomFields()['ec_is_set'];
            if ($keyIsTrue) {
                $calculatedStock = $this->getAvailableStock($product->getId(), $event->getContext());
                $product->setStock((int) $calculatedStock['stock']);
                $product->setAvailableStock((int) $calculatedStock['available_stock']);
            }
        }
    }

    private function getAvailableStock(string $productId, Context $context): array
    {
        $sql = "select
                	floor(min(stock)) as stock,
                	floor(min(available_stock)) as available_stock
                from (
                	select
                		(subProduct.stock / pp.quantity) as stock,
                		(subProduct.available_stock / pp.quantity) as available_stock,
                		pp.quantity
                	from
                		product as subProduct
                		inner join ec_product_product as pp on subProduct.id = pp.product_id and subProduct.version_id = pp.product_version_id
                	where
                		pp.set_product_id = :productId and pp.product_version_id = :version) as calculated;";

        try {
            $result = $this->connection->fetchAssociative($sql, [
                'productId' => Uuid::fromHexToBytes($productId),
                'version' => Uuid::fromHexToBytes($context->getVersionId())
            ]);
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage());
        }

        return $result;
    }
}