<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use App\Notifications\Workflow\Concerns\RespectsEmailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class WorkflowTransitionedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsEmailPreference;

    public function __construct(
        private readonly string $terrenoNome,
        private readonly string $previousStage,
        private readonly string $newStage,
        private readonly string $newLabel,
        private readonly ?string $reasonNotes,
    ) {}

    protected function notificationCategory(): string
    {
        return 'workflow.transicao';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('SIG.APP — Etapa do terreno atualizada')
            ->view('emails.workflow-transitioned', [
                'terrenoNome' => $this->terrenoNome,
                'previousStage' => $this->previousStage,
                'newStage' => $this->newStage,
                'newLabel' => $this->newLabel,
                'reasonNotes' => $this->reasonNotes,
            ])
            ->text('emails.plain.workflow-transitioned', [
                'terrenoNome' => $this->terrenoNome,
                'previousStage' => $this->previousStage,
                'newStage' => $this->newStage,
                'newLabel' => $this->newLabel,
                'reasonNotes' => $this->reasonNotes,
            ]);
    }
}
