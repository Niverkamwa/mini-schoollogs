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
- **Modèles & relations :** ajout des relations Eloquent sur `Itineraire`, `Fenetre`, `Anomalie` et `Eleve`, ainsi que de `protected $guarded = [];` sur `Etablissement` (après résolution d'une `MassAssignmentException` lors de l'exécution des tests).
- **Moteur d'anomalies & contrôle :** création de `AnomalieDetectionService` (`hors_fenetre`, `double_scan`, `passage_manquant`, `point_hors_itineraire`, `badge_non_actif` et drapeaux `horloge_decalee` / `heure_incoherente` sur `passages`), branchement dans `PassageSyncController` avec alignement de `resultat_controle` sur `'accepte'`, et ajout de la commande Artisan `php artisan anomalies:verifier`.
- **Vérification :** ajout de `AnomalieDetectionTest` et validation complète de la suite avec `php artisan test` (**14 tests réussis, 52 assertions**).

**Cinquième anomalie (`hors_heures_etablissement`) :** ajout de la valeur `hors_heures_etablissement` à l'énumération `anomalies.type` (avec gestion de l'index unique SQLite dans la migration), détection dans `AnomalieDetectionService::analyserPassage()`, ajout de `protected $guarded = [];` sur `Etablissement`, et validation avec `php artisan migrate:fresh --seed` puis `php artisan test` (**15 tests réussis, 53 assertions**).
- **Notifications ciblées (`messages_sortants`) :** création de `MessageSortant`, `NotificateurParentInterface`, `SimulateurNotificateurParent`, `NotificationService` (messages neutres, plafond journalier configurable par élève, gestion de `non_envoye_plafond`), liaison dans `AppServiceProvider::register()` (après résolution d'une erreur `BindingResolutionException` liée à l'absence de `register()`), branchement à la création d'anomalie et ajout de `NotificationTest` (**16 tests réussis, 58 assertions**).

## Session — Interfaces par rôle, synchronisation hors ligne et réémission de badge
- **Interfaces & navigation :** Isolé `/connexion` sans navigation publique et structuré `resources/views/layouts/app.blade.php` avec une sidebar contextuelle par rôle (`agent`, `responsable`, `parent`). Retiré l'accès au poste de scan de la sidebar du responsable suite au refus explicite d'ajouter un poste de scan dans l'interface responsable.
- **Synchronisation (`/api/passages/sync` & `scan.blade.php`) :** Aligné le payload sur `evenements`, connecté l'analyse d'anomalies sur les passages synchronisés, ajouté la gestion de file locale avec interruption/reprise et bouton de vidage de file.
- **Modèles & contrôleurs :** Corrigé `Anomalie` (`HasMany`), `Eleve` (`anomalies()`, `passages()`), `PortailController` (`fenetre.pointControle`) et adapté `CloisonnementRolesTest` à la redirection de l'agent vers `/scan` (16 tests, 59 assertions au vert).
- **Réémission de badge (`BadgeService.php`) :** Corrigé l'erreur `500` liée à l'import manquant de `DB` et ajouté `emettrePourEleve(Eleve $eleve)` sous transaction.
- **Décision humaine / arbitrage :** Refus de l'ajout d'un poste de scan dans l'interface du responsable afin de maintenir la séparation stricte des rôles.
