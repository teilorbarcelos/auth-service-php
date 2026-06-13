<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

class ValidationException extends Exception
{
    /** @var array<string, string> */
    private array $errors;

    /**
     * @param array<string, string> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('Validation Failed', 400);
        $this->errors = $errors;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
