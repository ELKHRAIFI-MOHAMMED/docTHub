<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientConsent extends Model
{
    public const TYPES = ['terms', 'privacy', 'health_data'];

    protected $fillable = ['patient_id', 'consent_type', 'version', 'granted_at', 'revoked_at', 'ip_address'];

    protected $casts = ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
}
