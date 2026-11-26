<?php

namespace App\Contracts;

use App\Models\Anomalie;
use App\Models\User;

interface NotificateurParentInterface
{
    /**
     * Envoie (ou simule l'envoi) d'un message neutre au parent.
     *
     * @return array{ok: bool, motif_erreur?: string|null}
     */
    public function envoyer(User $parent, Anomalie $anomalie, string $texte): array;
}