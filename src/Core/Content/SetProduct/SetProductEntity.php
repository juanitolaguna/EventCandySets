<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\SetProduct;

use EventCandy\LabelMe\Core\Content\Candy\CandyEntity;
use EventCandy\LabelMe\Core\Content\Package\PackageEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SetProductEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var ProductEntity|null
     */
    protected $setProduct;

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
     * @return ProductEntity|null
     */
    public function getSetProduct(): ?ProductEntity
    {
        return $this->setProduct;
    }

    /**
     * @param ProductEntity|null $product
     */
    public function setSetProduct(?ProductEntity $setProduct): void
    {
        $this->setProduct = $setProduct;
    }





}
