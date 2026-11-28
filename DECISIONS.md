# Décisions d'architecture — mini-schoollogs (« Passage »)

## Décision 1 — Base de données SQLite
- **Choix retenu :** SQLite (`database/database.sqlite`).
- **Alternatives écartées :** PostgreSQL.
- **Raison :** zéro installation serveur nécessaire, exécution immédiate avec `php artisan migrate:fresh --seed` et compatibilité native avec `pdo_sqlite` dans GitHub Actions[cite: 2, 4, 7].

## Décision 2 — Authentification par `identifiant` sur la table `users`
- **Choix retenu :** conserver la table `users` avec une colonne `identifiant` unique, des mots de passe hachés (minimum 8 caractères), un `RateLimiter` (`connexion`) avec message d'erreur uniforme et l'invalidation complète de session à la déconnexion[cite: 2, 3, 7].
- **Alternatives écartées :** créer une table séparée sans réutiliser le guard `web` standard de Laravel.
- **Raison :** s'appuyer sur le mécanisme de session et de sécurité natif de Laravel tout en respectant les champs métier.

## Décision 3 — Cloisonnement des accès par rôle et Policies
- **Choix retenu :** contrôler les accès côté serveur via `ElevePolicy` et `AnomaliePolicy`, filtrer les listes selon le rôle (`responsable`, `agent`, `parent`) et retourner `403` lorsqu'un parent tente d'accéder à un élève ou une anomalie qui ne lui est pas rattaché[cite: 2, 3, 5, 7].
- **Alternatives écartées :** masquer uniquement les liens ou boutons dans les vues Blade sans contrôle côté contrôleur/policy[cite: 2, 3].
- **Raison :** garantir l'étanchéité des données familiales même en cas d'appel direct à une URL[cite: 3, 5, 7].

## Décision 4 — Badge tournant signé par HMAC-SHA256 et contrainte agent
- **Choix retenu :** générer un jeton à fenêtre temporelle courte (pas de 60 secondes avec tolérance contrôlée) signé avec `hash_hmac('sha256', ...)` à partir du `secret` chiffré (`'encrypted'`) du badge, vérifié via `hash_equals()`, et révoquer l'ancien badge lors d'un renouvellement[cite: 4, 6, 7].
- **Alternatives écartées :** utiliser un identifiant statique en clair dans le QR code / badge sans rotation ni signature[cite: 4, 6, 9].
- **Raison et contrainte agent :** un jeton photographié ou capturé devient rapidement caduc hors de sa fenêtre de validité, et la révocation/suspension bloque immédiatement le badge[cite: 4, 6, 8]. Cela impose à l'agent de portail de scanner/transmettre le jeton au moment du passage (une capture ancienne rejouée plus tard est rejetée). *Limite assumée :* le prototype manipule des jetons textuels à l'écran sans badge physique imprimé ni test matériel avec caméra[cite: 4, 6, 7].

## Décision 5 — Synchronisation hors-ligne idempotente et traitement d'une horloge fausse
- **Choix retenu :** dédupliquer chaque scan via `client_event_id` (et `local_event_id`) dans `PassageSyncController::sync()` avec double contrôle avant et dans une transaction `DB::transaction()`, rendre `appareil_id` nullable sur `passages`, et enregistrer les horodatages appareil et serveur[cite: 6, 7, 8]. Lorsqu'un appareil présente une horloge manifestement fausse (écart dépassant le seuil défini), **le passage n'est pas rejeté : il est signalé (`horloge_decalee`) et l'horodatage serveur fait foi (`heure_retenue`)**[cite: 6, 8].
- **Alternatives écartées :** insérer directement sans clé d'idempotence client, rejeter en bloc tout scan dont l'heure locale est fausse, ou écraser l'heure sans conserver la trace de l'écart[cite: 6, 8].
- **Raison :** éviter tout doublon ou perte lors des coupures réseau et interruptions/reprises de lots, tout en empêchant une horloge d'appareil déréglée de corrompre la chronologie retenue côté serveur[cite: 6, 8].

## Décision 6 — Détection des anomalies, déclenchement automatique et cinquième anomalie
- **Choix retenu :** centraliser l'évaluation dans `AnomalieDetectionService` (`passage_manquant`, `hors_fenetre`, `point_hors_itineraire`, `double_scan`, `badge_non_actif`) en tenant compte des `exceptions_jour` (`ferie`, `absence_justifiee`), prévoir le déclenchement périodique de la vérification des passages manquants via la commande planifiée (`anomalies:verifier` / scheduler) sans intervention manuelle dans l'UI, et ajouter la cinquième anomalie **`hors_heures_etablissement`**[cite: 6, 7, 8].
- **Alternatives écartées :** laisser la logique éparpillée dans les contrôleurs/vues ou ne pas distinguer les passages hors plage d'ouverture de l'établissement[cite: 2, 6, 8].
- **Raison :** `hors_heures_etablissement` permet de signaler spécifiquement un scan effectué en dehors des horaires normaux d'ouverture/activité de l'établissement (ex. en pleine nuit ou hors plage prévue), distinct d'un simple écart de fenêtre d'itinéraire[cite: 6, 8]. *Limite assumée :* en environnement local de démonstration, la vérification des passages manquants peut aussi être déclenchée à la demande et le scheduler continu n'est pas démarré en tâche de fond par défaut[cite: 6, 7, 8].

## Décision 7 — Notifications parents découplées, neutres et plafonnées par jour
- **Choix retenu :** isoler l'envoi derrière `NotificateurParentInterface` (`SimulateurNotificateurParent` par défaut), composer un message neutre sans donnée sensible pour chaque parent lié à l'élève, respecter l'unicité `(anomalie_id, parent_id)` et appliquer un plafond journalier configurable par élève (`non_envoye_plafond` avec `motif_non_envoi` explicite au-delà)[cite: 6, 7].
- **Alternatives écartées :** appeler directement un fournisseur externe dans le service d'anomalies ou envoyer un message par anomalie sans limite journalière ni contrôle de doublon[cite: 6].
- **Raison :** faciliter le branchement d'un fournisseur SMS réel sans toucher au domaine, éviter la saturation des parents en cas d'incidents répétés et garder la traçabilité complète en base (`messages_sortants`)[cite: 6, 7].

## Décision 8 — Isolation de `/connexion`, sidebars dédiées par rôle et suivi de synchronisation
- **Choix retenu :** isoler `/connexion`, fournir des navigations adaptées à chaque rôle (`agent` vers `/scan`, `responsable` sur le pilotage, `parent` sur ses enfants et notifications) sans exposer `/scan` au responsable, verrouiller le scan unitaire contre le double clic et compter les statuts directement dans `data.resultats` (`accepte`, `doublon`, `rejete`)[cite: 3, 5, 6].
- **Alternatives écartées :** afficher le poste de scan dans l'espace responsable ou compter la synchronisation sur des clés inexistantes de la réponse JSON[cite: 5, 6].
- **Raison :** respecter strictement la séparation des rôles et refléter fidèlement le résultat réel de chaque lot synchronisé[cite: 5, 6].