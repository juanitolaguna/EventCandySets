<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandlerBundle;

use Shopware\Core\Checkout\Cart\Exception\InsufficientPermissionException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * ToDo: refactor - maybe extend one standard factory & override the type
 */
class SetProductLineItemFactory implements LineItemFactoryInterface
{
    public const TYPE = 'setproduct';
    /**
     * @var PriceDefinitionFactory
     */
    private $priceDefinitionFactory;

    public function __construct(PriceDefinitionFactory $priceDefinitionFactory)
    {
        $this->priceDefinitionFactory = $priceDefinitionFactory;
    }

    public function supports(string $type): bool
    {
        return $type === self::TYPE;
    }

    public function create(array $data, SalesChannelContext $context): LineItem
    {
        $lineItem = new LineItem($data['id'], self::TYPE, $data['referencedId'] ?? null, $data['quantity'] ?? 1);
        $lineItem->markModified();

        $lineItem->setRemovable(true);
        $lineItem->setStackable(true);

        $lineItem->addExtension(ProductCartProcessor::CUSTOM_PRICE, new ArrayEntity());

        $this->update($lineItem, $data, $context);

        return $lineItem;
    }

    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        if (isset($data['referencedId'])) {
            $lineItem->setReferencedId($data['referencedId']);
        }

        if (isset($data['payload'])) {
            $lineItem->setPayload($data['payload'] ?? []);
        }

        if (isset($data['quantity'])) {
            $lineItem->markModified();
            $lineItem->setQuantity((int)$data['quantity']);
        }

        if (isset($data['priceDefinition']) && !$context->hasPermission(
                ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES
            )) {
            throw new InsufficientPermissionException();
        }

        if (isset($data['priceDefinition'])) {
            $lineItem->setPriceDefinition(
                $this->priceDefinitionFactory->factory($context->getContext(), $data['priceDefinition'], $data['type'])
            );
        }
    }
}
