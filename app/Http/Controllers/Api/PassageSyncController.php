<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passage;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PassageSyncController extends Controller
{
    public function __construct(
        private readonly BadgeService $badgeService
    ) {}

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['responsable', 'agent'], true)) {
            return response()->json(['message' => 'Accès interdit.'], 403);
        }

        $valide = $request->validate([
            'evenements' => ['required', 'array', 'min:1', 'max:200'],
            'evenements.*.client_event_id' => ['required', 'string', 'max:120'],
            'evenements.*.jeton' => ['required', 'string', 'max:500'],
            'evenements.*.point_controle_id' => ['required', 'integer', 'exists:points_controle,id'],
            'evenements.*.sens' => ['required', 'in:entree,sortie'],
            'evenements.*.horodatage_appareil' => ['required', 'date'],
        ]);

        $resultats = [];
        $serveurMaintenant = Carbon::now('UTC');

        foreach ($valide['evenements'] as $evt) {
            $eventId = $evt['client_event_id'];

            $existant = Passage::where('client_event_id', $eventId)->first();
            if ($existant) {
                $resultats[] = [
                    'client_event_id' => $eventId,
                    'statut' => 'doublon',
                    'passage_id' => $existant->id,
                ];
                continue;
            }

            $verification = $this->badgeService->verifierJeton($evt['jeton']);
            if (! $verification['valide'] || ! $verification['badge']) {
                $resultats[] = [
                    'client_event_id' => $eventId,
                    'statut' => 'rejete',
                    'motif' => $verification['motif'] ?? 'jeton_invalide',
                ];
                continue;
            }

            $badge = $verification['badge'];
            $horodatageAppareil = Carbon::parse($evt['horodatage_appareil'])->utc();

            $passage = DB::transaction(function () use ($eventId, $badge, $evt, $horodatageAppareil, $serveurMaintenant) {
                $dejaCree = Passage::where('client_event_id', $eventId)->first();
                if ($dejaCree) {
                    return $dejaCree;
                }

                $tz = config('app.timezone');
                $heureLocale = $horodatageAppareil->copy()->timezone($tz);
                $heureServeur = $serveurMaintenant->copy()->timezone($tz);

                return Passage::create([
                    'client_event_id' => $eventId,
                    'local_event_id' => $eventId,
                    'eleve_id' => $badge->eleve_id,
                    'badge_id' => $badge->id,
                    'point_controle_id' => $evt['point_controle_id'],
                    'sens' => $evt['sens'],
                    'code_brut' => $evt['jeton'],
                    'resultat_controle' => 'valide',
                    'etat_synchronisation' => 'synchronise',
                    'horloge_decalee' => false,
                    'heure_incoherente' => false,
                    'heure_locale' => $heureLocale,
                    'heure_corrigee' => $heureLocale,
                    'heure_serveur' => $heureServeur,
                    'heure_retenue' => $heureServeur,
                    'horodatage_appareil' => $horodatageAppareil,
                    'horodatage_serveur' => $serveurMaintenant,
                    'source_sync' => 'pwa_sync',
                ]);
            });

            $resultats[] = [
                'client_event_id' => $eventId,
                'statut' => 'accepte',
                'passage_id' => $passage->id,
            ];
        }

        return response()->json([
            'resultats' => $resultats,
            'horodatage_serveur' => $serveurMaintenant->toIso8601String(),
        ]);
    }
}