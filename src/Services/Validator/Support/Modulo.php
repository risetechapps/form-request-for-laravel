<?php

namespace RiseTechApps\FormRequest\Services\Validator\Support;

/**
 * Check digit algorithms used by the FEBRABAN standards.
 */
final class Modulo
{
    /**
     * Modulo 10 (FEBRABAN): weights 2 and 1 alternating from right to left,
     * with the digits of any product greater than 9 summed together.
     */
    public static function mod10(string $digits): int
    {
        $sum = 0;
        $weight = 2;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $product = (int)$digits[$i] * $weight;

            if ($product > 9) {
                $product = intdiv($product, 10) + ($product % 10);
            }

            $sum += $product;
            $weight = $weight === 2 ? 1 : 2;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Modulo 11 with cyclic weights from 2 to 9, right to left. Returns the
     * remainder of the division by 11 without applying the conversion rule,
     * which varies per document.
     */
    public static function mod11Remainder(string $digits): int
    {
        $sum = 0;
        $weight = 2;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $sum += (int)$digits[$i] * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }

        return $sum % 11;
    }
}
