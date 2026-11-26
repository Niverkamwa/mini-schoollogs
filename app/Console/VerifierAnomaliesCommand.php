<?php

namespace App\Console\Commands;

use App\Services\AnomalieDetectionService;
use Illuminate\Console\Command;

class VerifierAnomaliesCommand extends Command
{
    protected $signature = 'anomalies:verifier {--date= : Date locale au format Y-m-d}';

    protected $description = 'Détecte les passages manquants pour une date donnée';

    public function handle(AnomalieDetectionService $service): int
    {
        $date = $this->option('date') ?: now(config('app.timezone', 'Africa/Douala'))->toDateString();
        $anomalies = $service->detecterPassagesManquants($date);

        $this->info(count($anomalies) . " anomalie(s) de type passage_manquant enregistrée(s) pour le {$date}.");

        return self::SUCCESS;
    }
}