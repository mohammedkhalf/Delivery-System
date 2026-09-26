<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Exports\ProCustomerSubscriptionListExport;
use App\Exports\ProCustomerTransactionListExport;
use App\Http\Controllers\Controller;
use App\Models\DataSetting;
use App\Models\ProCustomerBenefitSetting;
use App\Models\ProCustomerFaq;
use App\Models\ProCustomerSubscription;
use App\Models\ProCustomerSubscriptionPlan;
use App\Models\ProCustomerTransaction;
use App\Models\User;
use App\Traits\ManagesProCustomerSubscription;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProCustomerController extends Controller
{
    use ManagesProCustomerSubscription;

    private const SETTINGS_TYPE = 'pro_customer_benefits';

    private const STATUS_KEYS = [
        'discount_status', 'delivery_fee_status', 'coupon_status', 'discount_setup_mode',
    ];

    public function benefitsSetup()
    {
        $rows    = DataSetting::where('type', self::SETTINGS_TYPE)
            ->whereIn('key', self::STATUS_KEYS)
            ->pluck('value', 'key')
            ->toArray();

        $settings = array_merge([
            'discount_status'    => 0,
            'delivery_fee_status' => 0,
            'coupon_status'      => 0,
            'discount_setup_mode' => 'central',
        ], $rows);

        $anyOn = (int) $settings['discount_status']
            + (int) $settings['delivery_fee_status']
            + (int) $settings['coupon_status'];
        if ($anyOn === 0) {
            $settings['discount_status'] = 1;
        }

        $discountRows    = ProCustomerBenefitSetting::getAllForBenefit('discount');
        $deliveryFeeRows = ProCustomerBenefitSetting::getAllForBenefit('delivery_fee');

        $discountConfig = [];
        foreach (ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES as $mod) {
            $discountConfig[$mod] = $discountRows->get($mod)?->settings ?? [];
        }
        $discountConfig['central'] = $discountRows->get(null)?->settings ?? [];

        $deliveryFeeConfig = [];
        foreach (ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES as $mod) {
            $deliveryFeeConfig[$mod] = $deliveryFeeRows->get($mod)?->settings ?? [];
        }

        $minStep         = Helpers::getDecimalPlaces();
        $currencySymbol  = Helpers::currency_symbol();
        $discountModules = $this->activeDiscountModules();
        $deliveryFeeModules = ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES;
        $moduleLabels    = [
            'grocery'    => translate('messages.Grocery'),
            'food'       => translate('messages.Food'),
            'ecommerce'  => translate('messages.Shop'),
            'pharmacy'   => translate('messages.Pharmacy'),
            'parcel'     => translate('messages.Parcel'),
            'ride-share' => translate('messages.Ride_Share'),
            'rental'     => translate('messages.Rental'),
            'service'    => translate('messages.Service'),
        ];
        $minOrderLabels  = [
            'grocery'    => translate('messages.Minimum_order_amount'),
            'food'       => translate('messages.Minimum_order_amount'),
            'ecommerce'  => translate('messages.Minimum_order_amount'),
            'pharmacy'   => translate('messages.Minimum_order_amount'),
            'parcel'     => translate('messages.Minimum_delivery_amount'),
            'ride-share' => translate('messages.Minimum_ride_amount'),
            'rental'     => translate('messages.Minimum_trip_fare'),
            'service'    => translate('messages.Minimum_booking_amount'),
        ];
        $minOrderTooltips = [
            'grocery'    => translate('messages.Minimum order total required to qualify for the discount in this module'),
            'food'       => translate('messages.Minimum order total required to qualify for the discount in this module'),
            'ecommerce'  => translate('messages.Minimum order total required to qualify for the discount in this module'),
            'pharmacy'   => translate('messages.Minimum order total required to qualify for the discount in this module'),
            'parcel'     => translate('messages.Minimum delivery charge required to qualify for the discount'),
            'ride-share' => translate('messages.Minimum ride amount required to qualify for the discount'),
            'rental'     => translate('messages.Minimum trip fare required to qualify for the discount'),
            'service'    => translate('messages.Minimum booking total required to qualify for the discount'),
        ];

        return view('admin-views.pro-customer.benefits-setup', compact(
            'settings', 'discountConfig', 'deliveryFeeConfig',
            'minStep', 'currencySymbol', 'discountModules', 'deliveryFeeModules',
            'moduleLabels', 'minOrderLabels', 'minOrderTooltips'
        ));
    }

    public function benefitsSetupUpdate(Request $request)
    {
        $minStep        = Helpers::getDecimalPlaces();
        $discountStatus = (int) $request->input('discount_status', 0);
        $deliveryStatus = (int) $request->input('delivery_fee_status', 0);
        $couponStatus   = (int) $request->input('coupon_status', 0);
        $setupMode      = $request->input('discount_setup_mode', 'central');

        $enabledBenefits = $discountStatus + $deliveryStatus + $couponStatus;

        if ($enabledBenefits > 1) {
            Toastr::error(translate('messages.only_one_pro_customer_benefit_can_be_enabled_at_a_time'));
            return back()->withInput();
        }

        if ($enabledBenefits === 0) {
            Toastr::error(translate('messages.at_least_one_pro_customer_benefit_must_be_enabled'));
            return back()->withInput();
        }

        $rules = [
            'discount_status'     => 'nullable|in:0,1',
            'delivery_fee_status' => 'nullable|in:0,1',
            'coupon_status'       => 'nullable|in:0,1',
            'discount_setup_mode' => 'nullable|in:central,individual',
        ];

        $discountModules = $this->activeDiscountModules();

        if ($discountStatus === 1) {
            if ($setupMode === 'central') {
                $rules['discount_central.percentage'] = "required|numeric|min:{$minStep}|max:100";
                $rules['discount_central.max_amount'] = "required|numeric|min:{$minStep}";
                if ((int) $request->input('discount_central.min_order_status', 0) === 1) {
                    $rules['discount_central.min_order_amount'] = "required|numeric|min:{$minStep}";
                }
            } else {
                foreach ($discountModules as $mod) {
                    $rules["discount_individual.{$mod}.percentage"] = "required|numeric|min:{$minStep}|max:100";
                    $rules["discount_individual.{$mod}.max_amount"] = "required|numeric|min:{$minStep}";
                    if ((int) $request->input("discount_individual.{$mod}.min_order_status", 0) === 1) {
                        $rules["discount_individual.{$mod}.min_order_amount"] = "required|numeric|min:{$minStep}";
                    }
                }
            }
        }

        if ($deliveryStatus === 1) {
            foreach (ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES as $mod) {
                $offerType = $mod === 'parcel' ? 'partial_free' : $request->input("delivery_fee.{$mod}.offer_type", 'full_free');
                if ($offerType === 'partial_free') {
                    $rules["delivery_fee.{$mod}.charge_discount"] = "required|numeric|min:{$minStep}|max:100";
                }
                if ((int) $request->input("delivery_fee.{$mod}.min_order_status", 0) === 1) {
                    $rules["delivery_fee.{$mod}.min_order_amount"] = "required|numeric|min:{$minStep}";
                }
            }
        }

        $request->validate($rules);

        foreach (self::STATUS_KEYS as $key) {
            $value = match ($key) {
                'discount_status'     => $discountStatus,
                'delivery_fee_status' => $deliveryStatus,
                'coupon_status'       => $couponStatus,
                'discount_setup_mode' => $setupMode,
            };
            Helpers::dataUpdateOrInsert(['key' => $key, 'type' => self::SETTINGS_TYPE], ['value' => $value]);
        }

        if ($discountStatus === 1) {
            if ($setupMode === 'central') {
                $central = $request->input('discount_central', []);
                ProCustomerBenefitSetting::upsertForModule('discount', null, [
                    'percentage'       => isset($central['percentage']) && $central['percentage'] !== '' ? (float) $central['percentage'] : null,
                    'max_amount'       => isset($central['max_amount']) && $central['max_amount'] !== '' ? (float) $central['max_amount'] : null,
                    'min_order_status' => (int) ($central['min_order_status'] ?? 0),
                    'min_order_amount' => isset($central['min_order_amount']) && $central['min_order_amount'] !== '' ? (float) $central['min_order_amount'] : null,
                ]);
            } else {
                $individual = $request->input('discount_individual', []);
                foreach ($discountModules as $mod) {
                    $cfg = $individual[$mod] ?? [];
                    ProCustomerBenefitSetting::upsertForModule('discount', $mod, [
                        'percentage'       => isset($cfg['percentage']) && $cfg['percentage'] !== '' ? (float) $cfg['percentage'] : null,
                        'max_amount'       => isset($cfg['max_amount']) && $cfg['max_amount'] !== '' ? (float) $cfg['max_amount'] : null,
                        'min_order_status' => (int) ($cfg['min_order_status'] ?? 0),
                        'min_order_amount' => isset($cfg['min_order_amount']) && $cfg['min_order_amount'] !== '' ? (float) $cfg['min_order_amount'] : null,
                    ]);
                }
            }
        }

        if ($deliveryStatus === 1) {
            $deliveryInput = $request->input('delivery_fee', []);
            foreach (ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES as $mod) {
                $cfg = $deliveryInput[$mod] ?? [];
                ProCustomerBenefitSetting::upsertForModule('delivery_fee', $mod, [
                    'offer_type'       => $mod === 'parcel' ? 'partial_free' : ($cfg['offer_type'] ?? 'full_free'),
                    'min_order_status' => (int) ($cfg['min_order_status'] ?? 0),
                    'min_order_amount' => isset($cfg['min_order_amount']) && $cfg['min_order_amount'] !== '' ? (float) $cfg['min_order_amount'] : null,
                    'charge_discount'  => isset($cfg['charge_discount']) && $cfg['charge_discount'] !== '' ? (float) $cfg['charge_discount'] : null,
                ]);
            }
        }

        Toastr::success(translate('messages.pro_customer_benefits_updated_successfully'));
        return back();
    }

    public function priceSetup()
    {
        $plans       = ProCustomerSubscriptionPlan::
            select(['id', 'plan_name', 'plan_type', 'price', 'duration', 'status'])
            ->orderBy('id', 'desc')
            ->paginate(9);
        $language = getWebConfig('language');

        return view('admin-views.pro-customer.price-setup', compact('plans', 'language'));
    }

    public function planStore(Request $request)
    {
        $request->validate([
            'plan_name'   => 'array',
            'plan_name.*' => 'nullable|string|max:70',
            'plan_name.0' => 'required|string|max:70',
            'plan_type'   => 'required|in:free_trial,paid',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1|max:3650',
        ], [
            'plan_name.0.required' => translate('messages.default_plan_name_is_required'),
            'plan_name.0.max'      => translate('messages.plan_name_cannot_exceed_70_characters'),
            'plan_name.*.max'      => translate('messages.plan_name_cannot_exceed_70_characters'),
            'duration.max'         => translate('messages.duration_cannot_exceed_10_years'),
        ]);

        $defaultIndex       = array_search('default', $request->lang ?? ['default']);
        $plan               = new ProCustomerSubscriptionPlan;
        $plan->plan_name    = $request->plan_name[$defaultIndex] ?? $request->plan_name[0];
        $plan->plan_type    = $request->plan_type;
        $plan->price        = $request->plan_type === 'free_trial' ? 0 : $request->price;
        $plan->duration     = $request->duration;
        $plan->status       = 1;
        $plan->save();

        if ($request->has('lang')) {
            Helpers::add_or_update_translations(
                request: $request,
                key_data: 'plan_name',
                name_field: 'plan_name',
                model_name: 'ProCustomerSubscriptionPlan',
                data_id: $plan->id,
                data_value: $plan->plan_name
            );
        }

        Toastr::success(translate('messages.pro_customer_plan_added_successfully'));
        return back();
    }

    public function planEdit($id)
    {
        $plan = ProCustomerSubscriptionPlan::withoutGlobalScope('translate')
            ->with('translations')
            ->findOrFail($id);

        return response()->json($plan);
    }

    public function planUpdate(Request $request, $id)
    {
        $plan = ProCustomerSubscriptionPlan::findOrFail($id);

        $request->validate([
            'plan_name'   => 'array',
            'plan_name.*' => 'nullable|string|max:70',
            'plan_name.0' => 'required|string|max:70',
            'plan_type'   => 'required|in:free_trial,paid',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1|max:3650',
        ], [
            'plan_name.0.required' => translate('messages.default_plan_name_is_required'),
            'plan_name.0.max'      => translate('messages.plan_name_cannot_exceed_70_characters'),
            'plan_name.*.max'      => translate('messages.plan_name_cannot_exceed_70_characters'),
            'duration.max'         => translate('messages.duration_cannot_exceed_10_years'),
        ]);

        $defaultIndex    = array_search('default', $request->lang ?? ['default']);
        $plan->plan_name = $request->plan_name[$defaultIndex] ?? $request->plan_name[0];
        $plan->plan_type = $request->plan_type;
        $plan->price     = $request->plan_type === 'free_trial' ? 0 : $request->price;
        $plan->duration  = $request->duration;
        $plan->save();

        if ($request->has('lang')) {
            Helpers::add_or_update_translations(
                request: $request,
                key_data: 'plan_name',
                name_field: 'plan_name',
                model_name: 'ProCustomerSubscriptionPlan',
                data_id: $plan->id,
                data_value: $plan->plan_name
            );
        }

        Toastr::success(translate('messages.pro_customer_plan_updated_successfully'));
        return back();
    }

    public function planStatus($id, $status)
    {
        $plan         = ProCustomerSubscriptionPlan::findOrFail($id);
        $plan->status = (int) $status;
        $plan->save();

        Toastr::success(translate('messages.pro_customer_plan_status_updated_successfully'));
        return back();
    }

    public function planDestroy($id)
    {
        $plan = ProCustomerSubscriptionPlan::findOrFail($id);
        $plan->translations()->delete();
        $plan->delete();

        Toastr::success(translate('messages.pro_customer_plan_deleted_successfully'));
        return back();
    }

    public function customerList(Request $request)
    {
        $stats         = $this->buildSubscriptionStats();
        $query         = $this->buildSubscriptionQuery($request);
        $subscriptions = $query->paginate(config('default_pagination'))->withQueryString();
        $plans         = $this->subscriptionPlanOptions($stats);

        return view('admin-views.pro-customer.list', compact('stats', 'subscriptions', 'plans'));
    }

    private function subscriptionPlanOptions(array $stats)
    {
        $statPlanIds = array_unique(array_merge(
            array_keys($stats['plan_wise']['total'] ?? []),
            array_keys($stats['plan_wise']['earned'] ?? [])
        ));

        $plans     = ProCustomerSubscriptionPlan::orderBy('id')->get(['id', 'plan_name']);
        $orphanIds = array_values(array_diff($statPlanIds, $plans->pluck('id')->all()));

        $orphans = collect($orphanIds)->map(function ($planId) {
            $name = ProCustomerSubscription::where('plan_id', $planId)->value('plan_name')
                ?? ProCustomerTransaction::where('plan_id', $planId)->value('plan_name')
                ?? translate('messages.deleted_plan');

            return (object) [
                'id'        => $planId,
                'plan_name' => $name . ' (' . translate('messages.Deleted') . ')',
            ];
        });

        return $plans->concat($orphans)->values();
    }

    public function customerExport(Request $request)
    {
        $subscriptions  = $this->buildSubscriptionQuery($request)->get();
        $stats          = $this->buildSubscriptionStats();
        $planFilterName = null;

        if ($request->filled('plan_id')) {
            $planFilterName = optional(ProCustomerSubscriptionPlan::find($request->plan_id))->plan_name;
        }

        $data = [
            'subscriptions'       => $subscriptions,
            'search'              => $request->input('search'),
            'tab'                 => $request->input('tab', 'all'),
            'plan_id'             => $request->input('plan_id'),
            'plan_name'           => $planFilterName,
            'subscription_status' => $request->input('subscription_status'),
            'renewal_status'      => $request->input('renewal_status'),
            'dates'               => $request->input('dates'),
            'stats'               => $stats,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new ProCustomerSubscriptionListExport($data), 'ProCustomerSubscriptions.xlsx');
        } elseif ($request->type == 'csv') {
            return Excel::download(new ProCustomerSubscriptionListExport($data), 'ProCustomerSubscriptions.csv');
        }

        return back();
    }

    private function buildSubscriptionQuery(Request $request)
    {
        $search             = $request->input('search');
        $tab                = $request->input('tab', 'all');
        $planId             = $request->input('plan_id');
        $subscriptionStatus = $request->input('subscription_status');
        $renewalStatus      = $request->input('renewal_status');
        $dates              = $request->input('dates');

        $query = ProCustomerSubscription::query()
            ->with(['user', 'plan'])
            ->withTotalOrders()
            ->when($search, fn($q) => $q->search($search, ['user' => 'f_name'], 'plan_name'))
            ->when($tab === 'active', fn($q) => $q->where('status', 'active'))
            ->when($tab === 'expired', fn($q) => $q->whereIn('status', ['expired', 'canceled']))
            ->when($subscriptionStatus, fn($q) => $q->where('status', $subscriptionStatus))
            ->when(!is_null($planId) && $planId !== '', fn($q) => $q->where('plan_id', $planId))
            ->when($renewalStatus === 'upcoming', fn($q) => $q->where('status', 'active')->whereDate('end_at', '<=', now()->addDays(7)))
            ->when($renewalStatus === 'expired', fn($q) => $q->whereDate('end_at', '<', now()))
            ->orderByDesc('id');

        if ($dates) {
            [$from, $to] = $this->parseDateRange($dates);
            if ($from && $to) {
                $query->whereBetween('start_at', [$from, $to]);
            }
        }

        return $query;
    }

    private function buildSubscriptionStats(): array
    {
        $now           = now();
        $twoMonthsAgo  = $now->copy()->subMonths(2)->startOfDay();
        $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();

        $counts = ProCustomerSubscription::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' AND end_at >= ? THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN NOT (status = 'active' AND end_at >= ?) THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN created_at >= ? AND status = 'active' AND end_at >= ? THEN 1 ELSE 0 END) as new_count
        ", [$now, $now, $twoMonthsAgo, $now])->first();

        $earnings = ProCustomerTransaction::where('payment_status', 'success')
            ->where('plan_type', 'paid')
            ->selectRaw("
                COALESCE(SUM(amount), 0) as total_earned,
                COALESCE(SUM(CASE WHEN paid_at >= ? THEN amount ELSE 0 END), 0) as earned_last_30
            ", [$thirtyDaysAgo])->first();

        $planSubs = ProCustomerSubscription::selectRaw("
                plan_id,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' AND end_at >= ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN NOT (status = 'active' AND end_at >= ?) THEN 1 ELSE 0 END) as inactive
            ", [$now, $now])
            ->whereNotNull('plan_id')
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $planEarnings = ProCustomerTransaction::where('payment_status', 'success')
            ->where('plan_type', 'paid')
            ->whereNotNull('plan_id')
            ->selectRaw("
                plan_id,
                COALESCE(SUM(amount), 0) as earned,
                COALESCE(SUM(CASE WHEN paid_at >= ? THEN amount ELSE 0 END), 0) as earned_last_30
            ", [$thirtyDaysAgo])
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $planWise = [
            'total'          => $planSubs->map(fn($r) => (int) $r->total)->toArray(),
            'active'         => $planSubs->map(fn($r) => (int) $r->active)->toArray(),
            'inactive'       => $planSubs->map(fn($r) => (int) $r->inactive)->toArray(),
            'earned'         => $planEarnings->map(fn($r) => (float) $r->earned)->toArray(),
            'earned_last_30' => $planEarnings->map(fn($r) => (float) $r->earned_last_30)->toArray(),
        ];

        return [
            'total'         => (int) ($counts->total ?? 0),
            'active'        => (int) ($counts->active ?? 0),
            'inactive'      => (int) ($counts->inactive ?? 0),
            'new'           => (int) ($counts->new_count ?? 0),
            'total_earned'  => (float) ($earnings->total_earned ?? 0),
            'earned_last_30' => (float) ($earnings->earned_last_30 ?? 0),
            'plan_wise'     => $planWise,
        ];
    }

    private function parseDateRange($range): array
    {
        $parts = explode(' - ', $range);
        if (count($parts) !== 2) return [null, null];
        try {
            $from = Carbon::createFromFormat('m/d/Y', trim($parts[0]))->startOfDay();
            $to   = Carbon::createFromFormat('m/d/Y', trim($parts[1]))->endOfDay();
            return [$from, $to];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    public function subscriptionPlanView($userId)
    {
        $customer = User::findOrFail($userId);

        $subscription = ProCustomerSubscription::where('user_id', $userId)
            ->with('plan')
            ->latest('id')
            ->first();

        $latestTransaction = $subscription
            ? ProCustomerTransaction::where('user_id', $userId)
                ->where('plan_id', $subscription->plan_id)
                ->latest('id')
                ->first()
            : null;

        $activeFreeTrialPlanId = ($subscription && $subscription->status === 'active' && $subscription->plan_type === 'free_trial')
            ? $subscription->plan_id
            : null;
        $hasUsedFreeTrial = $this->hasUsedFreeTrial((int) $userId);

        $plans = ProCustomerSubscriptionPlan::where('status', 1)
            ->when($hasUsedFreeTrial, function ($query) use ($activeFreeTrialPlanId) {
                $query->where(function ($q) use ($activeFreeTrialPlanId) {
                    $q->where('plan_type', '!=', 'free_trial');
                    if ($activeFreeTrialPlanId) {
                        $q->orWhere('id', $activeFreeTrialPlanId);
                    }
                });
            })
            ->orderBy('duration')
            ->get();

        $statusFlags  = DataSetting::where('type', self::SETTINGS_TYPE)
            ->whereIn('key', self::STATUS_KEYS)
            ->pluck('value', 'key')
            ->toArray();

        $benefitItems = $this->buildBenefitItems($statusFlags);

        $businessName = Helpers::get_business_settings('business_name') ?: 'Mart';
        $proBrand     = trim($businessName) . ' ' . translate('messages.Pro');
        $moduleType   = 'normal';

        return view('admin-views.customer.subscription-plan', compact(
            'customer', 'subscription', 'latestTransaction', 'plans', 'benefitItems', 'proBrand', 'moduleType'
        ));
    }

    public function subscriptionCancel($id)
    {
        $sub = ProCustomerSubscription::with('user')->findOrFail($id);
        $this->cancelProCustomerSubscription($sub);

        Toastr::success(translate('messages.pro_customer_subscription_canceled_successfully'));
        return back();
    }

    public function subscriptionStart(Request $request, $userId)
    {
        $request->validate(['plan_id' => 'required|exists:pro_customer_subscription_plans,id']);

        $user = User::findOrFail($userId);
        $plan = ProCustomerSubscriptionPlan::findOrFail($request->plan_id);

        return $this->applyAndRedirect($user, $plan, $request, 'start',
            translate('messages.pro_customer_subscription_started_successfully'));
    }

    public function subscriptionRenew(Request $request, $id)
    {
        $request->validate(['plan_id' => 'required|exists:pro_customer_subscription_plans,id']);

        $current = ProCustomerSubscription::with('user')->findOrFail($id);
        $plan    = ProCustomerSubscriptionPlan::findOrFail($request->plan_id);
        $user    = $current->user ?? User::findOrFail($current->user_id);

        return $this->applyAndRedirect($user, $plan, $request, 'renew',
            translate('messages.pro_customer_subscription_renewed_successfully'));
    }

    public function subscriptionShift(Request $request, $id)
    {
        $request->validate(['plan_id' => 'required|exists:pro_customer_subscription_plans,id']);

        $current = ProCustomerSubscription::with('user')->findOrFail($id);
        $plan    = ProCustomerSubscriptionPlan::findOrFail($request->plan_id);

        if ((int) $current->plan_id === (int) $plan->id) {
            Toastr::error(translate('messages.shift_requires_a_different_plan'));
            return back();
        }

        $user = $current->user ?? User::findOrFail($current->user_id);
        return $this->applyAndRedirect($user, $plan, $request, 'shift',
            translate('messages.pro_customer_subscription_shifted_successfully'));
    }

    private function applyAndRedirect(User $user, ProCustomerSubscriptionPlan $plan, Request $request, string $mode, string $successMessage)
    {
        $payment     = [];
        $isFreeTrial = $plan->plan_type === 'free_trial';

        if (!$isFreeTrial) {
            $request->validate(['payment_method' => 'required|in:wallet,manual']);
            $payment['payment_method'] = $request->input('payment_method');
        }

        try {
            $this->applyProCustomerPlan($user, $plan, $payment, $mode);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'insufficient_wallet_balance') {
                Toastr::error(translate('messages.customer_wallet_balance_is_insufficient_for_this_plan'));
                return back()->withInput();
            }
            if ($e->getMessage() === 'free_trial_already_used') {
                Toastr::error(translate('messages.free_trial_already_used'));
                return back()->withInput();
            }
            throw $e;
        }

        Toastr::success($successMessage);
        return back();
    }

    public function transactions(Request $request)
    {
        $query        = $this->buildTransactionQuery($request);
        $transactions = $query->paginate(config('default_pagination'))->withQueryString();
        $plans        = ProCustomerSubscriptionPlan::orderBy('id')->get(['id', 'plan_name']);

        $filtered = $request->hasAny(['plan_id', 'plan_type', 'dates']);

        return view('admin-views.pro-customer.transactions', compact('transactions', 'plans', 'filtered'));
    }

    public function transactionExport(Request $request)
    {
        $transactions   = $this->buildTransactionQuery($request)->get();
        $planFilterName = null;

        if ($request->filled('plan_id')) {
            $planFilterName = optional(ProCustomerSubscriptionPlan::find($request->plan_id))->plan_name;
        }

        $data = [
            'transactions'   => $transactions,
            'search'         => $request->input('search'),
            'plan_id'        => $request->input('plan_id'),
            'plan_name'      => $planFilterName,
            'plan_type'      => $request->input('plan_type'),
            'dates'          => $request->input('dates'),
        ];

        if ($request->type == 'excel') {
            return Excel::download(new ProCustomerTransactionListExport($data), 'ProCustomerTransactions.xlsx');
        } elseif ($request->type == 'csv') {
            return Excel::download(new ProCustomerTransactionListExport($data), 'ProCustomerTransactions.csv');
        }

        return back();
    }

    private function buildTransactionQuery(Request $request)
    {
        $search   = $request->input('search');
        $planId   = $request->input('plan_id');
        $planType = $request->input('plan_type');
        $dates    = $request->input('dates');

        $query = ProCustomerTransaction::query()
            ->with(['user', 'plan', 'subscription'])
            ->when($search, function ($q) use ($search) {
                $needle = ltrim(trim($search), '#');
                $q->where(function ($qq) use ($search, $needle) {
                    $qq->whereHas('user', function ($uq) use ($search) {
                        $uq->where('f_name', 'like', "%{$search}%")
                            ->orWhere('l_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })->orWhere('plan_name', 'like', "%{$search}%")
                      ->orWhere('transaction_reference', 'like', "%{$search}%");

                    if (ctype_digit($needle)) {
                        $qq->orWhere('id', (int) $needle);
                    }
                });
            })
            ->when(!is_null($planId) && $planId !== '', fn($q) => $q->where('plan_id', $planId))
            ->when($planType, fn($q) => $q->where('plan_type', $planType))
            ->orderByDesc('id');

        if ($dates) {
            [$from, $to] = $this->parseDateRange($dates);
            if ($from && $to) {
                $query->whereBetween('paid_at', [$from, $to]);
            }
        }

        return $query;
    }

    public function additionalSetup(Request $request)
    {
        $search   = $request->input('search');
        $faqs     = ProCustomerFaq::withoutGlobalScope('translate')
            ->with('translations')
            ->when($search, fn($q) => $q->search($search, ['translations' => 'value'], ['question', 'answer']))
            ->when(!$search, fn($q) => $q->orderBy('priority'))
            ->paginate(config('default_pagination'))
            ->withQueryString();

        $faqCount = ProCustomerFaq::count();
        $language = getWebConfig('language');

        return view('admin-views.pro-customer.additional-setup', compact('faqs', 'faqCount', 'language'));
    }

    public function termsSetup()
    {
        $language      = getWebConfig('language');
        $termsType     = 'pro_customer_terms';
        $termsRows     = DataSetting::where('type', $termsType)->get();
        $termsTitleRow = $termsRows->firstWhere('key', 'page_title');
        $termsDescRow  = $termsRows->firstWhere('key', 'page_description');
        $termsStatus   = (int) ($termsRows->firstWhere('key', 'page_status')?->getRawOriginal('value') ?? 0);
        $termsImageRow = $termsRows->firstWhere('key', 'page_image');
        $imageValue    = $termsImageRow?->getRawOriginal('value');
        $termsImageUrl = ($imageValue && $imageValue !== 'def.png')
            ? Helpers::get_full_url('pro_customer_terms', $imageValue, $termsImageRow->storage[0]?->value ?? 'public')
            : '';

        return view('admin-views.pro-customer.terms-and-conditions', compact(
            'language', 'termsTitleRow', 'termsDescRow', 'termsStatus', 'termsImageUrl'
        ));
    }

    private function validateFaqInput(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'question'   => 'required|array',
            'question.0' => 'required|string|max:150',
            'question.*' => 'nullable|string|max:150',
            'answer'     => 'required|array',
            'answer.0'   => 'required|string|max:500',
            'answer.*'   => 'nullable|string|max:500',
            'priority'   => 'required|integer|min:1',
        ], [
            'question.0.required' => translate('messages.default_question_is_required'),
            'answer.0.required'   => translate('messages.default_answer_is_required'),
            'question.*.max'      => translate('messages.question_cannot_exceed_150_characters'),
            'answer.*.max'        => translate('messages.answer_cannot_exceed_500_characters'),
        ]);

        $validator->after(function ($validator) use ($request) {
            $langs     = $request->input('lang', ['default']);
            $questions = $request->input('question', []);
            $answers   = $request->input('answer', []);

            foreach ($langs as $index => $lang) {
                if ($lang === 'default') {
                    continue;
                }

                $hasQuestion = trim((string) ($questions[$index] ?? '')) !== '';
                $hasAnswer   = trim((string) ($answers[$index] ?? '')) !== '';

                if ($hasQuestion === $hasAnswer) {
                    continue;
                }

                $langName = Helpers::get_language_name($lang) . ' (' . strtoupper($lang) . ')';

                if (! $hasQuestion) {
                    $validator->errors()->add('question.' . $index, translate('messages.question_is_required_for') . ' ' . $langName);
                }
                if (! $hasAnswer) {
                    $validator->errors()->add('answer.' . $index, translate('messages.answer_is_required_for') . ' ' . $langName);
                }
            }
        });

        $validator->validate();
    }

    public function faqStore(Request $request)
    {
        $this->validateFaqInput($request);

        $defaultIndex = array_search('default', $request->lang ?? ['default']);

        DB::transaction(function () use ($request, $defaultIndex) {
            $count       = ProCustomerFaq::count();
            $newPriority = (int) $request->priority;
            if ($newPriority > $count + 1) $newPriority = $count + 1;

            ProCustomerFaq::where('priority', '>=', $newPriority)->increment('priority');

            $faq           = new ProCustomerFaq();
            $faq->question = $request->question[$defaultIndex] ?? $request->question[0];
            $faq->answer   = $request->answer[$defaultIndex] ?? $request->answer[0];
            $faq->priority = $newPriority;
            $faq->status   = 1;
            $faq->save();

            Helpers::add_or_update_translations(request: $request, key_data: 'pro_faq_question', name_field: 'question', model_name: 'ProCustomerFaq', data_id: $faq->id, data_value: $faq->getRawOriginal('question'));
            Helpers::add_or_update_translations(request: $request, key_data: 'pro_faq_answer', name_field: 'answer', model_name: 'ProCustomerFaq', data_id: $faq->id, data_value: $faq->getRawOriginal('answer'));
        });

        Toastr::success(translate('messages.faq_added_successfully'));
        return redirect(route('admin.pro-customer.additional-setup') . '#pro-faq-list');
    }

    public function faqEdit($id)
    {
        $faq          = ProCustomerFaq::withoutGlobalScope('translate')->with('translations')->findOrFail($id);
        $translations = [];
        foreach ($faq->translations as $t) {
            $translations[$t->locale][$t->key] = $t->value;
        }

        return response()->json([
            'id'           => $faq->id,
            'question'     => $faq->getRawOriginal('question'),
            'answer'       => $faq->getRawOriginal('answer'),
            'priority'     => $faq->priority,
            'status'       => $faq->status,
            'translations' => $translations,
        ]);
    }

    public function faqUpdate(Request $request, $id)
    {
        $this->validateFaqInput($request);

        $defaultIndex = array_search('default', $request->lang ?? ['default']);

        DB::transaction(function () use ($request, $id, $defaultIndex) {
            $faq         = ProCustomerFaq::lockForUpdate()->findOrFail($id);
            $count       = ProCustomerFaq::count();
            $oldPriority = (int) $faq->priority;
            $newPriority = (int) $request->priority;
            if ($newPriority > $count) $newPriority = $count;

            if ($newPriority < $oldPriority) {
                ProCustomerFaq::whereBetween('priority', [$newPriority, $oldPriority - 1])->where('id', '!=', $faq->id)->increment('priority');
            } elseif ($newPriority > $oldPriority) {
                ProCustomerFaq::whereBetween('priority', [$oldPriority + 1, $newPriority])->where('id', '!=', $faq->id)->decrement('priority');
            }

            $faq->question = $request->question[$defaultIndex] ?? $request->question[0];
            $faq->answer   = $request->answer[$defaultIndex] ?? $request->answer[0];
            $faq->priority = $newPriority;
            $faq->save();

            Helpers::add_or_update_translations(request: $request, key_data: 'pro_faq_question', name_field: 'question', model_name: 'ProCustomerFaq', data_id: $faq->id, data_value: $faq->getRawOriginal('question'));
            Helpers::add_or_update_translations(request: $request, key_data: 'pro_faq_answer', name_field: 'answer', model_name: 'ProCustomerFaq', data_id: $faq->id, data_value: $faq->getRawOriginal('answer'));
        });

        Toastr::success(translate('messages.faq_updated_successfully'));
        return redirect(url()->previous() . '#pro-faq-list');
    }

    public function faqStatus($id, $status)
    {
        $faq         = ProCustomerFaq::findOrFail($id);
        $faq->status = (int) $status;
        $faq->save();

        Toastr::success(translate('messages.faq_status_updated'));
        return redirect(url()->previous() . '#pro-faq-list');
    }

    public function faqDestroy($id)
    {
        DB::transaction(function () use ($id) {
            $faq             = ProCustomerFaq::lockForUpdate()->findOrFail($id);
            $deletedPriority = (int) $faq->priority;
            $faq->translations()->delete();
            $faq->delete();
            ProCustomerFaq::where('priority', '>', $deletedPriority)->decrement('priority');
        });

        Toastr::success(translate('messages.faq_deleted_successfully'));
        return redirect(url()->previous() . '#pro-faq-list');
    }

    public function termsUpdate(Request $request)
    {
        $request->validate([
            'page_title'         => 'required|array',
            'page_title.0'       => 'required|string|max:100',
            'page_title.*'       => 'nullable|string|max:100',
            'page_description'   => 'required|array',
            'page_description.0' => 'required|string',
            'page_image'         => 'nullable|image|mimes:' . IMAGE_FORMAT_FOR_VALIDATION . '|max:' . MAX_FILE_SIZE * 1024,
        ]);

        $defaultIndex = array_search('default', $request->lang ?? ['default']);
        $titleDefault = $request->page_title[$defaultIndex] ?? $request->page_title[0];
        $descDefault  = $request->page_description[$defaultIndex] ?? $request->page_description[0];
        $type         = 'pro_customer_terms';

        Helpers::dataUpdateOrInsert(['key' => 'page_status', 'type' => $type], [
            'value' => (int) $request->input('page_status', 0),
        ]);

        if ($request->hasFile('page_image')) {
            $imageName = Helpers::upload('pro_customer_terms/', 'png', $request->file('page_image'));
            Helpers::dataUpdateOrInsert(['key' => 'page_image', 'type' => $type], ['value' => $imageName]);
        }

        Helpers::dataUpdateOrInsert(['key' => 'page_title', 'type' => $type], ['value' => $titleDefault]);
        $titleRow = DataSetting::where(['key' => 'page_title', 'type' => $type])->first();

        Helpers::dataUpdateOrInsert(['key' => 'page_description', 'type' => $type], ['value' => $descDefault]);
        $descRow = DataSetting::where(['key' => 'page_description', 'type' => $type])->first();

        if ($titleRow) {
            Helpers::add_or_update_translations(request: $request, key_data: 'pro_terms_page_title', name_field: 'page_title', model_name: DataSetting::class, data_id: $titleRow->id, data_value: $titleRow->getRawOriginal('value'), model_class: true);
        }
        if ($descRow) {
            Helpers::add_or_update_translations(request: $request, key_data: 'pro_terms_page_description', name_field: 'page_description', model_name: DataSetting::class, data_id: $descRow->id, data_value: $descRow->getRawOriginal('value'), model_class: true);
        }

        Toastr::success(translate('messages.pro_customer_terms_updated_successfully'));
        return back();
    }

    private function activeDiscountModules(): array
    {
        return $this->proAddonEnabledDiscountModules();
    }

    private function moduleLabels(): array
    {
        return [
            'grocery'    => translate('messages.Grocery'),
            'food'       => translate('messages.Food'),
            'ecommerce'  => translate('messages.Shop'),
            'pharmacy'   => translate('messages.Pharmacy'),
            'parcel'     => translate('messages.Parcel'),
            'ride-share' => translate('messages.Ride_Share'),
            'rental'     => translate('messages.Rental'),
            'service'    => translate('messages.Service'),
        ];
    }

    private function buildBenefitItems(array $statusFlags): array
    {
        $discountOn = (int) ($statusFlags['discount_status'] ?? 0) === 1;
        $deliveryOn = (int) ($statusFlags['delivery_fee_status'] ?? 0) === 1;
        $couponOn   = (int) ($statusFlags['coupon_status'] ?? 0) === 1;
        $setupMode  = $statusFlags['discount_setup_mode'] ?? 'central';
        $trim       = fn($v) => rtrim(rtrim((string) $v, '0'), '.');
        $items      = [];

        if ($discountOn) {
            if ($setupMode === 'individual') {
                $rows   = ProCustomerBenefitSetting::getAllForBenefit('discount');
                $labels = $this->moduleLabels();
                $before = count($items);

                foreach ($this->proVisibleDiscountModules() as $mod) {
                    $cfg = $rows->get($mod)?->settings ?? [];
                    $pct = $cfg['percentage'] ?? null;
                    if (!$pct) {
                        continue;
                    }
                    $maxAmt  = $cfg['max_amount'] ?? null;
                    $minOn   = (int) ($cfg['min_order_status'] ?? 0) === 1;
                    $minAmt  = $cfg['min_order_amount'] ?? null;
                    $details = [];
                    if ($maxAmt) {
                        $details[] = translate('messages.Max_discount') . ': ' . Helpers::format_currency((float) $maxAmt);
                    }
                    if ($minOn && $minAmt) {
                        $details[] = translate('messages.On_orders_above') . ' ' . Helpers::format_currency((float) $minAmt);
                    }
                    $items[] = [
                        'title'    => translate('messages.Up_to') . ' ' . $trim($pct) . '% ' . translate('messages.off_on') . ' ' . ($labels[$mod] ?? ucfirst($mod)),
                        'subtitle' => $details ? implode(' • ', $details) : translate('messages.Applied_automatically_at_checkout'),
                    ];
                }

                if (count($items) === $before) {
                    $items[] = [
                        'title'    => translate('messages.Discount_on_every_order'),
                        'subtitle' => translate('messages.Discount_rates_vary_by_module'),
                    ];
                }
            } else {
                $cfg     = ProCustomerBenefitSetting::getSettings('discount', null);
                $pct     = $cfg['percentage'] ?? null;
                $maxAmt  = $cfg['max_amount'] ?? null;
                $minOn   = (int) ($cfg['min_order_status'] ?? 0) === 1;
                $minAmt  = $cfg['min_order_amount'] ?? null;
                $title   = $pct
                    ? translate('messages.Up_to') . ' ' . $trim($pct) . '% ' . translate('messages.off_on_all_orders')
                    : translate('messages.Discount_on_every_order');
                $details = [];
                if ($maxAmt) {
                    $details[] = translate('messages.Max_discount') . ': ' . Helpers::format_currency((float) $maxAmt);
                }
                if ($minOn && $minAmt) {
                    $details[] = translate('messages.On_orders_above') . ' ' . Helpers::format_currency((float) $minAmt);
                }
                $items[] = [
                    'title'    => $title,
                    'subtitle' => $details ? implode(' • ', $details) : translate('messages.Applied_automatically_at_checkout'),
                ];
            }
        }

        if ($deliveryOn) {
            $rows   = ProCustomerBenefitSetting::getAllForBenefit('delivery_fee');
            $labels = $this->moduleLabels();
            $before = count($items);

            foreach ($this->proVisibleDeliveryFeeModules() as $mod) {
                $cfg = $rows->get($mod)?->settings ?? [];
                if (empty($cfg)) {
                    continue;
                }
                $offerType = $cfg['offer_type'] ?? 'full_free';
                $minOn     = (int) ($cfg['min_order_status'] ?? 0) === 1;
                $minAmt    = $cfg['min_order_amount'] ?? null;
                $label     = $labels[$mod] ?? ucfirst($mod);

                if ($offerType === 'partial_free') {
                    $charge = $cfg['charge_discount'] ?? null;
                    if (!$charge) {
                        continue;
                    }
                    $title = translate('messages.Up_to') . ' ' . $trim($charge) . '% ' . translate('messages.off_delivery_on') . ' ' . $label;
                } else {
                    $title = translate('messages.Free_delivery_on') . ' ' . $label;
                }

                $items[] = [
                    'title'    => $title,
                    'subtitle' => $minOn && $minAmt
                        ? translate('messages.On_orders_above') . ' ' . Helpers::format_currency((float) $minAmt)
                        : translate('messages.Reduced_delivery_fee_on_every_order'),
                ];
            }

            if (count($items) === $before) {
                $items[] = [
                    'title'    => translate('messages.Free_Delivery'),
                    'subtitle' => translate('messages.Reduced_delivery_fee_on_every_order'),
                ];
            }
        }

        if ($couponOn) {
            $items[] = [
                'title'    => translate('messages.Exclusive_Coupons'),
                'subtitle' => translate('messages.Pro_only_deals_and_early_access'),
            ];
        }

        return $items;
    }
}
