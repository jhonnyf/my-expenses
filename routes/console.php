<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove tokens Sanctum expirados (retém os últimos 30 dias = 720h)
Schedule::command('sanctum:prune-expired --hours=720')->daily();
