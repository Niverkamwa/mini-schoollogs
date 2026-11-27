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
                <div class="flex items-center justify-between gap-2 mb-1">
                    <label for="jeton" class="block text-sm font-medium text-slate-700">Jeton tournant du badge</label>
                    <button type="button" id="btn-effacer-jeton" class="text-xs text-blue-700 hover:underline">
                        Vider le champ
                    </button>
                </div>
                <input id="jeton" type="text" value="{{ $jetonExemple }}" placeholder="Coller un jeton v1.<public_id>.<slot>.<signature>"
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
    let envoiEnCours = false;
    let syncEnCours = false;

    const badgeReseau = document.getElementById('etat-reseau-badge');
    const compteurFile = document.getElementById('compteur-file');
    const journalSync = document.getElementById('journal-sync');
    const messageScan = document.getElementById('message-scan');
    const btnScanner = document.getElementById('btn-scanner');
    const btnSimuler20 = document.getElementById('btn-simuler-20');
    const inputJeton = document.getElementById('jeton');

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

    function construireEvenement(indexOffsetSeconds = 0) {
        const decalageHeures = parseFloat(document.getElementById('decalage_heures').value || '0') || 0;
        const heureLocale = new Date(Date.now() + decalageHeures * 3600 * 1000 + indexOffsetSeconds * 1000).toISOString();
        const jeton = inputJeton.value.trim();

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
        if (!reseauActif) {
            throw new Error('Réseau coupé');
        }

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

        const texteReponse = await resp.text();
        let data = {};
        try {
            data = JSON.parse(texteReponse);
        } catch {
            data = { brut: texteReponse };
        }

        if (!resp.ok) {
            throw new Error(`HTTP ${resp.status} — ${texteReponse.slice(0, 180)}`);
        }

        const listeResultats = Array.isArray(data.resultats) ? data.resultats : [];
        const nbAcceptes = listeResultats.filter((r) => r.statut === 'accepte').length;
        const nbDoublons = listeResultats.filter((r) => r.statut === 'doublon').length;
        const nbRejetes = listeResultats.filter((r) => r.statut === 'rejete').length;
        log(`Lot (${lot.length}) traité : acceptés=${nbAcceptes}, doublons=${nbDoublons}, rejetés=${nbRejetes}`);

        return data;
    }

    async function synchroniserFile() {
        if (syncEnCours) {
            return;
        }

        interrompu = false;
        const fileInitiale = lireFile();
        if (fileInitiale.length === 0) {
            log('Aucun scan en attente.');
            return;
        }
        if (!reseauActif) {
            ecrireFile(fileInitiale);
            log(`Réseau coupé : ${fileInitiale.length} scan(s) conservés en file.`);
            return;
        }

        syncEnCours = true;
        log(`Synchronisation de ${fileInitiale.length} scan(s)...`);

        try {
            while (true) {
                const fileCourante = lireFile();
                if (fileCourante.length === 0) {
                    log('Synchronisation terminée sans perte ni doublon.');
                    return;
                }

                if (!reseauActif) {
                    ecrireFile(fileCourante);
                    log(`Réseau coupé en cours de sync (${fileCourante.length} scan(s) conservés en file).`);
                    return;
                }

                if (interrompu) {
                    ecrireFile(fileCourante);
                    log(`Synchronisation interrompue (${fileCourante.length} scan(s) conservés en file).`);
                    return;
                }

                const batch = fileCourante.slice(0, 3);
                try {
                    await envoyerLot(batch);
                    const restants = lireFile().slice(batch.length);
                    ecrireFile(restants);
                } catch (err) {
                    ecrireFile(lireFile());
                    log(`Envoi mis en pause (${lireFile().length} scan(s) en file) : ${err.message}`);
                    return;
                }

                if (lireFile().length > 0 && reseauActif && !interrompu) {
                    await pause(350);
                }
            }
        } finally {
            syncEnCours = false;
        }
    }

    document.getElementById('btn-effacer-jeton').addEventListener('click', () => {
        inputJeton.value = '';
        inputJeton.focus();
    });

    document.getElementById('btn-toggle-reseau').addEventListener('click', async () => {
        reseauActif = !reseauActif;
        majReseau();

        if (!reseauActif) {
            interrompu = true;
            ecrireFile(lireFile());
            log(`Réseau coupé (${lireFile().length} scan(s) en file).`);
            return;
        }

        interrompu = false;
        log(`Réseau rétabli (${lireFile().length} scan(s) en file).`);
        if (lireFile().length > 0) {
            await synchroniserFile();
        }
    });

    btnScanner.addEventListener('click', async () => {
        if (envoiEnCours) {
            return;
        }

        const evt = construireEvenement(0);
        if (!evt.jeton) {
            messageScan.textContent = 'Veuillez saisir ou coller un jeton de badge.';
            return;
        }

        envoiEnCours = true;
        btnScanner.disabled = true;
        btnSimuler20.disabled = true;

        try {
            const file = lireFile();
            file.push(evt);
            ecrireFile(file);
            messageScan.textContent = `1 scan mis en file (${evt.client_event_id.slice(0, 8)}).`;

            if (reseauActif) {
                await synchroniserFile();
            }
        } finally {
            envoiEnCours = false;
            btnScanner.disabled = false;
            btnSimuler20.disabled = false;
        }
    });

    btnSimuler20.addEventListener('click', async () => {
        if (envoiEnCours) {
            return;
        }

        const jeton = inputJeton.value.trim();
        if (!jeton) {
            messageScan.textContent = 'Veuillez saisir ou coller un jeton de badge.';
            return;
        }

        envoiEnCours = true;
        btnScanner.disabled = true;
        btnSimuler20.disabled = true;

        try {
            const file = lireFile();
            for (let i = 0; i < 20; i++) {
                file.push(construireEvenement(i));
            }
            ecrireFile(file);
            messageScan.textContent = '20 scans ajoutés à la file locale.';
            if (reseauActif) {
                await synchroniserFile();
            }
        } finally {
            envoiEnCours = false;
            btnScanner.disabled = false;
            btnSimuler20.disabled = false;
        }
    });

    document.getElementById('btn-sync').addEventListener('click', synchroniserFile);
    document.getElementById('btn-interrompre').addEventListener('click', () => {
        interrompu = true;
        ecrireFile(lireFile());
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