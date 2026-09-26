<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Models\BusinessSetting;
use App\Models\DataSetting;
use App\Models\ProCustomerBenefitSetting;
use App\Models\ProCustomerFaq;
use App\Models\ProCustomerSubscription;
use App\Models\ProCustomerSubscriptionPlan;
use App\Models\Translation;
use App\Models\User;
use App\Traits\ManagesProCustomerSubscription;
use App\Traits\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ProCustomerController extends Controller
{
    use ManagesProCustomerSubscription;

    private const SETTINGS_TYPE = 'pro_customer_benefits';

    private const STATUS_KEYS = [
        'discount_status', 'delivery_fee_status', 'coupon_status', 'discount_setup_mode',
    ];

    /**
     * GET /api/v1/pro-customer/plans
     * Public — no auth required. When called with a valid token, the free
     * trial plan is hidden for customers who have already used one.
     */
    public function plans(Request $request): JsonResponse
    {
        $proMemberStatus = (int) Helpers::get_business_settings('pro_member_status');

        if ($proMemberStatus !== 1) {
            return response()->json([
                'pro_member_status' => 0,
                'pro_brand' => null,
                'plans' => [],
                'benefits' => null,
            ], 200);
        }

        $businessName = Helpers::get_business_settings('business_name') ?: 'Mart';
        $proBrand = trim($businessName) . ' ' . translate('messages.Pro');

        $userId = $request->user()?->id ?? auth('api')->user()?->id;
        $hasUsedFreeTrial = $userId ? $this->hasUsedFreeTrial($userId) : false;

        $plans = ProCustomerSubscriptionPlan::where('status', 1)
            ->when($hasUsedFreeTrial, fn($q) => $q->where('plan_type', '!=', 'free_trial'))
            ->orderBy('duration')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => (int) $plan->id,
                    'plan_name' => $plan->plan_name,
                    'plan_type' => $plan->plan_type,
                    'price' => (float) $plan->price,
                    'duration' => (int) $plan->duration,
                    'duration_label' => (int) $plan->duration . ' ' . translate('messages.Days'),
                    'status' => (int) $plan->status,
                ];
            })
            ->values();

        $statusFlags = DataSetting::where('type', self::SETTINGS_TYPE)
            ->whereIn('key', self::STATUS_KEYS)
            ->pluck('value', 'key')
            ->toArray();

        return response()->json([
            'pro_member_status' => 1,
            'pro_brand'         => $proBrand,
            'plans'             => $plans,
            'benefits'          => $this->normalizeBenefits($statusFlags),
        ], 200);
    }

    /**
     * GET /api/v1/pro-customer/active-offer   (auth:api)
     * Optional query param: ?module_type=food
     */
    public function activeOffer(Request $request): JsonResponse
    {
        $moduleType = $request->input('module_type');
        return response()->json($this->getProCustomerOffer(
            userId: $request->user()->id,
            moduleType: $moduleType,
            showOnlyActivePlan: false
        ), 200);
    }

    /**
     * GET /api/v1/pro-customer/faqs
     * Public — supports ?search=, ?limit=, ?offset=
     */
    public function faqs(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $limit = (int) $request->input('limit', 20);
        $limit = $limit > 0 && $limit <= 100 ? $limit : 20;
        $offset = max(0, (int) $request->input('offset', 0));

        $query = ProCustomerFaq::where('status', 1)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('question', 'like', "%{$search}%")
                        ->orWhere('answer', 'like', "%{$search}%");
                });
            })
            ->orderBy('priority');

        $total = (clone $query)->count();
        $faqs = $query->skip($offset)->take($limit)->get()
            ->map(function ($faq) {
                return [
                    'id' => (int) $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'priority' => (int) $faq->priority,
                ];
            })
            ->values();

        return response()->json([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'faqs' => $faqs,
        ], 200);
    }

    /**
     * GET /api/v1/pro-customer/terms-and-conditions
     * Public.
     */
    public function termsAndConditions(): JsonResponse
    {
        $type   = 'pro_customer_terms';
        $locale = app()->getLocale();

        $rows = DataSetting::where('type', $type)
            ->whereIn('key', ['page_title', 'page_description', 'page_status', 'page_image'])
            ->get();

        $titleRow  = $rows->firstWhere('key', 'page_title');
        $descRow   = $rows->firstWhere('key', 'page_description');
        $statusRow = $rows->firstWhere('key', 'page_status');
        $imageRow  = $rows->firstWhere('key', 'page_image');

        $title       = $this->translatedDataSettingValue($titleRow, 'pro_terms_page_title', $locale);
        $description = $this->translatedDataSettingValue($descRow, 'pro_terms_page_description', $locale);
        $imageValue  = $imageRow?->getRawOriginal('value');

        return response()->json([
            'page_status'         => (int) ($statusRow?->getRawOriginal('value') ?? 0),
            'page_title'          => $title,
            'page_description'    => $description,
            'page_image_full_url' => ($imageValue && $imageValue !== 'def.png')
                ? Helpers::get_full_url('pro_customer_terms', $imageValue, $imageRow->storage[0]?->value ?? 'public')
                : null,
        ], 200);
    }

    /**
     * POST /api/v1/customer/pro-customer/subscribe   (auth:api)
     *
     * Body:
     *   plan_id          required
     *   payment_type     required — "wallet" | "digital_payment" | "free_trial"
     *   payment_method   required when payment_type=digital_payment
     *   payment_platform optional — "app" | "web"
     *   callback         optional — redirect after gateway success/failure
     */
    public function subscribe(Request $request): JsonResponse
    {
        if ((int) Helpers::get_business_settings('pro_member_status') !== 1) {
            return response()->json(['errors' => [['code' => 'pro_disabled', 'message' => translate('messages.pro_member_feature_is_disabled')]]], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|integer|exists:pro_customer_subscription_plans,id',
            'payment_type' => 'required|in:wallet,digital_payment,free_trial',
            'payment_method' => 'required_if:payment_type,digital_payment|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = $request->user();
        $plan = ProCustomerSubscriptionPlan::where('id', $request->plan_id)
            ->where('status', 1)
            ->first();
        if (!$plan) {
            return response()->json(['errors' => [['code' => 'plan_unavailable', 'message' => translate('messages.plan_not_available')]]], 404);
        }

        $current = ProCustomerSubscription::where('user_id', $user->id)->latest('id')->first();
        $mode = match (true) {
            !$current => 'start',
            (int) $current->plan_id === (int) $plan->id => 'renew',
            default => 'shift',
        };

        if ($plan->plan_type === 'free_trial') {
            return $this->finalizeSubscription($user, $plan, ['payment_method' => 'free_trial'], $mode);
        }

        if ($request->payment_type === 'wallet') {
            return $this->finalizeSubscription($user, $plan, ['payment_method' => 'wallet'], $mode);
        }

        $digital = Helpers::get_business_settings('digital_payment');
        if (($digital['status'] ?? 0) == 0) {
            return response()->json(['errors' => [['code' => 'digital_payment_disabled', 'message' => translate('messages.digital_payment_is_disable')]]], 403);
        }

        return $this->buildGatewayRedirect($request, $user, $plan, $mode);
    }

    /**
     * POST /api/v1/customer/pro-customer/cancel   (auth:api)
     *
     * Cancels the authenticated user's currently-active Pro Customer subscription.
     * Sets status to "canceled", disables auto-renew, and clears user.pro_status.
     * Returns 404 when there is no active subscription to cancel.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = ProCustomerSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$subscription) {
            return response()->json([
                'errors' => [['code' => 'no_active_subscription', 'message' => translate('messages.no_active_subscription_to_cancel')]],
            ], 404);
        }

        $this->cancelProCustomerSubscription($subscription);

        return response()->json([
            'message' => translate('messages.subscription_canceled'),
        ], 200);
    }

    private function finalizeSubscription(User $user, ProCustomerSubscriptionPlan $plan, array $payment, string $mode): JsonResponse
    {
        try {
            $subscription = $this->applyProCustomerPlan($user, $plan, $payment, $mode);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'insufficient_wallet_balance') {
                return response()->json(['errors' => [['code' => 'insufficient_wallet_balance', 'message' => translate('messages.wallet_balance_is_insufficient_for_this_plan')]]], 403);
            }
            if ($e->getMessage() === 'free_trial_already_used') {
                return response()->json(['errors' => [['code' => 'free_trial_already_used', 'message' => translate('messages.free_trial_already_used')]]], 403);
            }
            throw $e;
        }

        return response()->json([
            'message' => translate('messages.subscription_activated'),
            'subscription' => [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan_name,
                'plan_type' => $subscription->plan_type,
                'plan_price' => (float) $subscription->plan_price,
                'start_at' => optional($subscription->start_at)->toIso8601String(),
                'end_at' => optional($subscription->end_at)->toIso8601String(),
                'status' => $subscription->status,
            ],
        ], 200);
    }

    private function buildGatewayRedirect(Request $request, User $user, ProCustomerSubscriptionPlan $plan, string $mode): JsonResponse
    {
        $payer = new Payer(
            ($user->f_name ?? '') . ' ' . ($user->l_name ?? ''),
            $user->email,
            $user->phone,
            ''
        );

        $logoRow = BusinessSetting::where('key', 'logo')->first();
        $currency = Helpers::currency_code();
        $businessName = Helpers::get_business_settings('business_name');

        $additional = [
            'business_name' => $businessName,
            'business_logo' => Helpers::get_full_url('business', $logoRow?->value, $logoRow?->storage[0]?->value ?? 'public'),
            'plan_id' => (int) $plan->id,
            'mode' => $mode,
        ];

        $paymentInfo = new PaymentInfo(
            success_hook: 'pro_customer_subscription_success',
            failure_hook: 'pro_customer_subscription_failed',
            currency_code: $currency,
            payment_method: $request->payment_method,
            payment_platform: $request->payment_platform,
            payer_id: $user->id,
            receiver_id: '100',
            additional_data: $additional,
            payment_amount: (float) $plan->price,
            external_redirect_link: $request->input('callback', session('callback')),
            attribute: 'pro_customer_subscription_payment',
            attribute_id: (string) $user->id,
        );

        $receiver = new Receiver('receiver_name', 'example.png');

        $redirectLink = Payment::generate_link($payer, $paymentInfo, $receiver);

        if (!$redirectLink) {
            return response()->json(['errors' => [['code' => 'invalid_gateway', 'message' => translate('messages.payment_gateway_not_supported')]]], 400);
        }

        return response()->json(['redirect_link' => $redirectLink], 200);
    }

    private function translatedDataSettingValue(?DataSetting $row, string $translationKey, string $locale): ?string
    {
        if (!$row) {
            return null;
        }

        $translated = Translation::where([
            'translationable_type' => DataSetting::class,
            'translationable_id' => $row->id,
            'key' => $translationKey,
            'locale' => $locale,
        ])->value('value');

        return $translated ?: $row->getRawOriginal('value');
    }

    private function normalizeBenefits(array $statusFlags): array
    {
        $discountActive = (int) ($statusFlags['discount_status'] ?? 0) === 1;
        $deliveryActive = (int) ($statusFlags['delivery_fee_status'] ?? 0) === 1;
        $couponActive   = (int) ($statusFlags['coupon_status'] ?? 0) === 1;
        $setupMode      = $statusFlags['discount_setup_mode'] ?? 'central';

        $type = match (true) {
            $discountActive => 'discount',
            $deliveryActive => 'delivery_fee',
            $couponActive   => 'coupon',
            default         => null,
        };

        $f = static fn($v) => $v !== null && $v !== '' ? (float) $v : null;

        $discountData = ['active' => $discountActive ? 1 : 0, 'setup_mode' => $setupMode];
        if ($setupMode === 'central') {
            $cfg = ProCustomerBenefitSetting::getSettings('discount', null);
            $discountData['config'] = [
                'percentage'       => $f($cfg['percentage'] ?? null),
                'max_amount'       => $f($cfg['max_amount'] ?? null),
                'min_order_status' => (int) ($cfg['min_order_status'] ?? 0),
                'min_order_amount' => $f($cfg['min_order_amount'] ?? null),
            ];
        } else {
            $modules = [];
            foreach ($this->proVisibleDiscountModules() as $mod) {
                $cfg = ProCustomerBenefitSetting::getSettings('discount', $mod);
                $modules[$mod] = [
                    'percentage'       => $f($cfg['percentage'] ?? null),
                    'max_amount'       => $f($cfg['max_amount'] ?? null),
                    'min_order_status' => (int) ($cfg['min_order_status'] ?? 0),
                    'min_order_amount' => $f($cfg['min_order_amount'] ?? null),
                ];
            }
            $discountData['modules'] = $modules;
        }

        $deliveryFeeData = ['active' => $deliveryActive ? 1 : 0, 'modules' => []];
        foreach ($this->proVisibleDeliveryFeeModules() as $mod) {
            $cfg = ProCustomerBenefitSetting::getSettings('delivery_fee', $mod);
            $deliveryFeeData['modules'][$mod] = [
                'offer_type'                 => $cfg['offer_type'] ?? 'full_free',
                'min_order_status'           => (int) ($cfg['min_order_status'] ?? 0),
                'min_order_amount'           => $f($cfg['min_order_amount'] ?? null),
                'charge_discount_percentage' => $f($cfg['charge_discount'] ?? null),
            ];
        }

        return [
            'active_type'  => $type,
            'discount'     => $discountData,
            'delivery_fee' => $deliveryFeeData,
            'coupon'       => ['active' => $couponActive ? 1 : 0],
        ];
    }
}
