# Cadrage technique complet - Moderation IA des annonces

## Objet

Mettre en place une moderation assistee par IA sur les annonces envoyees en `moderation`, avec publication automatique uniquement pour les cas simples et fiables.

La recommandation de V1 est volontairement conservative:

- si regles deterministes `OK` et IA `OK` => publication automatique
- si regles deterministes `KO` et/ou IA `KO` => pas de rejet automatique
- dans ce cas l'annonce reste en `moderation` pour revue humaine

Le rejet automatique pourra etre introduit plus tard, apres observation des cas reels en production.

## Principes de conception

La V1 doit etre:

- conservative
- tracable
- reversible
- simple a operer

Le pattern technique recommande est:

- creation d'un job dedie chaque fois qu'une annonce entre en `moderation`
- traitement asynchrone par cron Symfony
- decision hybride `hard rules + IA`
- application du resultat sur l'annonce
- supervision BO des jobs

## Etat actuel du code

### Flux annonce

Le flux principal est gere par [ProfileAnnouncementController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php).

Points clefs identifies:

- [ProfileAnnouncementController.php:1110](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1110) : methode `ajouterAnnonce(Request $request)`
- [ProfileAnnouncementController.php:1718](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1718) : methode `editerAnnonce(Request $request)`
- [ProfileAnnouncementController.php:1246](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1246) : branche `state == 'edition_admin'`
- [ProfileAnnouncementController.php:1754](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1754) : branche `state == 'edition'`
- [ProfileAnnouncementController.php:1786](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1786) : remise explicite de l'annonce en `moderation`

### Donnees annonce reutilisables

Les donnees consolidees existent deja via [ServiceManager.php](C:\Users\kalbr\Documents\GitHub\production\src\Service\ServiceManager.php):

- [ServiceManager.php:3807](C:\Users\kalbr\Documents\GitHub\production\src\Service\ServiceManager.php:3807) : `readAllAnnonceData($postId)`
- [ServiceManager.php:6137](C:\Users\kalbr\Documents\GitHub\production\src\Service\ServiceManager.php:6137) : `getPostStringDataValue(string $userId, string $tag)`

Les metas annonce actuellement ecrites par le flux sont notamment:

- `_price`
- `_product_country`
- `_product_adress`
- `_product_code_postal`
- `_product_precision`
- `_product_city`
- `_product_has_equipments_bureau`
- `_product_has_equipments_wifi`
- `_product_has_equipments_cofe`
- `_product_other_equipments`
- `_product_image_gallery`
- `images_annonces`
- `_product_video`
- `_product_devise`

### Notifications existantes

Le controleur envoie deja les emails selon le statut final de l'annonce:

- [ProfileAnnouncementController.php:1650](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1650) environ : bloc notification apres creation / edition admin
- [ProfileAnnouncementController.php:1948](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1948) environ : bloc notification apres edition front

Templates Brevo actuellement utilises:

- `27` : moderation / draft
- `28` : publication
- `29` : rejet

Le service reutilisable existe deja dans [BrevoMailer.php:5](C:\Users\kalbr\Documents\GitHub\production\src\Service\BrevoMailer.php:5).

### Pattern IA existant a reprendre

Le projet contient deja un pattern de jobs IA exploitable comme modele:

- [ProfileAiEnrichmentWorkerCommand.php:18](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:18) : worker Symfony
- [ProfileAiEnrichmentWorkerCommand.php:100](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:100) : traitement d'un job
- [ProfileAiEnrichmentWorkerCommand.php:178](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:178) : `claimJob()`
- [ProfileAiEnrichmentWorkerCommand.php:1044](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:1044) : lecture `OPENAI_API_KEY`
- [ProfileAiEnrichmentWorkerCommand.php:1061](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:1061) : lecture `AI_WORKER_REQUEST_TIMEOUT`

Supervision BO existante a dupliquer:

