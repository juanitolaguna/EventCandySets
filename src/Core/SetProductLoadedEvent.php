<?php declare(strict_types=1);

namespace EventCandy\Sets\Core;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class SetProductLoadedEvent extends Event
{
    /**
     * @var SalesChannelContext
     */
    protected $context;


    /**
     * @var array
     */
    protected $entities;

    /**
     * SetProductLoadedEvent constructor.
     * @param SalesChannelContext $context
     * @param array $entities
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
     * @return array
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

}
