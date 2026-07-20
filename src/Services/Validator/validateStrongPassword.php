<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

/**
 * Validates password strength with configurable parameters.
 *
 * Usage: strong_password             (defaults: min 8, upper, lower, number and symbol)
 *        strong_password:12          (only changes the minimum length)
 *        strong_password:10,upper,number
 *
 * Accepted requirements: upper, lower, number, symbol.
 */
class validateStrongPassword implements ValidatorContract
{
    private const DEFAULT_MIN_LENGTH = 8;

    private const DEFAULT_REQUIREMENTS = ['upper', 'lower', 'number', 'symbol'];

    /**
     * Unicode classes so accented letters count as letters instead of
     * symbols in passwords written in Portuguese.
     */
    private const PATTERNS = [
        'upper' => '/\p{Lu}/u',
        'lower' => '/\p{Ll}/u',
        'number' => '/\p{N}/u',
        'symbol' => '/[^\p{L}\p{N}]/u',
    ];

    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            if (!is_string($value)) {
                return false;
            }

            $minLength = self::DEFAULT_MIN_LENGTH;
            $requirements = [];

            foreach ($parameters as $parameter) {
                $parameter = strtolower(trim((string)$parameter));

                if ($parameter === '') {
                    continue;
                }

                if (ctype_digit($parameter)) {
                    $minLength = (int)$parameter;
                    continue;
                }

                if (!array_key_exists($parameter, self::PATTERNS)) {
                    throw new \InvalidArgumentException("The strong_password rule received an unknown requirement: {$parameter}.");
                }

                $requirements[] = $parameter;
            }

            // Without explicit requirements, the full set is enforced.
            if ($requirements === []) {
                $requirements = self::DEFAULT_REQUIREMENTS;
            }

            if (mb_strlen($value) < $minLength) {
                return false;
            }

            foreach ($requirements as $requirement) {
                if (!preg_match(self::PATTERNS[$requirement], $value)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
