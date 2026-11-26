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