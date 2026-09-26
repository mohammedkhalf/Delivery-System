<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Exports\StoreCategoryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryAddRequest;
use App\Http\Requests\StoreCategoryUpdateRequest;
use App\Models\Item;
use App\Models\StoreCategory;
use App\Services\StoreCategoryService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreCategoryController extends Controller
{
    public function __construct(protected StoreCategoryService $service)
    {
        $this->middleware(function ($request, $next) {
            if (!Helpers::vendorCategoryStatus()) {
                Toastr::warning(translate('messages.Store_category_feature_is_disabled'));
                return back();
            }
            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $categories = $this->service->buildQuery([
            'search' => $request['search'] ?? null,
            'priority' => $request->query('priority'),
            'store_id' => Helpers::get_store_id(),
        ])
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->all());

        $language = getWebConfig('language');

        return view('vendor-views.store-category.index', compact('categories', 'language'));
    }

    public function create(): JsonResponse
    {
        $language = getWebConfig('language');
        $category = null;

        return response()->json([
            'view' => view('vendor-views.store-category._form', compact('category', 'language'))->render(),
        ]);
    }

    public function store(StoreCategoryAddRequest $request): RedirectResponse
    {
        $category = $this->service->create(
            storeId: Helpers::get_store_id(),
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveFormTranslations($category, $request);

        Toastr::success(translate('messages.Store_category_added_successfully'));

        // Chain into the "Select Items For Category" off-canvas (assign flow).
        return redirect()->route('vendor.store-category.list', ['assign_items' => $category->id]);
    }

    public function getUpdateView(string|int $id): JsonResponse
    {
        $category = $this->ownedQuery()
            ->withoutGlobalScope('translate')
            ->with('translations')
            ->findOrFail($id);
        $language = getWebConfig('language');

        return response()->json([
            'view' => view('vendor-views.store-category._form', compact('category', 'language'))->render(),
        ]);
    }

    public function update(StoreCategoryUpdateRequest $request, string|int $id): RedirectResponse
    {
        $category = $this->ownedQuery()->findOrFail($id);
        $this->service->update(
            category: $category,
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveFormTranslations($category, $request);

        Toastr::success(translate('messages.Store_category_updated_successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->service->updateStatus($this->ownedQuery()->findOrFail($request['id']), (int) $request['status']);
        Toastr::success(translate('messages.Store_category_status_updated'));
        return back();
    }

    public function updatePriority(Request $request, $id): RedirectResponse
    {
        $this->service->updatePriority($this->ownedQuery()->findOrFail($id), (int) ($request->priority ?? 0));
        Toastr::success(translate('messages.Store_category_priority_updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->service->delete($this->ownedQuery()->findOrFail($request['id']));
        Toastr::success(translate('messages.Store_category_removed_successfully'));
        return back();
    }

    public function getAll(Request $request): JsonResponse
    {
        $categories = $this->ownedQuery()
            ->where('status', 1)
            ->when($request->q, function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%');
            })
            ->orderBy('priority', 'desc')
            ->limit(20)
            ->get(['id', 'name as text']);

        return response()->json($categories);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $categories = $this->service->buildQuery([
            'search' => $request->query('search'),
            'priority' => $request->query('priority'),
            'store_id' => Helpers::get_store_id(),
        ])->latest()->get();

        $data = [
            'data' => $categories,
            'search' => $request->query('search'),
            'categoryWiseTax' => false,
        ];

        $extension = $request->query('type') === 'csv' ? 'csv' : 'xlsx';
        return Excel::download(new StoreCategoryExport($data), 'StoreCategories.' . $extension);
    }

    private function ownedQuery()
    {
        return StoreCategory::where('store_id', Helpers::get_store_id());
    }

    /**
     * Render the "Select Items For Category" offcanvas.
     * Lists items that are either uncategorized OR already assigned to this category,
     * scoped to the logged-in vendor's store.
     */
    public function assignItemsView(string|int $id, Request $request): JsonResponse
    {
        $storeId = Helpers::get_store_id();
        $category = $this->ownedQuery()->findOrFail($id);

        $items = $this->queryAssignableItems((int) $category->id, $request->input('search'))->get();
        $unassignedCount = $this->queryAssignableItems((int) $category->id)
            ->whereNull('store_category_id')
            ->count();
        $isService = $this->isServiceModule();

        return response()->json([
            'view' => view('vendor-views.store-category._assign_items', compact(
                'category',
                'items',
                'unassignedCount',
                'isService'
            ))->render(),
        ]);
    }

    /**
     * AJAX search endpoint — returns just the item list partial.
     */
    public function searchAssignableItems(string|int $id, Request $request): JsonResponse
    {
        $category = $this->ownedQuery()->findOrFail($id);
        $items = $this->queryAssignableItems((int) $category->id, $request->input('search'))->get();

        return response()->json([
            'view' => view('vendor-views.store-category._assign_items_list', [
                'category' => $category,
                'items' => $items,
                'isService' => $this->isServiceModule(),
            ])->render(),
        ]);
    }

    /**
     * Persist the item → store_category_id assignment.
     * Items explicitly checked get this category id; previously-assigned items not
     * present in the submitted list get cleared back to NULL.
     */
    public function storeAssignedItems(string|int $id, Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $storeId = Helpers::get_store_id();
        $category = $this->ownedQuery()->findOrFail($id);

        $submittedIds = collect($request->input('item_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $model = $this->bindableModel();

        // Only allow assigning records that belong to this vendor's store AND are
        // either uncategorized or already in this category.
        $allowedNewIds = [];
        if (!empty($submittedIds)) {
            $allowedNewIds = $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $submittedIds)
                ->where(function ($q) use ($category) {
                    $q->whereNull('store_category_id')
                      ->orWhere('store_category_id', $category->id);
                })
                ->pluck('id')
                ->all();
        }

        if (!empty($allowedNewIds)) {
            $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $allowedNewIds)
                ->update(['store_category_id' => $category->id]);
        }

        // Un-assign records previously in this category but unchecked.
        $model::query()
            ->where('store_id', $storeId)
            ->where('store_category_id', $category->id)
            ->when(!empty($allowedNewIds), fn ($q) => $q->whereNotIn('id', $allowedNewIds))
            ->update(['store_category_id' => null]);

        return response()->json([
            'success' => true,
            'message' => $this->isServiceModule()
                ? translate('messages.Services_assigned_successfully')
                : translate('messages.Items_assigned_successfully'),
            'assigned_count' => count($allowedNewIds),
        ]);
    }

    /**
     * The vendor's store-category feature binds the store's sellable records. For a Service-module
     * store that's a Service; for every other module it's an Item. Both tables carry `store_id` +
     * `store_category_id`, so the assign flow is identical apart from the model.
     */
    private function isServiceModule(): bool
    {
        return Helpers::get_store_data()?->module_type === 'service' && addon_published_status('Service');
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function bindableModel(): string
    {
        return $this->isServiceModule() ? \Modules\Service\Entities\Service::class : Item::class;
    }

    /**
     * Reusable builder for the records (items, or services on a service store) shown in the
     * assign offcanvas — uncategorized OR already in this category, scoped to the vendor's store.
     */
    private function queryAssignableItems(int $categoryId, ?string $search = null)
    {
        $storeId = Helpers::get_store_id();
        $model = $this->bindableModel();

        return $model::query()
            ->where('store_id', $storeId)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('store_category_id')
                  ->orWhere('store_category_id', $categoryId);
            })
            ->when($search, function ($q) use ($search) {
                $term = '%' . trim($search) . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                          ->orWhere('id', 'like', $term);
                });
            })
            ->orderByDesc('id');
    }
}
