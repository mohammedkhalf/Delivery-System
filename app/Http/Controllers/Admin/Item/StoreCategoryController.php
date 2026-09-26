<?php

namespace App\Http\Controllers\Admin\Item;

use App\CentralLogics\Helpers;
use App\Exports\StoreCategoryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryAddRequest;
use App\Http\Requests\StoreCategoryUpdateRequest;
use App\Models\StoreCategory;
use App\Services\StoreCategoryService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreCategoryController extends Controller
{
    public function __construct(protected StoreCategoryService $service)
    {
        $this->middleware(function ($request, $next) {
            if (!Helpers::storeCategoryStatus()) {
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
            'store_id' => $request->query('store_id'),
            'module_id' => Config::get('module.current_module_id'),
        ])
            ->with('store')
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->all());

        $language = getWebConfig('language');

        return view('admin-views.store-category.index', compact('categories', 'language'));
    }

    public function store(StoreCategoryAddRequest $request): RedirectResponse
    {
        $category = $this->service->create(
            storeId: (int) $request->store_id,
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveFormTranslations($category, $request);

        Toastr::success(translate('messages.Store_category_added_successfully'));
        return back();
    }

    public function getUpdateView(string|int $id): JsonResponse
    {
        $category = StoreCategory::withoutGlobalScope('translate')
            ->with(['translations', 'store'])
            ->findOrFail($id);
        $language = getWebConfig('language');

        return response()->json([
            'view' => view('admin-views.store-category._edit', compact('category', 'language'))->render(),
        ]);
    }

    public function update(StoreCategoryUpdateRequest $request, string|int $id): RedirectResponse
    {
        $category = StoreCategory::findOrFail($id);
        $this->service->update(
            category: $category,
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image'),
            storeId: (int) $request->store_id
        );
        $this->service->saveFormTranslations($category, $request);

        Toastr::success(translate('messages.Store_category_updated_successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->service->updateStatus(StoreCategory::findOrFail($request['id']), (int) $request['status']);
        Toastr::success(translate('messages.Store_category_status_updated'));
        return back();
    }

    public function updatePriority(Request $request, $id): RedirectResponse
    {
        $this->service->updatePriority(StoreCategory::findOrFail($id), (int) ($request->priority ?? 0));
        Toastr::success(translate('messages.Store_category_priority_updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->service->delete(StoreCategory::findOrFail($request['id']));
        Toastr::success(translate('messages.Store_category_removed_successfully'));
        return back();
    }

    public function getByStore(Request $request): JsonResponse
    {
        $storeId = (int) $request->store_id;

        $categories = StoreCategory::active()
            ->where('store_id', $storeId)
            ->orderBy('priority', 'desc')
            ->get(['id', 'name']);

        // Drives the "Store Category *" asterisk + required attribute on the
        // product form. Independent of `status` — any row counts.
        $hasCategories = $storeId
            ? \App\CentralLogics\Helpers::hasAnyStoreCategory($storeId)
            : false;

        // Preserve the original array shape for old callers via top-level
        // `categories`, while also exposing the flag for new callers.
        return response()->json([
            'categories' => $categories,
            'has_categories' => $hasCategories,
        ]);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $categories = $this->service->buildQuery([
            'search' => $request->query('search'),
            'priority' => $request->query('priority'),
            'store_id' => $request->query('store_id'),
            'module_id' => Config::get('module.current_module_id'),
        ])->with('store')->latest()->get();

        $data = [
            'data' => $categories,
            'search' => $request->query('search'),
            'categoryWiseTax' => false,
            'showStore' => true,
        ];

        $extension = $request->query('type') === 'csv' ? 'csv' : 'xlsx';
        return Excel::download(new StoreCategoryExport($data), 'StoreCategories.' . $extension);
    }
}
