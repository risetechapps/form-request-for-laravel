<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateCNPJ implements ValidatorContract
{

    public static function validate($attribute, $value, $parameters, $validator)
    {
        try {
            $cnpj = preg_replace('/[^0-9]/', '', (string)$value);

            if (strlen($cnpj) != 14)
                return false;

            if (preg_match('/(\d)\1{13}/', $cnpj))
                return false;

            for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
                $soma += $cnpj[$i] * $j;
                $j = ($j == 2) ? 9 : $j - 1;
            }

            $resto = $soma % 11;

            if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto))
                return false;

            for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
                $soma += $cnpj[$i] * $j;
                $j = ($j == 2) ? 9 : $j - 1;
            }

            $resto = $soma % 11;

            return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
        } catch (\Exception $exception) {
            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
