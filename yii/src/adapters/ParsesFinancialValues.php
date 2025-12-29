<?php

declare(strict_types=1);

namespace app\adapters;

/**
 * Shared value parsing logic for financial data adapters.
 */
trait ParsesFinancialValues
{
    private const INVALID_VALUES = ['n/a', 'na', '-', '--'];

    /**
     * @return array{value: float, currency?: string, scale?: string}|null
     */
    private function parseValue(string $raw, string $unit): ?array
    {
        $normalized = trim($raw);
        if ($this->isInvalidValue($normalized)) {
            return null;
        }

        $normalized = str_replace(',', '', $normalized);

        return match ($unit) {
            'currency' => $this->parseCurrencyValue($normalized),
            'ratio' => $this->parseRatioValue($normalized),
            'percent' => $this->parsePercentValue($normalized),
            'number' => $this->parseNumberValue($normalized),
            default => null,
        };
    }

    private function isInvalidValue(string $value): bool
    {
        return in_array(strtolower($value), self::INVALID_VALUES, true);
    }

    /**
     * @return array{value: float, scale: string, currency?: string}|null
     */
    private function parseCurrencyValue(string $value): ?array
    {
        $currency = null;
        $upper = strtoupper($value);
        $hasDollar = str_contains($value, '$');

        if (str_contains($upper, 'USD')) {
            $currency = 'USD';
            $value = str_replace('USD', '', $upper);
        } elseif (str_contains($upper, 'EUR')) {
            $currency = 'EUR';
            $value = str_replace('EUR', '', $upper);
        } elseif (str_contains($upper, 'GBP')) {
            $currency = 'GBP';
            $value = str_replace('GBP', '', $upper);
        }

        $value = str_replace('$', '', $value);
        $value = trim($value);

        if ($currency === null && $hasDollar) {
            $currency = 'USD';
        }

        if (preg_match('/^(-?[\d.]+)([TBMK])?$/', $value, $matches) !== 1) {
            return null;
        }

        $number = (float)$matches[1];
        $suffix = strtoupper($matches[2] ?? '');

        $scale = match ($suffix) {
            'T' => 'trillions',
            'B' => 'billions',
            'M' => 'millions',
            'K' => 'thousands',
            default => 'units',
        };

        return [
            'value' => $number,
            'scale' => $scale,
            'currency' => $currency,
        ];
    }

    /**
     * @return array{value: float}|null
     */
    private function parseRatioValue(string $value): ?array
    {
        $value = rtrim($value, 'x');

        if (!is_numeric($value)) {
            return null;
        }

        return ['value' => (float)$value];
    }

    /**
     * @return array{value: float}|null
     */
    private function parsePercentValue(string $value): ?array
    {
        $value = rtrim($value, '%');

        if (!is_numeric($value)) {
            return null;
        }

        return ['value' => (float)$value];
    }

    /**
     * @return array{value: float}|null
     */
    private function parseNumberValue(string $value): ?array
    {
        if (!is_numeric($value)) {
            return null;
        }

        return ['value' => (float)$value];
    }
}
