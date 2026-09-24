<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=90 : Apaga as notificações mais antigas que isso}';

    protected $description = 'Remove notificações antigas (retenção de dados pessoais)';

    public function handle(): int
    {
        $deleted = DatabaseNotification::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();

        $this->info("{$deleted} notificação(ões) removida(s).");

        return self::SUCCESS;
    }
}
