<?php

namespace RiseTechApps\FormRequest\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RiseTechApps\FormRequest\Services\Validator\validatePixKey;
use RiseTechApps\FormRequest\Services\Validator\validateStrongPassword;
use RiseTechApps\FormRequest\Tests\Support\MakesValidator;

class SecurityValidatorTest extends TestCase
{
    use MakesValidator;

    /**
     * @param array<int, string> $parameters
     */
    #[DataProvider('passwordProvider')]
    public function test_validates_strong_password(string $password, array $parameters, bool $expected): void
    {
        $this->assertSame($expected, validateStrongPassword::validate('password', $password, $parameters, $this->makeValidator()));
    }

    /**
     * @param array<int, string> $parameters
     */
    #[DataProvider('pixKeyProvider')]
    public function test_validates_pix_key(string $key, array $parameters, bool $expected): void
    {
        $this->assertSame($expected, validatePixKey::validate('pix', $key, $parameters, $this->makeValidator()));
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>, 2: bool}>
     */
    public static function passwordProvider(): array
    {
        return [
            'meets every default requirement' => ['Abcdef1@', [], true],
            'missing symbol' => ['Abcdefg1', [], false],
            'missing uppercase' => ['abcdef1@', [], false],
            'missing lowercase' => ['ABCDEF1@', [], false],
            'missing number' => ['Abcdefg@', [], false],
            'shorter than default' => ['Abc1@fg', [], false],
            'custom minimum length' => ['Abcdef1@', ['12'], false],
            'custom minimum length met' => ['Abcdefgh1@xy', ['12'], true],
            'only selected requirements' => ['ABCDEFGH', ['8', 'upper'], true],
            'selected requirement missing' => ['abcdefgh', ['8', 'upper'], false],
            'unknown requirement' => ['Abcdef1@', ['banana'], false],
            'accented uppercase counts as upper' => ['Ábcdéf1@', [], true],
            'accented lowercase is not a symbol' => ['Abcdefçx1', [], false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>, 2: bool}>
     */
    public static function pixKeyProvider(): array
    {
        return [
            'valid cpf' => ['52998224725', [], true],
            'invalid cpf' => ['52998224724', [], false],
            'valid cnpj' => ['11222333000181', [], true],
            'valid alphanumeric cnpj' => ['12ABC34501DE35', [], true],
            'valid email' => ['pix@risetech.com.br', [], true],
            'invalid email' => ['pix@@risetech', [], false],
            'valid phone' => ['+5511999998888', [], true],
            'phone without country code' => ['11999998888', [], false],
            'valid random key' => ['123e4567-e89b-42d3-a456-426614174000', [], true],
            'random key wrong version' => ['123e4567-e89b-12d3-a456-426614174000', [], false],
            'restricted to email' => ['pix@risetech.com.br', ['email'], true],
            'restricted type rejected' => ['52998224725', ['email'], false],
            'restricted to list' => ['+5511999998888', ['email', 'phone'], true],
            'unknown type' => ['52998224725', ['bank_slip'], false],
            'empty' => ['', [], false],
        ];
    }
}
