# TrustAgent IA
## Documentation technico-fonctionnelle (version de travail)

Date: 2026-05-03  
Perimetre: Back-end, Worker, BO, Front profil pro

---

## 1. Objectif produit
TrustAgent aide un professionnel a completer automatiquement son profil a partir de donnees publiques.

Flux utilisateur cible:
1. L utilisateur clique sur le CTA `Trouver mes informations automatiquement avec TrustAgent`.
2. Une modale demande une source.
3. Soit `Nom du studio` (+ `Ville / Region`).
4. Soit `Site web`.
5. TrustAgent lance un job d enrichissement.
6. Les suggestions sont affichees dans une modale de revue.
7. L utilisateur accepte, edite ou rejette.
8. Les suggestions validees sont appliquees sur le profil.

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

Champs explicitement exclus du MVP:
- `photos`
- `videos`
- `avatar_url`

Remarque:
Ces 3 champs sont exclus dans les traitements et filtres cote API/BO.

---

## 3. Architecture logique
Composants:
1. Front profil pro (CTA + modale entree + modale revue).
2. API metier `ProfileAiEnrichmentController`.
3. Worker asynchrone `ProfileAiEnrichmentWorkerCommand`.
4. Persistance SQL (`profile_ai_enrichment_jobs`, `profile_ai_suggestions`).
5. Backoffice de supervision (`ProfileAiEnrichmentBackofficeController` + ecrans EasyAdmin).
6. Services externes en mode live:
7. Google Places API
8. Scraping site web
9. OpenAI (LLM)

Principe:
- Le front ne fait pas l enrichissement en direct.
- Le front cree un job.
- Le worker traite le job.
- Le front lit l etat et affiche la revue.

---

## 4. Modele de donnees

### 4.1 Table `profile_ai_enrichment_jobs`
Colonnes cles:
- `id`
- `profile_id`
- `input_type` (`studio_name` ou `website`)
- `input_value`
- `input_region` (optionnel)
- `status`
- `attempt_count` (observabilite)
- `last_error` (observabilite)
- `prompt_version` (observabilite)
- `confidence_global`
- `active_profile_id` (verrou "un job actif par profil")
- `created_at`
- `updated_at`

Index/contrainte importants:
- `UNIQ_PAEJ_ONE_ACTIVE_PROFILE (active_profile_id)`
- `IDX_PAEJ_PROFILE_STATUS_CREATED (profile_id, status, created_at)`
- `IDX_PAEJ_STATUS_CREATED (status, created_at)`

### 4.2 Table `profile_ai_suggestions`
Colonnes cles:
- `id`
- `enrichment_job_id`
- `profile_id`
- `field_name`
- `suggested_value` (JSON/string)
- `confidence_score`
- `source_type`
- `status` (`suggested`, `accepted`, `edited`, `rejected`)
- `final_value`
- `created_at`
- `updated_at`

Index/contrainte importants:
- `UNIQ_PAS_JOB_FIELD (enrichment_job_id, field_name)`
- `IDX_PAS_JOB_ID (enrichment_job_id)`
- `IDX_PAS_PROFILE_STATUS (profile_id, status)`

Scripts SQL de reference:
- `sql/create_profile_ai_enrichment_tables.sql`
- `sql/alter_profile_ai_enrichment_jobs_add_input_region.sql`
- `sql/mock_test_profile_ai_worker.sql`

---

## 5. Machine d etats

### 5.1 Etats job
- `pending`: job cree, en attente de worker
- `processing`: pris par le worker
- `awaiting_user`: enrichissement technique termine, attente de decision utilisateur
- `applying`: application des decisions en cours
- `success`: application terminee (succes technique)
- `failed`: echec technique

### 5.2 Etats suggestion
- `suggested`: suggestion generee, non arbitree
- `accepted`: acceptee sans modification
- `edited`: acceptee avec modification (`final_value`)
- `rejected`: refusee

Point metier important:
- `success` represente la fin technique du workflow (pas la qualite des suggestions).

---

## 6. API Front TrustAgent
Controller: `src/Controller/ProfileAiEnrichmentController.php`

### 6.1 `POST /enrichment-jobs`
Role:
- cree un job pour l utilisateur connecte.

Entree JSON:
- `input_type`: `studio_name` ou `website`
- `input_value`: obligatoire
- `input_region`: utilise pour `studio_name`
- `prompt_version`: optionnel

Regles:
- verifie qu il n existe pas deja de job actif sur le profil.
- statut initial: `pending`.

### 6.2 `GET /enrichment-jobs/{id}`
Role:
- retourne le job et ses suggestions pour le profil connecte.

### 6.3 `GET /enrichment-jobs/active`
Role:
- retourne le job actif du profil connecte (si present).

### 6.4 `POST /enrichment-jobs/{id}/decisions`
Role:
- enregistre les decisions utilisateur champ par champ.

Entree JSON:
- `decisions[]`
- `suggestion_id`
- `status`: `accepted` / `edited` / `rejected`
- `final_value` requis fonctionnellement pour `edited`

Precondition:
- job en statut `awaiting_user`.

