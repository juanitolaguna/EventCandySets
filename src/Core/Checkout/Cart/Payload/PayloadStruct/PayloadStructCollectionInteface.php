<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Payload;

interface PayloadStructCollectionInteface extends \IteratorAggregate
{
    public function add(PayloadStruct $payloadStruct): void;

    public function getElements(): array;

}