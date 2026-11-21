<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAlert extends Model
{
    protected $fillable = ['assessment_id', 'rule_id', 'severity', 'message', 'emergency_number'];
}
