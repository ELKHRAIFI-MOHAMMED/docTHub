<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Doctor extends Model
{
    use HasPublicationStatus, HasTranslations, SoftDeletes;

    public const VERIFICATION_STATUSES = ['pending', 'verified', 'rejected', 'suspended'];

    protected array $translatable = ['bio', 'qualifications'];

    protected $fillable = [
        'user_id', 'title', 'first_name', 'last_name', 'slug', 'photo_path', 'bio', 'qualifications',
        'years_of_experience', 'email', 'phone', 'website', 'consultation_fee', 'fee_currency',
        'verification_status', 'verified_at', 'verified_by', 'is_demo', 'status',
    ];

    protected $appends = ['full_name', 'photo_url'];

    protected $casts = [
        'is_demo' => 'boolean',
        'verified_at' => 'datetime',
        'consultation_fee' => 'decimal:2',
        'years_of_experience' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Doctor $doctor) {
            if (! $doctor->slug) {
                $base = Str::slug($doctor->first_name.' '.$doctor->last_name) ?: 'doctor';
                $slug = $base;
                $i = 2;
                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $doctor->slug = $slug;
            }
        });
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties')->withPivot('is_primary');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'doctor_languages');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DoctorLocation::class);
    }

    public function primaryLocation(): HasOne
    {
        return $this->hasOne(DoctorLocation::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DoctorDocument::class);
    }

    /** Publicly listed = published and verified. */
    public function scopePubliclyVisible(Builder $q): Builder
    {
        return $q->where('doctors.status', 'published')->where('doctors.verification_status', 'verified');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->title ? $this->title.' ' : '').$this->first_name.' '.$this->last_name);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk(config('docthub.public_disk'))->url($this->photo_path) : null;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
