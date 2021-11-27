<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct;


use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DynamicProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ec_dynamic_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DynamicProductCollection::class;
    }

    public function getEntityClass(): string
    {
        return DynamicProductEntity::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([

            (new IdField('id', 'id'))
                ->addFlags(new Required(), new PrimaryKey(), new ApiAware()),

            (new StringField('token', 'token'))
                ->addFlags(new Required(), new ApiAware()),

            (new StringField('line_item_id', 'lineItemId'))
                ->addFlags(new Required(), new ApiAware()),

            (new FkField('product_id', 'productId', ProductDefinition::class)
            )->addFlags(new ApiAware()),
            (new ManyToOneAssociationField(
                'product',
                'product_id',
                ProductDefinition::class
            ))->addFlags(new ApiAware()),
            (new BoolField('is_new', 'isNew'))->addFlags(new ApiAware())
        ]);
    }

}