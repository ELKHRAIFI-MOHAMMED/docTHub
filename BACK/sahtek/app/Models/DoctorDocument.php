<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verification documents. Stored on a private disk; never exposed through public endpoints. */
class DoctorDocument extends Model
{
    public const TYPES = ['license', 'diploma', 'identity', 'other'];

    protected $fillable = [
        'doctor_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size', 'status', 'reviewed_by', 'reviewed_at', 'notes',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'size' => 'integer'];

    protected $hidden = ['path', 'disk'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
