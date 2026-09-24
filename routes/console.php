<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove tokens Sanctum expirados (retém os últimos 30 dias = 720h)
Schedule::command('sanctum:prune-expired --hours=720')->daily();

// Notifica quedas de preço em produtos favoritados pelos usuários
Schedule::command('prices:check-favorite-drops')->daily();

// Confirma notas em contingência pendentes de autorização (portal SEFAZ) e expira as antigas
Schedule::command('invoices:reconcile-pending')->everyThirtyMinutes()->withoutOverlapping();

// Remove exportações de dados pessoais (LGPD) expiradas (App\Models\File::prunable())
Schedule::command('model:prune')->daily();

// Relatórios agendados por e-mail (semanal às segundas, mensal no dia 1º) — plano Pro
Schedule::command('reports:send-scheduled')->dailyAt('06:00')->withoutOverlapping();

// Notificações do sino com mais de 90 dias (LGPD: sem retenção indefinida)
Schedule::command('notifications:prune')->daily();
