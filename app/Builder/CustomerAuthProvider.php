<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\CentralLogics\SMS_module;
use App\Mail\EmailVerification;
use App\Mail\UserPasswordResetMail;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Guest;
use App\Models\PasswordReset;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\CarbonInterval;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Builder\Contracts\CustomerAuthProvider as CustomerAuthProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\StorefrontCustomer;
use Modules\Gateways\Traits\SmsGateway;
use App\Scopes\HostScope;

class CustomerAuthProvider implements CustomerAuthProviderContract
{
    private const OTP_INTERVAL_SECONDS = 60;
    private const OTP_MAX_HITS         = 5;
    private const OTP_BLOCK_SECONDS    = 600;
    private const PASSPORT_TOKEN_NAME  = 'RestaurantCustomerAuth';

    /**
     * Session key holding the user id of an OTP/social-authenticated
     * customer who still owes mandatory profile fields. While this key
     * is set we deliberately do NOT call `Auth::guard('customer')->login*`
     * — the storefront treats them as un-authenticated until
     * `completeProfile()` finishes.
     */
    private const PENDING_PROFILE_SESSION_KEY = 'pending_profile_user_id';

    public function __construct(private StorefrontContext $context)
    {
    }

    /**
     * Current storefront's (tenant_id, sub_tenant_id) — used to filter
     * every User / PasswordReset / OTP query and to stamp new rows on
     * insert. Host requests don't set a scope, so this returns (0, 0)
     * and the adapter naturally points at host rows.
     *
     * Returned as an associative array so callers can chain it via
     * `->where($this->scopeFilter())` or merge it with insert payloads
     * via `array + $this->scopeFilter()`.
     */
    private function scopeFilter(): array
    {
        $scope = $this->context->getScope();
        return [
            'tenant_id'     => (int) ($scope?->tenantId ?? 0),
            'sub_tenant_id' => (int) ($scope?->subTenantId ?? 0),
        ];
    }

    /**
     * Build a fresh User query with the HostScope global scope removed,
     * so the adapter can apply its own explicit storefront scope. Host
     * code that doesn't go through the adapter continues to auto-filter
     * to host rows via the global scope (see App\Scopes\HostScope).
     */
    private function userQuery()
    {
        return User::withoutGlobalScope(HostScope::class);
    }

    /**
     * Mandatory profile fields a customer must provide before Laravel's
     * customer guard is allowed to log them in. Used to decide whether
     * OTP/social authentication can finalize the login or has to defer
     * to `completeProfile()`.
     */
    private function isProfileComplete(User $user): bool
    {
        return (string) ($user->f_name ?? '') !== ''
            && (string) ($user->phone  ?? '') !== ''
            && (string) ($user->email  ?? '') !== '';
    }

    /**
     * Resolve the pending-profile user (if any) for the CURRENT storefront
     * scope. Clears the session pointer when it's stale (user deleted,
     * scope mismatch). Returns null when no pending user is set, i.e. the
     * visitor is either fully authenticated or fully anonymous.
     */
    public function pendingProfileUser(): ?StorefrontCustomer
    {
        $id = \session(self::PENDING_PROFILE_SESSION_KEY);
        if (! $id) {
            return null;
        }

        $scope = $this->scopeFilter();
        $user  = $this->userQuery()
            ->where('id', $id)
            ->where($scope)
            ->first();

        if (! $user) {
            \session()->forget(self::PENDING_PROFILE_SESSION_KEY);
            return null;
        }

        return $this->toCustomer($user);
    }

    /**
     * Finalize a deferred login: write the customer guard, merge any
     * guest cart, and clear the pending-profile pointer. Called once
     * `completeProfile()` has saved the missing fields.
     */
    private function promotePendingProfileLogin(User $user): void
    {
        Auth::guard('customer')->loginUsingId($user->id);
        $this->mergeGuestCart($user);
        \session()->forget(self::PENDING_PROFILE_SESSION_KEY);
    }

