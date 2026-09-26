<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Route;

class VendorSearchShortcuts
{
    public static function all(string $keyword, ?string $moduleType): array
    {
        if ($moduleType !== 'service') {
            return [];
        }

        return array_merge(
            self::bookingKeywordRoutes($keyword, 'vendor.service.booking.list'),
            self::serviceTaxRoutes($keyword),
        );
    }

    private static function bookingKeywordRoutes(string $keyword, string $routeName): array
    {
        $keyword = strtolower(trim($keyword));
        if (! str_contains($keyword, 'booking') || ! Route::has($routeName)) {
            return [];
        }

        $base = route($routeName);

        $tabs = [
            'pending' => ['booking_status' => 'pending'],
            'accepted' => ['booking_status' => 'accepted'],
            'confirmed' => ['booking_status' => 'confirmed'],
            'ongoing' => ['booking_status' => 'ongoing'],
            'completed' => ['booking_status' => 'completed'],
            'on hold' => ['booking_status' => 'on_hold'],
            'on_hold' => ['booking_status' => 'on_hold'],
            'canceled' => ['booking_status' => 'canceled'],
            'cancelled' => ['booking_status' => 'canceled'],
            'regular' => ['type' => 'regular'],
            'repeat' => ['type' => 'repeat'],
        ];

        $entry = function (string $label, array $params) use ($base) {
            $url = empty($params) ? $base : $base.'?'.http_build_query($params);

            return [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
            ];
        };

        $label = fn (string $token) => ucwords(str_replace('_', ' ', $token)).' Booking';

        foreach ($tabs as $token => $params) {
            if (str_contains($keyword, $token)) {
                return [$entry($label($token === 'cancelled' ? 'canceled' : $token), $params)];
            }
        }

        $routes = [$entry('All Booking', [])];
        foreach ($tabs as $token => $params) {
            if (in_array($token, ['on_hold', 'cancelled'], true)) {
                continue;
            }
            $routes[] = $entry($label($token), $params);
        }

        return $routes;
    }

    private static function serviceTaxRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if (! preg_match('/\b(tax|compliance|vat)\b/', $keyword) || ! Route::has('vendor.service.report.providerTax')) {
            return [];
        }

        $url = route('vendor.service.report.providerTax');

        return [[
            'routeName' => 'Provider Tax Report',
            'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
            'fullRoute' => $url,
        ]];
    }

}
