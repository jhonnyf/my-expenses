<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;

class PruneExpiredExports extends Command
{
    private const RETENTION_DAYS = 7;

    protected $signature = 'exports:prune-expired';

    protected $description = 'Remove exportações de dados pessoais com mais de 7 dias (arquivo + registro)';

    public function handle(): int
    {
        $expired = File::where('collection', 'personal-data-export')
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->get();

        // ->each->delete() (não delete() em massa) para acionar o hook de
        // File::booted() que remove o arquivo físico do disco.
        $expired->each->delete();

        $this->info("{$expired->count()} exportação(ões) removida(s).");

        return self::SUCCESS;
    }
}
