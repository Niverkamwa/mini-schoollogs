<?php

namespace App\Http\Controllers;

use App\Models\Anomalie;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\MessageSortant;
use App\Models\Passage;
use App\Services\AnomalieDetectionService;
use App\Services\BadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PortailController extends Controller
{
    public function tableauDeBord(Request $request, BadgeService $badgeService): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'agent') {
            return redirect()->route('scan');
        }

        if ($user->role === 'parent') {
            $enfants = $user->enfants()
                ->with(['badgeActif', 'anomalies' => fn ($q) => $q->latest('date_locale')->latest('id')])
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get();

            $notifications = MessageSortant::query()
                ->where('parent_id', $user->id)
                ->latest('id')
                ->limit(30)
                ->get();

            return view('portail.parent', [
                'user' => $user,
                'enfants' => $enfants,
                'notifications' => $notifications,
            ]);
        }

        // Rôle responsable
        $typeFiltre = $request->query('type_anomalie');

        $eleves = Eleve::query()
            ->with(['badgeActif', 'parents', 'itineraire'])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $jetonsActifs = [];
        foreach ($eleves as $eleve) {
            if ($eleve->badgeActif) {
                $jetonsActifs[$eleve->id] = $badgeService->genererJeton($eleve->badgeActif);
            }
        }

        $passages = Passage::query()
            ->with(['eleve', 'pointControle', 'badge'])
            ->latest('heure_retenue')
            ->latest('id')
            ->limit(30)
            ->get();

        $anomaliesQuery = Anomalie::query()
            ->with(['eleve', 'fenetre.pointControle', 'passage'])
            ->latest('date_locale')
            ->latest('id');

        if ($typeFiltre) {
            $anomaliesQuery->where('type', $typeFiltre);
        }

        $anomalies = $anomaliesQuery->limit(40)->get();

        $messages = MessageSortant::query()
            ->with(['parent', 'anomalie.eleve'])
            ->latest('id')
            ->limit(30)
            ->get();

        return view('portail.responsable', [
            'user' => $user,
            'eleves' => $eleves,
            'jetonsActifs' => $jetonsActifs,
            'passages' => $passages,
            'anomalies' => $anomalies,
            'messages' => $messages,
            'typeFiltre' => $typeFiltre,
        ]);
    }

    public function afficherEleve(Request $request, Eleve $eleve): View
    {
        $user = $request->user();

        if ($user->role === 'agent') {
            abort(403, 'Accès interdit aux agents de portail.');
        }

        if ($user->role === 'parent') {
            $estParent = $user->enfants()->where('eleves.id', $eleve->id)->exists();
            if (! $estParent) {
                abort(403, 'Vous ne pouvez consulter que vos propres enfants.');
            }
        }

        $eleve->load([
            'badgeActif',
            'itineraire.fenetres.pointControle',
            'passages' => fn ($q) => $q->with('pointControle')->latest('heure_retenue')->latest('id'),
            'anomalies' => fn ($q) => $q->with('fenetre.pointControle')->latest('date_locale')->latest('id'),
        ]);

        return view('portail.eleve', [
            'user' => $user,
            'eleve' => $eleve,
        ]);
    }

    public function reemettreBadge(Request $request, Eleve $eleve, BadgeService $badgeService): RedirectResponse
    {
        abort_unless($request->user()->role === 'responsable', 403);

        $badgeService->emettrePourEleve($eleve);

        return back()->with('statut', "Nouveau badge généré pour {$eleve->prenom} {$eleve->nom}. L'ancien badge est immédiatement invalide.");
    }

    public function basculerEtatBadge(Request $request, Badge $badge): RedirectResponse
    {
        abort_unless($request->user()->role === 'responsable', 403);

        $nouvelEtat = $badge->etat === 'actif' ? 'suspendu' : 'actif';
        $badge->update(['etat' => $nouvelEtat]);

        return back()->with('statut', "Badge passé à l'état : {$nouvelEtat}.");
    }

    public function verifierPassagesManquants(Request $request, AnomalieDetectionService $detectionService): RedirectResponse
    {
        abort_unless($request->user()->role === 'responsable', 403);

        $date = $request->input('date') ?: Carbon::now(config('app.timezone', 'Africa/Douala'))->toDateString();
        $crees = $detectionService->detecterPassagesManquants($date);

        return back()->with('statut', count($crees).' nouvelle(s) anomalie(s) de passage manquant détectée(s) pour le '.$date.'.');
    }
}