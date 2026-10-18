# Publication planifiée des articles de blog

Les articles planifiés utilisent le statut `future`, avec :

- `post_date` en heure de Paris ;
- `post_date_gmt` en UTC.

Les requêtes publiques vérifient systématiquement `post_date_gmt <= UTC_TIMESTAMP()`.
Un article n'est donc jamais exposé avant son échéance, même par son URL directe,
et devient visible à l'heure prévue indépendamment du passage de la commande cron.

## Commande de normalisation

La commande suivante bascule les articles arrivés à échéance de `future` vers
`publish` :

```bash
php bin/console app:blog:publish-scheduled
```

Elle est idempotente et peut être exécutée chaque minute.

Exemple de cron de production :

```cron
* * * * * cd /var/www/vhosts/trustandmarket.com/trustandmarket.com && /opt/plesk/php/8.3/bin/php bin/console app:blog:publish-scheduled >> var/log/blog-publication.log 2>&1
```

Adapter le chemin du projet et le binaire PHP à l'hébergement.
