<?php

namespace App\Policies;

use App\Models\Eleve;
use App\Models\User;

class ElevePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'responsable';
    }

    public function view(User $user, Eleve $eleve): bool
    {
        if ($user->role === 'responsable') {
            return true;
        }

        if ($user->role === 'parent') {
            return $eleve->parents()->where('users.id', $user->id)->exists();
        }

        return false;
    }
}