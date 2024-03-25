<?php

namespace Ycstar\Huisuiyun\Exceptions;

class InvalidResponseException extends \Exception
{
    public function __construct(string $message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }

}