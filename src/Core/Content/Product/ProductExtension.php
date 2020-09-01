<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product;

use EventCandy\Sets\Core\Content\SetProduct\SetProductDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new BoolField('is_set', 'isSet'))->addFlags(new Inherited())
        );
        $collection->add(
            (new ManyToManyAssociationField(
                'products',
                ProductDefinition::class,
                SetProductDefinition::class,
                'set_product_id',
                'product_id'
            ))->addFlags(new Runtime())
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
