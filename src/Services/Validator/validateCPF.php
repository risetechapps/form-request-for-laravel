<?php

namespace RiseTechApps\FormRequest\Services\Validator;


use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateCPF implements ValidatorContract
{
    public static function validate($attribute, $value, $parameters, $validator): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $value);

        if ((strlen($cpf) != 11) || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }

            $d = ((10 * $d) % 11) % 10;

            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}
