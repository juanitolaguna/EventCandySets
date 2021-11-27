<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Subscriber;
use EventCandy\Sets\Core\Checkout\Cart\SetProductCartCollector;
use EventCandy\Sets\Core\Event\BeforeLineItemAddToCartEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * If a normal product is a set product, hand over to the AddToCartSubscriber
 * So product considers itself in cart.
 */
class SetProductAddedSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param EntityRepositoryInterface $productRepository
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EntityRepositoryInterface $productRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }


    public static function getSubscribedEvents(): array
    {
       return [
           BeforeLineItemAddedEvent::class => 'onSetProductAdded'
       ];
    }

    public function onSetProductAdded(BeforeLineItemAddedEvent $event)
    {
        if ($event->getLineItem()->getType() !== SetProductCartCollector::TYPE) {
            return;
        }

        $allreadyExists = $event->isMerged();
        if ($allreadyExists) {
            return;
        }

        $productId = $event->getLineItem()->getReferencedId();
        $context = $event->getSalesChannelContext();
        $product = $this->getProductEntity($productId, $context) ?? false;

        if ($product && !$this->isSetProduct($product)) {
            return;
        }
        $this->eventDispatcher->dispatch(new BeforeLineItemAddToCartEvent($context, [$event->getLineItem()]));
    }


    private function isSetProduct(ProductEntity $productEntity): bool
    {
        return array_key_exists('ec_is_set', $productEntity->getCustomFields())
            && $productEntity->getCustomFields()['ec_is_set'];
    }

    private function getProductEntity(
        ?string $productId,
        SalesChannelContext $context
    ) {
        /** @var ProductEntity $product */
        $product =  $this->productRepository->search(new Criteria([$productId]), $context->getContext())->first();
        return $product;
    }

}


