<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\OrderLineItemProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;


/**
 * @method void                add(OrderLineItemProductEntity $entity)
 * @method void                set(string $key, OrderLineItemProductEntity $entity)
 * @method OrderLineItemProductEntity[]    getIterator()
 * @method OrderLineItemProductEntity[]    getElements()
 * @method OrderLineItemProductEntity|null get(string $key)
 * @method OrderLineItemProductEntity|null first()
 * @method OrderLineItemProductEntity|null last()
 */
class OrderLineItemProductCollection extends EntityCollection
{

    public function getApiAlias(): string
    {
        return 'ec_order_line_item_product';
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemProductEntity::class;
    }

}