- [ProfileAiEnrichmentBackofficeController.php:64](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\ProfileAiEnrichmentBackofficeController.php:64) : endpoint `stats`
- [ProfileAiEnrichmentBackofficeController.php:297](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\ProfileAiEnrichmentBackofficeController.php:297) : endpoint `detail`
- [ProfileAiEnrichmentBackofficeController.php:396](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\ProfileAiEnrichmentBackofficeController.php:396) : endpoint `retry`

## Architecture recommandee

### Choix V1

Je recommande:

- une file de jobs dediee a la moderation IA des annonces
- un cron Symfony toutes les 30 minutes
- un worker qui traite les jobs `pending` un a un
- une decision hybride `regles + IA`
- aucune suppression ni rejet automatique en V1

Je ne recommande pas un moteur evenementiel en V1.

Pourquoi:

- l'existant a deja un pattern `job + worker`
- le besoin n'impose pas de temps reel strict
- le cron est bien plus simple a exploiter et debugguer

## Schema SQL recommande

### Fichier SQL a creer

- `sql/create_announcement_ai_moderation_tables.sql`

### Table principale des jobs

```sql
CREATE TABLE IF NOT EXISTS announcement_ai_moderation_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    post_status_snapshot VARCHAR(20) NOT NULL DEFAULT 'moderation',
    source_transition VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    decision_source VARCHAR(30) DEFAULT NULL,
    decision_code VARCHAR(50) DEFAULT NULL,
    decision_summary VARCHAR(255) DEFAULT NULL,
    ai_model VARCHAR(100) DEFAULT NULL,
    ai_confidence DECIMAL(5,4) DEFAULT NULL,
    payload_snapshot LONGTEXT DEFAULT NULL,
    hard_rules_pass TINYINT(1) DEFAULT NULL,
    ai_pass TINYINT(1) DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX IDX_AAMJ_STATUS_CREATED (status, created_at),
    INDEX IDX_AAMJ_ANNOUNCEMENT_STATUS (announcement_id, status),
    INDEX IDX_AAMJ_USER_STATUS (user_id, status),
    INDEX IDX_AAMJ_PROCESSED_AT (processed_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
```

### Table detail des checks

Cette table porte la tracabilite des controles, tres utile en BO.

```sql
CREATE TABLE IF NOT EXISTS announcement_ai_moderation_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    moderation_job_id BIGINT UNSIGNED NOT NULL,
    criterion_code VARCHAR(80) NOT NULL,
    criterion_label VARCHAR(120) NOT NULL,
    source_type VARCHAR(30) NOT NULL,
    result VARCHAR(20) NOT NULL,
    score DECIMAL(5,4) DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    raw_value LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX IDX_AAMC_JOB_ID (moderation_job_id),
    INDEX IDX_AAMC_RESULT (result),
    UNIQUE INDEX UNIQ_AAMC_JOB_CRITERION (moderation_job_id, criterion_code),
    CONSTRAINT FK_AAMC_JOB FOREIGN KEY (moderation_job_id)
        REFERENCES announcement_ai_moderation_jobs (id)
        ON DELETE CASCADE,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
```

### Statuts de job recommandes

- `pending`
- `processing`
- `approved`
- `manual_review`
- `failed`
- `cancelled`

### Sens des colonnes importantes

- `source_transition` : origine du passage en moderation
  - valeurs proposees: `front_create`, `front_resubmit`, `admin_resubmit`
- `decision_source` : origine de la decision finale
  - valeurs proposees: `rules_only`, `rules_and_ai`, `technical_failure`
- `decision_code` : code metier synthese
  - valeurs proposees: `auto_publish`, `missing_required_fields`, `ai_not_confident`, `ai_refused`, `timeout`
- `payload_snapshot` : snapshot JSON de l'annonce au moment du traitement
- `hard_rules_pass` : resultat global des checks deterministes
- `ai_pass` : resultat global de la qualification IA

## Criteres a analyser

### Hard rules V1

Je recommande de commencer par ces checks:

