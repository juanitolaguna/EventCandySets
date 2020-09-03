<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Set;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(SetEntity $entity)
 * @method void              set(string $key, SetEntity $entity)
 * @method SetEntity[]    getIterator()
 * @method SetEntity[]    getElements()
 * @method SetEntity|null get(string $key)
 * @method SetEntity|null first()
 * @method SetEntity|null last()
 */
class SetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SetEntity::class;
    }
}