### 6.5 `POST /enrichment-jobs/{id}/apply`
Role:
- applique en base profil les suggestions `accepted` + `edited`.
- passe le job en `success` si l application technique se termine.

Precondition:
- job en statut `awaiting_user`.

Reponse:
- `applied_count`
- `applied_fields`
- `warnings`

---

## 7. Worker d enrichissement
Commande:
- `app:profile-ai:worker`
- Fichier: `src/Command/ProfileAiEnrichmentWorkerCommand.php`

Options:
- `--mode=mock|live`
- `--limit=<n>`
- `--job-id=<id>` (ciblage d un job)

### 7.1 Mode mock
Usage:
- tests locaux et integration front/BO sans dependances externes.

### 7.2 Mode live
Pipeline:
1. Collecte des preuves (Google Places, scraping, OpenGraph).
2. Construction du prompt.
3. Appel LLM.
4. Nettoyage/sanitation des suggestions.
5. Ajout de placeholders pour champs manquants.
6. Persistance suggestions + passage job en `awaiting_user`.

Sources selon l input:
- `studio_name`: Google Find Place -> Google Details -> Scraping website (si site trouve).
- `website`: Scraping website -> tentative Google Places via requete deduite du site.

### 7.3 Variables d environnement live
Obligatoires:
- `GOOGLE_PLACES_API_KEY`
- `OPENAI_API_KEY`

Optionnelles:
- `OPENAI_BASE_URL` (defaut `https://api.openai.com/v1`)
- `OPENAI_MODEL` (defaut `gpt-4o-mini`)
- `AI_WORKER_REQUEST_TIMEOUT`
- `AI_WORKER_USER_AGENT`

### 7.4 Execution planifiee
Le worker est asynchrone et prevu pour cron.

Exemple:
```bash
php bin/console app:profile-ai:worker --mode=live --limit=1
```

---

## 8. Mapping apply vers profil
Mapping actuel (coeur MVP):
- `phone` -> `billing_phone`, `telephone`
- `business_name` -> `billing_company`, `nom_commercial`
- `siret` -> `siret`
- `tva_number` -> `tva`
- `skills` -> `competence` (CSV)
- `addresses` -> `billing_address_1`, `billing_city`, `billing_postcode`, `billing_country`, `billing_state`, + champs domicile
- `experiences_text` -> `description`
- `project_references_text` -> `reference`
- `main_activity` -> `activite_principale` (ID referentiel, pas texte libre)

Point cle `main_activity`:
- resolution texte/ID vers le referentiel `product_activity`.
- persistance finale en ID taxonomy attendu par les ecrans profil.

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

Retry:
- uniquement sur job `failed`.
- cree un nouveau job `pending` avec la meme entree.

---

## 10. Front profil pro
Template principal:
- `templates/profile/index.html.twig`

### 10.1 Encart CTA
Comportement:
- visible sur profil pro.
- bouton `Lancer TrustAgent`.
- bouton `Verifier les suggestions` active quand job `awaiting_user`.

Messages d etat:
- `Recherche de vos informations en cours.`
- `Recherche terminee. Vous pouvez verifier les informations.`
- message affiche aussi au rechargement si job toujours `awaiting_user`.

### 10.2 Modale 1 (entree)
Regles:
- source `Nom du studio` ou `Site web`.
- champ principal obligatoire.
- si source `website`, URL strictement `http://` ou `https://`.
- erreur front: `Format invalide. Exemple : https://monstudio.fr`.
- champ `Ville / Region` present.
- Google Places autocomplete active sur ce champ.

### 10.3 Modale 2 (revue suggestions)
Regles:
- affiche tous les champs metiers (y compris non trouves, editables).
- utilisateur coche/decoche par champ.
- `main_activity` rendu en `select` depuis le referentiel BO.
- pre-selection automatique par matching tolerant sur suggestion IA.
- application via `decisions` puis `apply`.

---

## 11. Observabilite et exploitation
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

Exploitation recommandee:
1. mesurer taux `accepted/edited/rejected` par champ/source.
2. analyser ecarts `suggested_value` vs `final_value`.
3. ajuster regles/mapping/seuils, puis prompt.

Important:
- pas d auto-apprentissage implicite.
- amelioration via boucle batch pilotee (analyse + decisions produit/tech).

---

## 12. Known issues / points de vigilance
1. En mode live, `main_activity` peut rester `not_found` si le LLM ne renvoie pas de valeur exploitable ou trop eloignee du referentiel.
2. Les resultats Google Places dependent fortement des restrictions de cle API (IP, APIs activees).
3. `success` est un statut technique. La qualite metier doit etre lue via les decisions utilisateur.

---

## 13. Commandes utiles
Worker mock:
```bash
php bin/console app:profile-ai:worker --mode=mock --limit=1
```

Worker live:
```bash
php bin/console app:profile-ai:worker --mode=live --limit=1
```

Worker sur job precis:
```bash
php bin/console app:profile-ai:worker --mode=live --job-id=123 --limit=1
```

---

## 14. A completer demain
1. Section responsive detaillee des deux modales front.
2. Captures d ecran BO/FO.
3. Jeux de tests E2E (mock + live) pas-a-pas.
4. Catalogue KPI officiel (version 1) pour boucle d amelioration.

