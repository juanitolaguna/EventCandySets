<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart;

class DynamicProduct {

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $lineItemId;

    /**
     * @param string $id
     * @param string $token
     * @param string $productId
     * @param string $lineItemId
     */
    public function __construct(string $id, string $token, string $productId, string $lineItemId)
    {
        $this->id = $id;
        $this->token = $token;
        $this->productId = $productId;
        $this->lineItemId = $lineItemId;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

}