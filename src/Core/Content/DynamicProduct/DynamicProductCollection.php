<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct;

use EventCandy\Sets\Storefront\Page\Product\Subscriber\ProductListingSubscriber;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @method void                add(DynamicProductEntity $entity)
 * @method void                set(string $key, DynamicProductEntity $entity)
 * @method DynamicProductEntity[]    getIterator()
 * @method DynamicProductEntity[]    getElements()
 * @method DynamicProductEntity|null get(string $key)
 * @method DynamicProductEntity|null first()
 * @method DynamicProductEntity|null last()
 */
class DynamicProductCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ec_dynamic_product';
    }

    protected function getExpectedClass(): string
    {
        return DynamicProductEntity::class;
    }
}