- `price_min`
- `has_title`
- `title_min_length`
- `has_description`
- `description_min_length`
- `has_main_photo`
- `has_location_core_fields`
- `has_category`

### Qualification IA V1

L'IA doit evaluer:

- la clarte du titre
- la qualite minimale de la description
- la coherence entre titre, description, categorie et caracteristiques
- la completude generale
- la presence d'elements manifestement insuffisants ou trompeurs

Le format de reponse recommande est un JSON strict:

```json
{
  "eligible": true,
  "confidence": 0.91,
  "summary": "Annonce exploitable et suffisamment complete.",
  "checks": [
    {
      "criterion_code": "title_quality",
      "result": "pass",
      "score": 0.92,
      "reason": "Titre clair et exploitable."
    },
    {
      "criterion_code": "description_quality",
      "result": "pass",
      "score": 0.88,
      "reason": "Description suffisamment informative."
    }
  ]
}
```

## Services et classes a creer

### Services coeur

- `src/Service/AnnouncementModeration/AnnouncementModerationJobManager.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationContextBuilder.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationPrecheckService.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationAiService.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationDecisionService.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationStatusApplier.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationNotificationService.php`

### Commande Symfony

- `src/Command/AnnouncementAiModerationWorkerCommand.php`

### Supervision BO

- `src/Controller/Admin/AnnouncementAiModerationBackofficeController.php`

### DTO recommandes

- `src/Service/AnnouncementModeration/Dto/AnnouncementModerationContext.php`
- `src/Service/AnnouncementModeration/Dto/AnnouncementModerationCheckResult.php`
- `src/Service/AnnouncementModeration/Dto/AnnouncementModerationDecision.php`

## Responsabilites detaillees

### `AnnouncementModerationJobManager`

Responsabilites:

- creer un job
- empecher plusieurs jobs actifs pour une meme annonce
- fermer ou annuler les anciens jobs si necessaire
- charger le prochain job `pending`
- faire le claim atomique
- stocker `attempt_count`, `last_error`, `processed_at`

### `AnnouncementModerationContextBuilder`

Responsabilites:

- charger l'annonce `WpPosts`
- charger les metas utiles
- appeler `readAllAnnonceData()`
- produire un contexte normalise pour les checks et l'IA

Dependances cibles:

- `ServiceManager`
- `EntityManagerInterface`
- `WpPostsRepository`

### `AnnouncementModerationPrecheckService`

Responsabilites:

- executer les hard rules
- produire une liste de checks normalises
- sortir un verdict global `pass/fail`

### `AnnouncementModerationAiService`

Responsabilites:

- construire le prompt
- appeler le LLM
- parser le JSON strict
- normaliser la reponse
- lever une erreur explicite en cas de JSON invalide ou timeout

Recommandation:

- reprendre le style de gestion HTTP et timeout de `ProfileAiEnrichmentWorkerCommand`
- ne pas disperser les appels HTTP dans plusieurs classes

### `AnnouncementModerationDecisionService`

Responsabilites:

- fusionner le verdict hard rules et le verdict IA
- sortir une decision finale:
  - `publish`
  - `manual_review`
  - `failed`

### `AnnouncementModerationStatusApplier`

Responsabilites:

- appliquer la decision sur `wp_posts.post_status`
- publier l'annonce si feu vert
- laisser l'annonce en `moderation` sinon

Important:

- ce service n'appelle pas l'IA
- ce service ne decide pas
- ce service applique uniquement le resultat

### `AnnouncementModerationNotificationService`

Responsabilites:

- envoyer les emails metier
- reutiliser `BrevoMailer`
- centraliser les IDs de templates et la construction du payload
- envoyer un recap admin Trust des annonces a traiter manuellement

### `AnnouncementAiModerationWorkerCommand`

Responsabilites:

- lire les jobs `pending`
- traiter un job a la fois
- claim atomique
- construire le contexte
- lancer hard rules
- appeler l'IA si les hard rules passent
- enregistrer checks et decision
- appliquer la decision
- journaliser les erreurs

