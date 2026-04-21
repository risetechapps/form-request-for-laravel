<?php

namespace RiseTechApps\FormRequest\Contracts;

interface ValidatorContract
{
    /**
     * @param array<int, mixed> $parameters
     */
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool;
}
