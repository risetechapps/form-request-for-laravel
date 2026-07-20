<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use Illuminate\Support\Facades\DB;
use RiseTechApps\FormRequest\Contracts\ValidatorContract;

/**
 * Counterpart of uniqueJson: requires the value to already exist in a JSON key.
 *
 * Usage: existsJson:table,json_column.key
 */
class validateExistsJson implements ValidatorContract
{
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            if (count($parameters) < 2) {
                throw new \InvalidArgumentException('The existsJson rule requires at least 2 parameters: table and jsonPath.');
            }

            $table = $parameters[0];

            if (!str_contains((string)$parameters[1], '.')) {
                throw new \InvalidArgumentException('The existsJson rule requires the jsonPath in the "column.key" format.');
            }

            [$jsonField, $jsonKey] = explode('.', (string)$parameters[1], 2);

            return DB::table($table)
                    ->where("{$jsonField}->{$jsonKey}", $value)
                    ->count() > 0;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
