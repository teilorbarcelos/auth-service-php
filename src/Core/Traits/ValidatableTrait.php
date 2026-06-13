<?php

declare(strict_types=1);

namespace App\Core\Traits;

use App\Core\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

trait ValidatableTrait
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, \Respect\Validation\Validatable> $rules
     * @throws ValidationException
     */
    protected function validate(array $data, array $rules): void
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            try {
                $rule->setName(ucfirst($field))->assert($data[$field] ?? null);
            } catch (\Respect\Validation\Exceptions\ValidationException $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
