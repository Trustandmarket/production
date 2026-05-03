# TrustAgent IA
## Documentation technico-fonctionnelle (etat implemente)

Date de mise a jour: 2026-05-03  
Perimetre: Back-end, Worker, BO, Front profil pro

---

## 1. Objectif produit
TrustAgent aide un professionnel a completer son profil a partir de donnees publiques, avec validation humaine avant ecriture finale.

Flux utilisateur:
1. Clic sur le CTA `Trouver mes informations automatiquement avec TrustAgent`.
2. Ouverture de la modale 1 (entree source).
3. Creation d un job d enrichissement (`pending`).
4. Traitement asynchrone par le worker.
5. Passage du job en `awaiting_user` + creation des suggestions.
6. Ouverture de la modale 2 (revue suggestions).
7. L utilisateur accepte, edite ou rejette champ par champ.
8. Application en base profil (`apply`), puis fin technique (`success`).

---

## 2. Perimetre MVP implemente
Champs enrichis/suggeres:
- `business_name`
- `phone`
- `main_activity`
- `addresses`
- `skills`
- `experiences_text`
- `project_references_text`
- `siret`
- `tva_number`

Champs exclus volontairement du MVP:
- `photos`
- `videos`
- `avatar_url`

Ces champs exclus ne sont plus collectes, ni affines par le LLM, ni proposes en suggestions.

---

## 3. Architecture logique
Composants:
1. Front profil pro: `templates/profile/index.html.twig`
2. API metier: `src/Controller/ProfileAiEnrichmentController.php`
3. Worker asynchrone: `src/Command/ProfileAiEnrichmentWorkerCommand.php`
4. Persistance SQL: `profile_ai_enrichment_jobs`, `profile_ai_suggestions`
5. BO supervision: `src/Controller/Admin/ProfileAiEnrichmentBackofficeController.php`
6. Services externes (live): Google Places, scraping website/OpenGraph, OpenAI

Principe:
- le front ne fait pas l enrichissement directement;
- il cree un job puis lit son etat;
- le worker traite et alimente les suggestions.

---

## 4. Modele de donnees

### 4.1 Table `profile_ai_enrichment_jobs`
Colonnes cles:
- `id`
- `profile_id`
- `input_type` (`studio_name` ou `website`)
- `input_value`
- `input_region` (champ region/ville saisi en modale 1)
- `status`
- `attempt_count`
- `last_error`
- `prompt_version`
- `confidence_global`
- `active_profile_id` (verrou un job actif par profil)
- `created_at`
- `updated_at`

Indexes/contraintes:
- `UNIQ_PAEJ_ONE_ACTIVE_PROFILE (active_profile_id)`
- `IDX_PAEJ_PROFILE_STATUS_CREATED (profile_id, status, created_at)`
- `IDX_PAEJ_STATUS_CREATED (status, created_at)`

### 4.2 Table `profile_ai_suggestions`
Colonnes cles:
- `id`
- `enrichment_job_id`
- `profile_id`
- `field_name`
- `suggested_value` (string/JSON)
- `confidence_score`
- `source_type`
- `status` (`suggested`, `accepted`, `edited`, `rejected`)
- `final_value`
- `created_at`
- `updated_at`

Indexes/contraintes:
- `UNIQ_PAS_JOB_FIELD (enrichment_job_id, field_name)`
- `IDX_PAS_JOB_ID (enrichment_job_id)`
- `IDX_PAS_PROFILE_STATUS (profile_id, status)`

Scripts SQL:
- `sql/create_profile_ai_enrichment_tables.sql`
- `sql/alter_profile_ai_enrichment_jobs_add_input_region.sql`
- `sql/mock_test_profile_ai_worker.sql`

---

## 5. Machine d etats

### 5.1 Etats job
- `pending`: job cree
- `processing`: pris par le worker
- `awaiting_user`: enrichissement technique fini, attente des decisions user
- `applying`: application des decisions en cours
- `success`: fin technique du workflow
- `failed`: echec technique

### 5.2 Etats suggestion
- `suggested`
- `accepted`
- `edited`
- `rejected`

Important:
- `success` = succes technique, pas garantie de qualite metier.

---

