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
     * Trata uma tentativa de validação que falhou.
     *
     * @param Validator $validator
     * @return never
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
     * Trata uma tentativa de autorização que falhou.
     *
     * @return never
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
     * Retorna a mensagem traduzida para erro de validação.
     *
     * @return string|null
     */
    protected function validationErrorMessage(): ?string
    {
        return __('The data provided is invalid.');
    }

    /**
     * Retorna a mensagem traduzida para erro de autorização.
     *
     * @return string|null
     */
    protected function authorizationErrorMessage(): ?string
    {
        return __('Permission denied.');
    }

    /**
     * Dados adicionais mesclados na resposta de erro de validação.
     *
     * @return array<string, mixed>
     */
    protected function validationErrorExtras(Validator $validator): array
    {
        if (property_exists($this, 'validationResponseExtras')) {
            return Arr::wrap($this->validationResponseExtras);
        }

        return [];
    }

    /**
     * Dados adicionais mesclados na resposta de erro de autorização.
     *
     * @return array<string, mixed>
     */
    protected function authorizationErrorExtras(): array
    {
        if (property_exists($this, 'authorizationResponseExtras')) {
            return Arr::wrap($this->authorizationResponseExtras);
        }

        return [];
    }

    /**
     * Cria a resposta JSON para falhas de validação respeitando as macros do pacote.
     *
     * @param string|null $message
     * @param mixed $errors
     * @param array<string, mixed> $extras
     */
    protected function jsonNotValidatedResponse(?string $message, mixed $errors, array $extras = []): JsonResponse
    {
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
