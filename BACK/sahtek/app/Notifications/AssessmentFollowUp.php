<?php

namespace App\Notifications;

use App\Models\SymptomAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent once, about 7 days after an evaluation: "what did the doctor conclude?". */
class AssessmentFollowUp extends Notification
{
    use Queueable;

    public function __construct(public readonly SymptomAssessment $assessment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('docthub.frontend_url'), '/').'/results/'.$this->assessment->uuid.'#follow-up';

        return (new MailMessage())
            ->subject(__('summary.followup_subject'))
            ->greeting(__('summary.followup_greeting', ['name' => $notifiable->patient?->first_name ?? '']))
            ->line(__('summary.followup_line'))
            ->action(__('summary.followup_action'), $url)
            ->line(__('summary.followup_privacy'));
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'assessment_follow_up', 'assessment_id' => $this->assessment->uuid];
    }
}
