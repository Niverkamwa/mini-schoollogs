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