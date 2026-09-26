<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\Rental\Entities\VehicleCategory;
use Modules\Rental\Entities\Vehicle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Rental-module vehicle-category lookup. Read-only suggestion data — no
 * booking. Registered only when the conversation's moduleType is 'rental'.
 *
 * Returns category names with active vehicle counts so the LLM can answer
 * "what vehicle categories do you have", "show sedans", "which type is
 * most popular" with real data.
 */
class GetRentalCategoriesTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
    ) {}

    public function description(): string
    {
        return 'Get the rental platform\'s vehicle CATEGORIES with the number of active vehicles in each (e.g. Sedan — 12 vehicles). Use this when the user asks "what categories", "vehicle types", "show categories", or wants to browse by category. Read-only.';
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

        $vehicleCounts = Vehicle::where('status', 1)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as vehicle_count, SUM(total_trip) as total_trips')
            ->groupBy('category_id')
            ->pluck('vehicle_count', 'category_id')
            ->all();

        $categories = VehicleCategory::ofStatus(1)
            ->limit(50)
            ->get(['id', 'name'])
            ->map(function ($c) use ($vehicleCounts) {
                $c->vehicle_count = (int) ($vehicleCounts[$c->id] ?? 0);
                return $c;
            })
            ->sortByDesc('vehicle_count')
            ->take($limit)
            ->values();

        $this->context->recordTool('GetRentalCategoriesTool');

        if ($categories->isEmpty()) {
            return 'No vehicle categories available.';
        }

        $lines = $categories->map(function ($c): string {
            $count = (int) $c->vehicle_count;
            return $count > 0
                ? $c->name . ' (' . $count . ' vehicles)'
                : $c->name;
        })->all();

        return count($lines) . ' vehicle categories: ' . implode(', ', $lines);
    }
}
