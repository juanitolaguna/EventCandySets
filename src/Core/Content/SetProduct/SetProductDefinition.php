<?php

namespace EventCandy\Sets\Core\Content\SetProduct;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SetProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ec_set_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SetProductCollection::class;
    }

    public function getEntityClass(): string
    {
        return SetProductEntity::class;
    }

    protected function defineFields(): FieldCollection
    {

        return new FieldCollection([
            ( new IdField( 'id', 'id' ) )
                ->addFlags( new Required(), new PrimaryKey()),
            (new FkField('set_product_id', 'setProductId', ProductDefinition::class)),
            (new FkField('product_id', 'productId', ProductDefinition::class)),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('quantity', 'quantity')),

            new ManyToOneAssociationField(
                'setProduct',
                'set_product_id',
                ProductDefinition::class
            ),

            new ManyToOneAssociationField(
                'product',
                'product_id',
                ProductDefinition::class
            ),

            new CreatedAtField()
        ]);
    }

}