### `AnnouncementAiModerationBackofficeController`

Responsabilites:

- statistiques
- liste des jobs
- detail d'un job
- retry des jobs en echec

## Points d'integration exacts dans le code actuel

### 1. Creation du job lors du passage en moderation

#### Cas A - creation front

Fichier:

- [ProfileAnnouncementController.php:1110](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1110)

Point d'integration:

- dans `ajouterAnnonce()`
- apres creation de l'annonce et de ses metas
- si `post_type = product`
- si `post_status = moderation`

Action a ajouter:

- appeler `AnnouncementModerationJobManager::enqueueForModeration($announcementId, $userId, 'front_create')`

#### Cas B - re soumission front apres edition

Fichier:

- [ProfileAnnouncementController.php:1718](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1718)
- [ProfileAnnouncementController.php:1754](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1754)
- [ProfileAnnouncementController.php:1786](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1786)

Point d'integration:

- dans `editerAnnonce()`
- branche `state == 'edition'`
- juste apres le `setPostStatus('moderation')` et le `flush()`

Action a ajouter:

- appeler `AnnouncementModerationJobManager::enqueueForModeration($announcementId, $userId, 'front_resubmit')`
- annuler au prealable les jobs actifs existants sur cette annonce

#### Cas C - remise en moderation depuis le BO

Fichier:

- [ProfileAnnouncementController.php:1246](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1246)
- [ProfileAnnouncementController.php:1398](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1398)
- [edit.html.twig:66](C:\Users\kalbr\Documents\GitHub\production\templates\admin\Annonces\Annonces\edit.html.twig:66)
- [edit.html.twig:71](C:\Users\kalbr\Documents\GitHub\production\templates\admin\Annonces\Annonces\edit.html.twig:71)

Point d'integration:

- dans `ajouterAnnonce()`
- branche `state == 'edition_admin'`
- si l'admin choisit `status = moderation`

Action a ajouter:

- appeler `AnnouncementModerationJobManager::enqueueForModeration($announcementId, $userId, 'admin_resubmit')`

### 2. Construction du contexte annonce

Fichiers:

- [ServiceManager.php:3807](C:\Users\kalbr\Documents\GitHub\production\src\Service\ServiceManager.php:3807)
- [ServiceManager.php:6137](C:\Users\kalbr\Documents\GitHub\production\src\Service\ServiceManager.php:6137)

Point d'integration:

- `AnnouncementModerationContextBuilder`

Implementation recommandee:

- partir de `readAllAnnonceData($postId)` comme socle
- completer avec `getPostStringDataValue()` pour les metas simples
- injecter aussi l'objet `WpPosts` pour avoir le statut, le titre, le contenu, l'auteur et les dates

### 3. Application du statut final

Fichier source du comportement actuel:

- [ProfileAnnouncementController.php:1650](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1650)
- [ProfileAnnouncementController.php:1948](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1948)

Point d'integration:

- extraire le changement de statut final vers `AnnouncementModerationStatusApplier`

Regles V1:

- `publish` si hard rules `PASS` et IA `PASS`
- conserver `moderation` dans tous les autres cas non techniques
- ne jamais envoyer l'annonce en `trash` automatiquement en V1

### 4. Notifications

Fichier source du comportement actuel:

- [ProfileAnnouncementController.php:1683](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1683)
- [ProfileAnnouncementController.php:1966](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ProfileAnnouncementController.php:1966)
- [BrevoMailer.php:11](C:\Users\kalbr\Documents\GitHub\production\src\Service\BrevoMailer.php:11)

Point d'integration:

- extraire la logique de mail vers `AnnouncementModerationNotificationService`
- reutiliser `BrevoMailer::sendTemplate()`

Notifications a garder en V1:

- email de soumission en moderation
- email de publication automatique
- email admin Trust avec la liste des annonces basculees en `manual_review`

Notification a ne pas activer en V1:

- rejet automatique

### 4 bis. Notification admin Trust de revue manuelle

Besoin ajoute:

- a chaque traitement du worker, envoyer a l'admin Trust la liste des annonces qui restent a traiter manuellement

Recommendation technique:

- ne pas envoyer un email par annonce
- envoyer un email recapitulatif par run du worker
- inclure uniquement les annonces qui viennent d'etre basculees en `manual_review` pendant ce run

Pourquoi:

- evite le bruit si plusieurs annonces sont traitees sur un meme passage cron
- donne une liste exploitable directement par l'equipe Trust
- reste coherent avec une execution toutes les 30 minutes

Contenu recommande de la notification:

- identifiant du job
- identifiant de l'annonce
- titre de l'annonce
- proprietaire de l'annonce
- raison courte de la revue manuelle
- lien BO vers l'annonce
- lien BO vers le detail du job IA

Implementation recommandee:

- `AnnouncementAiModerationWorkerCommand` accumule les annonces passees en `manual_review` pendant le run
- en fin d'execution, il appelle `AnnouncementModerationNotificationService::sendManualReviewDigest(array $items)`
- le service envoie un template Brevo dedie a une liste admin

Template cible a prevoir:

- nouveau template Brevo dedie aux operations internes Trust

Destinataires recommandes:

- boite exploitation Trust
- eventuellement copie a `commerce@trustandmarket.com`

### 5. Worker et claim atomique

Fichier de reference:

- [ProfileAiEnrichmentWorkerCommand.php:100](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:100)
- [ProfileAiEnrichmentWorkerCommand.php:178](C:\Users\kalbr\Documents\GitHub\production\src\Command\ProfileAiEnrichmentWorkerCommand.php:178)

Point d'integration:

- `AnnouncementAiModerationWorkerCommand`

Comportements a reprendre:

- lecture FIFO des jobs `pending`
- claim atomique via `UPDATE ... WHERE status = 'pending'`
- increment de `attempt_count`
- stockage de `last_error`
- transitions explicites de statuts

### 6. BO de supervision

Fichier de reference:

- [ProfileAiEnrichmentBackofficeController.php:64](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\ProfileAiEnrichmentBackofficeController.php:64)
- [ProfileAiEnrichmentBackofficeController.php:297](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\ProfileAiEnrichmentBackofficeController.php:297)
- [ProfileAiEnrichmentBackofficeController.php:396](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\ProfileAiEnrichmentBackofficeController.php:396)

Point d'integration:

- nouveau controller admin dedie a la moderation IA des annonces

Comportements a exposer:

- stats
- liste des jobs
- detail d'un job
- retry

## Variables d'environnement recommandees

Deux options sont possibles.

### Option 1 - reutiliser les variables IA existantes

- `OPENAI_API_KEY`
- `OPENAI_BASE_URL`
- `OPENAI_MODEL`
- `AI_WORKER_REQUEST_TIMEOUT`

### Option 2 - dedier des variables a la moderation d'annonces

- `ANNOUNCEMENT_AI_OPENAI_API_KEY`
- `ANNOUNCEMENT_AI_OPENAI_BASE_URL`
- `ANNOUNCEMENT_AI_OPENAI_MODEL`
- `ANNOUNCEMENT_AI_REQUEST_TIMEOUT`
- `ANNOUNCEMENT_AI_MIN_PRICE_DEFAULT`
- `ANNOUNCEMENT_AI_TITLE_MIN_LENGTH`
- `ANNOUNCEMENT_AI_DESCRIPTION_MIN_LENGTH`

Recommandation:

- V1 peut reutiliser l'existant pour aller vite
- mais des variables dediees seront preferables si les seuils ou le modele doivent diverger

## Commande cron recommandee

```bash
php bin/console app:announcement-ai:worker --limit=20 --mode=live
```

Frequence:

- toutes les 30 minutes

## Algorithme de traitement recommande

