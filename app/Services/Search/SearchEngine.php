<?php

namespace App\Services\Search;

use Closure;
use Illuminate\Support\Str;

class SearchEngine
{
    private const SEARCH_PARAM_TYPES = ['subscriber-transactions', 'order', 'trip', 'Vehicle_Review'];

    private int $resultLimit;

    private int $perEntityLimit;

    public function __construct(
        private readonly RouteIndex $index,
        private readonly SearchContext $context,
    ) {
        $this->resultLimit = (int) config('search.result_limit', 50);
        $this->perEntityLimit = (int) config('search.per_entity_limit', 10);
    }

    public function pages(SearchKeyword $keyword, array $routes, Closure $isVisible, ?array $allowedUris = null): array
    {
        $rows = [];

        foreach ($routes as $route) {
            $uri = $route['URI'] ?? '';

            if ($uri === '' || ($allowedUris !== null && ! isset($allowedUris[$uri]))) {
                continue;
            }

            if (preg_match('/\{(.*?)\}/', $uri)) {
                continue;
            }

            if (! $isVisible($route)) {
                continue;
            }

            if (! $keyword->matches($route['routeName'] ?? '', $uri, $route['keywords'] ?? '')) {
                continue;
            }

            $rows[] = [
                'routeName' => ucwords($this->tidy($route['routeName'] ?? '')),
                'URI' => $uri,
                'fullRoute' => url($uri),
                'currentModuleType' => $this->context->moduleType,
                'data_from' => 'files',
                // Carried only for ranking (stripped in rank()) so a page that matched via its
                // keywords isn't dumped to the bottom below unrelated title-only matches.
                'keywords' => $route['keywords'] ?? '',
            ];
        }

        return $rows;
    }

    public function records(SearchKeyword $keyword, array $entities): array
    {
        $rows = [];

        foreach ($entities as $entity) {
            if (! $entity->isEnabled($this->context)) {
                continue;
            }

            if (! $entity->hasVariants() && $entity->matchingRoutes($this->index) === []) {
                continue;
            }

            foreach ($this->resolve($entity, $keyword) as $model) {
                foreach ($entity->matchingRoutes($this->index, $entity->variantFor($model)) as $route) {
                    $rows[] = $this->buildRow($entity, $model, $route);
                }
            }
        }

        return $rows;
    }

    public function finish(SearchKeyword $keyword, array ...$groups): array
    {
        $merged = array_merge(...$groups);

        $unique = [];
        foreach ($merged as $row) {
            $unique[$row['fullRoute']] ??= $row;
        }

        $ranked = $keyword->rank(array_values($unique), $this->resultLimit);

        return array_map(function (array $row) {
            unset($row['keywords']);

            return $row;
        }, $ranked);
    }

    private function resolve(SearchEntity $entity, SearchKeyword $keyword)
    {
        if ($keyword->isId()) {
            $rows = [];

            if ($entity->supportsIdSearch()) {
                $query = $entity->newQuery($this->context);
                $entity->applyIdFilter($query, $keyword->raw());
                $rows = $query->limit($entity->hasCustomIdFilter() ? $this->perEntityLimit : 1)->get();
            }

            if (count($rows) > 0 || ! $keyword->mayBeContactNumber() || ! $entity->hasTextColumns()) {
                return $rows;
            }
        }

        if (! $entity->hasTextColumns()) {
            return [];
        }

        $query = $entity->newQuery($this->context);
        $entity->applyTextFilter($query, $keyword->likeValue());

        return $query->limit($this->perEntityLimit)->get();
    }

    private function buildRow(SearchEntity $entity, $model, array $route): array
    {
        $uri = $route['uri'];
        $formatted = ucwords(str_replace(['.', '_'], ' ', Str::afterLast($route['name'], '.')));

        $resolvedUri = $this->fillParameters($uri, $entity->routeKeyFor($model));

        $param = $entity->searchParamFor($model);
        if ($param !== null && (! is_numeric($param) || in_array($entity->typeTag(), self::SEARCH_PARAM_TYPES, true))) {
            $resolvedUri .= '?search=' . urlencode($param);
        }

        $resolved = [
            'uri' => $resolvedUri,
            'url' => url('/') . '/' . $resolvedUri,
            'routeName' => $formatted,
        ];

        $override = $entity->overrideUrl($model, $resolved);
        if ($override !== null) {
            $resolved = array_merge($resolved, $override);
        }

        return [
            'routeName' => $this->composeLabel($entity->prefixLabel(), $formatted, $entity->labelFor($model)),
            'URI' => $resolved['uri'],
            'fullRoute' => $resolved['url'],
            'module_type' => $entity->moduleTypeValue(),
            'data_from' => 'database',
        ];
    }

    private function fillParameters(string $uri, string $key): string
    {
        preg_match_all('/\{(\w+\??)\}/', $uri, $matches);

        if (! empty($matches[1])) {
            $uri = str_replace('{' . $matches[1][0] . '}', $key, $uri);
        }

        $uri = preg_replace('/\{\w+\?\}/', '', $uri);
        $uri = preg_replace('/\/+/', '/', $uri);

        return rtrim($uri, '/');
    }

    private function composeLabel(string $prefix, string $formatted, string $name): string
    {
        $label = $prefix !== '' ? $prefix . ' ' . $formatted : $formatted;

        if ($name !== '') {
            $label .= ' - (' . $name . ')';
        }

        return $this->tidy($label);
    }

    private function tidy(string $value): string
    {
        $value = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
