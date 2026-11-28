@extends('layouts.app')

@section('content')
<div id="section-pilotage" class="space-y-6 scroll-mt-6">
    <div class="bg-white rounded-xl shadow p-5 border border-slate-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold">Tableau de bord Responsable</h1>
            <p class="text-sm text-slate-500">Gestion des badges, suivi des passages, anomalies et notifications parents.</p>
        </div>

        <form method="POST" action="{{ route('responsable.anomalies.verifier-manquants') }}" class="flex items-center gap-2">
            @csrf
            <input type="date" name="date" value="{{ now(config('app.timezone', 'Africa/Douala'))->toDateString() }}"
                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-1.5 rounded-lg text-sm font-medium">
                Détecter passages manquants
            </button>
        </form>
    </div>

    {{-- Synthèse encadrée (IA avec repli déterministe) --}}
    @if(isset($synthese))
        <div class="bg-white rounded-xl shadow p-5 border border-slate-200 space-y-2">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <h2 class="font-semibold text-base">Synthèse journalière encadrée</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ ($synthese['source'] ?? 'deterministe') === 'ia' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-800' }}">
                    Mode : {{ ($synthese['source'] ?? 'deterministe') === 'ia' ? 'IA encadrée' : 'Repli déterministe' }}
                </span>
            </div>
            <p class="text-sm text-slate-700 leading-relaxed">{{ $synthese['texte'] }}</p>
        </div>
    @endif

    {{-- Élèves et badges --}}
    <div id="section-badges" class="bg-white rounded-xl shadow p-5 border border-slate-200 scroll-mt-6">
        <h2 class="font-semibold text-base mb-3">Élèves &amp; Badges tournants</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Élève</th>
                        <th class="py-2 pr-3">Classe</th>
                        <th class="py-2 pr-3">État badge</th>
                        <th class="py-2 pr-3">Jeton actif</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($eleves as $eleve)
                        @php
                            $jetonCourant = $jetonsActifs[$eleve->id] ?? null;
                        @endphp
                        <tr>
                            <td class="py-2 pr-3 font-medium">
                                <a href="{{ route('eleves.show', $eleve) }}" class="text-blue-700 hover:underline">
                                    {{ $eleve->prenom }} {{ $eleve->nom }}
                                </a>
                            </td>
                            <td class="py-2 pr-3">{{ $eleve->classe }}</td>
                            <td class="py-2 pr-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ ($eleve->badgeActif?->etat ?? '') === 'actif' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $eleve->badgeActif?->etat ?? 'aucun' }}
                                </span>
                            </td>
                            <td class="py-2 pr-3 font-mono text-xs break-all max-w-xs">
                                @if($jetonCourant)
                                    <div class="flex items-start gap-2">
                                        <span>{{ $jetonCourant }}</span>
                                        <button type="button"
                                                data-copier-jeton="{{ $jetonCourant }}"
                                                class="btn-copier-jeton shrink-0 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 px-2 py-0.5 rounded text-[11px] font-sans">
                                            Copier
                                        </button>
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('responsable.badge.reemettre', $eleve) }}">
                                        @csrf
                                        <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-2.5 py-1 rounded text-xs">
                                            Réémettre
                                        </button>
                                    </form>
                                    @if($eleve->badgeActif)
                                        <form method="POST" action="{{ route('responsable.badge.etat', $eleve->badgeActif) }}">
                                            @csrf
                                            <button type="submit" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-2.5 py-1 rounded text-xs">
                                                {{ $eleve->badgeActif->etat === 'actif' ? 'Suspendre' : 'Réactiver' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Passages récents --}}
    <div id="section-passages" class="bg-white rounded-xl shadow p-5 border border-slate-200 scroll-mt-6">
        <h2 class="font-semibold text-base mb-3">Passages synchronisés récents</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Heure retenue</th>
                        <th class="py-2 pr-3">Élève</th>
                        <th class="py-2 pr-3">Point de contrôle</th>
                        <th class="py-2 pr-3">Sens</th>
                        <th class="py-2">Décalage horloge</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($passages as $passage)
                        <tr>
                            <td class="py-2 pr-3 font-mono text-xs">{{ $passage->heure_retenue ?? $passage->horodatage_serveur }}</td>
                            <td class="py-2 pr-3">{{ $passage->eleve?->prenom }} {{ $passage->eleve?->nom }}</td>
                            <td class="py-2 pr-3">{{ $passage->pointControle?->libelle ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $passage->sens }}</td>
                            <td class="py-2 text-xs">
                                @if($passage->horloge_decalee)
                                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">Décalée</span>
                                @else
                                    <span class="text-slate-500">Normale</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-3 text-slate-500">Aucun passage enregistré pour l'instant.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Anomalies --}}
    <div id="section-anomalies" class="bg-white rounded-xl shadow p-5 border border-slate-200 scroll-mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h2 class="font-semibold text-base">Anomalies détectées</h2>
            <form method="GET" action="{{ route('tableau-de-bord') }}" class="flex items-center gap-2">
                <select name="type_anomalie" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs">
                    <option value="">Tous les types</option>
                    @foreach(['passage_manquant','hors_fenetre','point_hors_itineraire','double_scan','badge_non_actif','hors_heures_etablissement'] as $t)
                        <option value="{{ $t }}" @selected($typeFiltre === $t)>{{ $t }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-slate-800 text-white px-3 py-1 rounded text-xs">Filtrer</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Élève</th>
                        <th class="py-2 pr-3">Type</th>
                        <th class="py-2">État</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($anomalies as $anomalie)
                        <tr>
                            <td class="py-2 pr-3">{{ $anomalie->date_locale }}</td>
                            <td class="py-2 pr-3">{{ $anomalie->eleve?->prenom }} {{ $anomalie->eleve?->nom }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ $anomalie->type }}</td>
                            <td class="py-2">{{ $anomalie->etat_traitement }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-3 text-slate-500">Aucune anomalie enregistrée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Messages sortants --}}
    <div id="section-messages" class="bg-white rounded-xl shadow p-5 border border-slate-200 scroll-mt-6">
        <h2 class="font-semibold text-base mb-3">Messages sortants (parents)</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Parent</th>
                        <th class="py-2 pr-3">État</th>
                        <th class="py-2 pr-3">Texte</th>
                        <th class="py-2">Motif non-envoi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($messages as $msg)
                        <tr>
                            <td class="py-2 pr-3">{{ $msg->parent?->prenom }} {{ $msg->parent?->nom }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ $msg->etat }}</td>
                            <td class="py-2 pr-3">{{ $msg->texte }}</td>
                            <td class="py-2 text-xs text-slate-600">{{ $msg->motif_non_envoi ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-3 text-slate-500">Aucun message sortant pour l'instant.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-copier-jeton').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const jeton = btn.getAttribute('data-copier-jeton') || '';
        if (!jeton) {
            return;
        }
        try {
            await navigator.clipboard.writeText(jeton);
            const texteInitial = btn.textContent;
            btn.textContent = 'Copié';
            setTimeout(() => {
                btn.textContent = texteInitial;
            }, 1200);
        } catch {
            // Repli silencieux si le presse-papier est bloqué par le navigateur
        }
    });
});
</script>
@endsection