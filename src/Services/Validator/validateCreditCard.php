<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

/**
 * Validates a card number using the Luhn algorithm (ISO/IEC 7812).
 *
 * Usage: credit_card
 *        credit_card:visa,mastercard   (restricts the accepted brands)
 *
 * Brands: visa, mastercard, amex, elo, hipercard, diners, discover, jcb.
 */
class validateCreditCard implements ValidatorContract
{
    private const BRANDS = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?(?:[0-9]{3})?$/',
        'mastercard' => '/^(?:5[1-5][0-9]{14}|2(?:2(?:2[1-9]|[3-9][0-9])|[3-6][0-9]{2}|7(?:[01][0-9]|20))[0-9]{12})$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'elo' => '/^(?:4011(?:78|79)|43(?:1274|8935)|45(?:1416|7393|763(?:1|2))|50(?:4175|6699|67[0-7][0-9]|9[0-9]{3})|627780|63(?:6297|6368)|65(?:0(?:0(?:3([1-3]|[5-9])|4([0-9])|5([0-1]))|4(?:[0-5][0-9]|8[5-9]|9[0-9])|5([0-2][0-9]|3[0-8]))|16(?:5[2-9]|[6-7][0-9])|500(?:3[5-9]|4[0-9]|5[0-1])))[0-9]{10,12}$/',
        'hipercard' => '/^(?:606282|3841[0-9]{3})[0-9]{10,12}$/',
        'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
        'jcb' => '/^(?:2131|1800|35[0-9]{3})[0-9]{11}$/',
    ];

    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $number = preg_replace('/[\s.-]/', '', (string)$value);

            if (!preg_match('/^[0-9]{13,19}$/', $number)) {
                return false;
            }

            if (!self::luhn($number)) {
                return false;
            }

            $brands = array_filter(array_map(fn($brand) => strtolower(trim((string)$brand)), $parameters));

            if ($brands === []) {
                return true;
            }

            foreach ($brands as $brand) {
                if (!array_key_exists($brand, self::BRANDS)) {
                    throw new \InvalidArgumentException("The credit_card rule received an unknown brand: {$brand}.");
                }

                if (preg_match(self::BRANDS[$brand], $number)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }

    /**
     * Luhn algorithm: doubles the digits in alternating positions starting from
     * the right and requires the total sum to be a multiple of 10.
     */
    private static function luhn(string $number): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int)$number[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }
}
