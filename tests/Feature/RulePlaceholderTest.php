<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Tests\TestCase;

class RulePlaceholderTest extends TestCase
{
    private const ID = '01a01775-cbbb-7500-8509-01569c5ae17c';

    #[DataProvider('ruleProvider')]
    public function test_placeholder_resolution(string $rule, string $expected): void
    {
        FormRequest::register('form', ['field' => $rule]);

        $this->assertSame(
            $expected,
            FormRequest::resolve('form', ['id' => self::ID])['rules']['field']
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ruleProvider(): array
    {
        $id = self::ID;

        return [
            // Regressão: o ':' era consumido, fundindo nome e valor e gerando
            // "Method validateUniqueCpfAuthentication01a01775... does not exist".
            'nome de regra preservado' => [
                'uniqueCpfAuthentication:id',
                'uniqueCpfAuthentication:id',
            ],
            'coluna chamada id preservada' => [
                'exists:authentications,id',
                'exists:authentications,id',
            ],
            'placeholder prefixado em parametro' => [
                'exists:authentications,id,:id',
                "exists:authentications,id,{$id}",
            ],
            'placeholder delimitado' => [
                'uniqueCpfAuthentication:{id}',
                "uniqueCpfAuthentication:{$id}",
            ],
            'regra sem parametros intocada' => [
                'required',
                'required',
            ],
            'substring nao e corrompida' => [
                'exists:paid_users,video_id',
                'exists:paid_users,video_id',
            ],
            'segmento posterior nao afeta o anterior' => [
                'bail|required|cpf|uniqueCpfAuthentication:id',
                'bail|required|cpf|uniqueCpfAuthentication:id',
            ],
        ];
    }

    public function test_custom_rule_receives_the_field_name_as_parameter(): void
    {
        $received = null;

        Validator::extend('uniqueCpfAuthentication', function ($attribute, $value, $parameters) use (&$received) {
            $received = $parameters;

            return true;
        });

        FormRequest::register('profile_update', [
            'cpf' => 'bail|required|uniqueCpfAuthentication:id',
        ]);

        $rules = FormRequest::resolve('profile_update', ['id' => self::ID])['rules'];

        Validator::make(['cpf' => '11144477735'], $rules)->passes();

        // Idioma nativo do Laravel (same:password, gt:idade): o parâmetro é o
        // nome do campo e quem resolve o valor é o validador.
        $this->assertSame(['id'], $received);
    }

    public function test_unique_rules_still_receive_the_id_automatically(): void
    {
        // setIdUpdate continua cobrindo o caso comum sem marcador nenhum.
        FormRequest::register('profile_update', [
            'email' => 'bail|required|email|unique:authentications,email',
        ]);

        $this->assertSame(
            'bail|required|email|unique:authentications,email,' . self::ID,
            FormRequest::resolve('profile_update', ['id' => self::ID])['rules']['email']
        );
    }
}
