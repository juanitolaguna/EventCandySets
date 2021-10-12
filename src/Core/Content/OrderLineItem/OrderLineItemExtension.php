<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\OrderLineItem;

use EventCandy\Sets\Core\Content\OrderLineItemProduct\OrderLineItemProductDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderLineItemExtension extends EntityExtension
{

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'lineItemProducts',
                OrderLineItemProductDefinition::class,
                'order_line_item_id',
                'id'))
        );
    }


    public function getDefinitionClass(): string
    {
        return OrderLineItemDefinition::class;
    }
}