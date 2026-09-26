<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Validator;

/**
 * @property int parent_id
 * @property array name
 * @property array lang
 * @property int position
 */
class CategoryAddRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'name.0' => 'required',
            'image' => 'required_if:position,==,0',
        ];
    }

    /**
     * Reject a category whose name duplicates a sibling: main categories must be unique
     * within the module, sub categories unique within their parent.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $defaultName = Category::defaultName($this->name, $this->lang);
            if ($defaultName === null) {
                return;
            }

            $parentId = (int) ($this->position == 0 ? 0 : ($this->parent_id ?? 0));
            $moduleId = (int) Config::get('module.current_module_id');

            if (Category::isDuplicateName($defaultName, $moduleId, $parentId)) {
                $validator->errors()->add('name.0', translate($parentId === 0
                    ? 'messages.category_name_already_exists'
                    : 'messages.sub_category_name_already_exists_under_this_category'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => translate('messages.Name is required!'),
            'image.required_if' => translate('messages.Image is required!'),
            'name.0.required' => translate('default_name_is_required'),
        ];
    }
}
