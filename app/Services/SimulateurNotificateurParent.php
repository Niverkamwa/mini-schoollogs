<?php

namespace App\Services;

use App\Contracts\NotificateurParentInterface;
use App\Models\Anomalie;
use App\Models\User;

class SimulateurNotificateurParent implements NotificateurParentInterface
{
    public function envoyer(User $parent, Anomalie $anomalie, string $texte): array
    {
        if (! $parent->telephone) {
            return [
                'ok' => false,
                'motif_erreur' => 'Aucun numéro de téléphone enregistré pour le parent.',
            ];
        }

        return [
            'ok' => true,
            'motif_erreur' => null,
        ];
    }
}