<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer;

use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\CartOptimizer\CartOptimizerInterface;
use EventCandy\Sets\Core\Checkout\Cart\CartProduct\CartProductService;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepository\DynamicProductRepositoryInterface;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService\DynamicProductService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * To be injected directly into the @link CartHandler::collect()
 * (Class in older commits: CollectorOptimizer::collect())
 */
class AggregateCartOptimizerService implements AggregateCartOptimizerInterface
{
    /**
     * @var array<CartOptimizerInterface>
     */
    private array $optimizers;

    private CartPersisterInterface $cartPersister;

    private DynamicProductService $dynamicProductService;

    private DynamicProductRepositoryInterface $dynamicProductRepository;

    private CartProductService $cartProductService;

    /**
     * @param array<CartOptimizerInterface> $optimizers
     */
    public function __construct(
        array $optimizers,
        CartPersisterInterface $cartPersister,
        DynamicProductService $dynamicProductService,
        DynamicProductRepositoryInterface $dynamicProductRepository,
        CartProductService $cartProductService
    ) {
        $this->optimizers = $optimizers;
        $this->cartPersister = $cartPersister;
        $this->dynamicProductService = $dynamicProductService;
        $this->dynamicProductRepository = $dynamicProductRepository;
        $this->cartProductService = $cartProductService;
    }

    /**
     * @throws Exception
     */
    public function runOptimizers(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $this->createCartIfNotExists($context, $original);
        $this->resetSessionStateInDb($context);

        foreach ($this->optimizers as $optimizer) {
            $optimizer->saveDynamicProductsBeforeCollect($data, $original, $context, $behavior);
        }
        $this->dynamicProductRepository->resetNewFlag($context->getToken());
    }

    private function createCartIfNotExists(SalesChannelContext $context, Cart $original): void
    {
        try {
            $this->cartPersister->load($context->getToken(), $context);
        } catch (CartTokenNotFoundException $exception) {
            $this->cartPersister->save($original, $context);
        }
    }

    /**
     * @throws Exception
     */
    private function resetSessionStateInDb(SalesChannelContext $context): void
    {
        $this->dynamicProductRepository->removeDynamicProductsByToken($context->getToken(), true);
        $this->cartProductService->removeCartProductsByToken($context->getToken());
    }
}