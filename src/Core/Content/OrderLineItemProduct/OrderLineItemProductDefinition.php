<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\OrderLineItemProduct;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderLineItemProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ec_order_line_item_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderLineItemProductCollection::class;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return OrderLineItemProductEntity::class;
    }



    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new ParentFkField(self::class),
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),

            (new IntField('quantity', 'quantity'))->addFlags(new Required()),

            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),

            new FkField('product_id', 'productId', ProductDefinition::class),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),

            new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class),
            (new ReferenceVersionField(OrderLineItemDefinition::class))->addFlags(new Required()),
            //new OneToOneAssociationField('orderLineItem', 'order_line_item_id', 'id', OrderLineItemDefinition::class, false)
            new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, 'id', false)
        ]);

    }
}