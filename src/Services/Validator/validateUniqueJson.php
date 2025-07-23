<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateUniqueJson implements ValidatorContract
{

    public static function validate($attribute, $value, $parameters, $validator): bool
    {
        try {

            $table = $parameters[0];

            $secondItem = $parameters[1];

            $jsonPath = $secondItem;
            $valueId = null;

            if (str_contains($secondItem, ':')) {
                [$jsonPath, $valueId] = explode(':', $secondItem, 2);
            }

            [$jsonField, $jsonKey] = explode('.', $jsonPath);

            return DB::table($table)
                    ->where("{$jsonField}->{$jsonKey}", $value)
                    ->where('id', '!=', $valueId)
                    ->count() === 0;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
