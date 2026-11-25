# Décisions d'architecture — mini-schoollogs (« Passage »)

## Décision 1 — Base de données SQLite
- **Choix retenu :** SQLite (`database/database.sqlite`).
- **Alternatives écartées :** PostgreSQL.
- **Raison :** zéro installation serveur necessaire, exécution immédiate avec `php artisan migrate:fresh --seed` et compatibilité native avec `pdo_sqlite` dans GitHub Actions.

## Décision 2 — Authentification par `identifiant` sur la table `users`
- **Choix retenu :** conserver la table `users` avec une colonne `identifiant` unique, des mots de passe hachés, un `RateLimiter` (`connexion`) et l'invalidation complète de session à la déconnexion.
- **Alternatives écartées :** créer une table séparée sans réutiliser le guard `web` standard de Laravel.
- **Raison :** s'appuyer sur le mécanisme de session et de sécurité natif de Laravel tout en respectant les champs métier.