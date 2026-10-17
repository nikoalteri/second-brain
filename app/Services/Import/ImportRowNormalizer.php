<?php

namespace App\Services\Import;

use DateTimeImmutable;

/**
 * Turns one row of raw cells into {date, amount_minor, description, category, currency} using an
 * explicit mapping. Sign convention: negative = money out (spending), positive = money in.
 */
class ImportRowNormalizer
{
    public const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd/m/y', 'm/d/y'];

    public function __construct(private readonly ImportAmountParser $amounts) {}

    /**
     * @param list<string> $cells
     * @param array<string, mixed> $mapping validated mapping
     * @param list<int> $numericColumns columns that held a real XLSX number: parsed canonically
     * @return array{date: string, amount_minor: int, description: string, category: ?string, currency: ?string}
     *
     * @throws ImportFileException with a message safe to show next to the row
     */
    public function normalize(array $cells, array $mapping, string $fileFormat, array $numericColumns = []): array
    {
        $date = $this->date($this->cell($cells, $mapping['date_column']), $mapping['date_format'], $fileFormat);
        $amount = $this->amount($cells, $mapping, $numericColumns);

        $description = preg_replace('/\s+/u', ' ', $this->cell($cells, $mapping['description_column'])) ?? '';
        $description = mb_substr(trim($description), 0, 500);

        $category = isset($mapping['category_column']) ? trim($this->cell($cells, $mapping['category_column'])) : null;
        $currency = isset($mapping['currency_column']) ? strtoupper(trim($this->cell($cells, $mapping['currency_column']))) : null;

        return [
            'date' => $date,
            'amount_minor' => $amount,
            'description' => $description,
            'category' => $category === '' ? null : $category,
            'currency' => $currency === '' ? null : $currency,
        ];
    }

    /** @param list<string> $cells */
    private function cell(array $cells, ?int $index): string
    {
        return $index === null ? '' : (string) ($cells[$index] ?? '');
    }

    private function date(string $text, string $format, string $fileFormat): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new ImportFileException('The date is empty.');
        }

        // A time part is ignored: only the first token is the date.
        $token = preg_split('/\s+/', $text)[0];

        $parsed = $this->parseDate($token, $format);

        // XLSX date cells are already emitted as ISO dates by the reader.
        if ($parsed === null && $fileFormat === 'xlsx') {
            $parsed = $this->parseDate($token, 'Y-m-d');
        }

        if ($parsed === null) {
            throw new ImportFileException('The date does not match the chosen format.');
        }

        $year = (int) $parsed->format('Y');

        if ($year < 1990 || $year > 2100) {
            throw new ImportFileException('The date is out of range.');
        }

        return $parsed->format('Y-m-d');
    }

    private function parseDate(string $text, string $format): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }

    /**
     * @param list<string> $cells
     * @param list<int> $numericColumns
     */
    private function amount(array $cells, array $mapping, array $numericColumns): int
    {
        // A real number is already canonical ("-12.5"): the separators describe TEXT and would corrupt it.
        $parse = function (string $text, int $column) use ($mapping, $numericColumns): int {
            return in_array($column, $numericColumns, true)
                ? $this->amounts->parse($text, '.', '')
                : $this->amounts->parse($text, $mapping['decimal_separator'], $mapping['thousands_separator']);
        };

        if ($mapping['amount_mode'] === 'split') {
            $debit = trim($this->cell($cells, $mapping['debit_column']));
            $credit = trim($this->cell($cells, $mapping['credit_column']));

            if ($debit !== '' && $credit !== '') {
                throw new ImportFileException('Both the debit and the credit column are filled.');
            }

            if ($debit === '' && $credit === '') {
                throw new ImportFileException('The amount is empty.');
            }

            $minor = $debit !== ''
                ? -abs($parse($debit, $mapping['debit_column']))
                : abs($parse($credit, $mapping['credit_column']));
        } else {
            $minor = $parse($this->cell($cells, $mapping['amount_column']), $mapping['amount_column']);
        }

        return ! empty($mapping['invert_sign']) ? -$minor : $minor;
    }
}
