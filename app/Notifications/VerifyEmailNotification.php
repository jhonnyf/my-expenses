<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu endereço de e-mail')
            ->greeting('Olá!')
            ->line('Antes de acessar sua conta, confirme seu endereço de e-mail clicando no botão abaixo.')
            ->action('Confirmar e-mail', $url)
            ->line('Se você não criou uma conta, nenhuma ação adicional é necessária.');
    }
}
