<?php

namespace App\Services;

use App\Models\Anomalie;
use App\Models\Eleve;
use App\Models\Fenetre;
use App\Models\Passage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnomalieDetectionService
{
    public const SEUIL_DOUBLE_SCAN_SECONDES = 60;
    public const SEUIL_HORLOGE_DECALEE_SECONDES = 120;

    public function __construct(
        private readonly ?NotificationService $notificationService = null
    ) {}

    /**
     * Analyse un passage nouvellement enregistré et crée les anomalies pertinentes.
     *
     * @return array<int, Anomalie>
     */
    public function analyserPassage(Passage $passage): array
    {
        $anomaliesCreees = [];

        $passage->loadMissing(['eleve.itineraire.fenetres', 'badge']);
        $eleve = $passage->eleve;

        if (! $eleve) {
            return $anomaliesCreees;
        }

        $tz = config('app.timezone', 'Africa/Douala');
        $horodatageRetenu = $passage->heure_retenue
            ? Carbon::parse($passage->heure_retenue)->timezone($tz)
            : Carbon::parse($passage->heure_locale ?? now())->timezone($tz);

        $dateLocale = $horodatageRetenu->toDateString();
        $jourIso = (int) $horodatageRetenu->dayOfWeekIso;
        $heurePassage = $horodatageRetenu->format('H:i:s');

        // 1. Contrôle horloge appareil vs serveur
        if ($passage->horodatage_appareil && $passage->horodatage_serveur) {
            $ecartSecondes = abs(
                Carbon::parse($passage->horodatage_serveur)->diffInSeconds(Carbon::parse($passage->horodatage_appareil))
            );
            $horlogeDecalee = $ecartSecondes > self::SEUIL_HORLOGE_DECALEE_SECONDES;

            $updates = [];
            if ($horlogeDecalee && ! $passage->horloge_decalee) {
                $updates['horloge_decalee'] = true;
            }
            if ($passage->heure_locale && $passage->heure_retenue) {
                $loc = Carbon::parse($passage->heure_locale);
                $ret = Carbon::parse($passage->heure_retenue);
                if ($ret->lt($loc->copy()->subSeconds(self::SEUIL_HORLOGE_DECALEE_SECONDES))) {
                    $updates['heure_incoherente'] = true;
                }
            }
            if ($updates !== []) {
                $passage->update($updates);
            }
        }

        // 1bis. Scan hors plage normale d'ouverture de l'établissement (ex. nuit / dimanche)
        $estJourOuvreNormal = $jourIso >= 1 && $jourIso <= 6; // 1 = lundi .. 6 = samedi
        $dansPlageOuverture = $this->heureDansFenetre($heurePassage, '06:00:00', '20:00:00');

        if (! $estJourOuvreNormal || ! $dansPlageOuverture) {
            $anomaliesCreees[] = $this->enregistrerAnomalie(
                eleveId: $eleve->id,
                type: 'hors_heures_etablissement',
                dateLocale: $dateLocale,
                fenetreId: null,
                passageId: $passage->id
            );
        }

        // 2. Badge manquant ou non actif
        $badge = $passage->badge;
        if (! $badge || $badge->etat !== 'actif') {
            $anomaliesCreees[] = $this->enregistrerAnomalie(
                eleveId: $eleve->id,
                type: 'badge_non_actif',
                dateLocale: $dateLocale,
                fenetreId: null,
                passageId: $passage->id
            );
        }

        // 3. Double scan rapproché (< 60s)
        $seuilDoubleScan = $horodatageRetenu->copy()->subSeconds(self::SEUIL_DOUBLE_SCAN_SECONDES)->utc();
        $passageCourantUtc = $horodatageRetenu->copy()->utc();

        $passagePrecedent = Passage::query()
            ->where('eleve_id', $eleve->id)
            ->where('id', '!=', $passage->id)
            ->where('point_controle_id', $passage->point_controle_id)
            ->whereBetween('heure_retenue', [$seuilDoubleScan, $passageCourantUtc])
            ->orderByDesc('heure_retenue')
            ->first();

        if ($passagePrecedent) {
            $fenetreAssociee = $this->trouverFenetrePourPoint($eleve, $jourIso, (int) $passage->point_controle_id, $passage->sens);
            $anomaliesCreees[] = $this->enregistrerAnomalie(
                eleveId: $eleve->id,
                type: 'double_scan',
                dateLocale: $dateLocale,
                fenetreId: $fenetreAssociee?->id,
                passageId: $passage->id
            );
        }

        // 4. Contrôle itinéraire et fenêtre horaire
        $itineraire = $eleve->itineraire;
        if ($itineraire) {
            $fenetresDuJour = $itineraire->fenetres
                ->filter(fn (Fenetre $f) => $f->sAppliqueLeJour($jourIso));

            $fenetresMemeSens = $passage->sens
                ? $fenetresDuJour->where('sens', $passage->sens)
                : $fenetresDuJour;

            if ($fenetresMemeSens->isNotEmpty()) {
                $fenetrePoint = $fenetresMemeSens->firstWhere('point_controle_id', $passage->point_controle_id);

                if (! $fenetrePoint) {
                    $anomaliesCreees[] = $this->enregistrerAnomalie(
                        eleveId: $eleve->id,
                        type: 'point_hors_itineraire',
                        dateLocale: $dateLocale,
                        fenetreId: null,
                        passageId: $passage->id
                    );
                } elseif (! $this->heureDansFenetre($heurePassage, (string) $fenetrePoint->heure_debut, (string) $fenetrePoint->heure_fin)) {
                    $anomaliesCreees[] = $this->enregistrerAnomalie(
                        eleveId: $eleve->id,
                        type: 'hors_fenetre',
                        dateLocale: $dateLocale,
                        fenetreId: $fenetrePoint->id,
                        passageId: $passage->id
                    );
                }
            }
        }

        return array_values(array_filter($anomaliesCreees));
    }

    /**
     * Détecte les passages manquants pour une date donnée (ex. fin de fenêtre ou fin de journée).
     *
     * @return array<int, Anomalie>
     */
    public function detecterPassagesManquants(string $dateLocale, ?Carbon $referenceTemps = null): array
    {
        $tz = config('app.timezone', 'Africa/Douala');
        $date = Carbon::parse($dateLocale, $tz)->startOfDay();
        $maintenant = ($referenceTemps ?? Carbon::now($tz))->timezone($tz);
        $jourIso = (int) $date->dayOfWeekIso;
        $estAujourdHui = $date->isSameDay($maintenant);
        $heureActuelle = $maintenant->format('H:i:s');

        $anomaliesCreees = [];

        $eleves = Eleve::query()
            ->where('etat', 'actif')
            ->whereNotNull('itineraire_id')
            ->with('itineraire.fenetres')
            ->get();

        foreach ($eleves as $eleve) {
            if ($this->aExceptionJour($eleve, $date->toDateString())) {
                continue;
            }

            foreach ($eleve->itineraire->fenetres as $fenetre) {
                if (! $fenetre->sAppliqueLeJour($jourIso)) {
                    continue;
                }

                // Ne marquer manquant que si la fenêtre est terminée (pour la date courante) ou si la date est passée
                if ($estAujourdHui && $heureActuelle <= (string) $fenetre->heure_fin) {
                    continue;
                }

                $debutJourUtc = $date->copy()->startOfDay()->utc();
                $finJourUtc = $date->copy()->endOfDay()->utc();

                $query = Passage::query()
                    ->where('eleve_id', $eleve->id)
                    ->where('point_controle_id', $fenetre->point_controle_id)
                    ->whereBetween('heure_retenue', [$debutJourUtc, $finJourUtc]);

                if ($fenetre->sens && Schema::hasColumn('passages', 'sens')) {
                    $query->where('sens', $fenetre->sens);
                }

                if (! $query->exists()) {
                    $anomaliesCreees[] = $this->enregistrerAnomalie(
                        eleveId: $eleve->id,
                        type: 'passage_manquant',
                        dateLocale: $date->toDateString(),
                        fenetreId: $fenetre->id,
                        passageId: null
                    );
                }
            }
        }

        return $anomaliesCreees;
    }

    private function trouverFenetrePourPoint(Eleve $eleve, int $jourIso, int $pointControleId, ?string $sens): ?Fenetre
    {
        if (! $eleve->itineraire) {
            return null;
        }

        return $eleve->itineraire->fenetres->first(function (Fenetre $f) use ($jourIso, $pointControleId, $sens) {
            if (! $f->sAppliqueLeJour($jourIso) || (int) $f->point_controle_id !== $pointControleId) {
                return false;
            }

            return $sens === null || $f->sens === $sens;
        });
    }

    private function heureDansFenetre(string $heure, string $debut, string $fin): bool
    {
        $h = substr($heure, 0, 8);
        $d = substr($debut, 0, 8);
        $f = substr($fin, 0, 8);

        return $h >= $d && $h <= $f;
    }

    private function aExceptionJour(Eleve $eleve, string $dateStr): bool
    {
        if (! Schema::hasTable('exceptions_jour')) {
            return false;
        }

        return DB::table('exceptions_jour')
            ->whereDate('date', $dateStr)
            ->whereIn('type', ['ferie', 'absence_justifiee'])
            ->where(function ($q) use ($eleve) {
                $q->where('eleve_id', $eleve->id)
                    ->orWhere(function ($sub) use ($eleve) {
                        $sub->whereNull('eleve_id')
                            ->where('etablissement_id', $eleve->etablissement_id);
                    });
            })
            ->exists();
    }

    private function enregistrerAnomalie(
        int $eleveId,
        string $type,
        string $dateLocale,
        ?int $fenetreId,
        ?int $passageId
    ): Anomalie {
        $existante = Anomalie::query()
            ->where('type', $type)
            ->where('eleve_id', $eleveId)
            ->whereDate('date_locale', $dateLocale)
            ->when(
                $fenetreId !== null,
                fn ($q) => $q->where('fenetre_id', $fenetreId),
                fn ($q) => $q->whereNull('fenetre_id')->where('passage_id', $passageId)
            )
            ->first();

        if ($existante) {
            return $existante;
        }

        $nouvelle = Anomalie::create([
            'eleve_id' => $eleveId,
            'type' => $type,
            'date_locale' => $dateLocale,
            'fenetre_id' => $fenetreId,
            'passage_id' => $passageId,
            'etat_traitement' => 'ouverte',
        ]);

        ($this->notificationService ?? app(NotificationService::class))->notifierPourAnomalie($nouvelle);

        return $nouvelle;
    }
}