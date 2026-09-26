<?php

namespace App\Models;

use App\Scopes\HostScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // See app/Scopes/HostScope.php — default-filters to host scope
        // unless the current request is admin/vendor (backend operators
        // bypass). Storefront adapter calls withoutGlobalScope explicitly.
        static::addGlobalScope(new HostScope());
    }
}
