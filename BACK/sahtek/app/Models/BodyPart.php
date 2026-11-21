<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BodyPart extends Model
{
    use HasPublicationStatus, HasTranslations, SoftDeletes;

    public const VIEWS = ['front', 'back', 'both'];

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['code', 'name', 'description', 'view', 'svg_region', 'parent_id', 'sort_order', 'status'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BodyPart::class, 'parent_id');
    }

    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class, 'disease_body_parts')->withPivot('weight');
    }
}
