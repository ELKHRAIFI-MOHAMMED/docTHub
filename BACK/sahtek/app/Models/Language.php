<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'native_name', 'direction', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Active language codes, cached per request. */
    public static function activeCodes(): array
    {
        static $codes = null;
        if ($codes === null || app()->runningUnitTests()) {
            try {
                $codes = static::active()->pluck('code')->all();
            } catch (\Throwable) {
                $codes = [];
            }
        }

        return $codes ?: ['en'];
    }
}
