<?php

namespace App\Services\Orientation;

use App\Models\OrientationRule;
use App\Models\SymptomQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Short fingerprints of the questionnaire and of the rule set in use.
 * Stored on every assessment so that analytics only compare answers collected with the same questions
 * and results produced by the same rules.
 */
class ContentVersion
{
    public function __construct(private readonly ContentGate $gate)
    {
    }

    public function questionnaire(): string
    {
        $parts = [
            SymptomQuestion::published()->orderBy('id')->get(['id', 'updated_at'])->map(fn ($q) => $q->id.':'.$q->updated_at?->timestamp)->implode(','),
            DB::table('question_options')->orderBy('id')->get(['id', 'updated_at'])->map(fn ($o) => $o->id.':'.$o->updated_at)->implode(','),
            DB::table('question_conditions')->orderBy('id')->get(['id', 'updated_at'])->map(fn ($c) => $c->id.':'.$c->updated_at)->implode(','),
            (string) DB::table('translations')->whereIn('translatable_type', ['question', 'question_option'])->max('updated_at'),
        ];

        return 'q-'.substr(sha1(implode('|', $parts)), 0, 10);
    }

    public function ruleset(): string
    {
        $statuses = array_values(array_unique(array_merge($this->gate->usableStatuses(), $this->gate->emergencyStatuses())));
        $fingerprint = OrientationRule::query()
            ->whereIn('validation_status', $statuses)
            ->orderBy('id')
            ->get(['id', 'version', 'validation_status'])
            ->map(fn ($r) => "$r->id:$r->version:$r->validation_status")
            ->implode(',');

        return 'r-'.substr(sha1($fingerprint.'|'.($this->gate->allowsUnreviewed() ? 'dev' : 'prod')), 0, 10);
    }
}
