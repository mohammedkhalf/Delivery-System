<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCategoriesTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int              $moduleId = null,
    ) {}

    public function description(): string
    {
        return 'Get categories on the platform with their IDs. Pass a keyword to find a specific category (including sub-categories at any depth, e.g. "baby care"). Leave keyword null to list top-level categories. Useful when you need a concrete category_id for SearchProductsTool.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword'       => $schema->string()->description('Optional category-name keyword. Matches sub-categories too. Null = list top-level only.')->required()->nullable(),
            'featured_only' => $schema->boolean()->description('true to return only featured/promoted categories, null for all')->required()->nullable(),
            'limit'         => $schema->number()->description('Number of categories to return, default 10, max 15')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args         = $request->all();
        $limit        = min((int) ($args['limit'] ?? 10), 15);
        $keyword      = isset($args['keyword']) && $args['keyword'] !== null
            ? trim((string) $args['keyword'])
            : null;
        $featuredOnly = ($args['featured_only'] ?? null) !== null ? (bool) $args['featured_only'] : null;

        /** @var \Illuminate\Database\Eloquent\Collection<int, Category> $categories */
        $categories = Category::active()
            ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
            ->when($featuredOnly, fn ($q) => $q->featured())
            // When no keyword is given, restrict to top-level so the listing is
            // human-readable. With a keyword, search the full tree.
            ->when(
                $keyword === null || $keyword === '',
                fn ($q) => $q->where(fn ($w) => $w->whereNull('parent_id')->orWhere('parent_id', 0))
            )
            ->when($keyword !== null && $keyword !== '', function ($q) use ($keyword) {
                $keys = array_filter(array_map('trim', explode(' ', $keyword)));
                $q->where(function ($qq) use ($keys, $keyword) {
                    $qq->where('name', 'like', "%{$keyword}%");
                    foreach ($keys as $kw) {
                        if (mb_strlen($kw) >= 3) {
                            $qq->orWhere('name', 'like', "%{$kw}%");
                        }
                    }
                });
            })
            ->orderBy('priority')
            ->orderBy('position')
            ->limit($limit)
            ->get(['id', 'name', 'image', 'parent_id', 'module_id']);

        $this->context->recordTool('GetCategoriesTool');

        if ($categories->isEmpty()) {
            return $keyword !== null && $keyword !== ''
                ? "No categories matched \"{$keyword}\"."
                : 'No categories found.';
        }

        $categoryList = $categories->map(fn (Category $c) => [
            'id'             => $c->getKey(),
            'name'           => $c->getAttribute('name'),
            'parent_id'      => (int) $c->getAttribute('parent_id'),
            'image'          => $c->getAttribute('image'),
            'image_full_url' => $c->image_full_url,
            'module_id'      => $c->getAttribute('module_id'),
        ])->values()->all();

        $this->context->addCategories($categoryList);

        $lines = implode('; ', array_map(
            fn (array $c) => $c['name'] . ' [ID:' . $c['id'] . ']' . ($c['parent_id'] > 0 ? ' (sub)' : ''),
            $categoryList
        ));

        return count($categoryList) . ' categories: ' . $lines;
    }
}
