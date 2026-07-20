<?php

namespace RiseTechApps\FormRequest\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RiseTechApps\FormRequest\Services\Validator\validateCnae;
use RiseTechApps\FormRequest\Services\Validator\validateNcm;
use RiseTechApps\FormRequest\Tests\Support\MakesValidator;

class FiscalValidatorTest extends TestCase
{
    use MakesValidator;

    #[DataProvider('cnaeProvider')]
    public function test_validates_cnae(string $cnae, bool $expected): void
    {
        $this->assertSame($expected, validateCnae::validate('cnae', $cnae, [], $this->makeValidator()));
    }

    #[DataProvider('ncmProvider')]
    public function test_validates_ncm(string $ncm, bool $expected): void
    {
        $this->assertSame($expected, validateNcm::validate('ncm', $ncm, [], $this->makeValidator()));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function cnaeProvider(): array
    {
        return [
            'valid formatted' => ['6201-5/01', true],
            'valid unformatted' => ['6201501', true],
            'valid low division' => ['0111301', true],
            'zero division' => ['0011301', false],
            'all zeros' => ['0000000', false],
            'too short' => ['620150', false],
            'too long' => ['62015011', false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function ncmProvider(): array
    {
        return [
            'valid formatted' => ['8471.30.12', true],
            'valid unformatted' => ['84713012', true],
            'zero chapter' => ['00713012', false],
            'reserved chapter 77' => ['77123456', false],
            'all zeros' => ['00000000', false],
            'too short' => ['8471301', false],
            'too long' => ['847130121', false],
        ];
    }
}
