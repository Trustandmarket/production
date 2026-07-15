# Base analytique KPI quotidiens

## Objectif

Mettre en place une base de donnees analytique dediee pour stocker, une fois par jour, un snapshot de KPI metier.

Cette base analytique est separee de la base applicative principale.

Le snapshot sera insere chaque jour a `23:00`.

## KPI a stocker

Les KPI a enregistrer, dans cet ordre, sont :

1. `utilisateurs_total`
2. `professionnels_total`
3. `professionnels_verifies_total`
4. `annonces_total`
5. `annonces_rejetees_total`
6. `annonces_brouillon_total`
7. `annonces_moderation_total`
8. `annonces_publiees_total`

## Regles metier

### Utilisateurs total

Compter uniquement les profils metier :

- `ROLE_ABONNE`
- `ROLE_SOCIETE`
- `ROLE_AUTO_ENTREPRENEUR`

Ne pas inclure :

- `ROLE_SUPER_ADMIN`
- `ROLE_COMMERCE`
- `ROLE_CONTRIBUTEUR`
- les comptes simples `ROLE_USER` hors profil metier

### Professionnels total

Compter :

- `ROLE_SOCIETE`
- `ROLE_AUTO_ENTREPRENEUR`

### Professionnels verifies total

Compter les profils professionnels avec :

- `isVerified = true`

### Annonces total

Compter les `WpPosts` de type :

- `postType = 'product'`

### Annonces rejetees total

Compter les annonces avec :

- `postStatus = 'trash'`

### Annonces brouillon total

Compter les annonces avec :

- `postStatus = 'draft'`

### Annonces moderation total

Compter les annonces avec :

- `postStatus = 'moderation'`

### Annonces publiees total

Compter les annonces avec :

- `postStatus = 'publish'`

## Architecture cible

### Base de donnees

Creer une base dediee a l'analytique, par exemple :

