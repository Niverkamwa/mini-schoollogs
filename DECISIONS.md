# Décisions d'architecture — mini-schoollogs (« Passage »)

## Décision 1 — Base de données SQLite
- **Choix retenu :** SQLite (`database/database.sqlite`).
- **Alternatives écartées :** PostgreSQL.
- **Raison :** zéro installation serveur necessaire, exécution immédiate avec `php artisan migrate:fresh --seed` et compatibilité native avec `pdo_sqlite` dans GitHub Actions.

## Décision 2 — Authentification par `identifiant` sur la table `users`
- **Choix retenu :** conserver la table `users` avec une colonne `identifiant` unique, des mots de passe hachés, un `RateLimiter` (`connexion`) et l'invalidation complète de session à la déconnexion.
- **Alternatives écartées :** créer une table séparée sans réutiliser le guard `web` standard de Laravel.
- **Raison :** s'appuyer sur le mécanisme de session et de sécurité natif de Laravel tout en respectant les champs métier.

## Décision 3 — Cloisonnement des accès par rôle et Policies
- **Choix retenu :** contrôler les accès côté serveur via `ElevePolicy` et `AnomaliePolicy`, filtrer les listes selon le rôle (`responsable`, `agent`, `parent`) et retourner `403` lorsqu'un parent tente d'accéder à un élève ou une anomalie qui ne lui est pas rattaché.
- **Alternatives écartées :** masquer uniquement les liens ou boutons dans les vues Blade sans contrôle côté contrôleur/policy.
- **Raison :** garantir l'étanchéité des données familiales même en cas d'appel direct à une URL.

## Décision 4 — Badge tournant signé par HMAC-SHA256 et chiffrement au repos
- **Choix retenu :** générer un jeton à fenêtre temporelle courte (pas de 60 secondes avec tolérance contrôlée) signé avec `hash_hmac('sha256', ...)` à partir du `secret` chiffré (`'encrypted'`) du badge, vérifié via `hash_equals()`, et révoquer l'ancien badge lors d'un renouvellement.
- **Alternatives écartées :** utiliser un identifiant statique en clair dans le QR code / badge sans rotation ni signature.
- **Raison :** limiter la rejouabilité d'un code capturé et gérer proprement le cycle de vie (`actif`, `suspendu`, `perdu`, `revoque`).

## Décision 5 — Synchronisation hors-ligne idempotente et alignement du schéma `passages`
- **Choix retenu :** dédupliquer chaque scan via `client_event_id` (et `local_event_id`) dans `PassageSyncController::sync()` avec double contrôle avant et dans une transaction `DB::transaction()`, rendre `appareil_id` nullable sur `passages`, et enregistrer systématiquement les horodatages au format `datetime` (`heure_locale`, `heure_corrigee`, `heure_serveur`, `heure_retenue`, `horodatage_appareil`, `horodatage_serveur`) avec `resultat_controle = 'valide'` et `etat_synchronisation = 'synchronise'`.
- **Alternatives écartées :** insérer directement chaque événement reçu sans clé d'idempotence client ou exiger un `appareil_id` non nul bloquant les terminaux mobiles non enregistrés.
- **Raison :** éviter tout doublon en base lors d'une coupure réseau, d'un redémarrage du terminal ou d'un rejeu de lot, tout en conservant la trace des heures appareil et serveur pour la détection d'anomalies (`horloge_decalee`).

## Décision 6 — Détection déterministe des anomalies sur fenêtres horaires et passages
- **Choix retenu :** centraliser l'évaluation des écarts dans `AnomalieDetectionService` en s'appuyant sur `Itineraire` et `Fenetre` (`jours_application`, `heure_debut`, `heure_fin`, `point_controle_id`, `sens`), sur la prise en compte de `exceptions_jour` (`ferie`, `absence_justifiee`), sur le seuil de double scan (60 s) et sur la déduplication idempotente des anomalies ouvertes par `(type, eleve_id, date_locale, fenetre_id/passage_id)`.
- **Alternatives écartées :** laisser la logique de retard, d'absence ou de doublon éparpillée dans les contrôleurs ou les vues Blade.
- **Raison :** produire des anomalies cohérentes et reproductibles quel que soit le point d'entrée (synchronisation en lot via `PassageSyncController` ou contrôle planifié/manuel via `anomalies:verifier`), tout en respectant les contraintes d'unicité de la table `anomalies`.

## Décision 7 — Cinquième anomalie : `hors_heures_etablissement`
- **Choix retenu :** ajouter le type d'anomalie `hors_heures_etablissement` lorsqu'un badge est scanné en dehors de la plage normale d'activité de l'établissement (en dehors de 06h00–20h00 ou un dimanche sans activité prévue).
- **Alternatives écartées :** détecter un décalage d'horloge uniquement via une anomalie dédiée ou ne lever qu'une alerte générique `hors_fenetre` même pour un passage nocturne isolé.
- **Raison :** distinguer un simple retard ou une sortie anticipée en journée scolaire d'un événement inhabituel en pleine nuit ou hors ouverture de l'établissement, qui mérite une vigilance particulière du responsable et du parent.

## Décision 8 — Notifications parents découplées, neutres et plafonnées par jour
- **Choix retenu :** isoler l'envoi derrière `NotificateurParentInterface` (`SimulateurNotificateurParent` par défaut), composer un message neutre sans donnée sensible pour chaque parent lié à l'élève, respecter l'unicité `(anomalie_id, parent_id)` et appliquer un plafond journalier configurable par élève (`non_envoye_plafond` avec `motif_non_envoi` explicite au-delà).
- **Alternatives écartées :** appeler directement un fournisseur externe dans le service d'anomalies ou envoyer un message par anomalie sans limite journalière ni contrôle de doublon.
- **Raison :** faciliter le branchement d'un fournisseur SMS réel sans toucher au domaine, éviter la saturation des parents en cas d'incidents répétés et garder la traçabilité complète en base (`messages_sortants`).