<?php

namespace RiseTechApps\FormRequest\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RiseTechApps\FormRequest\Services\Validator\validateBankBarcode;
use RiseTechApps\FormRequest\Services\Validator\validateCreditCard;
use RiseTechApps\FormRequest\Services\Validator\validateDigitableLine;
use RiseTechApps\FormRequest\Tests\Support\MakesValidator;

class BankingValidatorTest extends TestCase
{
    use MakesValidator;

    /**
     * @param array<int, string> $parameters
     */
    #[DataProvider('cardProvider')]
    public function test_validates_credit_card(string $number, array $parameters, bool $expected): void
    {
        $this->assertSame($expected, validateCreditCard::validate('card', $number, $parameters, $this->makeValidator()));
    }

    #[DataProvider('barcodeProvider')]
    public function test_validates_barcode(string $barcode, bool $expected): void
    {
        $this->assertSame($expected, validateBankBarcode::validate('barcode', $barcode, [], $this->makeValidator()));
    }

    #[DataProvider('digitableLineProvider')]
    public function test_validates_digitable_line(string $line, bool $expected): void
    {
        $this->assertSame($expected, validateDigitableLine::validate('bank_slip', $line, [], $this->makeValidator()));
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>, 2: bool}>
     */
    public static function cardProvider(): array
    {
        return [
            'valid visa' => ['4111111111111111', [], true],
            'valid visa spaced' => ['4111 1111 1111 1111', [], true],
            'valid mastercard' => ['5555555555554444', [], true],
            'valid amex' => ['378282246310005', [], true],
            'valid discover' => ['6011111111111117', [], true],
            'invalid luhn' => ['4111111111111112', [], false],
            'too short' => ['411111111111', [], false],
            'non numeric' => ['4111abcd11111111', [], false],
            'brand allowed' => ['4111111111111111', ['visa'], true],
            'brand rejected' => ['4111111111111111', ['mastercard'], false],
            'brand from list' => ['5555555555554444', ['visa', 'mastercard'], true],
            'unknown brand' => ['4111111111111111', ['nubank'], false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function barcodeProvider(): array
    {
        return [
            'valid bank slip' => ['34191999900000123451234567890123456789012345', true],
            'bank slip wrong check digit' => ['34199999900000123451234567890123456789012345', false],
            'valid utility mod10' => ['83620000123456789012345678901234567890123456', true],
            'valid utility mod11' => ['83820000123456789012345678901234567890123456', true],
            'utility wrong check digit' => ['83600000123456789012345678901234567890123456', false],
            'utility invalid value identifier' => ['83120000123456789012345678901234567890123456', false],
            'all zeros' => [str_repeat('0', 44), false],
            'wrong length' => ['3419199990000012345', false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function digitableLineProvider(): array
    {
        return [
            'valid bank slip' => ['34191234546789012345767890123457199990000012345', true],
            'valid bank slip formatted' => ['34191.23454 67890.123457 67890.123457 1 99990000012345', true],
            'bank slip wrong field digit' => ['34191234556789012345767890123457199990000012345', false],
            'bank slip wrong general digit' => ['34191234546789012345767890123457299990000012345', false],
            'valid utility mod10' => ['836200001235456789012345567890123456678901234560', true],
            'valid utility mod11' => ['838200001238456789012341567890123457678901234561', true],
            'utility wrong block digit' => ['836200001234456789012345567890123456678901234560', false],
            'bank slip wrong length' => ['3419123454678901234576789012345719999000001234', false],
            'utility wrong length' => ['83620000123545678901234556789012345667890123456', false],
            'empty' => ['', false],
        ];
    }
}
