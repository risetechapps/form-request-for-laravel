<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;
use RiseTechApps\FormRequest\Services\Validator\Support\Modulo;

/**
 * Validates the digitable line of a bank slip: 47 positions for regular slips
 * or 48 positions for utility payment slips (those starting with 8).
 *
 * Besides the check digit of each field, the barcode is reassembled and
 * revalidated, ensuring the line is internally consistent.
 */
class validateDigitableLine implements ValidatorContract
{
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $line = preg_replace('/[^0-9]/', '', (string)$value);

            if ($line === '' || (int)$line === 0) {
                return false;
            }

            if ($line[0] === '8') {
                return strlen($line) === 48 && self::checkUtility($line);
            }

            return strlen($line) === 47 && self::checkBankSlip($line);
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }

    /**
     * Bank slip: three fields with a modulo 10 check digit, the barcode general
     * check digit in position 33 and the trailing field with due date and amount.
     */
    private static function checkBankSlip(string $line): bool
    {
        $fields = [
            [substr($line, 0, 9), $line[9]],
            [substr($line, 10, 10), $line[20]],
            [substr($line, 21, 10), $line[31]],
        ];

        foreach ($fields as [$base, $digit]) {
            if ((int)$digit !== Modulo::mod10($base)) {
                return false;
            }
        }

        $barcode = substr($line, 0, 4)
            . $line[32]
            . substr($line, 33, 14)
            . substr($line, 4, 5)
            . substr($line, 10, 10)
            . substr($line, 21, 10);

        return validateBankBarcode::checkBankSlip($barcode);
    }

    /**
     * Utility payment: four blocks of 11 digits, each one followed by its check
     * digit. The block check digit algorithm follows the code value identifier.
     */
    private static function checkUtility(string $line): bool
    {
        $valueIdentifier = (int)$line[2];

        if (!in_array($valueIdentifier, [6, 7, 8, 9], true)) {
            return false;
        }

        $useMod10 = in_array($valueIdentifier, [6, 7], true);
        $barcode = '';

        foreach ([0, 12, 24, 36] as $offset) {
            $base = substr($line, $offset, 11);
            $digit = (int)$line[$offset + 11];

            if ($digit !== self::blockDigit($base, $useMod10)) {
                return false;
            }

            $barcode .= $base;
        }

        return validateBankBarcode::checkUtility($barcode);
    }

    private static function blockDigit(string $base, bool $useMod10): int
    {
        if ($useMod10) {
            return Modulo::mod10($base);
        }

        $digit = 11 - Modulo::mod11Remainder($base);

        return $digit > 9 ? 0 : $digit;
    }
}
