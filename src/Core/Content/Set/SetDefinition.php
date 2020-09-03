<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Set;

use EventCandy\Sets\Core\Content\Set\Aggregate\SetProductDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SetDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'ec_set';
    }

    public function getEntityClass(): string
    {
        return SetEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SetCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name')),
            new ManyToManyAssociationField('products', ProductDefinition::class, SetProductDefinition::class, 'set_id', 'product_id'),
        ]);
    }
}
