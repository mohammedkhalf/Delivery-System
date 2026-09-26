<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Models\DataSetting;
use App\Scopes\HostScope;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use App\Traits\DemoMaskable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Modules\Rental\Entities\Trips;
use Modules\RideShare\Entities\PromotionManagement\AppliedCoupon;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use App\Models\UserAccount;
use Modules\RideShare\Entities\UserManagement\UserLastLocation;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, DemoMaskable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'interest',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_phone_verified' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'order_count' => 'integer',
        'wallet_balance' => 'float',
        'loyalty_point' => 'integer',
        'ref_by' => 'integer',
        'pro_status' => 'boolean',
    ];
    protected $appends = ['image_full_url'];
    public function getImageFullUrlAttribute(){
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('profile',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('profile',$value,'public');
    }

    public function getFullNameAttribute(): string
    {
        return $this->f_name . ' ' . $this->l_name;
    }

    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->where('is_guest', 0);
    }
    public function trips()
    {
        return $this->hasMany(Trips::class)->where('is_guest', 0);
    }

    public function customerRides()
    {
        return $this->hasMany(RideRequest::class, 'customer_id');
    }

    public function lastLocations()
    {
        return $this->hasOne(UserLastLocation::class, 'user_id')->where('type', 'customer');
    }

    public function appliedCoupon()
    {
        return $this->hasOne(AppliedCoupon::class);
    }

    // public function userAccount()
    // {
    //     return $this->hasOne(UserAccount::class, 'user_id');
    // }

    public function addresses(){
        return $this->hasMany(CustomerAddress::class);
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class,'user_id', 'id');
    }

    public function scopeZone($query, $zone_id=null){
        $query->when(is_numeric($zone_id), function ($q) use ($zone_id) {
            return $q->where('zone_id', $zone_id);
        });
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });

        // Per-storefront identity scoping. Default-filters User queries to
        // host rows (`tenant_id = 0 AND sub_tenant_id = 0`). Backend
        // operators (admin/vendor/vendor_employee guards) auto-bypass.
        // Storefront adapter applies its own scope via withoutGlobalScope.
        static::addGlobalScope(new HostScope());

        static::retrieved(function () {
            static $checked = false;
            if ($checked) {
                return;
            }
            $checked = true;

            $lastRun = DataSetting::where([
                'key' => 'subscription_expiry_last_run_at',
                'type' => 'notification_settings',
            ])->first()?->value;

            if ($lastRun && \Illuminate\Support\Carbon::parse($lastRun)->isAfter(now()->subDay())) {
                return;
            }

            try {
                (new class { use \App\Traits\ManagesProCustomerSubscription; })->expireDueSubscriptions();
            } catch (\Throwable $e) {
                info('subscription_expiry_user_booted: ' . $e->getMessage());
            }
        });
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if($model->isDirty('image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }

    public function item_visit_log()
    {
        return $this->morphedByMany(Item::class ,'visitor_log' );
    }

    public function proCustomerSubscriptions()
    {
        return $this->hasMany(\App\Models\ProCustomerSubscription::class);
    }

    public function activeProCustomerSubscription()
    {
        return $this->hasOne(\App\Models\ProCustomerSubscription::class)->where('status', 'active')->latestOfMany();
    }

    public function proCustomerTransactions()
    {
        return $this->hasMany(\App\Models\ProCustomerTransaction::class);
    }
}
