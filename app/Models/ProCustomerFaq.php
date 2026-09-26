<?php

namespace App\Models;

use App\Traits\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProCustomerFaq extends Model
{
    use HasFactory, ReportFilter;

    protected $guarded = ['id'];

    protected $casts = [
        'priority' => 'integer',
        'status'   => 'integer',
    ];

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function getQuestionAttribute($value)
    {
        foreach ($this->translations as $translation) {
            if ($translation['key'] === 'pro_faq_question') {
                return $translation['value'];
            }
        }
        return $value;
    }

    public function getAnswerAttribute($value)
    {
        foreach ($this->translations as $translation) {
            if ($translation['key'] === 'pro_faq_answer') {
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
