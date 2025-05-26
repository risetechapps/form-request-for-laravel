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
            [$jsonPath, $valueId] = explode(':', $parameters[1]);
            [$jsonField, $jsonKey] = explode('.', $jsonPath);


            return DB::table($table)
                    ->where("{$jsonField}->{$jsonKey}", $value)
                    ->where('id', '!=', $valueId)
                    ->count() === 0;
        } catch (\Exception $exception) {
            logglyError()->exception($exception)->performedOn(self::class)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->withTags(['action' => 'validate'])->log("Error validating data rules");
            return false;
        }
    }
}
