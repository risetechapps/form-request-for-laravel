<?php

namespace RiseTechApps\FormRequest\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RiseTechApps\FormRequest\Services\Validator\validateCNPJ;
use RiseTechApps\FormRequest\Services\Validator\validateCPF;

class DocumentValidatorTest extends TestCase
{
    #[DataProvider('cpfProvider')]
    public function test_validates_cpf(string $cpf, bool $expected): void
    {
        $result = validateCPF::validate('cpf', $cpf, [], $this->makeValidator());

        $this->assertSame($expected, $result);
    }

    #[DataProvider('cnpjProvider')]
    public function test_validates_cnpj(string $cnpj, bool $expected): void
    {
        $result = validateCNPJ::validate('cnpj', $cnpj, [], $this->makeValidator());

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function cpfProvider(): array
    {
        return [
            'valid formatted' => ['529.982.247-25', true],
            'valid unformatted' => ['52998224725', true],
            'invalid check digit' => ['52998224724', false],
            'repeated digits' => ['11111111111', false],
            'wrong length' => ['123', false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function cnpjProvider(): array
    {
        return [
            'valid formatted' => ['11.222.333/0001-81', true],
            'valid unformatted' => ['11222333000181', true],
            'invalid check digit' => ['11222333000180', false],
            'repeated digits' => ['11111111111111', false],
            'wrong length' => ['123', false],
            'valid alphanumeric' => ['12ABC34501DE35', true],
            'valid alphanumeric formatted' => ['12.ABC.345/01DE-35', true],
            'valid alphanumeric lowercase' => ['12abc34501de35', true],
            'alphanumeric invalid check digit' => ['12ABC34501DE36', false],
            'repeated letters' => ['AAAAAAAAAAAA00', false],
            'letters in check digits' => ['12ABC34501DEAB', false],
            'invalid character' => ['12ABC34501D#35', false],
        ];
    }

    private function makeValidator(): \Illuminate\Validation\Validator
    {
        return new \Illuminate\Validation\Validator(
            new \Illuminate\Translation\Translator(
                new \Illuminate\Translation\ArrayLoader(),
                'en'
            ),
            [],
            []
        );
    }
}