    public function current(): ?StorefrontCustomer
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return null;
        }

        // Session-bleed defense: if the authenticated user's stored scope
        // doesn't match the current storefront, treat them as not-logged-in.
        // Cookies are typically domain-scoped so cross-storefront bleed is
        // rare, but a customer's session must NEVER unlock data at another
        // scope. Auth::user() bypasses our scope filters because it loads
        // by primary key — so this check is the last line of defense.
        $scope = $this->scopeFilter();
        if ((int) $user->tenant_id !== $scope['tenant_id']
            || (int) $user->sub_tenant_id !== $scope['sub_tenant_id']) {
            Auth::guard('customer')->logout();
            return null;
        }

        return $this->toCustomer($user);
    }

    public function loginWithPassword(string $emailOrPhone, string $password, string $fieldType): StorefrontCustomer
    {
        $credentials = $fieldType === 'email'
            ? ['email' => $emailOrPhone, 'password' => $password]
            : ['phone' => $this->normalizePhone($emailOrPhone), 'password' => $password];

        // Auth::attempt treats every non-`password` key as a WHERE clause
        // on the user lookup. Scope keys narrow it to the current storefront.
        // The HostScope global scope ALSO applies the current-scope filter
        // during this lookup (via StorefrontContext), but passing the keys
        // explicitly is defensive — it works even if HostScope is bypassed.
        if (!Auth::guard('customer')->attempt($credentials + $this->scopeFilter())) {
            throw ValidationException::withMessages([
                'email_or_phone' => __('messages.User_credential_does_not_match'),
            ]);
        }

        $user = Auth::guard('customer')->user();
        $this->guardActiveStatus($user);

        $user->login_medium = 'manual';
        $user->save();

        $this->ensureRefCode($user);
        $this->mergeGuestCart($user);

        return $this->toCustomer($user);
    }

    public function sendLoginOtp(string $phone): void
    {
        $phone = $this->normalizePhone($phone);
        $user = $this->userQuery()->where('phone', $phone)->where($this->scopeFilter())->first();
        if ($user && !$user->status) {
            throw ValidationException::withMessages([
                '_form' => __('messages.your_account_is_blocked'),
            ]);
        }

        $this->issuePhoneOtp($phone);
    }

    public function loginWithOtp(string $phone, string $otp): StorefrontCustomer
    {
        $phone = $this->normalizePhone($phone);
        $scope = $this->scopeFilter();

        $row = DB::table('phone_verifications')
            ->where(['phone' => $phone, 'token' => $otp] + $scope)
            ->first();

        if (!$row && !(in_array(getEnvMode(), ['test', 'demo'], true) && $otp === '123456')) {
            throw ValidationException::withMessages([
                'otp' => __('OTP does not match'),
            ]);
        }

        $user = $this->userQuery()->where('phone', $phone)->where($scope)->first();
        if (!$user) {
            $user = new User();
            $user->phone              = $phone;
            $user->password           = bcrypt($phone);
            $user->is_phone_verified  = 1;
            $user->login_medium       = 'otp';
            $user->tenant_id          = $scope['tenant_id'];
            $user->sub_tenant_id      = $scope['sub_tenant_id'];
            $user->save();
        } else {
            $this->guardActiveStatus($user);
            $user->login_medium      = 'otp';
            $user->is_phone_verified = 1;
            $user->save();
        }

        $this->ensureRefCode($user);

        DB::table('phone_verifications')
            ->where(['phone' => $phone] + $scope)
            ->delete();

        // OTP-only signup leaves the user without a name/email — defer
        // the customer-guard login until `completeProfile()` fills those
        // in. ShareStorefrontProps will surface them via `pendingProfile`
        // so the frontend can force-open the completion modal; until then
        // `current()` returns null and protected routes 401.
        if (! $this->isProfileComplete($user)) {
            \session([self::PENDING_PROFILE_SESSION_KEY => $user->id]);
            return $this->toCustomer($user);
        }

        Auth::guard('customer')->loginUsingId($user->id);
        $this->mergeGuestCart($user);

        return $this->toCustomer($user);
    }

    public function register(array $data): array
    {
        $name      = \trim((string) ($data['name'] ?? ''));
        $email     = $data['email']    ?? null;
        $phone     = $this->normalizePhone($data['phone'] ?? null);
        $password  = $data['password'] ?? null;
        // Referral crediting rides on the wallet-features master switch.
        // The signup form hides the field when off, but a stale page or
        // crafted request could still submit a code — silently drop it.
        $refCode   = \config('builder.wallet_features_enabled', true)
            ? ($data['ref_code'] ?? null)
            : null;

        $scope = $this->scopeFilter();

        $errors = [];
        if ($name === '')           { $errors['name']     = [__('The name field is required.')]; }
        if (!$email)                { $errors['email']    = [__('The email field is required.')]; }
        elseif ($this->userQuery()->where('email', $email)->where($scope)->exists()) { $errors['email'] = [__('The email has already been taken.')]; }
        if (!$phone)                { $errors['phone']    = [__('The phone field is required.')]; }
        elseif ($this->userQuery()->where('phone', $phone)->where($scope)->exists()) { $errors['phone'] = [__('The phone has already been taken.')]; }
        if (!$password || \strlen($password) < 8) {
            $errors['password'] = [__('The password must be at least 8 characters.')];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        [$firstName, $lastName] = $this->splitName($name);

        $refBy = $refCode ? $this->resolveReferrer($refCode, $phone, $firstName, $lastName) : null;

        $user = User::create([
            'f_name'   => $firstName,
            'l_name'   => $lastName,
            'email'    => $email,
            'phone'    => $phone,
            'ref_by'   => $refBy,
            'password' => bcrypt($password),
        ] + $scope);
        $user->ref_code = Helpers::generate_referer_code($user);
        $user->save();

        $loginSettings = $this->loginSettings();
        $needsPhone = (int) ($loginSettings['phone_verification_status'] ?? 0) === 1;
        $needsEmail = !$needsPhone && (int) ($loginSettings['email_verification_status'] ?? 0) === 1;

        if ($needsPhone) {
            $this->issuePhoneOtp($phone);
        } elseif ($needsEmail) {
            $this->issueEmailOtp($email, $name);
        } else {
            Auth::guard('customer')->loginUsingId($user->id);
            $this->mergeGuestCart($user);
        }

        try {
            if (\config('mail.status')
                && Helpers::getNotificationStatusData('customer', 'customer_registration', 'mail_status')
                && Helpers::get_mail_status('registration_mail_status_user') == '1'
            ) {
                Mail::to($email)->send(new \App\Mail\CustomerRegistration($name));
            }
        } catch (\Throwable $e) {
            \info($e->getMessage());
        }

        return [
            'customer'                => $this->toCustomer($user),
            'needsPhoneVerification'  => $needsPhone,
            'needsEmailVerification'  => $needsEmail,
        ];
    }

    public function verifyContact(string $type, string $value, string $otp): StorefrontCustomer
    {
        if ($type === 'phone') {
            $value = $this->normalizePhone($value);
        }
        $scope = $this->scopeFilter();

        $user = $type === 'phone'
            ? $this->userQuery()->where('phone', $value)->where($scope)->first()
            : $this->userQuery()->where('email', $value)->where($scope)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                '_form' => __('messages.user_not_found'),
            ]);
        }

        if ($type === 'phone' && $user->is_phone_verified) {
            throw ValidationException::withMessages([
                'otp' => __('messages.phone_number_is_already_verified'),
            ]);
        }
        if ($type === 'email' && $user->is_email_verified) {
            throw ValidationException::withMessages([
                'otp' => __('messages.email_number_is_already_verified'),
            ]);
        }

        if (in_array(getEnvMode(), ['test', 'demo'], true) && $otp === '123456') {
            $this->markVerified($user, $type);
        } else {
            $table  = $type === 'phone' ? 'phone_verifications' : 'email_verifications';
            $column = $type;
            $row    = DB::table($table)->where([$column => $value, 'token' => $otp] + $scope)->first();

            if (!$row) {
                if ($type === 'phone') { $this->bumpPhoneOtpHit($value); }
                throw ValidationException::withMessages([
                    'otp' => __('OTP does not match'),
                ]);
            }

            DB::table($table)->where([$column => $value, 'token' => $otp] + $scope)->delete();
            $this->markVerified($user, $type);
        }

        Auth::guard('customer')->loginUsingId($user->id);
        $this->mergeGuestCart($user);

        return $this->toCustomer($user);
    }

    public function resendVerificationOtp(string $type, string $value): void
    {
        if ($type === 'phone') {
            $this->issuePhoneOtp($this->normalizePhone($value));
        } else {
            $user = $this->userQuery()->where('email', $value)->where($this->scopeFilter())->first();
            $this->issueEmailOtp($value, $user?->f_name ?? '');
        }
    }

    public function sendPasswordResetOtp(string $type, string $value): void
    {
        if ($type === 'phone') {
            $value = $this->normalizePhone($value);
        }
        $scope = $this->scopeFilter();

        $user = $type === 'phone'
            ? $this->userQuery()->where('phone', $value)->where($scope)->first()
            : $this->userQuery()->where('email', $value)->where($scope)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                $type => __('messages.user_not_found!'),
            ]);
        }

        $existing = PasswordReset::withoutGlobalScope(HostScope::class)
            ->where($type, $value)
            ->where($scope)
            ->first();
        if ($existing && Carbon::parse($existing->created_at)->diffInSeconds() < self::OTP_INTERVAL_SECONDS) {
            $remaining = round(self::OTP_INTERVAL_SECONDS - Carbon::parse($existing->created_at)->diffInSeconds());
            throw ValidationException::withMessages([
                'otp' => __('messages.please_try_again_after_') . $remaining . ' ' . __('messages.seconds'),
            ]);
        }

        $token = in_array(getEnvMode(), ['test', 'demo'], true) ? '123456' : (string) \rand(100000, 999999);

        DB::table('password_resets')->updateOrInsert(
            [$type => $value] + $scope,
            ['token' => $token, 'created_at' => now()],
        );

        if (in_array(getEnvMode(), ['test', 'demo'], true)) {
            return;
        }

        $smsActive = Setting::whereJsonContains('live_values->status', '1')
            ->where('settings_type', 'sms_config')->exists();

        if ($type === 'phone' && $smsActive) {
            $sent = $this->sendSms($value, $token);
            if ($sent !== 'success') {
                throw ValidationException::withMessages([
                    'otp' => __('messages.failed_to_send_sms'),
                ]);
            }
            return;
        }

        if ($type === 'email' && \config('mail.status')) {
            try {
                if (Helpers::get_mail_status('forget_password_mail_status_user') == '1') {
                    Mail::to($user->getRawOriginal('email'))
                        ->send(new UserPasswordResetMail($token, $user->f_name));
                    return;
                }
            } catch (\Throwable $e) {
                \info($e->getMessage());
            }
        }

        throw ValidationException::withMessages([
            'otp' => __('messages.failed_to_send_otp'),
        ]);
    }

    public function verifyPasswordResetOtp(string $type, string $value, string $otp): bool
    {
        if ($type === 'phone') {
            $value = $this->normalizePhone($value);
        }

        if (in_array(getEnvMode(), ['test', 'demo'], true) && $otp === '123456') {
            return true;
        }

        $row = DB::table('password_resets')
            ->where([$type => $value, 'token' => $otp] + $this->scopeFilter())
            ->first();

        if ($row) {
            return true;
        }

        $this->bumpPasswordResetHit($type, $value);

        throw ValidationException::withMessages([
            'otp' => __('Invalid OTP.'),
        ]);
    }

    public function resetPassword(string $type, string $value, string $otp, string $newPassword): void
    {
        if (\strlen($newPassword) < 8) {
            throw ValidationException::withMessages([
                'password' => [__('The password must be at least 8 characters.')],
            ]);
        }

        if ($type === 'phone') {
            $value = $this->normalizePhone($value);
        }
        $scope = $this->scopeFilter();

        $user = $type === 'phone'
            ? $this->userQuery()->where('phone', $value)->where($scope)->first()
            : $this->userQuery()->where('email', $value)->where($scope)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                $type => __('messages.user_not_found!'),
            ]);
        }

        $isTestOk = in_array(getEnvMode(), ['test', 'demo'], true) && $otp === '123456';
        $row      = $isTestOk ? null : PasswordReset::withoutGlobalScope(HostScope::class)
            ->where(['token' => $otp])
            ->where($type, $value)
            ->where($scope)
            ->first();

        if (!$isTestOk && !$row) {
            throw ValidationException::withMessages([
                'otp' => __('messages.invalid_otp'),
            ]);
        }

        $user->password = bcrypt($newPassword);
        $user->save();

        PasswordReset::withoutGlobalScope(HostScope::class)
            ->where(['token' => $otp])
            ->where($type, $value)
            ->where($scope)
            ->delete();
    }

    public function loginWithSocial(string $provider, array $payload): array
    {
        $token    = $payload['token']     ?? null;
        $uniqueId = $payload['unique_id'] ?? null;
        $email    = $payload['email']     ?? null;

        if (!$token || !$uniqueId) {
            throw ValidationException::withMessages([
                '_form' => __('messages.invalid_request'),
            ]);
        }

        $data = $this->verifySocialToken($provider, [
            'token'     => $token,
            'unique_id' => $uniqueId,
            'email'     => $email,
            'access_token' => (bool) ($payload['access_token'] ?? false),
        ]);

        if (!isset($data['email'])) {
            throw ValidationException::withMessages([
                '_form' => __('messages.email_does_not_match'),
            ]);
        }

        $providerEmail = $data['email'];
        $scope = $this->scopeFilter();

        // Find-or-create at this scope. Same Google account at storefront A
        // and storefront B produces two independent user rows — matching
        // the "completely standalone storefront" feel.
        $user = $this->userQuery()->where('email', $providerEmail)->where($scope)->first();
        $needsCompletion = false;

        if (!$user) {
            $name      = $data['name'] ?? '';
            [$f, $l]   = $this->splitName($name !== '' ? $name : $providerEmail);
            $socialPk  = $data['id'] ?? $data['sub'] ?? $data['kid'] ?? null;

            $user = User::create([
                'f_name'       => $f,
                'l_name'       => $l,
                'email'        => $providerEmail,
                'login_medium' => $provider,
                'social_id'    => $socialPk,
                'password'     => bcrypt($socialPk ?? Str::random(16)),
                'is_email_verified' => 1,
            ] + $scope);
            $user->ref_code = Helpers::generate_referer_code($user);
            $user->save();

            $needsCompletion = $user->phone === null;
        } else {
            $this->guardActiveStatus($user);
            $user->login_medium       = $provider;
            $user->is_email_verified  = 1;
            $user->save();
            $this->ensureRefCode($user);
            $needsCompletion = $user->phone === null;
        }

        // Social authentication may land on a row without a phone (and
        // possibly without a first name) — defer Laravel's customer-guard
        // login until `completeProfile()` fills the missing fields. The
        // pending pointer is what ShareStorefrontProps reads to keep the
        // completion modal open.
        if (! $this->isProfileComplete($user)) {
            \session([self::PENDING_PROFILE_SESSION_KEY => $user->id]);

            return [
                'customer'         => $this->toCustomer($user),
                'needsCompletion'  => true,
            ];
        }

        Auth::guard('customer')->loginUsingId($user->id);
        $this->mergeGuestCart($user);

        return [
            'customer'         => $this->toCustomer($user),
            'needsCompletion'  => $needsCompletion,
        ];
    }

    public function logout(): void
    {
        Auth::guard('customer')->logout();
        \session()->invalidate();
        \session()->regenerateToken();
    }

    public function completeProfile(array $data): StorefrontCustomer
    {
        // Two ways to land here:
        //   1) Fully authenticated user (e.g. classic register flow that
        //      logged them in immediately) returning later to fix missing
        //      fields. `Auth::guard('customer')->user()` resolves them.
        //   2) OTP/social user whose login was deferred — they are NOT in
        //      the customer guard yet, and we need to look them up via
        //      the pending-profile session pointer.
        $user      = Auth::guard('customer')->user();
        $isPending = false;
        if (! $user) {
            $pendingId = \session(self::PENDING_PROFILE_SESSION_KEY);
            if ($pendingId) {
                $user = $this->userQuery()
                    ->where('id', $pendingId)
                    ->where($this->scopeFilter())
                    ->first();
                $isPending = (bool) $user;
            }
        }
        if (!$user) {
            throw ValidationException::withMessages([
                '_form' => __('Unauthenticated.'),
            ]);
        }

        $name    = \trim((string) ($data['name']     ?? ''));
        $phone   = $this->normalizePhone($data['phone'] ?? null);
        $email   = $data['email']    ?? null;
        // Referral crediting rides on the wallet-features master switch.
        $refCode = \config('builder.wallet_features_enabled', true)
            ? ($data['ref_code'] ?? null)
            : null;

        $scope = $this->scopeFilter();

        $errors = [];
        if (!$user->f_name && $name === '') {
            $errors['name'] = [__('The name field is required.')];
        }
        if (!$user->phone) {
            if (!$phone) {
                $errors['phone'] = [__('The phone field is required.')];
            } elseif ($this->userQuery()->where('phone', $phone)->where($scope)->where('id', '!=', $user->id)->exists()) {
                $errors['phone'] = [__('The phone has already been taken.')];
            }
        }
        if (!$user->email) {
            if (!$email) {
                $errors['email'] = [__('The email field is required.')];
            } elseif ($this->userQuery()->where('email', $email)->where($scope)->where('id', '!=', $user->id)->exists()) {
                $errors['email'] = [__('The email has already been taken.')];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if (!$user->f_name && $name !== '') {
            [$f, $l] = $this->splitName($name);
            $user->f_name = $f;
            $user->l_name = $l;
        }
        if (!$user->phone && $phone) {
            $user->phone = $phone;
        }
        if (!$user->email && $email) {
            $user->email = $email;
        }
        if ($refCode && !$user->ref_by) {
            $user->ref_by = $this->resolveReferrer(
                $refCode,
                $user->phone ?? '',
                $user->f_name ?? '',
                $user->l_name ?? '',
            );
        }

        $user->save();
        $this->ensureRefCode($user);

        // Pending users had their login deferred at OTP/social time — now
        // that the mandatory fields are saved, promote them into the
        // customer guard for real (and merge any guest cart they built
        // up while in the half-authenticated state).
        if ($isPending) {
            $this->promotePendingProfileLogin($user);
        }

        return $this->toCustomer($user);
    }

    public function updateProfile(array $data, ?\Illuminate\Http\UploadedFile $image = null): StorefrontCustomer
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            throw ValidationException::withMessages([
                '_form' => __('Unauthenticated.'),
            ]);
        }

        $fName    = isset($data['f_name'])   ? \trim((string) $data['f_name'])   : null;
        $lName    = isset($data['l_name'])   ? \trim((string) $data['l_name'])   : null;
        $phone    = $this->normalizePhone($data['phone'] ?? null);
        $email    = isset($data['email'])    ? \trim((string) $data['email'])    : null;
        $password = $data['password']        ?? null;

        $scope = $this->scopeFilter();

        // Field editability is host-configured (config/builder.php →
        // capabilities.profile). Locked fields are ignored even if a tampered
        // request carries a changed value, so client + server agree.
        $phoneEditable = (bool) config('builder.capabilities.profile.phoneEditable', true);
        $emailEditable = (bool) config('builder.capabilities.profile.emailEditable', true);

        $errors = [];
        if ($fName === '') {
            $errors['f_name'] = [__('The first name field is required.')];
        }
        if ($phoneEditable && $phone !== null && $phone !== '' && $this->userQuery()->where('phone', $phone)->where($scope)->where('id', '!=', $user->id)->exists()) {
            $errors['phone'] = [__('The phone has already been taken.')];
        }
        if ($emailEditable && $email !== null && $email !== '' && $this->userQuery()->where('email', $email)->where($scope)->where('id', '!=', $user->id)->exists()) {
            $errors['email'] = [__('The email has already been taken.')];
        }
        if ($password !== null && $password !== '' && \strlen($password) < 6) {
            $errors['password'] = [__('The password must be at least 6 characters.')];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($fName !== null) {
            $user->f_name = $fName;
        }
        if ($lName !== null) {
            $user->l_name = $lName;
        }
        if ($phoneEditable && $phone !== null && $phone !== '' && $phone !== $user->phone) {
            $user->phone = $phone;
        }
        if ($emailEditable && $email !== null && $email !== '' && $email !== $user->email) {
            $user->email = $email;
            $user->is_email_verified = 0;
        }
        if ($password !== null && $password !== '') {
            $user->password = \bcrypt($password);
        }
        if ($image) {
            $user->image = Helpers::update(
                dir: 'profile/',
                old_image: $user->image,
                format: 'png',
                image: $image,
            );
        }

        $user->save();

        return $this->toCustomer($user);
    }

    public function ensureGuestId(): ?int
    {
        if (Auth::guard('customer')->check()) {
            return null;
        }

        $existing = \session('guest_id');
        if ($existing && Guest::find($existing)) {
            return (int) $existing;
        }

        $guest = new Guest();
        $guest->ip_address = \request()->ip();
        $guest->fcm_token  = \request()->input('fcm_token');
        $guest->save();

        \session(['guest_id' => $guest->id]);

        return (int) $guest->id;
    }

    private function mergeGuestCart(User $user): void
    {
        $guestId = \session('guest_id');
        if (!$guestId || !$user->id) {
            return;
        }

        Cart::where(['user_id' => $user->id, 'is_guest' => 0])
            ->when(Cart::where(['user_id' => $guestId, 'is_guest' => 1])->exists(),
                fn ($q) => $q->delete(),
            );

        Cart::where('user_id', $guestId)->update([
            'user_id'  => $user->id,
            'is_guest' => 0,
        ]);

        if (\addon_published_status('Rental')) {
            \Modules\Rental\Entities\RentalCart::where(['user_id' => $user->id, 'is_guest' => 0])
                ->when(
                    \Modules\Rental\Entities\RentalCart::where(['user_id' => $guestId, 'is_guest' => 1])->exists(),
                    fn ($q) => $q->delete(),
                );

            \Modules\Rental\Entities\RentalCartUserData::where(['user_id' => $user->id, 'is_guest' => 0])
                ->when(
                    \Modules\Rental\Entities\RentalCartUserData::where(['user_id' => $guestId, 'is_guest' => 1])->exists(),
                    fn ($q) => $q->delete(),
                );

            \Modules\Rental\Entities\RentalCart::where('user_id', $guestId)
                ->update(['user_id' => $user->id, 'is_guest' => 0]);
            \Modules\Rental\Entities\RentalCartUserData::where('user_id', $guestId)
                ->update(['user_id' => $user->id, 'is_guest' => 0]);
        }

        \session()->forget('guest_id');
    }

    public function socialConfig(): array
    {
        $settings = $this->loginSettings();

        $googleClientId = null;
        $facebookAppId  = null;
        $socialRow = BusinessSetting::where('key', 'social_login')->first();
        if ($socialRow) {
            foreach (\json_decode($socialRow->value, true) ?? [] as $entry) {
                if (($entry['login_medium'] ?? null) === 'google') {
                    $googleClientId = $entry['client_id'] ?? null;
                } elseif (($entry['login_medium'] ?? null) === 'facebook') {
                    $facebookAppId = $entry['client_id'] ?? null;
                }
            }
        }

        $appleClientId = null;
        $appleRedirect = null;
        $appleRow = BusinessSetting::where('key', 'apple_login')->first();
        if ($appleRow) {
            $cfg = \json_decode($appleRow->value, true);
            if (\is_array($cfg) && isset($cfg[0])) {
                $appleClientId = $cfg[0]['client_id']           ?? null;
                $appleRedirect = $cfg[0]['redirect_url_react']  ?? null;
            }
        }

        return [
            'google' => [
                'enabled'  => (int) ($settings['google_login_status'] ?? 0) === 1 && !empty($googleClientId),
                'clientId' => $googleClientId ?: null,
            ],
            'facebook' => [
                'enabled' => (int) ($settings['facebook_login_status'] ?? 0) === 1 && !empty($facebookAppId),
                'appId'   => $facebookAppId ?: null,
            ],
            'apple' => [
                'enabled'     => (int) ($settings['apple_login_status'] ?? 0) === 1 && !empty($appleClientId),
                'clientId'    => $appleClientId,
                'redirectUri' => $appleRedirect,
            ],
        ];
    }

    private function toCustomer(User $user): StorefrontCustomer
    {
        return new StorefrontCustomer(
            id:               (int) $user->id,
            firstName:        $user->f_name,
            lastName:         $user->l_name,
            email:            $user->email,
            phone:            $user->phone,
            imageUrl:         $user->image_full_url,
            isPhoneVerified:  (bool) $user->is_phone_verified,
            isEmailVerified:  (bool) $user->is_email_verified,
            walletBalance:    (float) ($user->wallet_balance ?? 0),
            loyaltyPoint:     (float) ($user->loyalty_point ?? 0),
            refCode:          $user->ref_code,
            createdAt:        $user->created_at?->toIso8601String(),
        );
    }

    private function guardActiveStatus(?User $user): void
    {
        if (!$user || !$user->status) {
            Auth::guard('customer')->logout();
            throw ValidationException::withMessages([
                '_form' => __('messages.your_account_is_blocked'),
            ]);
        }
    }

    private function ensureRefCode(User $user): void
    {
        if (!$user->ref_code) {
            DB::table('users')->where('id', $user->id)
                ->update(['ref_code' => Helpers::generate_referer_code($user)]);
        }
    }

    private function splitName(string $name): array
    {
        $parts = \explode(' ', \trim($name), 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }
        return '+' . \ltrim(\trim($phone), '+');
    }

    public function loginMethods(): array
    {
        $s = $this->loginSettings();

        // Reflect admin toggles. If the manual-login key was never seeded
        // (fresh / un-migrated install) default it to enabled so the
        // storefront isn't locked out; an explicit 0 is respected. OTP is
        // opt-in.
        return [
            'manual' => \array_key_exists('manual_login_status', $s)
                ? (int) $s['manual_login_status'] === 1
                : true,
            'otp'    => (int) ($s['otp_login_status'] ?? 0) === 1,
        ];
    }

    private function loginSettings(): array
    {
        return \array_column(
            BusinessSetting::whereIn('key', [
                'manual_login_status', 'otp_login_status', 'social_login_status',
                'google_login_status', 'facebook_login_status', 'apple_login_status',
                'email_verification_status', 'phone_verification_status',
            ])->get(['key', 'value'])->toArray(),
            'value',
            'key',
        );
    }

    private function markVerified(User $user, string $type): void
    {
        if ($type === 'phone') {
            $user->is_phone_verified = 1;
        } else {
            $user->is_email_verified = 1;
        }
        $user->save();
    }

    private function issuePhoneOtp(string $phone): void
    {
        $scope = $this->scopeFilter();
        $existing = DB::table('phone_verifications')->where('phone', $phone)->where($scope)->first();
        if ($existing && Carbon::parse($existing->updated_at)->diffInSeconds() < self::OTP_INTERVAL_SECONDS) {
            $remaining = round(self::OTP_INTERVAL_SECONDS - Carbon::parse($existing->updated_at)->diffInSeconds());
            throw ValidationException::withMessages([
                'otp' => __('messages.please_try_again_after_') . $remaining . ' ' . __('messages.seconds'),
            ]);
        }

        $otp = in_array(getEnvMode(), ['test', 'demo'], true) ? '123456' : (string) \rand(100000, 999999);

        DB::table('phone_verifications')->updateOrInsert(
            ['phone' => $phone] + $scope,
            [
                'token'         => $otp,
                'otp_hit_count' => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        );

        if (in_array(getEnvMode(), ['test', 'demo'], true)) {
            return;
        }

        if ($this->sendSms($phone, $otp) !== 'success') {
            throw ValidationException::withMessages([
                'otp' => __('messages.failed_to_send_sms'),
            ]);
        }
    }

    private function issueEmailOtp(string $email, string $name): void
    {
        $otp = in_array(getEnvMode(), ['test', 'demo'], true) ? '123456' : (string) \rand(100000, 999999);

        DB::table('email_verifications')->updateOrInsert(
            ['email' => $email] + $this->scopeFilter(),
            ['token' => $otp, 'created_at' => now(), 'updated_at' => now()],
        );

        if (in_array(getEnvMode(), ['test', 'demo'], true)) {
            return;
        }

        try {
            if (\config('mail.status') && Helpers::get_mail_status('registration_otp_mail_status_user') == '1') {
                Mail::to($email)->send(new EmailVerification($otp, $name));
                return;
            }
        } catch (\Throwable $e) {
            \info($e->getMessage());
        }

        throw ValidationException::withMessages([
            'otp' => __('messages.failed_to_send_mail'),
        ]);
    }

    private function bumpPhoneOtpHit(string $phone): void
    {
        $scope = $this->scopeFilter();
        $row = DB::table('phone_verifications')->where('phone', $phone)->where($scope)->first();
        if (!$row) {
            return;
        }

        if (isset($row->temp_block_time)
            && Carbon::parse($row->temp_block_time)->diffInSeconds() <= self::OTP_BLOCK_SECONDS) {
            $time = round(self::OTP_BLOCK_SECONDS - Carbon::parse($row->temp_block_time)->diffInSeconds());
            throw ValidationException::withMessages([
                'otp' => __('messages.please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans(),
            ]);
        }

        if ($row->is_temp_blocked == 1
            && Carbon::parse($row->updated_at)->diffInSeconds() >= self::OTP_INTERVAL_SECONDS) {
            DB::table('phone_verifications')->where('phone', $phone)->where($scope)->update([
                'otp_hit_count'    => 0,
                'is_temp_blocked'  => 0,
                'temp_block_time'  => null,
                'updated_at'       => now(),
            ]);
        }

        if ($row->otp_hit_count >= self::OTP_MAX_HITS
            && Carbon::parse($row->updated_at)->diffInSeconds() < self::OTP_INTERVAL_SECONDS
            && $row->is_temp_blocked == 0) {
            DB::table('phone_verifications')->where('phone', $phone)->where($scope)->update([
                'is_temp_blocked' => 1,
                'temp_block_time' => now(),
                'updated_at'      => now(),
            ]);
            throw ValidationException::withMessages([
                'otp' => __('messages.Too_many_attemps'),
            ]);
        }

        DB::table('phone_verifications')->where('phone', $phone)->where($scope)->update([
            'otp_hit_count'   => DB::raw('otp_hit_count + 1'),
            'temp_block_time' => null,
            'updated_at'      => now(),
        ]);
    }

    private function bumpPasswordResetHit(string $type, string $value): void
    {
        $scope = $this->scopeFilter();
        $row = DB::table('password_resets')->where($type, $value)->where($scope)->first();
        if (!$row) {
            return;
        }

        if (isset($row->temp_block_time)
            && Carbon::parse($row->temp_block_time)->diffInSeconds() <= self::OTP_BLOCK_SECONDS) {
            $time = round(self::OTP_BLOCK_SECONDS - Carbon::parse($row->temp_block_time)->diffInSeconds());
            throw ValidationException::withMessages([
                'otp' => __('messages.please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans(),
            ]);
        }

        if ($row->is_temp_blocked == 1
            && Carbon::parse($row->created_at)->diffInSeconds() >= self::OTP_INTERVAL_SECONDS) {
            DB::table('password_resets')->where($type, $value)->where($scope)->update([
                'otp_hit_count'   => 0,
                'is_temp_blocked' => 0,
                'temp_block_time' => null,
                'created_at'      => now(),
            ]);
        }

        if ($row->otp_hit_count >= self::OTP_MAX_HITS
            && Carbon::parse($row->created_at)->diffInSeconds() < self::OTP_INTERVAL_SECONDS
            && $row->is_temp_blocked == 0) {
            DB::table('password_resets')->where($type, $value)->where($scope)->update([
                'is_temp_blocked' => 1,
                'temp_block_time' => now(),
                'created_at'      => now(),
            ]);
            throw ValidationException::withMessages([
                'otp' => __('messages.Too_many_attemps'),
            ]);
        }

        DB::table('password_resets')->where($type, $value)->where($scope)->update([
            'otp_hit_count'   => DB::raw('otp_hit_count + 1'),
            'temp_block_time' => null,
            'created_at'      => now(),
        ]);
    }

    private function sendSms(string $phone, string $token): ?string
    {
        $publishedStatus = 0;
        $paymentPublished = \config('get_payment_publish_status');
        if (isset($paymentPublished[0]['is_published'])) {
            $publishedStatus = $paymentPublished[0]['is_published'];
        }

        return $publishedStatus == 1
            ? SmsGateway::send($phone, $token)
            : SMS_module::send($phone, $token);
    }

    private function resolveReferrer(string $refCode, string $newPhone, string $firstName, string $lastName): ?int
    {
        $refStatus = BusinessSetting::where('key', 'ref_earning_status')->first()?->value;
        if ($refStatus != '1') {
            throw ValidationException::withMessages([
                'ref_code' => __('messages.referer_disable'),
            ]);
        }

        // Ref codes are per-storefront (composite UNIQUE on ref_code +
        // tenant_id + sub_tenant_id). A code from storefront A is not
        // valid at storefront B and vice versa.
        $referrer = $this->userQuery()->where('ref_code', $refCode)->where($this->scopeFilter())->first();
        if (!$referrer || !$referrer->status) {
            throw ValidationException::withMessages([
                'ref_code' => __('messages.referer_code_not_found'),
            ]);
        }

        // "Has this phone already claimed a referral here?" — scope via the
        // user relation so a phone used at storefront A doesn't block its
        // independent use at storefront B. WalletTransaction has no scope
        // column of its own; the User model's HostScope is bypassed via
        // withoutGlobalScopes() so the join can see other-scope users too,
        // then we explicitly narrow to the current scope.
        if (WalletTransaction::where('reference', $newPhone)
                ->whereHas('user', fn ($q) => $q->withoutGlobalScope(HostScope::class)->where($this->scopeFilter()))
                ->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('Referrer code already used'),
            ]);
        }

        if (Helpers::getNotificationStatusData('customer', 'customer_new_referral_join', 'push_notification_status')
            && $referrer->cm_firebase_token) {
            $notif = [
                'title'       => __('messages.Your_referral_code_is_used_by') . ' ' . $firstName . ' ' . $lastName,
                'description' => __('Be prepare to receive when they complete there first purchase'),
                'order_id'    => 1,
                'image'       => '',
                'type'        => 'referral_code',
            ];
            Helpers::send_push_notif_to_device($referrer->cm_firebase_token, $notif);
            DB::table('user_notifications')->insert([
                'data'       => \json_encode($notif),
                'user_id'    => $referrer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $referrer->id;
    }

    private function verifySocialToken(string $provider, array $payload): array
    {
        $token    = $payload['token'];
        $uniqueId = $payload['unique_id'];

        try {
            if ($provider === 'google') {
                $url = $payload['access_token']
                    ? 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $token
                    : 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $token;
                $res = (new Client())->request('GET', $url);
                return \json_decode($res->getBody()->getContents(), true) ?: [];
            }

            if ($provider === 'facebook') {
                $url = 'https://graph.facebook.com/' . $uniqueId
                    . '?access_token=' . $token . '&fields=name,email';
                $res = (new Client())->request('GET', $url);
                return \json_decode($res->getBody()->getContents(), true) ?: [];
            }

            if ($provider === 'apple') {
                $appleSettings = BusinessSetting::where('key', 'apple_login')->first();
                if (!$appleSettings) {
                    throw new \RuntimeException('Apple login not configured');
                }
                $cfg = \json_decode($appleSettings->value)[0];

                $sub        = $cfg->client_id;
                $keyContent = \file_get_contents('storage/app/public/apple-login/' . $cfg->service_file);

                $clientSecret = JWT::encode([
                    'iss' => $cfg->team_id,
                    'iat' => \strtotime('now'),
                    'exp' => \strtotime('+60days'),
                    'aud' => 'https://appleid.apple.com',
                    'sub' => $sub,
                ], $keyContent, 'ES256', $cfg->key_id);

                $redirectUri = $cfg->redirect_url_react ?? 'www.example.com/apple-callback';

                $res = Http::asForm()->post('https://appleid.apple.com/auth/token', [
                    'grant_type'    => 'authorization_code',
                    'code'          => $uniqueId,
                    'redirect_uri'  => $redirectUri,
                    'client_id'     => $sub,
                    'client_secret' => $clientSecret,
                ]);

                $idToken = $res['id_token'] ?? null;
                if (!$idToken) {
                    throw new \RuntimeException('Apple did not return an id_token');
                }
                $claims = \explode('.', $idToken)[1] ?? '';
                return \json_decode(\base64_decode($claims), true) ?: [];
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                '_form' => __('messages.wrong_credential') ?? 'Wrong credential',
            ]);
        }

        throw ValidationException::withMessages([
            '_form' => 'Unsupported provider',
        ]);
    }
}
