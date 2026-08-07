<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Central\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class DemoRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly DemoRequest $demoRequest,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->demoRequest;

        return (new MailMessage)
            ->subject('Nova solicitação de demonstração — '.$this->value($request->company))
            ->replyTo($request->email, $request->name)
            ->greeting('Nova solicitação de demonstração')
            ->line('Nome: '.$this->value($request->name))
            ->line('E-mail: '.$this->value($request->email))
            ->line('Empresa: '.$this->value($request->company))
            ->line('Cidade: '.$this->value($request->city))
            ->line('Cargo: '.$this->value($request->role))
            ->line('Contexto do terreno: '.$this->value($request->land_context))
            ->line('Origem: '.$this->value($request->source))
            ->line('Página: '.$this->value($request->page));
    }

    private function value(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Não informado';
        }

        return str_replace(["\r", "\n"], ' ', $value);
    }
}
