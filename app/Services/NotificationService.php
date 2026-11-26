<?php

namespace App\Services;

use App\Contracts\NotificateurParentInterface;
use App\Models\Anomalie;
use App\Models\MessageSortant;
use Illuminate\Support\Carbon;

class NotificationService
{
    public function __construct(
        private readonly NotificateurParentInterface $notificateur
    ) {}

    /**
     * Génère les notifications pour les parents d'un élève à la suite d'une anomalie.
     *
     * @return array<int, MessageSortant>
     */
    public function notifierPourAnomalie(Anomalie $anomalie): array
    {
        $anomalie->loadMissing(['eleve.parents', 'fenetre.pointControle']);

        $eleve = $anomalie->eleve;
        if (! $eleve) {
            return [];
        }

        $parents = $eleve->parents;
        if ($parents->isEmpty()) {
            return [];
        }

        $plafond = max(0, (int) config('schoollogs.plafond_notifications_jour_par_eleve', 3));
        $dateLocale = Carbon::parse($anomalie->date_locale)->toDateString();
        $texte = $this->composerTexteNeutre($anomalie);

        $messages = [];

        foreach ($parents as $parent) {
            $existant = MessageSortant::query()
                ->where('anomalie_id', $anomalie->id)
                ->where('parent_id', $parent->id)
                ->first();

            if ($existant) {
                $messages[] = $existant;
                continue;
            }

            $dejaEnvoyesPourEleveCeJour = MessageSortant::query()
                ->where('etat', 'envoye')
                ->whereHas('anomalie', function ($q) use ($eleve, $dateLocale) {
                    $q->where('eleve_id', $eleve->id)
                        ->whereDate('date_locale', $dateLocale);
                })
                ->count();

            if ($dejaEnvoyesPourEleveCeJour >= $plafond) {
                $messages[] = MessageSortant::create([
                    'parent_id' => $parent->id,
                    'anomalie_id' => $anomalie->id,
                    'texte' => $texte,
                    'etat' => 'non_envoye_plafond',
                    'motif_non_envoi' => "Plafond journalier atteint ({$plafond}/jour pour cet élève le {$dateLocale}).",
                    'date_envoi' => null,
                ]);
                continue;
            }

            $resultat = $this->notificateur->envoyer($parent, $anomalie, $texte);

            if ($resultat['ok'] ?? false) {
                $messages[] = MessageSortant::create([
                    'parent_id' => $parent->id,
                    'anomalie_id' => $anomalie->id,
                    'texte' => $texte,
                    'etat' => 'envoye',
                    'motif_non_envoi' => null,
                    'date_envoi' => now(),
                ]);
            } else {
                $messages[] = MessageSortant::create([
                    'parent_id' => $parent->id,
                    'anomalie_id' => $anomalie->id,
                    'texte' => $texte,
                    'etat' => 'echec',
                    'motif_non_envoi' => $resultat['motif_erreur'] ?? 'Échec de remise du message.',
                    'date_envoi' => null,
                ]);
            }
        }

        return $messages;
    }

    private function composerTexteNeutre(Anomalie $anomalie): string
    {
        $prenom = $anomalie->eleve?->prenom ?? "l'élève";
        $date = Carbon::parse($anomalie->date_locale)->format('d/m/Y');

        $libelleType = match ($anomalie->type) {
            'passage_manquant' => 'un passage attendu non enregistré',
            'hors_fenetre' => 'un passage en dehors de la plage horaire prévue',
            'point_hors_itineraire' => "un passage sur un point hors de l'itinéraire prévu",
            'double_scan' => 'deux lectures rapprochées du badge',
            'badge_non_actif' => 'une présentation de badge inactif',
            'hors_heures_etablissement' => "un passage en dehors des heures d'ouverture de l'établissement",
            default => 'un événement de passage à signaler',
        };

        return "SchoolLogs : {$libelleType} concernant {$prenom} le {$date}. Merci de vérifier auprès de l'établissement.";
    }
}