<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportByEmail extends Notification
{
    public function __construct(
        private readonly string $content,
        private readonly string $filename,
        private readonly string $mime,
        private readonly ?string $periodLabel = null,
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
        return (new MailMessage)
            ->subject('Seu relatório de gastos')
            ->greeting('Olá!')
            ->line('Conforme você solicitou, o relatório de gastos segue em anexo.')
            ->when($this->periodLabel !== null, fn (MailMessage $mail) => $mail->line('Período: '.$this->periodLabel.'.'))
            ->attachData($this->content, $this->filename, ['mime' => $this->mime]);
    }
}
