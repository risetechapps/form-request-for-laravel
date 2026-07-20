<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;
use RiseTechApps\FormRequest\Services\Validator\Support\Modulo;

/**
 * Validates the 44 position bank barcode (FEBRABAN standard), covering both
 * regular bank slips and utility payment slips (those starting with 8).
 */
class validateBankBarcode implements ValidatorContract
{
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $barcode = preg_replace('/[^0-9]/', '', (string)$value);

            if (strlen($barcode) !== 44) {
                return false;
            }

            if (preg_match('/^0{44}$/', $barcode)) {
                return false;
            }

            return $barcode[0] === '8'
                ? self::checkUtility($barcode)
                : self::checkBankSlip($barcode);
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }

    /**
     * Bank slip: the general check digit sits in the 5th position and is
     * calculated with modulo 11 over the remaining 43 positions.
     */
    public static function checkBankSlip(string $barcode): bool
    {
        $rest = Modulo::mod11Remainder(substr($barcode, 0, 4) . substr($barcode, 5));
        $digit = 11 - $rest;

        // Results of 0, 10 and 11 are converted to 1 by the specification.
        if ($digit === 0 || $digit > 9) {
            $digit = 1;
        }

        return (int)$barcode[4] === $digit;
    }

    /**
     * Utility payment: the general check digit sits in the 4th position and the
     * algorithm depends on the value identifier in the 3rd position
     * (6 and 7 use modulo 10; 8 and 9 use modulo 11).
     */
    public static function checkUtility(string $barcode): bool
    {
        $base = substr($barcode, 0, 3) . substr($barcode, 4);
        $valueIdentifier = (int)$barcode[2];

        if (in_array($valueIdentifier, [6, 7], true)) {
            return (int)$barcode[3] === Modulo::mod10($base);
        }

        if (!in_array($valueIdentifier, [8, 9], true)) {
            return false;
        }

        $digit = 11 - Modulo::mod11Remainder($base);

        // Remainders of 0 and 1 yield check digit 0; a remainder of 10 yields 1.
        if ($digit > 9) {
            $digit = 0;
        }

        return (int)$barcode[3] === $digit;
    }
}
