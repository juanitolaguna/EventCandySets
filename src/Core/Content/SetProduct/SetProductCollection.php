<?php
declare( strict_types=1 );

namespace EventCandy\Sets\Core\Content\SetProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add( SetProductCollection $entity )
 * @method void              set( string $key, SetProductCollection $entity )
 * @method SetProductCollection[]    getIterator()
 * @method SetProductCollection[]    getElements()
 * @method SetProductCollection|null get( string $key )
 * @method SetProductCollection|null first()
 * @method SetProductCollection|null last()
 */
class SetProductCollection extends EntityCollection {
    protected function getExpectedClass(): string {
        return SetProductEntity::class;
    }
}
