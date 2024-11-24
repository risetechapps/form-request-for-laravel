<?php

namespace RiseTechApps\FormRequest\Traits\hasFormValidation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait hasFormValidation
{
    protected function failedValidation(Validator $validator)
    {
        $response = [
            "success" => false,
            "message" => __("The data provided is invalid."),
            "errors" => $validator->errors(),
        ];

        throw new HttpResponseException(response()->json($response, 422));
    }

    protected function failedAuthorization()
    {
        $response = [
            "success" => false,
            "message" => __("Permission denied."),
        ];

        throw new HttpResponseException(response()->json($response, 403));
    }
}
