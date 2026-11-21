<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasPublicationStatus
{
    public const STATUSES = ['draft', 'published', 'archived'];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->getTable().'.status', 'published');
    }
}
