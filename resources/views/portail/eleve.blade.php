@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow p-5 border border-slate-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold">{{ $eleve->prenom }} {{ $eleve->nom }}</h1>
            <p class="text-sm text-slate-500">
                Classe : <span class="font-medium text-slate-700">{{ $eleve->classe }}</span>
                @if($eleve->itineraire)
                    — Itinéraire : <span class="font-medium text-slate-700">{{ $eleve->itineraire->libelle ?? $eleve->itineraire->nom ?? ('#' . $eleve->itineraire->id) }}</span>
                @endif
            </p>
        </div>

        <a href="{{ route('tableau-de-bord') }}"
           class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-3.5 py-2 rounded-lg text-xs font-medium">
            Retour au tableau de bord
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-5 border border-slate-200">
        <h2 class="font-semibold text-base mb-2">Badge associé</h2>
        @if($eleve->badgeActif)
            <p class="text-sm text-slate-700">
                État :
                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $eleve->badgeActif->etat === 'actif' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $eleve->badgeActif->etat }}
                </span>
                — Identifiant public : <span class="font-mono text-xs">{{ $eleve->badgeActif->public_id }}</span>
            </p>
        @else
            <p class="text-sm text-slate-500">Aucun badge actif enregistré.</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow p-5 border border-slate-200">
        <h2 class="font-semibold text-base mb-3">Passages enregistrés</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Heure retenue</th>
                        <th class="py-2 pr-3">Point de contrôle</th>
                        <th class="py-2 pr-3">Sens</th>
                        <th class="py-2">Horloge</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($eleve->passages as $passage)
                        <tr>
                            <td class="py-2 pr-3 font-mono text-xs">{{ $passage->heure_retenue ?? $passage->horodatage_serveur }}</td>
                            <td class="py-2 pr-3">{{ $passage->pointControle?->libelle ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $passage->sens }}</td>
                            <td class="py-2 text-xs">
                                {{ $passage->horloge_decalee ? 'Décalée' : 'Normale' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-3 text-slate-500">Aucun passage enregistré pour cet élève.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-5 border border-slate-200">
        <h2 class="font-semibold text-base mb-3">Anomalies</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Type</th>
                        <th class="py-2 pr-3">Point de contrôle</th>
                        <th class="py-2">État</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($eleve->anomalies as $anomalie)
                        <tr>
                            <td class="py-2 pr-3">{{ $anomalie->date_locale }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ $anomalie->type }}</td>
                            <td class="py-2 pr-3">{{ $anomalie->fenetre?->pointControle?->libelle ?? '—' }}</td>
                            <td class="py-2">{{ $anomalie->etat_traitement }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-3 text-slate-500">Aucune anomalie enregistrée pour cet élève.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection