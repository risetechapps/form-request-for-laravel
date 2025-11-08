<?php

namespace RiseTechApps\FormRequest\Traits\HasFormValidation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Arr;

trait HasFormValidation
{
    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $message = $this->validationErrorMessage();
        $errors = $validator->errors();
        $extras = $this->validationErrorExtras($validator);

        $response = $this->jsonNotValidatedResponse($message, $errors, $extras);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        $message = $this->authorizationErrorMessage();
        $extras = $this->authorizationErrorExtras();

        $payload = array_merge([
            'success' => false,
        ], $extras);

        if (!is_null($message)) {
            $payload['message'] = $message;
        }

        throw new HttpResponseException(response()->json($payload, 403));
    }

    /**
     * Resolve the translated validation error message.
     */
    protected function validationErrorMessage(): ?string
    {
        return __('The data provided is invalid.');
    }

    /**
     * Resolve the translated authorization error message.
     */
    protected function authorizationErrorMessage(): ?string
    {
        return __('Permission denied.');
    }

    /**
     * Additional payload merged into validation error responses.
     */
    protected function validationErrorExtras(Validator $validator): array
    {
        if (property_exists($this, 'validationResponseExtras')) {
            return Arr::wrap($this->validationResponseExtras);
        }

        return [];
    }

    /**
     * Additional payload merged into authorization error responses.
     */
    protected function authorizationErrorExtras(): array
    {
        if (property_exists($this, 'authorizationResponseExtras')) {
            return Arr::wrap($this->authorizationResponseExtras);
        }

        return [];
    }

    /**
     * Create the JSON response for validation failures while honouring package macros.
     */
    protected function jsonNotValidatedResponse(?string $message, $errors, array $extras = []): JsonResponse
    {
        $factory = app(ResponseFactory::class);

        if (ResponseFactory::hasMacro('jsonNotValidated')) {
            return $factory->jsonNotValidated($message, $errors, $extras);
        }

        $payload = array_merge([
            'success' => false,
            'errors' => $errors,
        ], $extras);

        if (!is_null($message)) {
            $payload['message'] = $message;
        }

        return response()->json($payload, 422);
    }
}
