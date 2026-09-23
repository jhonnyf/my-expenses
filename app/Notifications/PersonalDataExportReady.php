<?php

namespace App\Notifications;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PersonalDataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly File $file) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'account.export.download',
            now()->addDays(7),
            ['file' => $this->file->id]
        );

        return (new MailMessage)
            ->subject('Seus dados estão prontos para download')
            ->greeting('Olá!')
            ->line('Concluímos a exportação dos seus dados pessoais, conforme você solicitou.')
            ->action('Baixar meus dados', $url)
            ->line('Por segurança, o link fica disponível por 7 dias. Depois disso, será preciso solicitar uma nova exportação.');
    }
}
