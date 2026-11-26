<?php

namespace App\Policies;

use App\Models\Eleve;
use App\Models\User;

class ElevePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['responsable', 'agent', 'parent'], true);
    }

    public function view(User $user, Eleve $eleve): bool
    {
        if (in_array($user->role, ['responsable', 'agent'], true)) {
            return true;
        }

        if ($user->role === 'parent') {
            return $eleve->parents()->where('users.id', $user->id)->exists();
        }

        return false;
    }
}