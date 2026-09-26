<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Models\StoreCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

class StoreCategoryService
{
    private const IMAGE_DIR = 'category/';

    public function list(array $filters = [], int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->latest()
            ->paginate($perPage, ['id', 'store_id', 'name', 'image', 'priority', 'status', 'created_at'], 'page', $page);
    }

    public function buildQuery(array $filters = []): Builder
    {
        return StoreCategory::query()
            ->withCount([$this->resolveCountRelation($filters['store_id'] ?? null) . ' as items_count'])
            ->when(isset($filters['store_id']) && $filters['store_id'] !== '', function ($q) use ($filters) {
                $q->where('store_id', $filters['store_id']);
            })
            ->when(isset($filters['module_id']) && $filters['module_id'] !== '' && $filters['module_id'] !== null, function ($q) use ($filters) {
                $q->where('module_id', $filters['module_id']);
            })
            ->when(isset($filters['priority']) && $filters['priority'] !== '' && $filters['priority'] !== null, function ($q) use ($filters) {
                $q->where('priority', $filters['priority']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null, function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->search(
                keywords: $filters['search'] ?? null,
                relations: ['translations' => 'value'],
                mainCol: ['name', 'id']
            );
    }

    private function resolveCountRelation($storeId): string
    {
        if (!empty($storeId) && addon_published_status('Service')) {
            // Resolve module_type through the module relation (accessor) rather than a direct
            // `stores.module_type` column select — standalone Service deployments have no such
            // column, so ->value('module_type') would throw "Unknown column 'module_type'".
            $moduleType = Store::with('module:id,module_type')->find($storeId)?->module_type;
            if ($moduleType === 'service') {
                return 'services';
            }
        }

        return 'items';
    }

    public function create(int $storeId, string $name, ?int $priority = 0, ?UploadedFile $image = null): StoreCategory
    {
        $category = new StoreCategory();
        $category->store_id = $storeId;
        $category->module_id = $this->resolveModuleId($storeId);
        $category->name = $name;
        $category->priority = $priority ?? 0;
        $category->status = 1;
        $category->image = $image ? Helpers::upload(self::IMAGE_DIR, 'png', $image) : null;
        $category->save();

        return $category;
    }

    public function update(StoreCategory $category, ?string $name = null, ?int $priority = null, ?UploadedFile $image = null, ?int $storeId = null): StoreCategory
    {
        if ($storeId !== null) {
            $category->store_id = $storeId;
            $category->module_id = $this->resolveModuleId($storeId);
        }
        if ($name !== null) {
            $category->name = $name;
        }
        if ($priority !== null) {
            $category->priority = $priority;
        }
        if ($image) {
            $category->image = Helpers::update(self::IMAGE_DIR, $category->image, 'png', $image);
        }
        $category->save();

        return $category;
    }

    public function resolveModuleId(int $storeId): ?int
    {
        return Store::where('id', $storeId)->value('module_id');
    }

    public function updateStatus(StoreCategory $category, int $status): StoreCategory
    {
        $category->status = $status;
        $category->save();
        return $category;
    }

    public function updatePriority(StoreCategory $category, int $priority): StoreCategory
    {
        $category->priority = $priority;
        $category->save();
        return $category;
    }

    public function delete(StoreCategory $category): bool
    {
        if ($category->image) {
            Helpers::check_and_delete(self::IMAGE_DIR, $category->image);
        }
        $category->translations()->delete();
        return (bool) $category->delete();
    }

    public function saveFormTranslations(StoreCategory $category, Request $request): void
    {
        Helpers::add_or_update_translations(
            request: $request,
            key_data: 'name',
            name_field: 'name',
            model_name: 'StoreCategory',
            data_id: $category->id,
            data_value: $category->name
        );
    }

    public function saveApiTranslations(StoreCategory $category, array $translations): void
    {
        foreach ($translations as $t) {
            $locale = $t['locale'] ?? null;
            $value = $t['value'] ?? null;
            if (!$locale || $locale === 'default' || empty($value)) {
                continue;
            }
            \App\Models\Translation::updateOrInsert(
                ['translationable_type' => StoreCategory::class, 'translationable_id' => $category->id, 'locale' => $locale, 'key' => 'name'],
                ['value' => $value]
            );
        }
    }
}
