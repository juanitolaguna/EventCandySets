<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Exception;

use Throwable;

class PayloadMissingInCartDataException extends \Exception
{

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = "No Payload for given Product found, make sure the payload was create before invoking this function" . $message;
        parent::__construct($message, $code, $previous);
    }
}