## 6. API Front TrustAgent
Controller: `src/Controller/ProfileAiEnrichmentController.php`

Endpoints:
1. `POST /enrichment-jobs`
2. `GET /enrichment-jobs/{id}`
3. `GET /enrichment-jobs/active`
4. `POST /enrichment-jobs/{id}/decisions`
5. `POST /enrichment-jobs/{id}/apply`

### 6.1 POST /enrichment-jobs
Entree JSON:
- `input_type`: `studio_name` ou `website`
- `input_value`: obligatoire
- `input_region`: attendu quand `studio_name`
- `prompt_version`: optionnel

Regles:
- bloque si un job actif existe deja pour le profil;
- cree le job en `pending`.

### 6.2 POST /enrichment-jobs/{id}/decisions
Entree JSON:
- `decisions[]`
- `suggestion_id`
- `status`: `accepted` / `edited` / `rejected`
- `final_value` attendu si `edited`

Precondition:
- job en `awaiting_user`.

### 6.3 POST /enrichment-jobs/{id}/apply
Role:
- applique les champs `accepted` + `edited`;
- passe le job en `success` si l application technique termine.

Reponse:
- `applied_count`
- `applied_fields`
- `warnings`

---

## 7. Worker d enrichissement
Commande:
- `app:profile-ai:worker`
- fichier: `src/Command/ProfileAiEnrichmentWorkerCommand.php`

Options:
- `--mode=mock|live`
- `--limit=<n>`
- `--job-id=<id>`

### 7.1 Mode mock
Usage:
- tests fonctionnels API/BO/FO sans dependances externes.

### 7.2 Mode live
Pipeline:
1. collecte preuves (Google Places + scraping/OpenGraph)
2. construction prompt
3. appel LLM
4. normalisation/sanitation
5. placeholders pour champs manquants
6. persistance suggestions
7. job -> `awaiting_user`

Strategie par input:
- `studio_name`: Google Places puis scraping site detecte si disponible
- `website`: scraping site puis tentative Google Places deduite

### 7.3 Variables d environnement
Obligatoires:
- `GOOGLE_PLACES_API_KEY`
- `OPENAI_API_KEY`

Optionnelles:
- `OPENAI_BASE_URL` (defaut: `https://api.openai.com/v1`)
- `OPENAI_MODEL` (defaut code actuel: `gpt-4o-mini`)
- `AI_WORKER_REQUEST_TIMEOUT`
- `AI_WORKER_USER_AGENT`

### 7.4 Planification (cron)
Le worker est asynchrone et doit etre execute periodiquement.

Exemple:
```bash
php bin/console app:profile-ai:worker --mode=live --limit=1
```

---

## 8. Mapping apply vers profil
Mapping principal:
- `phone` -> `billing_phone`, `telephone`
- `business_name` -> `billing_company`, `nom_commercial`
- `addresses` -> `billing_address_1`, `billing_city`, `billing_postcode`, `billing_country`, `billing_state` (+ champs domicile associes)
- `skills` -> `competence` (string CSV)
- `experiences_text` -> `description`
- `project_references_text` -> `reference`
- `siret` -> `siret`
- `tva_number` / `tva` -> `tva`
- `main_activity` -> `activite_principale` (ID referentiel)

Point `main_activity`:
- pre-selection FO par matching tolerant;
- persistance finale en ID taxonomy attendu par le profil.

---

## 9. Backoffice Trust Agentique IA
Controllers:
- `src/Controller/Admin/DashboardController.php`
- `src/Controller/Admin/ProfileAiEnrichmentBackofficeController.php`

Acces:
- `ROLE_SUPER_ADMIN`
- `ROLE_COMMERCE`

Menu:
- `Trust Agentique IA`
- `Dashboard IA`
- `Jobs IA`

API BO:
- `GET /bo/enrichment-jobs/stats`
- `GET /bo/enrichment-jobs`
- `GET /bo/enrichment-jobs/{id}`
- `POST /bo/enrichment-jobs/{id}/retry`

Navigation UI:
1. Depuis `Jobs IA`, l action `Voir` ouvre une page detail job dediee.
2. L ouverture se fait dans l onglet courant.
3. La page detail expose un lien `Retour liste jobs`.

