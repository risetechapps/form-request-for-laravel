<?php

namespace RiseTechApps\FormRequest\Contracts;

interface RulesContract
{
    /**
     * Retorna as regras de validação.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function Rules(): array;

    /**
     * Retorna as mensagens de validação personalizadas.
     *
     * @return array<string, array<string, string>>
     */
    public static function Messages(): array;

    /**
     * Retorna validadores customizados.
     *
     * @return array<string, string>
     */
    public static function Validator(): array;
}
