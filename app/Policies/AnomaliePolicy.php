<?php

namespace App\Policies;

use App\Models\Anomalie;
use App\Models\User;

class AnomaliePolicy
{
    public function view(User $user, Anomalie $anomalie): bool
    {
        if (in_array($user->role, ['responsable', 'agent'], true)) {
            return true;
        }

        if ($user->role === 'parent') {
            return $anomalie->eleve()
                ->whereHas('parents', fn ($q) => $q->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }

    public function traiter(User $user, Anomalie $anomalie): bool
    {
        return in_array($user->role, ['responsable', 'agent'], true);
    }
}