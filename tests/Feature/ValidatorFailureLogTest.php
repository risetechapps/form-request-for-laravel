<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RiseTechApps\FormRequest\Tests\TestCase;

class ValidatorFailureLogTest extends TestCase
{
    public function test_failing_validator_rejects_value_and_logs_rule_name(): void
    {
        Log::shouldReceive('channel')->once()->with(null)->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(
            fn(string $message, array $context) => $message === 'Validator uniqueJson failed'
                && $context['rule'] === 'uniqueJson'
                && $context['attribute'] === 'email'
                && $context['exception'] instanceof QueryException
        );

        // Tabela inexistente: a consulta falha e a regra nega o valor.
        $validator = Validator::make(
            ['email' => 'joao@example.com'],
            ['email' => 'uniqueJson:missing_table,data.email']
        );

        $this->assertTrue($validator->fails());
    }
}