```sql
CREATE DATABASE trust_market_analytics
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### Table principale

Creer une table de snapshot quotidien :

```sql
CREATE TABLE daily_business_kpis (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_date DATE NOT NULL,
    snapshot_at DATETIME NOT NULL,
    utilisateurs_total INT UNSIGNED NOT NULL DEFAULT 0,
    professionnels_total INT UNSIGNED NOT NULL DEFAULT 0,
    professionnels_verifies_total INT UNSIGNED NOT NULL DEFAULT 0,
    annonces_total INT UNSIGNED NOT NULL DEFAULT 0,
    annonces_rejetees_total INT UNSIGNED NOT NULL DEFAULT 0,
    annonces_brouillon_total INT UNSIGNED NOT NULL DEFAULT 0,
    annonces_moderation_total INT UNSIGNED NOT NULL DEFAULT 0,
    annonces_publiees_total INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_daily_business_kpis_snapshot_date (snapshot_date),
    KEY idx_daily_business_kpis_snapshot_at (snapshot_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

### Table de suivi des executions

Creer egalement une table de suivi technique des executions du job analytique :

```sql
CREATE TABLE analytics_job_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_name VARCHAR(100) NOT NULL,
    run_date DATE NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME DEFAULT NULL,
    status VARCHAR(20) NOT NULL,
    message TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_analytics_job_runs_job_name (job_name),
    KEY idx_analytics_job_runs_run_date (run_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

## Tables cibles

La base analytique contiendra donc des le depart :

- `daily_business_kpis`
- `analytics_job_runs`

## Strategie Doctrine

### Principe

Utiliser :

- la base metier actuelle pour lire les donnees source
- une seconde connexion Doctrine pour ecrire dans la base analytique

### Recommandation V1

Pour une premiere version, utiliser :

- lecture via l'entity manager metier existant
- ecriture via une connexion Doctrine DBAL `analytics`

Cette approche est plus simple qu'un second entity manager complet.

### Exemple de configuration Doctrine

```yaml
doctrine:
  dbal:
    default_connection: default
    connections:
      default:
        url: '%env(resolve:DATABASE_URL)%'
      analytics:
        url: '%env(resolve:ANALYTICS_DATABASE_URL)%'
```

### Variable d'environnement

```env
ANALYTICS_DATABASE_URL="mysql://user:password@127.0.0.1:3306/trust_market_analytics?serverVersion=8.0"
```

## Commande Symfony

### Nom propose

```bash
php bin/console app:analytics:snapshot-daily-kpis
```

### Role

La commande doit :

1. calculer les KPI depuis la base applicative
2. determiner la date de snapshot du jour
3. creer une entree de suivi dans `analytics_job_runs`
4. inserer ou mettre a jour la ligne du jour dans `daily_business_kpis`
5. mettre a jour le statut final d'execution dans `analytics_job_runs`

### Champs alimentes

- `snapshot_date`
- `snapshot_at`
- `utilisateurs_total`
- `professionnels_total`
- `professionnels_verifies_total`
- `annonces_total`
- `annonces_rejetees_total`
- `annonces_brouillon_total`
- `annonces_moderation_total`
- `annonces_publiees_total`

### Comportement recommande

Utiliser un mecanisme idempotent :

- une seule ligne par jour
- si la ligne existe deja, mise a jour des valeurs
- une ligne de suivi technique par execution du job

### Journalisation technique

Chaque execution doit etre tracee dans `analytics_job_runs` avec :

- `job_name`
- `run_date`
- `started_at`
- `finished_at`
- `status`
- `message`

Valeurs de statut recommandees :

- `started`
- `success`
- `failed`

Exemple de cycle :

1. insertion d'une ligne avec `status = 'started'`
2. calcul et ecriture des KPI
3. mise a jour en `status = 'success'` avec `finished_at`
4. en cas d'erreur, mise a jour en `status = 'failed'` avec le message d'erreur

### Exemple d'UPSERT MySQL

```sql
INSERT INTO daily_business_kpis (
    snapshot_date,
    snapshot_at,
    utilisateurs_total,
    professionnels_total,
    professionnels_verifies_total,
    annonces_total,
    annonces_rejetees_total,
    annonces_brouillon_total,
    annonces_moderation_total,
    annonces_publiees_total
) VALUES (
    :snapshot_date,
    :snapshot_at,
    :utilisateurs_total,
    :professionnels_total,
    :professionnels_verifies_total,
    :annonces_total,
    :annonces_rejetees_total,
    :annonces_brouillon_total,
    :annonces_moderation_total,
    :annonces_publiees_total
)
ON DUPLICATE KEY UPDATE
    snapshot_at = VALUES(snapshot_at),
    utilisateurs_total = VALUES(utilisateurs_total),
    professionnels_total = VALUES(professionnels_total),
    professionnels_verifies_total = VALUES(professionnels_verifies_total),
    annonces_total = VALUES(annonces_total),
    annonces_rejetees_total = VALUES(annonces_rejetees_total),
    annonces_brouillon_total = VALUES(annonces_brouillon_total),
    annonces_moderation_total = VALUES(annonces_moderation_total),
    annonces_publiees_total = VALUES(annonces_publiees_total);
```

## Planification

### Frequence

Execution quotidienne a :

- `23:00`

### Linux cron

```cron
0 23 * * * /usr/bin/php /path/to/project/bin/console app:analytics:snapshot-daily-kpis >> /var/log/trust_market_analytics.log 2>&1
```

### Windows Task Scheduler

Action quotidienne a `23:00` :

```powershell
php C:\path\to\project\bin\console app:analytics:snapshot-daily-kpis
```

## Recommandation de mise en oeuvre

Ordre conseille :

1. creer la base `trust_market_analytics`
2. creer la table `daily_business_kpis`
3. creer la table `analytics_job_runs`
4. ajouter `ANALYTICS_DATABASE_URL`
5. declarer la connexion Doctrine `analytics`
6. coder la commande Symfony
7. tester manuellement la commande
8. planifier l'execution quotidienne a `23:00`

## Perspective

Cette base analytique servira ensuite a :

- historiser les KPI metier
- produire du reporting stable
- alimenter des analyses evolutives
- preparer des usages IA sur des donnees consolidees
