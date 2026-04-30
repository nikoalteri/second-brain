<?php

namespace App\Support;

use Illuminate\Support\Str;

class PhoneNumber
{
    public const DEFAULT_COUNTRY_CODE = '+39';

    /**
     * @return array<string, string>
     */
    public static function countryCodeOptions(): array
    {
        return [
            '+39' => 'Italy (+39)',
            '+33' => 'France (+33)',
            '+34' => 'Spain (+34)',
            '+41' => 'Switzerland (+41)',
            '+43' => 'Austria (+43)',
            '+44' => 'United Kingdom (+44)',
            '+49' => 'Germany (+49)',
            '+31' => 'Netherlands (+31)',
            '+32' => 'Belgium (+32)',
            '+351' => 'Portugal (+351)',
            '+30' => 'Greece (+30)',
            '+1' => 'United States / Canada (+1)',
            '+353' => 'Ireland (+353)',
            '+48' => 'Poland (+48)',
            '+40' => 'Romania (+40)',
        ];
    }

    /**
     * @return array{country_code: string, local_number: string}
     */
    public static function split(?string $phone): array
    {
        if (! filled($phone)) {
            return [
                'country_code' => self::DEFAULT_COUNTRY_CODE,
                'local_number' => '',
            ];
        }

        $normalized = preg_replace('/[^\d+]/', '', trim((string) $phone)) ?? '';

        foreach (self::sortedCountryCodes() as $countryCode) {
            if (Str::startsWith($normalized, $countryCode)) {
                return [
                    'country_code' => $countryCode,
                    'local_number' => substr($normalized, strlen($countryCode)) ?: '',
                ];
            }
        }

        return [
            'country_code' => self::DEFAULT_COUNTRY_CODE,
            'local_number' => preg_replace('/\D+/', '', $normalized) ?? '',
        ];
    }

    public static function combine(?string $countryCode, ?string $localNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $localNumber) ?? '';

        if ($digits === '') {
            return null;
        }

        $normalizedCountryCode = self::normalizeCountryCode($countryCode);

        return $normalizedCountryCode.$digits;
    }

    public static function normalizeCountryCode(?string $countryCode): string
    {
        $digits = preg_replace('/\D+/', '', (string) $countryCode) ?? '';

        if ($digits === '') {
            return self::DEFAULT_COUNTRY_CODE;
        }

        return '+'.$digits;
    }

    /**
     * @return list<string>
     */
    private static function sortedCountryCodes(): array
    {
        $codes = array_keys(self::countryCodeOptions());

        usort($codes, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $codes;
    }
}
