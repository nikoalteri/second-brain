<?php

namespace App\Services\Import;

/**
 * Parses a statement amount into integer minor units with explicit separators, never the process
 * locale. Handles "-12,50", "(12,50)", "12,50-", "+1.234,56" and a currency symbol or code.
 */
class ImportAmountParser
{
    /**
     * @throws ImportFileException when the text is not a plain amount with at most two decimals
     */
    public function parse(string $text, string $decimalSeparator, string $thousandsSeparator): int
    {
        $value = trim(str_replace(["\u{00A0}", "\u{202F}", ' '], '', $text));
        $value = preg_replace('/^(?:[A-Z]{3}|[€$£])|(?:[A-Z]{3}|[€$£])$/u', '', $value) ?? $value;

        if ($value === '') {
            throw new ImportFileException('The amount is empty.');
        }

        $negative = false;

        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $negative = true;
            $value = substr($value, 1, -1);
        }

        if (str_ends_with($value, '-')) {
            $negative = ! $negative;
            $value = substr($value, 0, -1);
        }

        if (str_starts_with($value, '-')) {
            $negative = ! $negative;
            $value = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        if ($thousandsSeparator !== '') {
            $value = str_replace($thousandsSeparator, '', $value);
        }

        if ($decimalSeparator !== '.') {
            if (str_contains($value, '.')) {
                throw new ImportFileException('The amount does not match the chosen number format.');
            }

            $value = str_replace($decimalSeparator, '.', $value);
        }

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new ImportFileException('The amount is not a valid number.');
        }

        $minor = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');

        return $negative ? -$minor : $minor;
    }
}
