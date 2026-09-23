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
            ->subject('Confirme seu e-mail no CestaZen')
            ->greeting('Olá, seja bem-vindo(a)!')
            ->line('Falta só um passo para começar a organizar seus gastos: confirme que este endereço de e-mail é seu.')
            ->action('Confirmar meu e-mail', $url)
            ->line('Se você não criou uma conta no CestaZen, pode ignorar esta mensagem com tranquilidade.');
    }
}
