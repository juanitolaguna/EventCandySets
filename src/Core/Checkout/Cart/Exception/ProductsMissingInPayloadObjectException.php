<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Exception;

use Throwable;

class ProductsMissingInPayloadObjectException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = "PayloadLineItem does not contains any Products! " . $message;
        parent::__construct($message, $code, $previous);
    }
}