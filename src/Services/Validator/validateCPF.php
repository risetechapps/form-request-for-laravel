<?php

namespace RiseTechApps\FormRequest\Services\Validator;

use RiseTechApps\FormRequest\Contracts\ValidatorContract;
use RiseTechApps\FormRequest\Support\ErrorReporter;

class validateCPF implements ValidatorContract
{
    public static function validate(string $attribute, mixed $value, array $parameters, \Illuminate\Validation\Validator $validator): bool
    {
        try {
            $cpf = preg_replace('/[^0-9]/', '', (string) $value);

            if ((strlen((string) $cpf) != 11) || preg_match('/(\d)\1{10}/', (string) $cpf)) {
                return false;
            }

            for ($t = 9; $t < 11; $t++) {
                for ($d = 0, $c = 0; $c < $t; $c++) {
                    $d += (int) $cpf[$c] * (($t + 1) - $c);
                }

                $d = ((10 * $d) % 11) % 10;

                if ($cpf[$c] != $d) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $exception) {
            ErrorReporter::report($exception, 'Validator cpf failed', ['rule' => 'cpf', 'attribute' => $attribute, 'value' => $value, 'parameters' => $parameters]);
            return false;

        }
    }
}
