# mini-schoollogs — Module « Passage » (Test Technique Afric'Éduc)

Prototype du module **Passage** pour **SchoolLogs** , permettant le suivi des passages d'élèves aux points de contrôle en conditions de réseau dégradé, la détection d'anomalies, la notification des parents et une synthèse journalière encadrée[cite: 1].

---

## 1. Présentation du projet

Le module *Passage* répond à une contrainte opérationnelle précise : au portail ou lors de la montée en autobus, la connexion réseau peut être instable, l'horloge d'un appareil peut être décalée, et un badge ne doit pas pouvoir être réutilisé impunément par un tiers qui l'a photographié ou capturé.

L'application implémente :
- Un poste de scan (`/scan`) fonctionnant hors ligne via `localStorage` avec synchronisation par lots idempotente (`POST /api/passages/sync`).
- Un mécanisme de badges tournants signés par HMAC avec fenêtre de validité (`slot`) et tolérance de pas.
- Un cloisonnement strict par rôle (`agent`, `responsable`, `parent`) et par périmètre d'accès (`ElevePolicy`, vérifications serveur, refus `403`).
- La détection d'anomalies et la journalisation de messages sortants vers les parents (avec plafond quotidien).
- Une synthèse journalière encadrée reposant sur des métriques calculées côté serveur avec repli déterministe sans clé API.

---

## 2. Technologies utilisées et justification

- **PHP 8+ & Laravel 12** : framework web structuré offrant nativement le routage, les middlewares d'authentification et de limitation de débit, l'ORM Eloquent (requêtes paramétrées), les *Policies*, les migrations rejouables, les *seeders* et PHPUnit/Laravel Test pour les tests automatisés.
- **SQLite** : base de données locale légère, zéro configuration externe, recréable à tout moment via `php artisan migrate:fresh --seed`, adaptée à l'exécution locale et aux tests en environnement contraint.
- **Blade & Tailwind CSS (CDN)** : rendu serveur simple et lisible sur ordinateur comme sur mobile, sans complexité front-end superflue, tout en préservant l'échappement automatique (`{{ ... }}`) contre les injections XSS[cite: 1].
- **JavaScript natif + `localStorage` sur `/scan`** : gestion légère de la file d'attente hors ligne, simulation de coupure/rétablissement réseau, interruption/reprise de synchronisation et simulation de lots (20 scans) sans dépendance lourde.

---

## 3. Ce qui a réellement été fait

- **Authentification & sécurité de session** : connexion par identifiant (`/connexion`), hachage de mot de passe (longueur minimale 8 caractères), limitation des tentatives (6e tentative limitée), message d'erreur uniforme, déconnexion invalidant la session et page `/connexion` isolée.
- **Cloisonnement des rôles et des données** : `agent` redirigé vers `/scan` et bloqué (`403`) sur `/eleves/{eleve}` ; `responsable` accédant au pilotage ; `parent` limité strictement à ses propres enfants (`403` si accès direct à l'URL d'un autre élève) et consultant ses notifications reçues.
- **Badges tournants & cycle de vie** : génération et vérification de jetons signés HMAC (`v1.<public_id>.<slot>.<signature>`) avec pas temporel et tolérance de pas (`$tolerancePas`), contrôle de l'état actif, suspension/réactivation et réémission transactionnelle invalidant l'ancien badge.
- **Scan hors ligne & synchronisation idempotente** : mise en file locale (`localStorage`), coupure/rétablissement réseau, interruption/reprise, simulation de 20 scans, protection anti-double-clic, déduplication côté serveur sur `client_event_id` (`accepte`, `doublon`, `rejete`) et comptage fidèle sur `data.resultats`.
- **Anomalies & notifications parents** : détection des anomalies (`hors_fenetre`, `passage_manquant`, `double_scan`, `point_hors_itineraire`, `badge_non_actif`, `hors_heures_etablissement`) et enregistrement de messages sortants neutres avec plafond journalier.
- **Synthèse journalière encadrée (`SyntheseIaService`)** : calcul des métriques côté serveur et génération d'une synthèse avec repli déterministe lorsque aucune clé API n'est fournie.
- **Tests automatisés** : suite de tests feature/unit passant au vert (`18 passed`).

---

