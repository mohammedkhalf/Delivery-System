<?php

namespace App\Http\Controllers\Admin\Item;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Category;
use App\Enums\ViewPaths\Admin\Category as CategoryViewPath;
use App\Exports\CategoryExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CategoryAddRequest;
use App\Http\Requests\Admin\CategoryBulkExportRequest;
use App\Http\Requests\Admin\CategoryBulkImportRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Services\CategoryService;
use App\Traits\ImportExportTrait;
use Brian2694\Toastr\Facades\Toastr;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\TaxModule\Entities\SystemTaxSetup;
use Modules\TaxModule\Entities\Taxable;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends BaseController
{
    use ImportExportTrait;

    public function __construct(
        protected CategoryRepositoryInterface $categoryRepo,
        protected CategoryService $categoryService,
        protected TranslationRepositoryInterface $translationRepo
    ) {}

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getCategoryView($request);
    }

    /**
     * Category-wise tax follows the active workspace module: service categories are taxed under the
     * `service_provider` setup (the one the service cart/booking compute against), while every other
     * module keeps the default `vendor` setup. Keeps the form input and the saved Taxable rows on the
     * same setup, so the tax the admin picks is the tax that actually gets charged.
     */
    private function categoryTaxPayer(): string
    {
        return Config::get('module.current_module_type') === 'service' ? 'service_provider' : 'vendor';
    }

    private function categoryTaxSetup()
    {
        return SystemTaxSetup::where('is_active', 1)
            ->where('tax_payer', $this->categoryTaxPayer())
            ->where('is_default', 1)
            ->first();
    }

    private function getCategoryView(Request $request): View
    {
        // All / Active / Inactive list tabs. `status` rides through the repository's exact-match
        // `filters` (status column: 1 = active, 0 = inactive); 'all' leaves it unfiltered.
        $status = $request->query('status', 'all');
        $position = $request['position'] ?? 0;
        $filters = ['position' => $position];
        if ($status === 'active') {
            $filters['status'] = 1;
        } elseif ($status === 'inactive') {
            $filters['status'] = 0;
        }

        $categories = $this->categoryRepo->getListWhere(
            searchValue: $request['search'],
            filters: $filters,
            relations: ['module'],
            dataLimit: config('default_pagination')
        );

        // The status tabs (and search/pagination) fetch this list via AJAX so switching them never
        // reloads the "Add New" card above — that card carries its own unsaved language-tab state
        // (Default/EN/AR) and typed input, which a full page reload would otherwise wipe out.
        if ($request->ajax()) {
            $categoryWiseTax = $position == 0
                ? Helpers::getTaxSystemType(tax_payer: $this->categoryTaxPayer())['categoryWiseTax']
                : null;

            return view($position == 1
                ? 'admin-views.category.partials._list-sub'
                : 'admin-views.category.partials._list-main',
                compact('categories', 'status', 'categoryWiseTax'));
        }

        $mainCategories = $this->categoryRepo->getMainList(
            filters: ['position' => 0],
            relations: ['module'],
        );

        $language = getWebConfig('language');
        $taxData = Helpers::getTaxSystemType(tax_payer: $this->categoryTaxPayer());
        $categoryWiseTax = $taxData['categoryWiseTax'];
        $taxVats = $taxData['taxVats'];

        return view($this->categoryService->getViewByPosition($position), compact('categories', 'language', 'mainCategories', 'categoryWiseTax', 'taxVats', 'status'));
    }

    public function add(CategoryAddRequest $request): RedirectResponse
    {
        $parentCategory = $this->categoryRepo->getFirstWhere(params: ['id' => $request['parent_id']]);
        $category = $this->categoryRepo->add(
            data: $this->categoryService->getAddData(
                request: $request,
                parentCategory: $parentCategory
            )
        );
        $this->translationRepo->addByModel(request: $request, model: $category, modelPath: 'App\Models\Category', attribute: 'name');

        if (addon_published_status('TaxModule')) {
            $SystemTaxVat = $this->categoryTaxSetup();
            if ($SystemTaxVat?->tax_type == 'category_wise') {

                foreach ($request['tax_ids'] ?? [] as $tax_ids) {
                    Taxable::create(
                        [
                            'taxable_type' => 'App\Models\Category',
                            'taxable_id' => $category->id,
                            'system_tax_setup_id' => $SystemTaxVat->id, 'tax_id' => $tax_ids,
                        ],
                    );
                }

            }
        }

        Toastr::success($request['position'] == 0 ? translate('messages.category_added_successfully') : translate('messages.Sub_category_added_successfully'));

        return back();
    }

    public function getUpdateView(string|int $id): JsonResponse
    {
        $category = $this->categoryRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');

        $taxData = Helpers::getTaxSystemType(tax_payer: $this->categoryTaxPayer());
        $categoryWiseTax = $taxData['categoryWiseTax'];
        $taxVats = $taxData['taxVats'];
        $taxVatIds = $categoryWiseTax ? $category->taxVats()->pluck('tax_id')->toArray() : [];
        $mainCategories = $this->categoryRepo->getMainList(
            filters: ['position' => 0],
            relations: ['module'],
        );

        return response()->json([
            'view' => view('admin-views.category._edit', compact('mainCategories', 'category', 'taxVats', 'categoryWiseTax', 'language', 'taxVatIds'))->render(),
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->categoryRepo->update(id: $request['id'], data: ['status' => $request['status']]);
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['id']]);
        Toastr::success(translate($category && $category->position == 1 ? 'messages.sub_category_status_updated' : 'messages.category_status_updated'));

        return back();
    }

    public function updateFeatured(Request $request): RedirectResponse
    {
        $this->categoryRepo->update(id: $request['id'], data: ['featured' => $request['featured']]);
        Toastr::success(translate('messages.category_featured_updated'));

        return back();
    }

    public function update(CategoryUpdateRequest $request, string|int $id): RedirectResponse
    {
        $mainCategory = $this->categoryRepo->getFirstWhere(params: ['id' => $id]);
        $category = $this->categoryRepo->update(id: $id, data: $this->categoryService->getUpdateData(request: $request, object: $mainCategory));
        $this->translationRepo->updateByModel(request: $request, model: $category, modelPath: 'App\Models\Category', attribute: 'name');

        if (addon_published_status('TaxModule') && $category['position'] == 0) {
            $taxVatIds = $category->taxVats()->pluck('tax_id')->toArray() ?? [];
            $newTaxVatIds = array_map('intval', $request['tax_ids'] ?? []);
            sort($newTaxVatIds);
            sort($taxVatIds);
            if ($newTaxVatIds != $taxVatIds) {
                $category->taxVats()->delete();
                $SystemTaxVat = $this->categoryTaxSetup();
                if ($SystemTaxVat?->tax_type == 'category_wise') {
                    foreach ($request['tax_ids'] ?? [] as $tax_ids) {
                        Taxable::create(
                            [
                                'taxable_type' => 'App\Models\Category',
                                'taxable_id' => $category->id,
                                'system_tax_setup_id' => $SystemTaxVat->id, 'tax_id' => $tax_ids,
                            ],
                        );
                    }

                }
            }
        }

        Toastr::success($category['position'] == 0 ? translate('messages.category_updated_successfully') : translate('messages.Sub_category_updated_successfully'));

        return redirect()->route('admin.category.add', ['position' => $mainCategory->position]);
    }

    public function delete(Request $request): RedirectResponse
    {
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['id']]);
        $isSubCategory = $category && $category->position == 1;

        if ($this->categoryRepo->delete(id: $request['id'])) {
            Toastr::success(translate($isSubCategory ? 'messages.sub_category_removed_successfully' : 'messages.category_removed_successfully'));
        } else {
            Toastr::warning(translate('messages.remove_sub_categories_first'));
        }

        return back();
    }

    public function getNameList(Request $request): JsonResponse
    {
        $data = $this->categoryRepo->getNameList(request: $request, dataLimit: 8);
        $data[] = (object) ['id' => 'all', 'text' => translate('messages.all')];

        return response()->json($data);
    }

    public function updatePriority(Request $request): RedirectResponse
    {
        $this->categoryRepo->update(id: $request['category'], data: ['priority' => $request['priority']]);
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['category']]);
        Toastr::success(translate($category && $category->position == 1 ? 'messages.sub_category_priority_updated_successfully' : 'messages.category_priority_updated successfully'));

        return back();
    }

    public function getBulkImportView(): View
    {
        return view(CategoryViewPath::BULK_IMPORT['view']);
    }

    public function importBulkData(CategoryBulkImportRequest $request): RedirectResponse
    {
        $data = $this->categoryService->getImportData(request: $request);

        if (array_key_exists('flag', $data) && $data['flag'] == 'wrong_format') {
            Toastr::error(translate('messages.you_have_uploaded_a_wrong_format_file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'required_fields') {
            Toastr::error(translate('messages.please_fill_all_required_fields'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_position') {
            Toastr::error(translate('messages.invalid_category_position_in_file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_parent') {
            Toastr::error(translate('messages.invalid_parent_category_in_file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'duplicate_name') {
            Toastr::error(translate('messages.duplicate_category_name_in_file'));

            return back();
        }

        try {
            DB::beginTransaction();
            $this->categoryRepo->addByChunk(data: $data);
            DB::commit();
        } catch (Exception) {
            DB::rollBack();
            Toastr::error(translate('messages.failed_to_import_data'));

            return back();
        }

        Toastr::success(translate('messages.category_imported_successfully', ['count' => count($data)]));

        return back();
    }

    public function updateBulkData(CategoryBulkImportRequest $request): RedirectResponse
    {
        $data = $this->categoryService->getImportData(request: $request, toAdd: false);

        if (array_key_exists('flag', $data) && $data['flag'] == 'wrong_format') {
            Toastr::error(translate('messages.you_have_uploaded_a_wrong_format_file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'required_fields') {
            Toastr::error(translate('messages.please_fill_all_required_fields'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_position') {
            Toastr::error(translate('messages.invalid_category_position_in_file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_parent') {
            Toastr::error(translate('messages.invalid_parent_category_in_file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'duplicate_name') {
            Toastr::error(translate('messages.duplicate_category_name_in_file'));

            return back();
        }

        try {
            DB::beginTransaction();
            $this->categoryRepo->updateByChunk(data: $data);
            DB::commit();
        } catch (Exception) {
            DB::rollBack();
            Toastr::error(translate('messages.failed_to_import_data'));

            return back();
        }

        Toastr::success(translate('messages.category_updated_successfully', ['count' => count($data)]));

        return back();
    }

    public function getBulkExportView(): View
    {
        return view(CategoryViewPath::BULK_EXPORT['view']);
    }

    /**
     * @throws IOException
     * @throws WriterNotOpenedException
     * @throws UnsupportedTypeException
     * @throws InvalidArgumentException
     */
    public function exportBulkData(CategoryBulkExportRequest $request): StreamedResponse|string
    {
        $categories = $this->categoryRepo->getBulkExportList(request: $request);

        return (new FastExcel($this->categoryService->getExportData(collection: $this->exportGenerator(data: $categories))))->download(Category::EXPORT_XLSX);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $categories = $this->categoryRepo->getExportList(request: $request);
        $isSubCategory = (int) ($request['position'] ?? 0) === 1;

        $taxData = Helpers::getTaxSystemType();
        $categoryWiseTax = $taxData['categoryWiseTax'];

        $data = [
            'data' => $categories,
            'search' => $request['search'] ?? null,
            'categoryWiseTax' => $categoryWiseTax,
            'module' => Config::get('module.current_module_type'),
            'isSubCategory' => $isSubCategory,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new CategoryExport($data), $isSubCategory ? Category::SUB_EXPORT_CSV : Category::EXPORT_CSV);
        }

        return Excel::download(new CategoryExport($data), $isSubCategory ? Category::SUB_EXPORT_XLSX : Category::EXPORT_XLSX);
    }
}
