<?php

namespace App\Services\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class SearchEntity
{
    private string $model;

    private string $prefix = '';

    private ?string $type = null;

    private ?string $moduleType = null;

    private array $columns = [];

    private array $relations = [];

    private array $fullName = [];

    private ?string $idColumn = null;

    private bool $idSearch = true;

    private bool $moduleScoped = false;

    private ?Closure $query = null;

    private ?Closure $routeFilter = null;

    private ?Closure $nameUsing = null;

    private ?Closure $searchParamUsing = null;

    private ?Closure $keyUsing = null;

    private ?Closure $urlUsing = null;

    private ?Closure $enabledWhen = null;

    private ?Closure $variantUsing = null;

    private ?Closure $idFilter = null;

    private ?Closure $extraText = null;

    private array $with = [];

    private function __construct(public readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function type(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function moduleTypeTag(?string $moduleType): self
    {
        $this->moduleType = $moduleType;

        return $this;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function relation(string $relation, array $columns, bool $concat = false): self
    {
        $this->relations[] = ['name' => $relation, 'columns' => $columns, 'concat' => $concat];

        return $this;
    }

    public function fullName(string $first, string $last): self
    {
        $this->fullName = [$first, $last];

        return $this;
    }

    public function with(array $with): self
    {
        $this->with = $with;

        return $this;
    }

    public function idColumn(string $column): self
    {
        $this->idColumn = $column;

        return $this;
    }

    public function withoutIdSearch(): self
    {
        $this->idSearch = false;

        return $this;
    }

    public function moduleScoped(bool $scoped = true): self
    {
        $this->moduleScoped = $scoped;

        return $this;
    }

    public function query(Closure $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function routes(Closure $routeFilter): self
    {
        $this->routeFilter = $routeFilter;

        return $this;
    }

    public function name(Closure $nameUsing): self
    {
        $this->nameUsing = $nameUsing;

        return $this;
    }

    public function searchParam(Closure $searchParamUsing): self
    {
        $this->searchParamUsing = $searchParamUsing;

        return $this;
    }

    public function routeKey(Closure $keyUsing): self
    {
        $this->keyUsing = $keyUsing;

        return $this;
    }

    public function url(Closure $urlUsing): self
    {
        $this->urlUsing = $urlUsing;

        return $this;
    }

    public function when(Closure $enabledWhen): self
    {
        $this->enabledWhen = $enabledWhen;

        return $this;
    }

    public function isEnabled(SearchContext $context): bool
    {
        if ($this->moduleScoped && (! $context->inModuleWorkspace || ! $context->hasModuleId())) {
            return false;
        }

        return $this->enabledWhen === null || ($this->enabledWhen)($context) === true;
    }

    public function supportsIdSearch(): bool
    {
        return $this->idSearch;
    }

    public function extraText(Closure $extraText): self
    {
        $this->extraText = $extraText;

        return $this;
    }

    public function hasTextColumns(): bool
    {
        return $this->columns !== [] || $this->relations !== [] || $this->fullName !== [] || $this->extraText !== null;
    }

    public function newQuery(SearchContext $context): Builder
    {
        $query = ($this->model)::query();

        if ($this->with !== []) {
            $query->with($this->with);
        }

        if ($this->query !== null) {
            ($this->query)($query, $context);
        }

        return $query;
    }

    public function variant(Closure $variantUsing): self
    {
        $this->variantUsing = $variantUsing;

        return $this;
    }

    public function variantFor($model): string
    {
        return $this->variantUsing !== null ? (string) ($this->variantUsing)($model) : '';
    }

    public function idFilter(Closure $idFilter): self
    {
        $this->idFilter = $idFilter;

        return $this;
    }

    public function hasCustomIdFilter(): bool
    {
        return $this->idFilter !== null;
    }

    public function applyIdFilter(Builder $query, string $id): Builder
    {
        if ($this->idFilter !== null) {
            ($this->idFilter)($query, $id);

            return $query;
        }

        return $query->where($this->idColumn ?? $query->getModel()->getKeyName(), $id);
    }

    public function applyTextFilter(Builder $query, string $like): Builder
    {
        $columns = $this->columns;
        $relations = $this->relations;
        $fullName = $this->fullName;
        $extraText = $this->extraText;

        return $query->where(function (Builder $query) use ($columns, $relations, $fullName, $extraText, $like) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'LIKE', '%' . $like . '%');
            }

            if ($extraText !== null) {
                $extraText($query, $like);
            }

            if ($fullName !== []) {
                self::applyNameConcat($query, $fullName, $like);
            }

            foreach ($relations as $relation) {
                $query->orWhereHas($relation['name'], function (Builder $query) use ($relation, $like) {
                    $query->where(function (Builder $query) use ($relation, $like) {
                        foreach ($relation['columns'] as $column) {
                            $query->orWhere($column, 'LIKE', '%' . $like . '%');
                        }

                        if ($relation['concat'] && count($relation['columns']) >= 2) {
                            self::applyNameConcat($query, array_slice($relation['columns'], 0, 2), $like);
                        }
                    });
                });
            }
        });
    }

    private static function applyNameConcat(Builder $query, array $columns, string $like): void
    {
        [$first, $last] = $columns;
        $value = '%' . $like . '%';

        $query->orWhereRaw("CONCAT({$first}, ' ', {$last}) LIKE ?", [$value])
            ->orWhereRaw("CONCAT({$last}, ' ', {$first}) LIKE ?", [$value])
            ->orWhereRaw("CONCAT({$first}, {$last}) LIKE ?", [$value])
            ->orWhereRaw("CONCAT({$last}, {$first}) LIKE ?", [$value]);
    }

    public function matchingRoutes(RouteIndex $index, string $variant = ''): array
    {
        if ($this->routeFilter === null) {
            return [];
        }

        $filter = $this->routeFilter;

        return $index->where($this->key . ':' . $variant, function ($uri, $name) use ($filter, $variant) {
            return $filter($uri, $name, $variant) === true;
        });
    }

    public function hasVariants(): bool
    {
        return $this->variantUsing !== null;
    }

    public function labelFor($model): string
    {
        return $this->nameUsing !== null ? (string) ($this->nameUsing)($model) : '';
    }

    public function searchParamFor($model): ?string
    {
        if ($this->searchParamUsing === null) {
            return null;
        }

        $value = ($this->searchParamUsing)($model);

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function routeKeyFor($model): string
    {
        return (string) ($this->keyUsing !== null ? ($this->keyUsing)($model) : $model->id);
    }

    public function overrideUrl($model, array $resolved): ?array
    {
        if ($this->urlUsing === null) {
            return null;
        }

        return ($this->urlUsing)($model, $resolved);
    }

    public function prefixLabel(): string
    {
        return $this->prefix;
    }

    public function typeTag(): ?string
    {
        return $this->type;
    }

    public function moduleTypeValue(): ?string
    {
        return $this->moduleType;
    }
}