## 4. Limites actuelles et ce qui n'a pas pu être terminé / approfondi

Conformément à l'avertissement de l'énoncé sur le périmètre et l'arbitrage sous contrainte, les points suivants présentent des limites ou restent partiels[cite: 1, 2, 6] :

- **Absence de badge physique et de test matériel de photographie** : il n'y a pas de carte/QR physique imprimé ni de second appareil mobile dédié pour photographier un badge à l'écran et le présenter physiquement devant une caméra[cite: 4]. L'usurpation / rejeu est testée de manière logicielle via la réutilisation d'un jeton expiré, d'un badge suspendu ou révoqué, et les tests automatisés.
- **Badges réduits à des jetons textuels** : dans l'interface actuelle, le badge est représenté par une chaîne de jeton tournant (`v1.<public_id>.<slot>.<signature>`) copiée/collée ou préremplie dans un champ texte, plutôt que par un rendu visuel dédié (QR/code-barres) ou un support matériel.
- **Synthèse IA minimaliste** : la brique de synthèse repose actuellement sur une collecte de métriques et un repli déterministe fonctionnel sans clé API ; l'intégration complète d'un fournisseur LLM externe avec suivi de coût journalier plafonné, journalisation dédiée de chaque appel et nouvelle tentative unique sur schéma strict reste minimaliste/à consolider.
- **Déclenchement automatique planifié sans intervention humaine** : la détection des passages manquants est exposée via le service et l'action responsable (`verifierPassagesManquants`), mais l'automatisation périodique en tâche de fond (cron/scheduler OS tournant en continu sans ouverture préalable de l'application) n'est pas encore branchée en continu.



---

## 5. Sécurité

- **Mots de passe** : hachés via `Hash::make()` (`bcrypt`/`argon2`) avec validation d'une longueur minimale de 8 caractères.
- **Protection contre la force brute et énumération** : limitation du nombre de tentatives de connexion (`RateLimiter`) et message d'erreur générique identique, que l'identifiant existe ou non.
- **Session et déconnexion** : session Laravel à durée de vie configurée, régénérée à la connexion et invalidée (`invalidate()` + régénération du jeton CSRF) à la déconnexion.
- **Contrôle des rôles côté serveur** : toutes les routes et actions sensibles vérifient le rôle/droits côté serveur (`PortailController`, `PassageSyncController`, `ElevePolicy`), jamais uniquement par masquage UI.
- **Cloisonnement des données** : `PortailController::afficherEleve()` et `ElevePolicy::view()` vérifient l'appartenance de l'élève aux enfants du parent connecté et renvoient `403` en cas d'accès direct (`/eleves/{autre_id}`).
- **Validation, requêtes et XSS** : validation stricte via `$request->validate()`, requêtes via Eloquent (paramétrées) et échappement Blade par défaut (`{{ ... }}`).
- **Secrets** : aucune clé ni secret en dur dans le dépôt ; configuration via `.env` (avec `.env.example` sans secrets réels).
- **Avec davantage de temps** : ajout d'une table/journal d'audit (`journal_audit`) systématique sur les consultations sensibles, révocation/rotation de clés HMAC avec période de grâce, tests de non-régression sécurité supplémentaires et durcissement des en-têtes HTTP.

---

## 6. Mode dégradé et garde-fous IA

- **Principe** : le modèle ne calcule jamais les chiffres (comptage des passages, anomalies, messages) ; toutes les métriques sont agrégées par le code PHP puis transmises pour mise en forme.
- **Mode dégradé sans clé ou en cas d'erreur** : si `SERVICES_IA_KEY` / `SERVICES_IA_URL` est absent ou si l'appel échoue/expire, `SyntheseIaService` bascule automatiquement sur `construireTexteDeterministe()` qui produit une synthèse factuelle exacte à partir des chiffres en base, sans faire planter le tableau de bord.
- **Évolution prévue** : validation stricte de la sortie JSON/texte avec au plus une nouvelle tentative avant repli déterministe, journalisation de chaque appel (`modèle`, `volume`, `coût estimé`, `origine`), mise en cache/anti-doublon pour éviter de refacturer deux requêtes identiques et plafond quotidien configurable.

---

## 7. Installation et lancement

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve