<?php

namespace App\Models;

use App\Scopes\HostScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Default-filters OTP rows to the host scope. Storefront adapter
        // bypasses with withoutGlobalScope + explicit scope filter.
        static::addGlobalScope(new HostScope());
    }
}
