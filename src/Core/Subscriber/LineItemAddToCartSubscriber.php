<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Subscriber;

use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use EventCandy\Sets\Core\Event\BeforeLineItemAddToCartEvent;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LineItemAddToCartSubscriber implements EventSubscriberInterface
{

    /**
     * @var DynamicProductService
     */
    private $dynamicProductService;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @param DynamicProductService $dynamicProductService
     * @param CartPersisterInterface $cartPersister
     */
    public function __construct(DynamicProductService $dynamicProductService, CartPersisterInterface $cartPersister)
    {
        $this->dynamicProductService = $dynamicProductService;
        $this->cartPersister = $cartPersister;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddToCartEvent::class => 'createDynamicProducts'
        ];
    }

    public function createDynamicProducts(BeforeLineItemAddToCartEvent $event)
    {
        $context = $event->getContext();
        if (!$this->cartExists($context)) {
            return;
        }

        $lineItems = $event->getEntities();

        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection(
            $lineItems,
            $context->getToken()
        );
        $this->dynamicProductService->saveDynamicProductsToDb($dynamicProducts, true);
    }


   /**
    * @param SalesChannelContext $context
    * @return bool
    */
    private function cartExists(SalesChannelContext $context): bool
    {
        try {
            $this->cartPersister->load($context->getToken(), $context);
            return true;
        } catch (CartTokenNotFoundException $exception) {
            return false;
        }
    }
}