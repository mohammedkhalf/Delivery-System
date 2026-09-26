<?php

namespace App\Builder;

use App\Models\User;
use Modules\Builder\Contracts\PushNotificationProvider as PushNotificationProviderContract;

/**
 * Host adapter — persists customer FCM tokens against `users.cm_firebase_token`.
 *
 * Reads/writes the same column the host's existing mobile API uses
 * (`Api\V1\CustomerController::update_cm_firebase_token`), so a token
 * registered from the storefront is picked up by the host's dispatch
 * pipeline (`Helpers::send_order_notification`, `OrderLogic::*`, …).
 *
 * Single-column design means last-device-wins: a customer signing in on
 * the storefront and on the mobile app will only receive notifications
 * on whichever device registered last. Matches the host's existing
 * model — don't change without coordinating with the mobile team.
 */
class PushNotificationProvider implements PushNotificationProviderContract
{
    public function storeCustomerToken(int $customerId, string $token): void
    {
        // Use ->update() (not save on a fetched model) so we don't pay
        // the cost of hydrating a full User row + dirty-attribute check
        // for a one-column write.
        User::query()
            ->where('id', $customerId)
            ->update(['cm_firebase_token' => $token]);
    }

    public function clearCustomerToken(int $customerId): void
    {
        User::query()
            ->where('id', $customerId)
            ->update(['cm_firebase_token' => null]);
    }
}
