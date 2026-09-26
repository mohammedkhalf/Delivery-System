<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\RecentSearch;
use App\Services\Search\AdminSearchRegistry;
use App\Services\Search\AdminSearchShortcuts;
use App\Services\Search\RouteIndex;
use App\Services\Search\SearchContext;
use App\Services\Search\SearchEngine;
use App\Services\Search\SearchKeyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchRoutingController extends Controller
{
    private const PANEL_PREFIX = 'admin';

    private const RECENT_SEARCH_LIMIT = 15;

    private const BASE_EXCLUDED_TERMS = [
        '{status}',
        'review-status',
        'review-export',
        '{locale}',
        'export-review',
        'admin/transactions/report/vendor-tax-report',
        '-auto-fill',
    ];

    private const AJAX_EXCLUSION_ALLOWLIST = [
        'admin/users/cashback/edit/{id}',
        'admin/rental',
        'admin/rental/trip/details/{id}',
    ];

    public function index(Request $request): array
    {
        $keyword = new SearchKeyword($request->input('search'));
        session(['search_keyword' => $keyword->raw()]);

        if ($keyword->isEmpty()) {
            return [];
        }

        $context = $this->context();
        $index = new RouteIndex(self::PANEL_PREFIX, $this->excludedTerms($context), $this->permissionChecker());
        $engine = new SearchEngine($index, $context);
        $pageRoutes = $this->pageRoutes($context);

        return $engine->finish(
            $keyword,
            $this->allowedShortcuts(AdminSearchShortcuts::all($keyword->raw(), $context->moduleType)),
            $engine->pages($keyword, $pageRoutes, $this->pageVisibility($context), $this->allowedPageUris($pageRoutes)),
            $engine->records($keyword, AdminSearchRegistry::all()),
        );
    }

    private function context(): SearchContext
    {
        $moduleType = config('module.current_module_type');

        return new SearchContext(
            panelPrefix: self::PANEL_PREFIX,
            moduleType: $moduleType,
            moduleId: config('module.current_module_id'),
            inModuleWorkspace: in_array($moduleType, config('module.module_type', []), true),
        );
    }

    private function excludedTerms(SearchContext $context): array
    {
        $terms = self::BASE_EXCLUDED_TERMS;

        if (! addon_published_status('Rental') || $context->moduleTypeIsNot('rental')) {
            $terms[] = 'rental';
        }

        if (! addon_published_status('RideShare')) {
            $terms[] = 'ride-share';
            $terms[] = 'rider';
        }

        $ajax = array_diff(RouteIndex::jsonResponseUris(self::PANEL_PREFIX), self::AJAX_EXCLUSION_ALLOWLIST);

        return array_values(array_merge($terms, $ajax));
    }

    private function pageRoutes(SearchContext $context): array
    {
        $path = public_path('admin_formatted_routes.json');

        if (! file_exists($path)) {
            return [];
        }

        $routes = json_decode(file_get_contents($path), true) ?: [];

        if (! addon_published_status('Rental') || $context->moduleTypeIsNot('rental')) {
            $routes = array_filter($routes, function ($route) {
                return ! in_array('rental', $route['moduleType'] ?? [], true);
            });
        }

        if (! addon_published_status('RideShare')) {
            $routes = array_filter($routes, function ($route) {
                $types = $route['moduleType'] ?? [];

                return ! in_array('ride-share', $types, true) && ! in_array('rider', $types, true);
            });
        }

        return array_values($routes);
    }

    private function permissionChecker(): \Closure
    {
        $cache = [];

        return function (string $permission) use (&$cache) {
            return $cache[$permission] ??= (Helpers::module_permission_check($permission) === true);
        };
    }

    private function allowedUriMap(array $uris): array
    {
        $can = $this->permissionChecker();

        return array_filter(
            RouteIndex::resolvableUris(self::PANEL_PREFIX, $uris),
            function ($permission) use ($can) {
                return $permission === '' || $can($permission);
            }
        );
    }

    private function allowedPageUris(array $pageRoutes): array
    {
        $path = function ($uri) {
            return explode('?', (string) $uri)[0];
        };

        $uris = array_column($pageRoutes, 'URI');
        $allowed = $this->allowedUriMap(array_map($path, $uris));

        $result = [];
        foreach ($uris as $uri) {
            if (isset($allowed[$path($uri)])) {
                $result[$uri] = $allowed[$path($uri)];
            }
        }

        return $result;
    }

    private function allowedShortcuts(array $shortcuts): array
    {
        $path = function ($row) {
            return explode('?', (string) ($row['URI'] ?? ''))[0];
        };

        $allowed = $this->allowedUriMap(array_map($path, $shortcuts));

        return array_values(array_filter($shortcuts, function ($row) use ($allowed, $path) {
            return isset($allowed[$path($row)]);
        }));
    }

    private function pageVisibility(SearchContext $context): callable
    {
        return function (array $route) use ($context) {
            $types = $route['moduleType'] ?? [];

            return $types === [] || in_array($context->moduleType, $types, true);
        };
    }

    public function storeClickedRoute(Request $request): JsonResponse
    {
        $user = auth('admin')->user();
        $userId = $user->id;
        $userType = $user->role_id ? 'admin' : 'admin-employee';
        $routeUri = $request->input('routeUri');
        $searchKeyword = $request->input('searchKeyword');
        $moduleId = $request->input('moduleId');

        $existing = RecentSearch::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('route_uri', $routeUri)
            ->first();

        if ($existing) {
            $existing->created_at = now();
            $existing->update();
        } else {
            $clicked = new RecentSearch();
            $clicked->user_id = $userId;
            $clicked->user_type = $userType;
            $clicked->route_name = $request->input('routeName');
            $clicked->route_uri = $routeUri;
            $clicked->module_id = is_numeric($moduleId) ? $moduleId : null;
            $clicked->keyword = $searchKeyword;
            $clicked->route_full_url = $this->appendKeyword($request->input('routeFullUrl'), $searchKeyword);
            $clicked->save();

            $this->trimRecentSearches($userId, $userType);
        }

        return response()->json(['message' => 'Clicked route stored successfully']);
    }

    private function trimRecentSearches(int|string $userId, string $userType): void
    {
        $query = RecentSearch::where('user_id', $userId)->where('user_type', $userType);

        if ($query->count() <= self::RECENT_SEARCH_LIMIT) {
            return;
        }

        $query->orderBy('created_at', 'asc')->first()?->delete();
    }

    private function appendKeyword(?string $url, ?string $keyword): ?string
    {
        if ($url === null || $keyword === null || trim($keyword) === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'keyword=' . urlencode($keyword);
    }

    public function recentSearch(): JsonResponse
    {
        $user = auth('admin')->user();

        $recentSearches = RecentSearch::where('user_id', $user->id)
            ->where('user_type', $user->role_id ? 'admin' : 'admin-employee')
            ->where(function ($query) {
                $query->whereNull('module_id')->orWhere('module_id', config('module.current_module_id'));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($recentSearches);
    }
}
