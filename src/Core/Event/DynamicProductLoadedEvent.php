<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Event;

use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class DynamicProductLoadedEvent extends Event
{
    /**
     * @var SalesChannelContext
     */
    protected $context;


    /**
     * @var DynamicProductCollection
     */
    protected $entities;

    /**
     * @var bool
     */
    protected $calculateStock;

    /**
     * @param SalesChannelContext $context
     * @param DynamicProductCollection $entities
     * @param bool $calculateStock
     */
    public function __construct(SalesChannelContext $context, DynamicProductCollection $entities, bool $calculateStock)
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
     * @return DynamicProductCollection
     */
    public function getEntities(): DynamicProductCollection
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
