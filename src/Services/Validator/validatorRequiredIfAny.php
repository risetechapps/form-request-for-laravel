<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;
use Illuminate\Support\Facades\Validator;

class validatorRequiredIfAny implements ValidatorContract
{

    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            if (count($parameters) < 2) {
                throw new \InvalidArgumentException('The required_if_any rule requires at least 2 parameters.');
            }

            $data = $validator->getData();

            // Obrigatório se QUALQUER um dos campos informados estiver preenchido
            // (presente e não-vazio), conforme documentado. Suporta N parâmetros.
            $shouldBeRequired = false;

            foreach ($parameters as $param) {
                $other = data_get($data, $param);

                if (!is_null($other) && $other !== '' && $other !== []) {
                    $shouldBeRequired = true;
                    break;
                }
            }

            if ($shouldBeRequired) {
                return !is_null($value) && $value !== '' && $value !== [];
            }

            // Se nenhum estiver preenchido, validação passa mesmo sem valor
            return true;

        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}