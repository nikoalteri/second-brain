<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Import;

use App\Services\Import\ImportAmountParser;
use App\Services\Import\ImportFileException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportAmountParserTest extends TestCase
{
    #[DataProvider('valid')]
    public function test_parses_explicit_formats(string $text, string $decimal, string $thousands, int $expected): void
    {
        $this->assertSame($expected, (new ImportAmountParser)->parse($text, $decimal, $thousands));
    }

    public static function valid(): array
    {
        return [
            'english negative' => ['-12.50', '.', ',', -1250],
            'european negative' => ['-12,50', ',', '.', -1250],
            'european thousands' => ['1.234,56', ',', '.', 123456],
            'english thousands' => ['+1,234.56', '.', ',', 123456],
            'space thousands' => ['1 234,56', ',', '', 123456],
            'parentheses' => ['(12,50)', ',', '.', -1250],
            'trailing minus' => ['12,50-', ',', '.', -1250],
            'one decimal' => ['5.5', '.', '', 550],
            'integer' => ['7', '.', '', 700],
            'currency symbol' => ['€ -3,10', ',', '.', -310],
            'currency code' => ['-3.10 EUR', '.', ',', -310],
            'zero' => ['0,00', ',', '.', 0],
        ];
    }

    #[DataProvider('invalid')]
    public function test_rejects_ambiguous_or_malformed_amounts(string $text, string $decimal, string $thousands): void
    {
        $this->expectException(ImportFileException::class);
        (new ImportAmountParser)->parse($text, $decimal, $thousands);
    }

    public static function invalid(): array
    {
        return [
            'empty' => ['', '.', ','],
            'letters' => ['abc', '.', ','],
            'three decimals' => ['1.234', '.', ''],
            'wrong decimal separator' => ['12.50', ',', ''],
            'two separators' => ['1,2,3', '.', ''],
            'exponent' => ['1e3', '.', ','],
        ];
    }
}
