<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Event;

use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class ProductLoadedEvent
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var ProductCollection
     */
    protected $entities;

    /**
     * @var bool
     */
    protected $calculateStock;

    /**
     * @param SalesChannelContext $context
     * @param ProductCollection $entities
     * @param bool $calculateStock
     */
    public function __construct(SalesChannelContext $context, ProductCollection $entities, bool $calculateStock)
    {
        $this->context = $context;
        $this->entities = $entities;
        $this->calculateStock = $calculateStock;
    }

    /**
     * @return SalesChannelContext
     */
    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    /**
     * @return ProductCollection
     */
    public function getEntities(): ProductCollection
    {
        return $this->entities;
    }

    /**
     * @return bool
     */
    public function isCalculateStock(): bool
    {
        return $this->calculateStock;
    }
}
