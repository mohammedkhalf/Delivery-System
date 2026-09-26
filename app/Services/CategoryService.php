<?php

namespace App\Services;

use App\Enums\ViewPaths\Admin\Category as CategoryViewPath;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Models\Category;
use App\Traits\FileManagerTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class CategoryService
{
    use FileManagerTrait;

    public function getViewByPosition(int $position): string
    {
        return match ($position) {
            1 => CategoryViewPath::SUB_CATEGORY_INDEX['view'],
            default => CategoryViewPath::INDEX['view'],
        };
    }

    public function getAddData($request, string|null|Object $parentCategory): array
    {
        return [
            'name' => $request->name[array_search('default', $request->lang)],
            'image' => $this->upload('category/', 'png', $request->file('image')),
            'parent_id' => $request->parent_id == null ? 0 : $request->parent_id,
            'position' => $request->position,
            'priority' => $request->priority??0,
            'module_id' => isset($request->parent_id) ? $parentCategory['module_id'] : Config::get('module.current_module_id')
        ];
    }

    public function getUpdateData(CategoryUpdateRequest $request, object $object): array
    {

        $slug = Str::slug($request->name[array_search('default', $request->lang)]);
        return [
            'slug' => $object->slug ?? "{$slug}{$object->id}",
            'name' => $request->name[array_search('default', $request->lang)],
            'priority' => $request->priority??0,
            'status' => $request->status ?? 0,
            'parent_id' =>$request->parent_id ?? 0,
            'image' => $request->has('image') ? $this->updateAndUpload('category/', $object->image, 'png', $request->file('image')) : $object->image,
        ];
    }

    public function getImportData(Request $request, bool $toAdd = true): array
    {
        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (Exception) {
            return ['flag' => 'wrong_format'];
        }
        $moduleId = Config::get('module.current_module_id');

        // The uploaded file must be the category template. If a required column is missing
        // (e.g. a services bulk template was uploaded by mistake), fail cleanly with the
        // "wrong format" flag instead of throwing "Undefined array key".
        $requiredColumns = ['Name', 'Image', 'ParentId', 'Position', 'Priority', 'Status'];
        if (! $toAdd) {
            $requiredColumns[] = 'Id';
        }
        $firstRow = $collections->first();
        if ($firstRow !== null) {
            foreach ($requiredColumns as $column) {
                if (! array_key_exists($column, (array) $firstRow)) {
                    return ['flag' => 'wrong_format'];
                }
            }
        }

        $data = [];
        $seenNames = [];
        foreach ($collections as $collection) {
            if ($collection['Name'] === "") {
                return ['flag' => 'required_fields'];
            }

            // Position defines the level: 0 = main category, 1 = sub category. It is required and must be 0 or 1.
            $position = is_numeric($collection['Position']) ? (int) $collection['Position'] : null;
            if (! in_array($position, [0, 1], true)) {
                return ['flag' => 'invalid_position'];
            }

            // ParentId links a sub category to its parent main category. A main category never has a parent.
            $parentId = is_numeric($collection['ParentId']) ? (int) $collection['ParentId'] : 0;
            if ($position === 1) {
                $parentExists = $parentId > 0 && Category::where(['id' => $parentId, 'position' => 0, 'module_id' => $moduleId])->exists();
                if (! $parentExists) {
                    return ['flag' => 'invalid_parent'];
                }
            } else {
                $parentId = 0;
            }

            // Sibling-scoped name uniqueness: unique among mains per module, and among a parent's sub categories.
            $ignoreId = (! $toAdd && is_numeric($collection['Id'])) ? (int) $collection['Id'] : null;
            $nameKey = $parentId . '|' . mb_strtolower(trim($collection['Name']));
            if (in_array($nameKey, $seenNames, true) || Category::isDuplicateName($collection['Name'], $moduleId, $parentId, $ignoreId)) {
                return ['flag' => 'duplicate_name'];
            }
            $seenNames[] = $nameKey;

            $array = [
                'name' => $collection['Name'],
                'image' => $collection['Image'],
                'parent_id' => $parentId,
                'module_id' => $moduleId,
                'position' => $position,
                'priority' => is_numeric($collection['Priority']) ? $collection['Priority'] : 0,
                'status' => $collection['Status'] == 'active' ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now()
            ];

            if(!$toAdd){
                $array['id'] = $collection['Id'];
            }

            $data[] = $array;
        }

        return $data;
    }

    public function getExportData(object $collection): array
    {
        $data = [];
        foreach($collection as $item){
            $data[] = [
                'Id'=>$item->id,
                'Name'=>$item->name,
                'Image'=>$item->image,
                'ParentId'=>$item->parent_id,
                'Position'=>$item->position,
                'Priority'=>$item->priority,
                'Status'=>$item->status == 1 ? 'active' : 'inactive',
            ];
        }
        return $data;
    }
}