### Etape 1 - recuperer le prochain job

- lire le plus ancien job `pending`
- faire un claim atomique

### Etape 2 - construire le contexte

- charger `WpPosts`
- charger metas
- construire le snapshot JSON

### Etape 3 - executer les hard rules

- prix
- photo
- titre
- description
- localisation
- categorie

### Etape 4 - si hard rules FAIL

- enregistrer les checks
- marquer le job `manual_review`
- laisser l'annonce en `moderation`

### Etape 5 - sinon appeler l'IA

- evaluer la qualite globale
- parser le JSON strict

### Etape 6 - prendre la decision finale

- si IA `PASS` => `publish`
- sinon => `manual_review`

### Etape 7 - appliquer le resultat

- `publish` => statut annonce `publish` + notification
- `manual_review` => annonce conservee en `moderation`

### Etape 8 - finaliser le job

- `approved` si publication auto
- `manual_review` si revue humaine necessaire
- `failed` si erreur technique

### Etape 9 - envoyer le digest admin Trust

- si au moins une annonce du run est en `manual_review`
- envoyer un recapitulatif unique a l'admin Trust

## Shadow mode recommande

Avant activation automatique en production, je recommande une phase de shadow mode:

- creation des jobs
- execution des rules et de l'IA
- stockage de la decision
- aucune publication automatique

Objectif:

- mesurer les faux positifs
- ajuster les seuils
- comparer avec les decisions humaines BO

## Risques et parades

### Faux positifs IA

Parade:

- pas de rejet auto en V1
- maintien en moderation si doute

### Duplicats de jobs

Parade:

- interdire plusieurs jobs actifs pour une meme annonce
- annuler les jobs precedents lors d'une nouvelle soumission

### Timeouts ou erreurs LLM

Parade:

- statut `failed`
- retry BO
- annonce conservee en `moderation`

### Criteres metier insuffisamment formalises

Parade:

- commencer par peu de hard rules
- garder l'IA sur la qualite editoriale et la coherence, pas sur des champs techniques ambigus

## Plan d'implementation recommande

### Lot 1 - socle

- creer les tables SQL
- creer `AnnouncementModerationJobManager`
- creer `AnnouncementModerationContextBuilder`
- creer `AnnouncementModerationPrecheckService`
- brancher la creation des jobs dans `ProfileAnnouncementController`

### Lot 2 - moteur IA

- creer `AnnouncementModerationAiService`
- creer `AnnouncementModerationDecisionService`
- creer `AnnouncementAiModerationWorkerCommand`
- persister les checks et decisions

### Lot 3 - application metier

- creer `AnnouncementModerationStatusApplier`
- creer `AnnouncementModerationNotificationService`
- activer la publication auto des cas verts
- ajouter le digest admin Trust des annonces a revoir manuellement

### Lot 4 - operabilite

- creer `AnnouncementAiModerationBackofficeController`
- ajouter stats, detail, retry
- lancer shadow mode si besoin

## Fichiers a creer

- `sql/create_announcement_ai_moderation_tables.sql`
- `src/Command/AnnouncementAiModerationWorkerCommand.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationJobManager.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationContextBuilder.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationPrecheckService.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationAiService.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationDecisionService.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationStatusApplier.php`
- `src/Service/AnnouncementModeration/AnnouncementModerationNotificationService.php`
- `src/Controller/Admin/AnnouncementAiModerationBackofficeController.php`

## Fichiers a modifier

- `src/Controller/ProfileAnnouncementController.php`
- `src/Service/BrevoMailer.php` si besoin de petits ajustements de payload
- `src/Repository/WpPostsRepository.php` si besoin d'acces complementaires

## Recommandation finale

La bonne V1 pour TrustandMarket est:

- une moderation IA asynchrone
- basee sur jobs + cron
- avec publication automatique seulement pour les cas simples et fiables
- sans rejet automatique
- en conservant la moderation humaine comme filet de securite
