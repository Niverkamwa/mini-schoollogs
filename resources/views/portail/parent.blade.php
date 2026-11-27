@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div id="section-enfants" class="bg-white rounded-xl shadow p-5 border border-slate-200 space-y-5 scroll-mt-6">
        <div>
            <h1 class="text-xl font-bold">Espace Parent — Mes enfants</h1>
            <p class="text-sm text-slate-500">Suivi des passages et anomalies de vos enfants rattachés.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($enfants as $enfant)
                @php
                    $anomaliesOuvertes = $enfant->anomalies->where('etat_traitement', 'ouverte')->count();
                @endphp
                <div class="border border-slate-200 rounded-lg p-4 flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <p class="font-semibold text-slate-900">{{ $enfant->prenom }} {{ $enfant->nom }}</p>
                        <p class="text-xs text-slate-600">
                            Classe : <span class="font-medium">{{ $enfant->classe }}</span>
                            — Anomalies : <span class="font-medium">{{ $enfant->anomalies->count() }}</span>
                            @if($anomaliesOuvertes > 0)
                                <span class="text-rose-700 font-medium">({{ $anomaliesOuvertes }} ouverte(s))</span>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('eleves.show', $enfant) }}"
                       class="bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 rounded-lg text-xs font-medium shrink-0">
                        Consulter
                    </a>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun enfant rattaché à ce compte.</p>
            @endforelse
        </div>
    </div>

    <div id="section-notifications" class="bg-white rounded-xl shadow p-5 border border-slate-200 space-y-4 scroll-mt-6">
        <div>
            <h2 class="font-semibold text-base">Notifications reçues</h2>
            <p class="text-xs text-slate-500">Messages transmis concernant vos enfants rattachés.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">État</th>
                        <th class="py-2">Message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($notifications ?? [] as $notif)
                        <tr>
                            <td class="py-2 pr-3 text-xs text-slate-500 whitespace-nowrap">
                                {{ $notif->created_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ $notif->etat }}</td>
                            <td class="py-2 text-slate-800">{{ $notif->texte }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-3 text-slate-500">Aucune notification reçue pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection