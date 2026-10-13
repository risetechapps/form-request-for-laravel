<?php

namespace RiseTechApps\FormRequest\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra falhas internas do pacote no canal de log configurado em
 * rules.log_channel (null usa o canal padrão da aplicação).
 */
final class ErrorReporter
{
    /**
     * @param array<string, mixed> $context
     */
    public static function report(Throwable $exception, string $message, array $context = []): void
    {
        // Validadores também rodam fora de uma aplicação (testes unitários,
        // uso direto da classe); sem logger não há onde registrar.
        if (!app()->bound('log')) {
            return;
        }

        Log::channel(app()->bound('config') ? config('rules.log_channel') : null)->error(
            $message,
            $context + ['exception' => $exception]
        );
    }
}
