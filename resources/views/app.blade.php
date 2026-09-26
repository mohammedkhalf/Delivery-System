<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bootstrap.min.css') }}">
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia

    {{-- Demo-only Website Builder promo modal (Builder storefront + setup pages). --}}
    @if ((function_exists('getEnvMode') ? getEnvMode() : config('app.app_mode')) === 'demo')
        @include('partials.builder-demo-promo')
    @endif
</body>
</html>
