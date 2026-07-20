<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

/**
 * Validates the structure of an 8 digit NCM/HS code.
 *
 * NCM has no check digit. The verification is structural: the chapter must be
 * between 01 and 99 (chapter 77 is reserved and therefore invalid).
 * Accepted formats: 0000.00.00, 00000000.
 */
class validateNcm implements ValidatorContract
{
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $ncm = preg_replace('/[^0-9]/', '', (string)$value);

            if (strlen($ncm) !== 8) {
                return false;
            }

            $chapter = (int)substr($ncm, 0, 2);

            // Chapter 77 is reserved for future use by the Harmonized System.
            return $chapter !== 0 && $chapter !== 77;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
