@extends('layouts.app')

@section('content')
<div id="section-scan" class="space-y-6 scroll-mt-6">
    <div class="bg-white rounded-xl shadow p-5 border border-slate-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold">Poste de scan — Agent de portail</h1>
            <p class="text-sm text-slate-500">Fonctionne hors ligne avec mise en file locale et synchronisation idempotente.</p>
        </div>

        <div class="flex items-center gap-3">
            <span id="etat-reseau-badge" class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                Réseau : En ligne
            </span>
            <button type="button" id="btn-toggle-reseau" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-white">
                Couper / Rétablir le réseau
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-5 border border-slate-200 space-y-4">
            <h2 class="font-semibold text-base">Nouveau scan de badge</h2>

            <div>
                <label for="point_controle_id" class="block text-sm font-medium text-slate-700 mb-1">Point de contrôle</label>
                <select id="point_controle_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach($pointsControle as $point)
                        <option value="{{ $point->id }}">{{ $point->libelle }} ({{ $point->type }})</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="sens" class="block text-sm font-medium text-slate-700 mb-1">Sens</label>
                    <select id="sens" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="entree">Entrée</option>
                        <option value="sortie">Sortie</option>
                    </select>
                </div>

                <div>
                    <label for="decalage_heures" class="block text-sm font-medium text-slate-700 mb-1">Décalage horloge (heures)</label>
                    <input id="decalage_heures" type="number" step="0.5" value="0"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label for="jeton" class="block text-sm font-medium text-slate-700 mb-1">Jeton tournant du badge</label>
                <input id="jeton" type="text" value="{{ $jetonExemple }}" placeholder="v1.<public_id>.<slot>.<signature>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" id="btn-scanner" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Enregistrer le scan
                </button>
                <button type="button" id="btn-simuler-20" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                    Simuler 20 scans
                </button>
            </div>

            <p id="message-scan" class="text-sm text-slate-700 min-h-[1.25rem]"></p>
        </div>

        <div id="section-file" class="bg-white rounded-xl shadow p-5 border border-slate-200 space-y-4 scroll-mt-6">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <h2 class="font-semibold text-base">File locale (<span id="compteur-file">0</span> en attente)</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btn-sync" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                        Synchroniser
                    </button>
                    <button type="button" id="btn-interrompre" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                        Interrompre
                    </button>
                    <button type="button" id="btn-vider-file" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-3 py-1.5 rounded-lg text-xs font-medium">
                        Vider la file
                    </button>
                </div>
            </div>

            <div id="journal-sync" class="text-xs text-slate-600 bg-slate-50 rounded-lg p-3 border border-slate-200 max-h-48 overflow-y-auto space-y-1">
                Prêt.
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const STORAGE_KEY = 'schoollogs_file_passages_v1';
    let reseauActif = true;
    let interrompu = false;

    const badgeReseau = document.getElementById('etat-reseau-badge');
    const compteurFile = document.getElementById('compteur-file');
    const journalSync = document.getElementById('journal-sync');
    const messageScan = document.getElementById('message-scan');

    function lireFile() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        } catch {
            return [];
        }
    }

    function ecrireFile(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        compteurFile.textContent = String(items.length);
    }

    function log(texte) {
        const ligne = document.createElement('div');
        ligne.textContent = `[${new Date().toLocaleTimeString()}] ${texte}`;
        journalSync.prepend(ligne);
    }

    function majReseau() {
        if (reseauActif) {
            badgeReseau.textContent = 'Réseau : En ligne';
            badgeReseau.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800';
        } else {
            badgeReseau.textContent = 'Réseau : Hors ligne';
            badgeReseau.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800';
        }
    }

    function uuid() {
        return crypto.randomUUID ? crypto.randomUUID() : 'evt-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    function pause(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function construireEvenement() {
        const decalageHeures = parseFloat(document.getElementById('decalage_heures').value || '0') || 0;
        const heureLocale = new Date(Date.now() + decalageHeures * 3600 * 1000).toISOString();
        const jeton = document.getElementById('jeton').value.trim();

        return {
            client_event_id: uuid(),
            local_event_id: uuid(),
            jeton: jeton,
            token: jeton,
            point_controle_id: parseInt(document.getElementById('point_controle_id').value, 10),
            sens: document.getElementById('sens').value,
            heure_locale: heureLocale,
            horodatage_appareil: heureLocale,
            appareil_id: 'navigateur-agent-01',
        };
    }

    async function envoyerLot(lot) {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const resp = await fetch("{{ route('api.passages.sync') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ evenements: lot }),
        });

        if (!resp.ok) {
            throw new Error(`HTTP ${resp.status}`);
        }

        return await resp.json();
    }

    async function synchroniserFile() {
        interrompu = false;
        const file = lireFile();
        if (file.length === 0) {
            log('Aucun scan en attente.');
            return;
        }
        if (!reseauActif) {
            log('Réseau coupé : synchronisation impossible pour le moment.');
            return;
        }

        log(`Synchronisation de ${file.length} scan(s)...`);
        const restants = [...file];

        while (restants.length > 0) {
            if (interrompu) {
                ecrireFile(restants);
                log(`Synchronisation interrompue (${restants.length} scan(s) conservés en file).`);
                return;
            }

            const batch = restants.splice(0, 3);
            try {
                await envoyerLot(batch);
                ecrireFile(restants);
            } catch (err) {
                restants.unshift(...batch);
                ecrireFile(restants);
                log(`Erreur de synchronisation : ${err.message}`);
                return;
            }

            if (restants.length > 0) {
                await pause(350);
            }
        }

        log('Synchronisation terminée sans perte ni doublon.');
    }

    document.getElementById('btn-toggle-reseau').addEventListener('click', async () => {
        reseauActif = !reseauActif;
        majReseau();
        log(reseauActif ? 'Réseau rétabli.' : 'Réseau coupé.');
        if (reseauActif && lireFile().length > 0) {
            await synchroniserFile();
        }
    });

    document.getElementById('btn-scanner').addEventListener('click', async () => {
        const evt = construireEvenement();
        if (!evt.jeton) {
            messageScan.textContent = 'Veuillez saisir un jeton de badge.';
            return;
        }

        const file = lireFile();
        file.push(evt);
        ecrireFile(file);
        messageScan.textContent = `Scan mis en file (${evt.client_event_id.slice(0, 8)}).`;

        if (reseauActif) {
            await synchroniserFile();
        }
    });

    document.getElementById('btn-simuler-20').addEventListener('click', async () => {
        const jeton = document.getElementById('jeton').value.trim();
        if (!jeton) {
            messageScan.textContent = 'Veuillez saisir un jeton de badge.';
            return;
        }
        const file = lireFile();
        for (let i = 0; i < 20; i++) {
            file.push({
                ...construireEvenement(),
                client_event_id: uuid(),
                local_event_id: uuid(),
            });
        }
        ecrireFile(file);
        messageScan.textContent = '20 scans ajoutés à la file locale.';
        if (reseauActif) {
            await synchroniserFile();
        }
    });

    document.getElementById('btn-sync').addEventListener('click', synchroniserFile);
    document.getElementById('btn-interrompre').addEventListener('click', () => {
        interrompu = true;
    });
    document.getElementById('btn-vider-file').addEventListener('click', () => {
        ecrireFile([]);
        messageScan.textContent = '';
        log('File locale vidée.');
    });

    majReseau();
    ecrireFile(lireFile());
})();
</script>
@endsection