<?php

namespace RiseTechApps\FormRequest\Contracts;

interface ValidatorContract
{
    public static function validate($attribute, $value, $parameters, $validator);
}
