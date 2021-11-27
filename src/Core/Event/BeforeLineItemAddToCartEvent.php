<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Event;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class BeforeLineItemAddToCartEvent extends Event
{
    /**
     * @var SalesChannelContext
     */
    protected $context;


    /**
     * @var LineItem[]
     */
    protected $entities;

    /**
     * @param SalesChannelContext $context
     * @param LineItem[] $entities
     */
    public function __construct(SalesChannelContext $context, array $entities)
    {
        $this->context = $context;
        $this->entities = $entities;
    }

    /**
     * @return SalesChannelContext
     */
    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    /**
     * @return LineItem[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
