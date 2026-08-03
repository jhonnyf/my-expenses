<?php

namespace App\Console\Commands;

use App\Jobs\GeocodeIssuerJob;
use App\Models\Issuer;
use Illuminate\Console\Command;

class GeocodeExistingIssuers extends Command
{
    protected $signature = 'issuers:geocode';

    protected $description = 'Despacha o geocoding (latitude/longitude) para emitentes já existentes que ainda não têm coordenadas';

    public function handle(): int
    {
        $total = 0;

        Issuer::whereNotNull('city')
            ->where('city', '!=', '')
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->whereNull('latitude')
            ->select('id')
            ->chunkById(200, function ($issuers) use (&$total) {
                foreach ($issuers as $issuer) {
                    GeocodeIssuerJob::dispatch($issuer->id);
                    $total++;
                }
            });

        $this->info("Geocoding despachado para {$total} emitente(s). A fila + rate limit cuidam do ritmo.");

        return self::SUCCESS;
    }
}
