<?php

namespace App\Models;

use App\Scopes\HostScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerifications extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Default-filters email-verification rows to the host scope.
        // Storefront adapter bypasses via withoutGlobalScope.
        static::addGlobalScope(new HostScope());
    }
}
