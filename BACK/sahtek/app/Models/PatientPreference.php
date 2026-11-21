<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientPreference extends Model
{
    protected $fillable = ['patient_id', 'language_code', 'share_location', 'email_notifications'];

    protected $casts = ['share_location' => 'boolean', 'email_notifications' => 'boolean'];
}
