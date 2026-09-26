<?php

namespace App\Http\Requests;

use App\CentralLogics\Helpers;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'image' => 'nullable|image|mimes:' . IMAGE_FORMAT_FOR_VALIDATION . '|max:' . (MAX_FILE_SIZE * 1024),
            'priority' => 'nullable|integer|in:0,1,2',
        ];

        if (auth('admin')->check()) {
            $rules['store_id'] = 'required|exists:stores,id';
        }

        if ($this->filled('translations')) {
            $rules['translations'] = 'required';
        } else {
            $rules['name'] = 'required|array';
            $rules['name.0'] = 'required|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'store_id.required' => translate('messages.Store_is_required'),
            'store_id.exists' => translate('messages.Store_is_invalid'),
            'name.0.required' => translate('messages.default_name_is_required'),
            'translations.required' => translate('messages.default_name_is_required'),
            'image.image' => translate('messages.image_must_be_a_valid_image_file'),
            'image.mimes' => translate('messages.image_must_be_in_format') . ': ' . IMAGE_FORMAT,
            'image.max' => translate('messages.image_must_be_less_than') . ' ' . MAX_FILE_SIZE . 'mb',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson() || $this->is('api/*')) {
            throw new ValidationException(
                $validator,
                response()->json(['errors' => Helpers::error_processor($validator)], 403)
            );
        }
        parent::failedValidation($validator);
    }
}
