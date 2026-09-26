<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\BusinessSetting;
use App\Models\ParcelCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Parcel-module category lookup. Read-only — suggestion data only,
 * no actions (no booking, no cart). Registered only when the
 * conversation's moduleType is 'parcel'.
 *
 * Returns text including per-category shipping rates (per-km and
 * minimum), falling back to the platform-wide BusinessSetting when a
 * category does not override them. Lets the LLM answer pricing and
 * suggestion questions without inventing numbers.
 */
class GetParcelCategoriesTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
    ) {}

    public function description(): string
    {
        return 'Get parcel categories the platform supports with their per-km and minimum shipping charges (falls back to platform default when not category-specific). Use this when the user asks what types of parcels they can ship, what categories exist, or wants pricing context for a parcel. Read-only — suggestions only, you cannot create bookings.';
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

        $categories = ParcelCategory::active()
            ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
            ->orderBy('id')
            ->limit($limit)
            ->get([
                'id', 'name', 'description',
                'parcel_per_km_shipping_charge', 'parcel_minimum_shipping_charge',
            ]);

        $this->context->recordTool('GetParcelCategoriesTool');

        if ($categories->isEmpty()) {
            return 'No parcel categories available.';
        }

        $defaults = BusinessSetting::whereIn('key', [
            'parcel_per_km_shipping_charge',
            'parcel_minimum_shipping_charge',
        ])->pluck('value', 'key');

        $defaultPerKm = (float) ($defaults['parcel_per_km_shipping_charge'] ?? 0);
        $defaultMin   = (float) ($defaults['parcel_minimum_shipping_charge'] ?? 0);

        $lines = $categories->map(function (ParcelCategory $c) use ($defaultPerKm, $defaultMin): string {
            $perKm = (float) ($c->getAttribute('parcel_per_km_shipping_charge') ?? 0);
            $min   = (float) ($c->getAttribute('parcel_minimum_shipping_charge') ?? 0);

            $rate = $min > 0 || $perKm > 0
                ? ($perKm ?: $defaultPerKm) . '/km, min ' . ($min ?: $defaultMin)
                : ($defaultPerKm . '/km, min ' . $defaultMin . ' (platform default)');

            $parts = [$c->getAttribute('name'), $rate];

            $desc = trim((string) $c->getAttribute('description'));
            if ($desc !== '') {
                $parts[] = mb_substr($desc, 0, 80);
            }

            return implode(' — ', $parts);
        })->all();

        return count($lines) . ' parcel categories: ' . implode(' | ', $lines);
    }
}
