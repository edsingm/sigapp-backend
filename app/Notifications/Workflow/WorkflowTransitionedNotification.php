<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowTransitionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $terrenoNome,
        private readonly string $previousStage,
        private readonly string $newStage,
        private readonly string $newLabel,
        private readonly ?string $reasonNotes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
