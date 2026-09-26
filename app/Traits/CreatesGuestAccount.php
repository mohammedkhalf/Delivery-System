<?php

namespace App\Traits;

use App\CentralLogics\Helpers;
use App\Mail\CustomerRegistration;
use App\Models\Cart;
use App\Models\User;
use App\Scopes\HostScope;
use Illuminate\Support\Facades\Mail;

trait CreatesGuestAccount
{
    protected function createNewUser($request)
    {
        if (!$request->create_new_user) {
            return false;
        }

        $createsAt = ['tenant_id' => 0, 'sub_tenant_id' => 0];

        $validationError = match (true) {
            !$request->password => [
                'status_code' => 403,
                'message'     => translate('messages.password_is_required'),
                'code'        => 'password',
            ],
            User::withoutGlobalScope(HostScope::class)
                ->where('phone', $request->contact_person_number)
                ->where($createsAt)
                ->exists() => [
                'status_code' => 403,
                'message'     => translate('messages.phone_already_taken'),
                'code'        => 'phone_person_email',
            ],
            User::withoutGlobalScope(HostScope::class)
                ->where('email', $request->contact_person_email)
                ->where($createsAt)
                ->exists() => [
                'status_code' => 403,
                'message'     => translate('messages.email_already_taken'),
                'code'        => 'contact_person_email',
            ],
            default => null,
        };

        if ($validationError) {
            return $validationError;
        }

        $user = new User();
        $user->f_name = $request->contact_person_name;
        $user->email = $request->contact_person_email;
        $user->phone = $request->contact_person_number;
        $user->password = bcrypt($request->password);
        $user->ref_code = Helpers::generate_referer_code($user);
        $user->login_medium = 'manual';
        $user->tenant_id     = $createsAt['tenant_id'];
        $user->sub_tenant_id = $createsAt['sub_tenant_id'];
        $user->save();

        try {
            if (config('mail.status') && $request->contact_person_email && Helpers::get_mail_status('registration_mail_status_user') == '1' && Helpers::getNotificationStatusData('customer', 'customer_registration', 'mail_status')) {
                Mail::to($request->contact_person_email)->send(new CustomerRegistration($request->contact_person_name));
            }
        } catch (\Exception $exception) {
            info('createNewUser' ,[$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
        }
        if ($request->guest_id  && isset($user->id)) {

            Cart::where('user_id', $request->guest_id)->update(['user_id' => $user->id, 'is_guest' => 0]);
        }

        return ['newUser' => true, 'user' => $user, 'token' => $user->createToken('RestaurantCustomerAuth')->accessToken];
    }
}
