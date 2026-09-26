<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ItemFilter
{
    protected static function resolveSearchFilters(?Request $request, $filter = null): array
    {
        $filter_list = self::normalizeFilterValues($filter);

        $quick_action = self::normalizeFilterValues($request?->query('quick_action'));

        $filter_by = self::normalizeFilterValues($request?->query('filter_by'));
        $filter_by = array_values(array_unique(array_merge($filter_by, $quick_action, $filter_list)));
        if ($request?->query('type') === 'halal' && !in_array('halal', $filter_by)) {
            $filter_by[] = 'halal';
        }

        $sort_by = self::normalizeSortValue($request?->query('sort_by') ?? 'default');

        if ($request) {
            if (!$request->filled('rating_count') && $request->filled('rating')) {
                $rating_threshold = self::ratingThreshold($request->query('rating'));
                if ($rating_threshold > 0) {
                    $request->merge(['rating_count' => $rating_threshold]);
                }
            }
            if (!$request->filled('min_price') && $request->filled('price_min')) {
                $request->merge(['min_price' => $request->query('price_min')]);
            }
            if (!$request->filled('max_price') && $request->filled('price_max')) {
                $request->merge(['max_price' => $request->query('price_max')]);
            }
        }

        return [
            'sort_by' => $sort_by,
            'filter_by' => $filter_by,
            'filter_list' => $filter_list,
        ];
    }

    protected static function storeFilterInputs(?Request $request, array $only = []): array
    {
        $keys = ['search', 'sort_by', 'quick_action', 'price_min', 'price_max', 'type', 'category_ids', 'rating'];
        if (!empty($only)) {
            $keys = array_values(array_intersect($keys, $only));
        }

        return $request ? $request->only($keys) : [];
    }

    protected static function normalizeSortValue($sortBy): string
    {
        if (is_array($sortBy)) {
            $sortBy = array_values(array_filter($sortBy, fn ($v) => $v !== null && $v !== '' && ! is_array($v)))[0] ?? null;
        }

        return match ($sortBy) {
            'price_low_high', 'low' => 'price_low_to_high',
            'price_high_low', 'high' => 'price_high_to_low',
            default => (string) ($sortBy === null || $sortBy === '' ? 'default' : $sortBy),
        };
    }

    protected static function normalizeFilterValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
        }

        if ($value === null || $value === '') {
            return [];
        }

        $string = trim((string) $value);

        $decoded = json_decode($string, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), $decoded),
                fn ($v) => $v !== ''
            ));
        }

        return array_values(array_filter(
            array_map(fn ($v) => trim($v, " \t\n\r\0\x0B\"'"), explode(',', trim($string, '[]'))),
            fn ($v) => $v !== ''
        ));
    }

    protected static function ratingThreshold($value): float
    {
        if (is_array($value)) {
            $thresholds = array_filter(array_map(fn ($v) => self::ratingThreshold($v), $value), fn ($v) => $v > 0);

            return $thresholds ? (float) min($thresholds) : 0;
        }
        if ($value === null || $value === '') {
            return 0;
        }
        return (float) match ((string) $value) {
            'only_5', '5' => 5,
            'four_plus', '4_plus', '4' => 4,
            'three_plus', '3_plus', '3' => 3,
            'two_plus', '2_plus', '2' => 2,
            default => is_numeric($value) ? (float) $value : 0,
        };
    }

    protected static function ratingIsExact($value): bool
    {
        if (is_array($value)) {
            $values = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));

            return count($values) === 1 && self::ratingIsExact($values[0]);
        }

        return in_array((string) $value, ['only_5', '5'], true);
    }
    
    protected static function categoryIdArray($value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => (int) $v, $value), fn ($v) => $v > 0));
        }

        $str = trim((string) $value);
        if ($str === '') {
            return [];
        }

        if (str_starts_with($str, '[')) {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => (int) $v, $decoded), fn ($v) => $v > 0));
            }
        }

        return array_values(array_filter(array_map(fn ($v) => (int) trim($v), explode(',', $str)), fn ($v) => $v > 0));
    }
}
