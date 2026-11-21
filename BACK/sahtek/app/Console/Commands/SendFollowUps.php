<?php

namespace App\Console\Commands;

use App\Models\AssessmentOutcome;
use App\Models\SymptomAssessment;
use App\Notifications\AssessmentFollowUp;
use Illuminate\Console\Command;

/**
 * Daily: ask patients, 7 days after an evaluation, what the doctor concluded.
 * Only once per assessment, only to active accounts that accept e-mails and whose health-data consent is active.
 * Emergency cases are included (we want to know whether the patient went to the emergency department).
 */
class SendFollowUps extends Command
{
    protected $signature = 'docthub:send-follow-ups {--days=7 : Days after evaluation} {--dry-run}';

    protected $description = 'Send post-consultation follow-up requests to patients';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $sent = 0;

        $assessments = SymptomAssessment::query()
            ->where('status', 'completed')
            ->whereNotNull('patient_id')
            ->whereBetween('evaluated_at', [now()->subDays(30), now()->subDays($days)])
            ->whereDoesntHave('outcome', fn ($q) => $q->where(fn ($w) => $w->whereNotNull('reminder_sent_at')->orWhereNotNull('collected_at')))
            ->whereHas('patient.user', fn ($q) => $q->where('is_active', true))
            ->whereHas('patient.consents', fn ($q) => $q->where('consent_type', 'health_data')->whereNull('revoked_at'))
            ->with(['patient.user', 'patient.preference'])
            ->get();

        foreach ($assessments as $assessment) {
            $patient = $assessment->patient;
            if ($patient->preference && ! $patient->preference->email_notifications) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("would notify assessment {$assessment->uuid}");
                continue;
            }
            $user = $patient->user;
            $user->notify((new AssessmentFollowUp($assessment))->locale($user->preferred_language ?: 'en'));
            AssessmentOutcome::updateOrCreate(['assessment_id' => $assessment->id], ['reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("Follow-up requests sent: $sent");

        return self::SUCCESS;
    }
}
