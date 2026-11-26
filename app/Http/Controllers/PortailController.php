<?php

namespace App\Http\Controllers;

use App\Models\Anomalie;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortailController extends Controller
{
    public function tableauDeBord(Request $request): View
    {
        $user = $request->user();

        $eleves = match ($user->role) {
            'parent' => $user->enfants()->with('etablissement', 'itineraire')->get(),
            default => Eleve::with('etablissement', 'itineraire')->get(),
        };

        $anomalies = match ($user->role) {
            'parent' => Anomalie::whereHas('eleve.parents', fn ($q) => $q->where('users.id', $user->id))
                ->with('eleve')
                ->latest()
                ->get(),
            default => Anomalie::with('eleve')->latest()->get(),
        };

        return view('portail.tableau-de-bord', [
            'user' => $user,
            'eleves' => $eleves,
            'anomalies' => $anomalies,
        ]);
    }

    public function afficherEleve(Eleve $eleve): View
    {
        Gate::authorize('view', $eleve);

        return view('portail.eleve', [
            'eleve' => $eleve->load('etablissement', 'itineraire', 'badgeActif'),
        ]);
    }
}