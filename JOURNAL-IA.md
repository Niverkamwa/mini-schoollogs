# Journal d'utilisation de l'IA — mini-schoollogs (« Passage »)

## 25 novembre 2026 — Squelette, CI, schéma SQLite, seeder et authentification
- **Squelette & CI :** initialisation Laravel 12, vérification de l'exclusion de `.env`, ajout du workflow GitHub Actions exécutant `php artisan test`.
- **Base de données & seeder :** création des migrations et de `DatabaseSeeder` (comptes de démonstration, élèves, parents, itinéraire, fenêtres, exemples d'anomalies).
- **Authentification & ajustements :** mise en place de la connexion avec limitation des tentatives et déconnexion. Ajustements réalisés lors des tests : redirection des invités vers `/connexion` dans `bootstrap/app.php`, alignement de `ExampleTest` sur la redirection `302` vers `/connexion`, et ajustement du test de seuil `429` sur le `RateLimiter`.
- **Vérification :** exécution locale de `php artisan test` (6 tests au vert) et envoi du courriel de point d'étape à l'équipe Afric'Éduc.
## 26 novembre 2026 (matin) — Rôles, cloisonnement côté serveur et badges tournants
- **RBAC & cloisonnement :** ajout de `ElevePolicy`, `AnomaliePolicy`, `PortailController`, des vues du portail et de `CloisonnementRolesTest`.
- **Ajustements techniques :** ajout de `$table = 'points_controle'` sur `PointControle` et des relations (`etablissement`, `itineraire`, `parents`, `badgeActif`) sur `Eleve` après constat d'échecs de tests.
- **Badges tournants :** création de `BadgeService` (génération HMAC par pas de 60 s, vérification avec `hash_equals`, changement d'état et renouvellement avec révocation de l'ancien badge) et de `BadgeServiceTest`.
- **Vérification :** exécution locale de `php artisan test` pour valider les tests d'accès et de badges avant commit et push.
## 26 novembre 2026 (après-midi) — Scan hors-ligne, synchronisation idempotente et résolution des contraintes SQLite
- **Scan terrain & synchronisation :** ajout de la vue `/portail/scan` (`resources/views/portail/scan.blade.php`) et de l'endpoint `POST /api/passages/sync` (`PassageSyncController`) assurant la déduplication par `client_event_id` et `local_event_id` au sein d'une transaction.
- **Diagnostic & ajustements techniques :** résolution des violations de contraintes `NOT NULL` sur SQLite après inspection du schéma réel via `PRAGMA table_info(passages)`. Ajout d'une migration rendant `appareil_id` nullable et renseignement complet des horodatages `datetime` (`heure_locale`, `heure_corrigee`, `heure_serveur`, `heure_retenue`, `horodatage_appareil`, `horodatage_serveur`), ainsi que `resultat_controle` et `etat_synchronisation`.
- **Vérification :** ajout de `PassageSyncTest` et exécution complète de `php artisan test` (12 tests réussis, 49 assertions au vert) avant mise à jour de `DECISIONS.md` et push sur GitHub.