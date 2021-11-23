<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart;

use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Event\DynamicProductLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DynamicProductGateway
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function get(array $ids, SalesChannelContext $context, bool $calculateStock = true): DynamicProductCollection
    {
        $criteria = new Criteria($ids);
        if ($calculateStock) {
            $criteria->setTitle('cart::dynamicProducts');
            $criteria->addAssociation('product.cover');
            $criteria->addAssociation('product.options.group');
            $criteria->addAssociation('product.featureSet');
            $criteria->addAssociation('product.properties.group');
        } else {
            $criteria->setTitle('cart::dynamicProducts::doNotRecalculateStock');
        }

        /** @var DynamicProductCollection $result */
        $result = $this->repository->search($criteria, $context->getContext())->getEntities();


        $this->eventDispatcher->dispatch(
            new DynamicProductLoadedEvent($context, $result, $calculateStock)
        );

        return $result;
    }
}
