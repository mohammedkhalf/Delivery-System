<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\Contact;
use Illuminate\Support\Facades\Http;
use Modules\Builder\Contracts\ContactProvider as ContactProviderContract;

/**
 * 6amMart host adapter for ContactProvider.
 *
 * Writes storefront contact messages into the same `contacts` table
 * the admin landing's `HomeController::send_message` uses, so admins
 * see all enquiries in one place (Inbox → Contact Messages).
 *
 * Captcha behaviour mirrors the admin landing: when the host has
 * Google reCAPTCHA v3 enabled in business settings, the v3 token
 * sent from the storefront is verified against Google. When the
 * feature is off, the storefront skips the check entirely (we do
 * NOT port the Gregwar/session captcha — it's blade-only and
 * inappropriate for an SPA).
 */
class ContactProvider implements ContactProviderContract
{
    public function submit(array $payload): array
    {
        $recaptchaError = $this->verifyRecaptcha(
            $payload['recaptchaToken'] ?? null,
            $payload['ip'] ?? null,
        );
        if ($recaptchaError) {
            return ['success' => false, 'errors' => [$recaptchaError]];
        }

        // Property-assignment write mirrors `HomeController::send_message`
        // (the admin-landing path). The Contact model has no `$fillable`,
        // so `Contact::create([...])` would silently drop the values.
        try {
            $contact = new Contact();
            $contact->setAttribute('name',    (string) ($payload['name']    ?? ''));
            $contact->setAttribute('email',   (string) ($payload['email']   ?? ''));
            $contact->setAttribute('subject', (string) ($payload['subject'] ?? ''));
            $contact->setAttribute('message', (string) ($payload['message'] ?? ''));
            $contact->save();
        } catch (\Throwable) {
            return ['success' => false, 'errors' => [[
                'code'    => 'persist',
                'message' => translate('messages.failed_to_send_message') ?: 'Could not send your message.',
            ]]];
        }

        return ['success' => true];
    }

    public function recaptchaSiteKey(): ?string
    {
        $settings = Helpers::get_business_settings('recaptcha');
        if (!is_array($settings) || (int) ($settings['status'] ?? 0) !== 1) {
            return null;
        }
        $key = (string) ($settings['site_key'] ?? '');
        return $key !== '' ? $key : null;
    }

    /**
     * Returns null when reCAPTCHA is disabled OR verification passes.
     * Returns an `errors[]` entry shape when it fails.
     *
     * Behavior intentionally mirrors the host's `HomeController::
     * send_message` + auth `LoginController` — both treat any HTTP 2xx
     * response from Google's verifier as a pass, even when Google
     * returns `success: false` (e.g. `browser-error` from a hostname
     * not on the site_key's domain allow-list). Going stricter here
     * would block users that the rest of the host happily lets
     * through, with no real security gain — captcha across this app
     * is a spam deterrent / network-error guard, not a hard gate.
     *
     * Soft `report()` on a rejection so the admin can still see what
     * Google said in laravel.log without surfacing it to the user.
     */
    private function verifyRecaptcha(?string $token, ?string $ip): ?array
    {
        $settings = Helpers::get_business_settings('recaptcha');
        if (!is_array($settings) || (int) ($settings['status'] ?? 0) !== 1) {
            return null;
        }

        $base = translate('messages.ReCAPTCHA Failed') ?: 'ReCAPTCHA failed.';

        if (!$token) {
            return ['code' => 'recaptcha', 'message' => $base];
        }

        $secret = (string) ($settings['secret_key'] ?? '');
        if ($secret === '') {
            return ['code' => 'recaptcha', 'message' => $base];
        }

        try {
            $response = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip ?? '',
            ]);

            if (!$response->successful()) {
                return ['code' => 'recaptcha', 'message' => $base];
            }

            $body = $response->json();
            if (($body['success'] ?? false) !== true) {
                $codes = is_array($body['error-codes'] ?? null) ? implode(', ', $body['error-codes']) : 'unknown';
                report(new \RuntimeException('reCAPTCHA soft-fail (matching host behavior): ' . $codes));
            }
        } catch (\Throwable $e) {
            report($e);
            return ['code' => 'recaptcha', 'message' => $base];
        }

        return null;
    }
}
