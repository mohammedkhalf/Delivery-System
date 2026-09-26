<?php

namespace Modules\AI\app\Agents\Tools;

use App\Models\Category;
use Modules\AI\app\Agents\AiResponseContext;
use Modules\Service\Entities\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Service-module category lookup. Read-only suggestion data — no booking.
 * Registered only when the conversation's moduleType is 'service'.
 *
 * Returns category names with active service counts so the LLM can answer
 * "what service categories do you have", "show cleaning services", "which
 * type is most popular" with real data.
 */
class GetServiceCategoriesTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
    ) {}

    public function description(): string
    {
        return 'Get the service platform\'s service CATEGORIES with the number of active services in each (e.g. Cleaning — 12 services). Use this when the user asks "what categories", "service types", "show categories", or wants to browse by category. Read-only.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->number()->description('Number of categories to return, default 8, max 10')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args  = $request->all();
        $limit = min((int) ($args['limit'] ?? 8), 10);

        $serviceCounts = Service::where('status', 1)
            ->where('is_approved', 1)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as service_count')
            ->groupBy('category_id')
            ->pluck('service_count', 'category_id')
            ->all();

        $this->context->recordTool('GetServiceCategoriesTool');

        if (empty($serviceCounts)) {
            return 'No service categories available.';
        }

        $categories = Category::whereIn('id', array_keys($serviceCounts))
            ->where('status', 1)
            ->get(['id', 'name'])
            ->map(function ($c) use ($serviceCounts) {
                $c->service_count = (int) ($serviceCounts[$c->id] ?? 0);
                return $c;
            })
            ->sortByDesc('service_count')
            ->take($limit)
            ->values();

        if ($categories->isEmpty()) {
            return 'No service categories available.';
        }

        $lines = $categories->map(function ($c): string {
            $count = (int) $c->service_count;
            return $count > 0
                ? $c->name . ' (' . $count . ' services)'
                : $c->name;
        })->all();

        return count($lines) . ' service categories: ' . implode(', ', $lines);
    }
}
