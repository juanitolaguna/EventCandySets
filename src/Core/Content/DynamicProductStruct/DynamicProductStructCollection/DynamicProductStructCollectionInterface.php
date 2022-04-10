<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection;

use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStruct;
use Traversable;

interface DynamicProductStructCollectionInterface extends Traversable
{
    public function add(DynamicProductStruct $product): void;

    /**
     * @return array<DynamicProductStruct>
     */
    public function getElements(): array;

}