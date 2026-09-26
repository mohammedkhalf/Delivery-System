<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property array title
 * @property string code
 * @property Carbon|null start_date
 * @property Carbon|null expire_date
 * @property float min_purchase
 * @property float max_discount
 * @property float discount
 * @property string discount_type
 * @property string coupon_type
 * @property int|null limit
 * @property bool status
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property string|null data
 * @property int total_uses
 * @property int module_id
 * @property string created_by
 * @property string customer_id
 * @property string|null slug
 * @property int|null store_id
 * @property array lang
 */
class CouponUpdateRequest extends FormRequest
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
            'code' => 'required|max:100|unique:coupons,code,'.$this->id,
            'title' => 'required|max:191',
            'start_date' => 'required_unless:coupon_type,pro_customer',
            'expire_date' => 'required_unless:coupon_type,pro_customer',
            'discount' => 'required|numeric|min:1',
            'limit' => 'nullable|numeric|min:1',
            'min_purchase' => 'nullable|numeric|min:1',
            'discount_type' => 'required_unless:coupon_type,free_delivery',
            'zone_ids' => 'required_if:coupon_type,zone_wise',
            'store_ids' => 'required_if:coupon_type,store_wise',
            'max_discount' => 'exclude_unless:discount_type,percent|required|numeric|min:0.01',
            'title.0' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'title.0.required'=>translate('default_title_is_required'),
            'discount.min'=>translate('Discount can not be 0'),
            'limit.min'=>translate('Limit for same user can not be 0'),
            'min_purchase.min'=>translate('Min purchase can not be 0'),
            'max_discount.required'=>translate('Max discount is required for percentage discount type'),
            'max_discount.min'=>translate('Max discount can not be 0 for percentage discount type'),
        ];
    }
}
