<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scan hors ligne — SchoolLogs Passage</title>
</head>
<body>
    <h1>Scan des passages (mode hors ligne pris en charge)</h1>
    <p><a href="/tableau-de-bord">Retour au tableau de bord</a></p>

    <p id="etat-reseau">État réseau : vérification...</p>
    <p id="compteur-file">Événements en attente : 0</p>

    <form id="formulaire-scan">
        <div>
            <label for="point_controle_id">Point de contrôle</label>
            <select id="point_controle_id" name="point_controle_id" required>
                @foreach ($pointsControle as $point)
                    <option value="{{ $point->id }}">{{ $point->libelle }} ({{ $point->type }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="sens">Sens</label>
            <select id="sens" name="sens" required>
                <option value="entree">Entrée</option>
                <option value="sortie">Sortie</option>
            </select>
        </div>

        <div>
            <label for="jeton">Jeton du badge</label>
            <input type="text" id="jeton" name="jeton" value="{{ $jetonExemple }}" required style="width: 100%; max-width: 520px;">
        </div>

        <button type="submit">Enregistrer le scan</button>
    </form>

    <button type="button" id="btn-sync" style="margin-top: 12px;">Synchroniser maintenant</button>
    <pre id="journal-sync"></pre>

    <script>
        const DB_NAME = 'schoollogs_offline_db';
        const STORE_NAME = 'passages_queue';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function ouvrirBase() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(DB_NAME, 1);
                req.onupgradeneeded = () => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains(STORE_NAME)) {
                        db.createObjectStore(STORE_NAME, { keyPath: 'client_event_id' });
                    }
                };
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
        }

        async function ajouterEnFile(evenement) {
            const db = await ouvrirBase();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_NAME, 'readwrite');
                tx.objectStore(STORE_NAME).put(evenement);
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            });
        }

        async function lireFile() {
            const db = await ouvrirBase();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_NAME, 'readonly');
                const req = tx.objectStore(STORE_NAME).getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
        }

        async function supprimerDeFile(ids) {
            const db = await ouvrirBase();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_NAME, 'readwrite');
                const store = tx.objectStore(STORE_NAME);
                ids.forEach((id) => store.delete(id));
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            });
        }

        async function majAffichageFile() {
            const items = await lireFile();
            document.getElementById('compteur-file').textContent = `Événements en attente : ${items.length}`;
            const ligne = navigator.onLine;
            document.getElementById('etat-reseau').textContent = `État réseau : ${ligne ? 'En ligne' : 'Hors ligne (stockage local IndexedDB)'}`;
        }

        function logMessage(texte) {
            const pre = document.getElementById('journal-sync');
            pre.textContent = `${new Date().toISOString()} — ${texte}\n` + pre.textContent;
        }

        async function synchroniser() {
            const items = await lireFile();
            if (items.length === 0) {
                logMessage('Aucun événement à synchroniser.');
                return;
            }

            try {
                const response = await fetch('/api/passages/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ evenements: items }),
                });

                if (!response.ok) {
                    logMessage(`Synchronisation différée (HTTP ${response.status}).`);
                    return;
                }

                const data = await response.json();
                const traitesIds = (data.resultats || [])
                    .filter((r) => r.statut === 'accepte' || r.statut === 'doublon' || r.statut === 'rejete')
                    .map((r) => r.client_event_id);

                await supprimerDeFile(traitesIds);
                logMessage(`Lot synchronisé : ${JSON.stringify(data.resultats)}`);
                await majAffichageFile();
            } catch (err) {
                logMessage('Réseau indisponible : événements conservés en IndexedDB.');
            }
        }

        document.getElementById('formulaire-scan').addEventListener('submit', async (e) => {
            e.preventDefault();
            const evenement = {
                client_event_id: (crypto.randomUUID ? crypto.randomUUID() : `evt_${Date.now()}_${Math.random().toString(16).slice(2)}`),
                point_controle_id: Number(document.getElementById('point_controle_id').value),
                sens: document.getElementById('sens').value,
                jeton: document.getElementById('jeton').value.trim(),
                horodatage_appareil: new Date().toISOString(),
            };

            await ajouterEnFile(evenement);
            await majAffichageFile();
            logMessage(`Scan enregistré localement (${evenement.client_event_id}).`);

            if (navigator.onLine) {
                await synchroniser();
            }
        });

        document.getElementById('btn-sync').addEventListener('click', synchroniser);
        window.addEventListener('online', async () => {
            await majAffichageFile();
            await synchroniser();
        });
        window.addEventListener('offline', majAffichageFile);

        majAffichageFile();
    </script>
</body>
</html>