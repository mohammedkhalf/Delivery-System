<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProCustomerSubscriptionPlan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'price'    => 'float',
        'duration' => 'integer',
        'status'   => 'integer',
    ];

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function subscriptions()
    {
        return $this->hasMany(ProCustomerSubscription::class, 'plan_id');
    }

    public function activeSubscriptions()
    {
        return $this->hasMany(ProCustomerSubscription::class, 'plan_id')->where('status', 'active');
    }

    public function getPlanNameAttribute($value)
    {
        foreach ($this->translations as $translation) {
            if ($translation['key'] === 'plan_name') {
                return $translation['value'];
            }
        }
        return $value;
    }

    protected static function booted()
    {
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                $query->where('locale', app()->getLocale());
            }]);
        });
    }
}
