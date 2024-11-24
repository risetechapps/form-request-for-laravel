<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateCellphone implements ValidatorContract
{

    public static function validate($attribute, $value, $parameters, $validator): bool
    {
        $cellphone = preg_replace('/[^0-9]/', '', $value);

        if ((strlen($cellphone) < 11) || preg_match('/(\d)\1{10}/', $cellphone)) {
            return false;
        }

        return true;
    }
}
