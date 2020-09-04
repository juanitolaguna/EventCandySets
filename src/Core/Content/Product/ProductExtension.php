<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product;

use EventCandy\Sets\Core\Content\Product\Aggregate\ProductProductDefinition;
use EventCandy\Sets\Core\Content\Set\Aggregate\SetProductDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ManyToManyAssociationField(
                'sets',
                ProductDefinition::class,
                SetProductDefinition::class,
                'set_id',
                'product_id'
            ))
        );

        $collection->add(
            (new ManyToManyAssociationField(
                'products',
                ProductDefinition::class,
                ProductProductDefinition::class,
                'set_product_id',
                'product_id'
            ))
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
