<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HandlesMissingAddonRelations
{
    protected function missingAddonRelation(string $foreignKey): BelongsTo
    {
        return $this->belongsTo(self::class, $foreignKey)->whereRaw('1 = 0');
    }

    protected function missingAddonHasMany(): HasMany
    {
        return $this->hasMany(self::class, $this->getKeyName())->whereRaw('1 = 0');
    }

    protected function missingAddonMorphMany(): MorphMany
    {
        return $this->morphMany(self::class, 'missing_addon', $this->getKeyName(), $this->getKeyName())
            ->whereRaw('1 = 0');
    }
}
