<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\AuthService\Models\Authentication\Authentication;
use RiseTechApps\FormRequest\Contracts\ValidatorContract;

class validateUniqueAuthenticationCpf implements ValidatorContract
{

    public static function validate($attribute, $value, $parameters, $validator): bool
    {

        try {
            $id = $parameters[0];
            $auth = Authentication::find($id);

            if (!is_null($auth)) {
                return !$auth->profile
                    ->where('cpf', $value)
                    ->where('authentication_id', '!=', $auth->id)
                    ->exists();
            }

            return false;
        } catch (\Exception $exception) {

            logglyError()->exception($exception)
                ->withProperties(['attribute' => $attribute, 'value' => $value, 'parameters' => $parameters])
                ->log("Error validating data rules");
            return false;
        }
    }
}
