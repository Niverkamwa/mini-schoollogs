<?php

namespace App\Services;

use App\Models\Anomalie;
use App\Models\MessageSortant;
use App\Models\Passage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class SyntheseIaService
{
    /**
     * Construit les métriques déterministes à partir des enregistrements réels.
     *
     * @return array<string, mixed>
     */
    public function collecterMetriques(?string $dateLocale = null): array
    {
        $tz = config('app.timezone', 'Africa/Douala');
        $date = $dateLocale ?: Carbon::now($tz)->toDateString();

        $totalPassages = Passage::query()
            ->whereDate('heure_retenue', $date)
            ->count();

        $passagesHorlogeDecalee = Passage::query()
            ->whereDate('heure_retenue', $date)
            ->where('horloge_decalee', true)
            ->count();

        $anomaliesParType = Anomalie::query()
            ->where('date_locale', $date)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $totalAnomalies = array_sum($anomaliesParType);

        $anomaliesOuvertes = Anomalie::query()
            ->where('date_locale', $date)
            ->where('etat_traitement', 'ouverte')
            ->count();

        $messagesEnvoyes = MessageSortant::query()
            ->whereDate('created_at', $date)
            ->count();

        return [
            'date' => $date,
            'total_passages' => $totalPassages,
            'passages_horloge_decalee' => $passagesHorlogeDecalee,
            'total_anomalies' => $totalAnomalies,
            'anomalies_ouvertes' => $anomaliesOuvertes,
            'anomalies_par_type' => $anomaliesParType,
            'messages_envoyes' => $messagesEnvoyes,
        ];
    }

    /**
     * Génère une synthèse encadrée (IA si configurée et joignable, sinon repli déterministe).
     *
     * @param  array<string, mixed>|null  $metriques
     * @return array{source: string, date: string, texte: string, metriques: array<string, mixed>}
     */
    public function genererSynthese(?array $metriques = null): array
    {
        $donnees = $metriques ?? $this->collecterMetriques();
        $apiKey = (string) config('services.ia.key', env('SERVICES_IA_KEY', ''));
        $apiUrl = (string) config('services.ia.url', env('SERVICES_IA_URL', ''));
        $modele = (string) config('services.ia.model', env('SERVICES_IA_MODEL', 'gpt-4o-mini'));

        if ($apiKey !== '' && $apiUrl !== '') {
            try {
                $texteIa = $this->appelerFournisseurIa($apiUrl, $apiKey, $modele, $donnees);
                if ($texteIa !== '') {
                    return [
                        'source' => 'ia',
                        'date' => (string) $donnees['date'],
                        'texte' => $texteIa,
                        'metriques' => $donnees,
                    ];
                }
            } catch (\Throwable) {
                // Repli déterministe garanti en cas d'indisponibilité ou d'erreur API
            }
        }

        return [
            'source' => 'deterministe',
            'date' => (string) $donnees['date'],
            'texte' => $this->construireTexteDeterministe($donnees),
            'metriques' => $donnees,
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function construireTexteDeterministe(array $donnees): string
    {
        $date = $donnees['date'] ?? date('Y-m-d');
        $passages = (int) ($donnees['total_passages'] ?? 0);
        $decales = (int) ($donnees['passages_horloge_decalee'] ?? 0);
        $anomalies = (int) ($donnees['total_anomalies'] ?? 0);
        $ouvertes = (int) ($donnees['anomalies_ouvertes'] ?? 0);
        $messages = (int) ($donnees['messages_envoyes'] ?? 0);
        $parType = is_array($donnees['anomalies_par_type'] ?? null) ? $donnees['anomalies_par_type'] : [];

        $detailsAnomalies = 'aucune anomalie détectée';
        if ($anomalies > 0) {
            $segments = [];
            foreach ($parType as $type => $nb) {
                $segments[] = "{$type} ({$nb})";
            }
            $detailsAnomalies = implode(', ', $segments);
        }

        $infoDecalage = $decales > 0
            ? " dont {$decales} avec horloge décalée corrigée par le serveur"
            : '';

        return "Synthèse du {$date} : {$passages} passage(s) validé(s){$infoDecalage}. "
            ."{$anomalies} anomalie(s) relevée(s) ({$detailsAnomalies}), dont {$ouvertes} ouverte(s). "
            ."{$messages} notification(s) parent enregistrée(s).";
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function appelerFournisseurIa(string $apiUrl, string $apiKey, string $modele, array $donnees): string
    {
        $promptSysteme = 'Tu es un assistant scolaire factuel. Résume uniquement les métriques JSON fournies en 2 à 3 phrases claires en français, sans inventer de faits ni citer de données absentes.';

        $reponse = Http::timeout(4)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($apiUrl, [
                'model' => $modele,
                'messages' => [
                    ['role' => 'system', 'content' => $promptSysteme],
                    ['role' => 'user', 'content' => json_encode($donnees, JSON_UNESCAPED_UNICODE)],
                ],
                'temperature' => 0.1,
                'max_tokens' => 180,
            ]);

        if (! $reponse->successful()) {
            return '';
        }

        $contenu = data_get($reponse->json(), 'choices.0.message.content', '');

        return is_string($contenu) ? trim($contenu) : '';
    }
}