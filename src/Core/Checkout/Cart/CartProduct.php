<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart;

use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Struct\Struct;

class CartProduct extends Struct {

    use EntityIdTrait;

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
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return CartProduct
     */
    public function setToken(string $token): CartProduct
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    /**
     * @param string $lineItemId
     * @return CartProduct
     */
    public function setLineItemId(string $lineItemId): CartProduct
    {
        $this->lineItemId = $lineItemId;
        return $this;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @param string $productId
     * @return CartProduct
     */
    public function setProductId(string $productId): CartProduct
    {
        $this->productId = $productId;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubProductId(): string
    {
        return $this->subProductId;
    }

    /**
     * @param string $subProductId
     * @return CartProduct
     */
    public function setSubProductId(string $subProductId): CartProduct
    {
        $this->subProductId = $subProductId;
        return $this;
    }

    /**
     * @return int
     */
    public function getSubProductQuantity(): int
    {
        return $this->subProductQuantity;
    }

    /**
     * @param int $subProductQuantity
     * @return CartProduct
     */
    public function setSubProductQuantity(int $subProductQuantity): CartProduct
    {
        $this->subProductQuantity = $subProductQuantity;
        return $this;
    }

    /**
     * @return int
     */
    public function getLineItemQuantity(): int
    {
        return $this->lineItemQuantity;
    }

    /**
     * @param int $lineItemQuantity
     * @return CartProduct
     */
    public function setLineItemQuantity(int $lineItemQuantity): CartProduct
    {
        $this->lineItemQuantity = $lineItemQuantity;
        return $this;
    }









}