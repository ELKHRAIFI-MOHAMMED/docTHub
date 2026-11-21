<?php

namespace App\Models\Concerns;

use App\Services\Orientation\ContentGate;
use Illuminate\Database\Eloquent\Builder;

/**
 * Medical content review workflow: draft → in_review → approved (or rejected / retired).
 * Only `approved` content is usable in production; see ContentGate.
 */
trait HasReviewStatus
{
    public const REVIEW_STATUSES = ['draft', 'in_review', 'approved', 'rejected', 'retired'];

    /** Approval date stamped when an approved item has a named reviewer and no date yet. */
    protected static function bootHasReviewStatus(): void
    {
        static::saving(function ($model) {
            if ($model->getAttribute($model->reviewColumn()) === 'approved' && filled($model->getAttribute('reviewed_by_name'))
                && ! $model->getAttribute('reviewed_at')) {
                $model->setAttribute('reviewed_at', now());
            }
        });
    }

    public function reviewColumn(): string
    {
        return property_exists($this, 'reviewColumn') ? $this->reviewColumn : 'review_status';
    }

    public function scopeUsableForOrientation(Builder $query): Builder
    {
        return $query->whereIn($this->getTable().'.'.$this->reviewColumn(), app(ContentGate::class)->usableStatuses());
    }

    public function isReviewed(): bool
    {
        return $this->getAttribute($this->reviewColumn()) === 'approved';
    }
}
