<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\RecentSearch;
use App\Services\Search\RouteIndex;
use App\Services\Search\SearchContext;
use App\Services\Search\SearchEngine;
use App\Services\Search\SearchKeyword;
use App\Services\Search\VendorSearchRegistry;
use App\Services\Search\VendorSearchShortcuts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchRoutingController extends Controller
{
    private const PANEL_PREFIX = 'vendor-panel';

    private const RECENT_SEARCH_LIMIT = 15;

    public function index(Request $request): array
    {
        $keyword = new SearchKeyword($request->input('search'));
        session(['search_keyword' => $keyword->raw()]);

        if ($keyword->isEmpty()) {
            return [];
        }

        $storeData = Helpers::get_store_data();
        $context = $this->context($storeData);
        $index = new RouteIndex(self::PANEL_PREFIX, [], $this->permissionChecker());
        $engine = new SearchEngine($index, $context);
        $pageRoutes = $this->pageRoutes();

        return $engine->finish(
            $keyword,
            $this->allowedShortcuts(VendorSearchShortcuts::all($keyword->raw(), $context->moduleType)),
            $engine->pages($keyword, $pageRoutes, $this->pageVisibility($context), $this->allowedPageUris($pageRoutes)),
            $engine->records($keyword, VendorSearchRegistry::all($storeData, $this->userType())),
        );
    }

    private function context(object $storeData): SearchContext
    {
        return new SearchContext(
            panelPrefix: self::PANEL_PREFIX,
            moduleType: $storeData->module_type,
            moduleId: $storeData->module_id,
            storeId: $storeData->id,
            vendorId: $storeData->vendor_id,
        );
    }

    private function userType(): string
    {
        return auth('vendor')->check() ? 'vendor' : 'vendor-employee';
    }

    private function pageRoutes(): array
    {
        $path = public_path('vendor_formatted_routes.json');

        if (! file_exists($path)) {
            return [];
        }

        $routes = json_decode(file_get_contents($path), true) ?: [];

        if (! addon_published_status('Rental')) {
            $routes = array_filter($routes, function ($route) {
                return ($route['moduleType'] ?? null) !== 'rental';
            });
        }

        if (! Helpers::check_website_builder_status()) {
            $routes = array_filter($routes, function ($route) {
                return ! str_starts_with($route['URI'] ?? '', 'vendor-panel/builder');
            });
        }

        return array_values($routes);
    }

    private function permissionChecker(): \Closure
    {
        $cache = [];

        return function (string $permission) use (&$cache) {
            return $cache[$permission] ??= (Helpers::employee_module_permission_check($permission) === true);
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
        return $this->allowedUriMap(array_column($pageRoutes, 'URI'));
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
            $type = $route['moduleType'] ?? null;

            return $type === null || $type === $context->moduleType;
        };
    }

    public function storeClickedRoute(Request $request): JsonResponse
    {
        $userId = Helpers::get_vendor_id();
        $userType = $this->userType();
        $routeUri = $request->input('routeUri');
        $searchKeyword = $request->input('searchKeyword');

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
            $clicked->module_id = Helpers::get_store_data()->module_id;
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
        $recentSearches = RecentSearch::where('user_id', Helpers::get_vendor_id())
            ->where('user_type', $this->userType())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($recentSearches);
    }
}
