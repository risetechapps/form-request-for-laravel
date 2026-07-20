<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use Illuminate\Support\Facades\DB;
use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateUniqueJson implements ValidatorContract
{

    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            if (count($parameters) < 2) {
                throw new \InvalidArgumentException('The uniqueJson rule requires at least 2 parameters: table and jsonPath.');
            }

            $table = $parameters[0];

            $secondItem = $parameters[1];

            $jsonPath = $secondItem;
            $valueId = null;

            if (str_contains((string) $secondItem, ':')) {
                [$jsonPath, $valueId] = explode(':', (string) $secondItem, 2);
            }

            [$jsonField, $jsonKey] = explode('.', (string) $jsonPath);

            $query = DB::table($table)
                ->where("{$jsonField}->{$jsonKey}", $value);

            // Exclui o próprio registro apenas em update (id informado).
            // No create $valueId é null: `id != null` nunca casa em SQL e
            // desativaria silenciosamente a checagem de unicidade.
            if (!is_null($valueId)) {
                $query->where('id', '!=', $valueId);
            }

            return $query->count() === 0;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
