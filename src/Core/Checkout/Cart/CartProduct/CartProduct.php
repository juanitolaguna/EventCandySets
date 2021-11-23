<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartProduct;

use EventCandy\Sets\Test\CartProcessorTest;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Struct\Struct;

class CartProduct extends Struct {

    use EntityIdTrait;

    /**
     * @var string
     */
    protected $uniqueId;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $lineItemId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $subProductId;

    /**
     * @var int
     */
    protected $subProductQuantity;

    /**
     * @var int
     */
    protected $lineItemQuantity;

    /**
     * @param string $uniqueId
     * @param string $token
     * @param string $lineItemId
     * @param string $productId
     * @param string $subProductId
     * @param int $subProductQuantity
     * @param int $lineItemQuantity
     */
    public function __construct(
        string $uniqueId,
        string $token,
        string $lineItemId,
        string $productId,
        string $subProductId,
        int $subProductQuantity,
        int $lineItemQuantity
    ) {
        $this->uniqueId = $uniqueId;
        $this->token = $token;
        $this->lineItemId = $lineItemId;
        $this->productId = $productId;
        $this->subProductId = $subProductId;
        $this->subProductQuantity = $subProductQuantity;
        $this->lineItemQuantity = $lineItemQuantity;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function getSubProductId(): string
    {
        return $this->subProductId;
    }

    /**
     * @return int
     */
    public function getSubProductQuantity(): int
    {
        return $this->subProductQuantity;
    }

    /**
     * @return int
     */
    public function getLineItemQuantity(): int
    {
        return $this->lineItemQuantity;
    }
}