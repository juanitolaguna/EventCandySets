<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(ProductProductEntity $entity)
 * @method void              set(string $key, ProductProductEntity $entity)
 * @method ProductProductEntity[]    getIterator()
 * @method ProductProductEntity[]    getElements()
 * @method ProductProductEntity|null get(string $key)
 * @method ProductProductEntity|null first()
 * @method ProductProductEntity|null last()
 */
class ProductProductCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductProductEntity::class;
    }
}
