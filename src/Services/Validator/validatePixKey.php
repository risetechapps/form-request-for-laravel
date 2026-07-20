<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

/**
 * Validates a Pix key in the formats defined by the Brazilian Central Bank.
 *
 * Usage: pix_key
 *        pix_key:email,phone   (restricts the accepted key types)
 *
 * Types: cpf, cnpj, email, phone, random.
 */
class validatePixKey implements ValidatorContract
{
    private const TYPES = ['cpf', 'cnpj', 'email', 'phone', 'random'];

    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            if (!is_string($value) || trim($value) === '') {
                return false;
            }

            $value = trim($value);
            $types = array_filter(array_map(fn($type) => strtolower(trim((string)$type)), $parameters));

            foreach ($types as $type) {
                if (!in_array($type, self::TYPES, true)) {
                    throw new \InvalidArgumentException("The pix_key rule received an unknown key type: {$type}.");
                }
            }

            if ($types === []) {
                $types = self::TYPES;
            }

            foreach ($types as $type) {
                if (self::matches($type, $attribute, $value, $validator)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }

    private static function matches(string $type, string $attribute, string $value, \Illuminate\Validation\Validator $validator): bool
    {
        return match ($type) {
            // Document keys travel through DICT with digits only.
            'cpf' => preg_match('/^[0-9]{11}$/', $value) === 1
                && validateCPF::validate($attribute, $value, [], $validator),

            'cnpj' => preg_match('/^[0-9A-Z]{14}$/', $value) === 1
                && validateCNPJ::validate($attribute, $value, [], $validator),

            // 77 character limit defined by the Central Bank.
            'email' => mb_strlen($value) <= 77
                && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,

            // Phone in E.164: +55 followed by area code and 8 or 9 digits.
            'phone' => preg_match('/^\+55[1-9][0-9][0-9]{8,9}$/', $value) === 1,

            // Random key (EVP): version 4 UUID.
            'random' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,

            default => false,
        };
    }
}