Retry:
- limite aux jobs `failed`;
- cree un nouveau job `pending` avec la meme entree.

---

## 10. Front profil pro
Template principal:
- `templates/profile/index.html.twig`

### 10.1 Encart CTA
Comportement:
- visible pour les profils pro;
- bouton `Lancer TrustAgent`;
- bouton `Verifier les suggestions` active uniquement en `awaiting_user`.

Messages:
- `Recherche de vos informations en cours.`
- `Recherche terminee. Vous pouvez verifier les informations.`
- message restaure au rechargement si job actif en `awaiting_user`.

### 10.2 Modale 1 (entree)
Design:
1. titre et structure custom
2. bouton `Trouver mes informations` (fond `#ff7e10`)
3. bouton `Annuler` (fond `#262626`)

Regles fonctionnelles:
- choix source: `Nom du studio` ou `Site web`
- champ principal obligatoire
- si source `website`: URL obligatoire en `http://` ou `https://`
- erreur URL: `Format invalide. Exemple : https://monstudio.fr`
- si source `studio_name`: `Ville / Region` obligatoire
- validation FR renforcee sur ville/region
- message: `Veuillez saisir une ville ou une region Francaise.`
- bouton de confirmation desactive tant que invalide

Google Places:
- autocomplete sur `Ville / Region`
- restriction `country=fr`
- support mobile modale
- `pac-container` z-index eleve + pointer-events
- blocage fermeture externe de la modale pour fiabiliser la selection

Note fallback:
- si Google Places indisponible (script absent/cle non chargee), la validation repasse sur controle local non vide.

### 10.3 Modale 2 (revue suggestions)
Regles:
- affiche tous les champs du MVP, y compris non trouves (placeholders editables)
- checkbox par champ pour accepter/rejeter
- edition directe des valeurs
- `main_activity` en select base sur referentiel BO
- matching tolerant pour preselection activite principale
- sequence d action:
1. `decisions`
2. `apply`

### 10.4 Responsive modales (implante)
Modale 1:
- popup responsive dediee
- radios empiles en mobile
- boutons full-width en mobile

Modale 2:
- popup responsive dediee
- scroll interne
- header sticky (titre + sous-titre)
- cards compressees en mobile
- boutons full-width en mobile

---

## 11. Observabilite et KPIs
Niveau job:
- `attempt_count`
- `last_error`
- `prompt_version`
- `confidence_global`

Niveau suggestion:
- `confidence_score`
- `source_type`
- `status`
- `final_value`

KPI metier conseille:
1. taux `accepted/edited/rejected` par champ
2. taux d application (`applied_count`)
3. ecarts `suggested_value` vs `final_value`
4. suivi des erreurs live (Google/scraping/LLM)

---

## 12. Securite et acces
- FO: endpoints lies au profil connecte (isolation par `profile_id`).
- BO: acces strict `ROLE_SUPER_ADMIN` ou `ROLE_COMMERCE`.
- Cles API: via variables d environnement (pas en dur dans le code).

---

## 13. Runbook tests et exploitation
Tests mock:
```bash
php bin/console app:profile-ai:worker --mode=mock --limit=1
```

Tests live:
```bash
php bin/console app:profile-ai:worker --mode=live --limit=1
```

Job cible:
```bash
php bin/console app:profile-ai:worker --mode=live --job-id=123 --limit=1
```

Verification BO:
1. creer job
2. lancer worker
3. verifier passage `pending -> processing -> awaiting_user`
4. verifier suggestions et details job
5. appliquer et verifier passage en `success`

---

## 14. Points de vigilance
1. Les resultats Google Places dependent fortement de la configuration de cle (APIs activees, restrictions IP).
2. `Aucune source exploitable` signifie que Google/scraping n ont pas fourni de preuve exploitable.
3. `success` reste un statut technique. La qualite metier se lit via les decisions utilisateur.
4. Il n y a pas d auto-apprentissage implicite du modele a partir des tables SQL.

---

## 15. Boucle d amelioration continue (recommandee)
1. Batch d analyse des retours `accepted/edited/rejected`.
2. Ajustement mapping, seuils et normalisation.
3. Ajustement prompt/pipeline.
4. Re-mesure sur les KPIs.
