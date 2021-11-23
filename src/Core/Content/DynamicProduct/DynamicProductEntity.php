<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DynamicProductEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var ProductEntity|null
     */
    protected $product;


    /**
     * @var string
     */
    protected $lineItemId;

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
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
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return ProductEntity|null
     */
    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    /**
     * @param ProductEntity|null $product
     */
    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
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
     */
    public function setLineItemId(string $lineItemId): void
    {
        $this->lineItemId = $lineItemId;
    }

}