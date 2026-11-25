# Journal d'utilisation de l'IA — mini-schoollogs (« Passage »)

## 25 novembre 2026 — Squelette, CI, schéma SQLite, seeder et authentification
- **Squelette & CI :** initialisation Laravel 12, vérification de l'exclusion de `.env`, ajout du workflow GitHub Actions exécutant `php artisan test`.
- **Base de données & seeder :** création des migrations et de `DatabaseSeeder` (comptes de démonstration, élèves, parents, itinéraire, fenêtres, exemples d'anomalies).
- **Authentification & ajustements :** mise en place de la connexion avec limitation des tentatives et déconnexion. Ajustements réalisés lors des tests : redirection des invités vers `/connexion` dans `bootstrap/app.php`, alignement de `ExampleTest` sur la redirection `302` vers `/connexion`, et ajustement du test de seuil `429` sur le `RateLimiter`.
- **Vérification :** exécution locale de `php artisan test` (6 tests au vert) et envoi du courriel de point d'étape à l'équipe Afric'Éduc.