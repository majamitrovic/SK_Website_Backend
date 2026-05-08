<?php

namespace App;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    private $errors;

    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    public function errors()
    {
        return $this->errors;
    }
}
