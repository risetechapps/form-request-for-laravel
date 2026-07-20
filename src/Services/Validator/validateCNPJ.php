<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateCNPJ implements ValidatorContract
{
    /**
     * Modulo 11 weights applied from right to left.
     */
    private const WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $cnpj = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)$value));

            // The first 12 positions are alphanumeric and the last 2 are the check digits.
            if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj)) {
                return false;
            }

            if (preg_match('/^(.)\1{13}$/', $cnpj)) {
                return false;
            }

            $firstDigit = self::checkDigit(substr($cnpj, 0, 12));

            if ((int)$cnpj[12] !== $firstDigit) {
                return false;
            }

            return (int)$cnpj[13] === self::checkDigit(substr($cnpj, 0, 12) . $firstDigit);
        } catch (\Exception $exception) {
            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }

    /**
     * Calculates the check digit using the character ASCII value minus 48,
     * as defined by Technical Note COTEC 49/2024 (alphanumeric CNPJ).
     */
    private static function checkDigit(string $base): int
    {
        $length = strlen($base);
        $weights = array_slice(self::WEIGHTS, -$length);
        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $sum += (ord($base[$i]) - 48) * $weights[$i];
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
