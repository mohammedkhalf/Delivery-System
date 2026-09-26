<?php

namespace App\Builder;

use App\Models\Newsletter;
use Modules\Builder\Contracts\NewsletterProvider as NewsletterProviderContract;

/**
 * 6amMart host adapter for NewsletterProvider.
 *
 * Writes storefront footer newsletter subscriptions into the same
 * `newsletters` table the admin landing's
 * `NewsletterController::newsLetterSubscribe` uses, with the same
 * duplicate-email guard, so admins see all subscribers in one place.
 */
class NewsletterProvider implements NewsletterProviderContract
{
    public function subscribe(string $email): array
    {
        $email = strtolower(trim($email));

        if (Newsletter::where('email', $email)->exists()) {
            return ['success' => false, 'errors' => [[
                'code'    => 'exists',
                'message' => translate('messages.subscription_exist') ?: 'You are already subscribed.',
            ]]];
        }

        try {
            Newsletter::create(['email' => $email]);
        } catch (\Throwable) {
            return ['success' => false, 'errors' => [[
                'code'    => 'persist',
                'message' => translate('messages.subscription_failed') ?: 'Could not subscribe right now.',
            ]]];
        }

        return ['success' => true];
    }
}
