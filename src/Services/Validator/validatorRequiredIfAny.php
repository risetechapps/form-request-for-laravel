<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;
use Illuminate\Support\Facades\Validator;

class validatorRequiredIfAny implements ValidatorContract
{

    public static function validate($attribute, $value, $parameters, $validator)
    {
        try {
            $data = $validator->getData();

            $first  = data_get($data, $parameters[0]);
            $second = data_get($data, $parameters[1]);

            // Se qualquer um for true / 1 / "true", então o campo é obrigatório
            $shouldBeRequired = filter_var($first, FILTER_VALIDATE_BOOLEAN) || filter_var($second, FILTER_VALIDATE_BOOLEAN);

            if ($shouldBeRequired) {
                return !is_null($value) && $value !== '';
            }

            // Se nenhum for true, validação passa mesmo sem valor
            return true;

        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}