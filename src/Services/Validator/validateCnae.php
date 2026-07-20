<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

/**
 * Validates the structure of a 7 digit CNAE 2.x code (subclass).
 *
 * CNAE has no check digit, so the verification is structural: the division
 * must be between 01 and 99 and the code must not be zero.
 * Accepted formats: 0000-0/00, 0000000.
 */
class validateCnae implements ValidatorContract
{
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $cnae = preg_replace('/[^0-9]/', '', (string)$value);

            if (strlen($cnae) !== 7) {
                return false;
            }

            // The first two digits are the division, ranging from 01 to 99.
            if ((int)substr($cnae, 0, 2) === 0) {
                return false;
            }

            return (int)$cnae !== 0;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
