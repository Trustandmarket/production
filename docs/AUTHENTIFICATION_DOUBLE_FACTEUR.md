# Authentification double facteur sur TrustandMarket

## Objectif

Ajouter une authentification double facteur (2FA) pour les comptes TrustandMarket sans casser le login actuel, en restant compatible avec l'architecture Symfony + Twig + `wp_users` / `wp_usermeta` deja en place.

## Ce que le projet fait deja

Le point d'entree du login est gere par un authenticator Symfony personnalise dans `src/Security/AppAuthenticator.php`.

La route de connexion applicative est exposee par `src/Controller/SecurityController.php`.

La zone naturelle pour laisser l'utilisateur activer ou desactiver le 2FA existe deja dans la page `Parametres`, rendue par `src/Controller/ProfileDashboardController.php` avec les vues:

- `templates/profile/parameters.html.twig`
- `templates/profile/parametersAbonne.html.twig`

Le projet dispose deja d'un mecanisme de stockage de donnees complementaires utilisateur dans `wp_usermeta`, manipule via:

- `src/Service/ServiceManager.php`
- `src/Entity/WpUsermeta.php`

Cette structure permet d'ajouter le 2FA sans migration lourde sur `wp_users`.

## Recommandation

Je recommande de mettre en place un 2FA de type TOTP (application d'authentification: Google Authenticator, Microsoft Authenticator, 1Password, Authy, etc.).

Pourquoi ce choix:

- plus robuste qu'un code par email
- pas de dependance a un provider SMS
- UX connue et fiable
- bien supporte par l'ecosysteme Symfony

En complement, il faut ajouter:

- des codes de secours a usage unique
- en option, les appareils de confiance

Je ne recommande pas un 2FA "email uniquement" comme solution finale pour TrustandMarket, surtout si des actions sensibles existent deja ou vont arriver: paiements, donnees personnelles, facturation, profil marchand.

## Choix technique

Le meilleur point de depart ici est le bundle Symfony officiellement documente:

- `scheb/2fa-bundle`
- `scheb/2fa-totp`
- optionnel: `scheb/2fa-backup-code`
- optionnel: `scheb/2fa-trusted-device`

Le bundle se branche au niveau de la couche Security et place l'utilisateur dans un etat intermediaire tant que le code 2FA n'a pas ete valide. Les roles complets ne sont accordes qu'apres validation du second facteur.

## Architecture cible

### 1. Stockage des donnees 2FA

Comme le projet utilise deja `wp_usermeta`, je recommande d'y stocker les informations 2FA plutot que de modifier `wp_users`.

Meta-keys conseillees:

- `_2fa_enabled`
- `_2fa_totp_secret_encrypted`
- `_2fa_backup_codes_hash`
- `_2fa_trusted_version`
- `_2fa_enabled_at`
- `_2fa_last_used_at`

Important:

- le secret TOTP ne doit pas etre stocke en clair
- les backup codes ne doivent pas etre stockes en clair non plus
- chaque backup code doit etre hache individuellement

Concretement:

- secret TOTP: chiffrement avec une cle applicative serveur
- backup codes: hash `password_hash()` ou equivalent fort

## 2. Ou brancher le parcours utilisateur

### Activation / gestion

Le meilleur ecran pour exposer le 2FA est la page Parametres:

- `src/Controller/ProfileDashboardController.php`
- `templates/profile/parameters.html.twig`
- `templates/profile/parametersAbonne.html.twig`

Je recommande d'ajouter un nouvel onglet `Securite` ou d'etendre l'onglet `Mot de passe` avec:

- statut 2FA active / inactive
- bouton `Activer le double facteur`
- affichage du QR code
- champ de confirmation du code a 6 chiffres
- regeneration des codes de secours
- bouton `Desactiver le double facteur`

### Login

Le login actuel reste porte par:

- `src/Security/AppAuthenticator.php`
- `src/Controller/SecurityController.php`

Le bundle 2FA se branchera apres la verification du mot de passe, avant d'accorder `IS_AUTHENTICATED_FULLY`.

## 3. Parcours utilisateur recommande

### Activation

1. L'utilisateur se connecte normalement.
2. Il ouvre `Parametres > Securite`.
3. Le serveur genere un secret TOTP temporaire.
4. L'interface affiche un QR code.
5. L'utilisateur scanne le QR code dans son application d'authentification.
6. Il saisit un code a 6 chiffres pour confirmer.
7. Le serveur active `_2fa_enabled = 1`.
8. Le serveur genere 8 a 10 codes de secours a usage unique.
9. L'utilisateur voit ces codes une seule fois.

### Connexion avec 2FA

1. Email + mot de passe.
2. Si le compte a le 2FA actif, redirection vers l'ecran `/2fa`.
3. Saisie du code TOTP.
4. Si le code est valide, la session devient pleinement authentifiee.

### Perte d'appareil

1. Utilisation d'un backup code.
2. Sinon, parcours de recuperation via support interne avec verification manuelle.

## 4. Ce qu'il faut modifier dans le code

### A. Dependencies

Ajouter les packages:

```bash
composer require scheb/2fa-bundle scheb/2fa-totp
composer require scheb/2fa-backup-code
composer require scheb/2fa-trusted-device
```

Les deux derniers peuvent etre ajoutes dans un second temps, mais je conseille au minimum les backup codes des la premiere release.

### B. Configuration security

Le snapshot actuel du depot ne contient pas les fichiers `config/packages/security.yaml` et `config/routes/*`, mais il faudra y ajouter:

- une route de formulaire 2FA
- une route de check 2FA
- la section `two_factor` sur le firewall principal
- les `access_control` 2FA tout en haut de la liste

Exemple de principe:

```yaml
# config/routes/scheb_2fa.yaml
2fa_login:
    path: /2fa
    controller: "scheb_two_factor.form_controller::form"

2fa_login_check:
    path: /2fa_check
```

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            # login deja existant
            two_factor:
                auth_form_path: 2fa_login
                check_path: 2fa_login_check

    access_control:
        - { path: ^/logout, role: PUBLIC_ACCESS }
        - { path: ^/2fa, role: IS_AUTHENTICATED_2FA_IN_PROGRESS }
```

Comme `AppAuthenticator` etend `AbstractLoginFormAuthenticator`, le token post-authentification Symfony est compatible avec le bundle dans le parcours standard.

### C. Couche domaine 2FA

Il faut ajouter un petit service dedie, par exemple:

- `src/Service/Security/TwoFactorManager.php`

Responsabilites:

- generer le secret TOTP
- chiffrer / dechiffrer le secret
- lire / ecrire les metas 2FA via `ServiceManager`
- generer les backup codes
- invalider les backup codes utilises
- incrementer la version des trusted devices

Je recommande aussi un service de facade pour isoler le bundle du stockage WordPress-like:

- `src/Service/Security/TwoFactorUserStorage.php`

### D. Modele utilisateur

Deux options sont possibles:

1. Faire implementer les interfaces 2FA directement a `App\Entity\User`
2. Ajouter un adaptateur applicatif autour du user

Je recommande l'option 1 si vous voulez aller vite.

Dans ce cas, `User` devra pouvoir exposer:

- si le 2FA est active
- le secret TOTP dechiffre
- les backup codes
- la version des trusted devices

Comme les donnees vivent dans `wp_usermeta`, il ne faut pas obligatoirement ajouter des colonnes a `wp_users`. En revanche, il faut eviter de faire des acces DB repetes pendant la verification du code. Le plus propre est d'hydrater ces informations via un service ou un provider dedie.

### E. Interface d'administration utilisateur

Sur la page Parametres, ajouter:

- un bloc d'etat
- un bouton d'activation
- un formulaire de confirmation du code
- un bloc de regeneration des codes de secours
- un bouton de desactivation qui demande le mot de passe actuel

Je recommande aussi d'ajouter:

- la date d'activation
- la date de derniere validation reussie
- un journal simple d'evenements de securite si vous avez deja un systeme de logs metier

### F. Routes / controlleurs applicatifs

Il faudra probablement ajouter un controlleur dedie, par exemple:

- `src/Controller/TwoFactorController.php`

Actions a prevoir:

- affichage de la page d'activation
- generation du QR code
- confirmation de l'activation
- desactivation
- regeneration des backup codes

## 5. Donnees sensibles et securite

### Secret TOTP

Ne pas stocker le secret en clair dans `wp_usermeta`.

Approche conseillee:

- chiffrement serveur avec une cle applicative
- rotation possible de cle a prevoir si vous avez deja une strategie de secrets

### Backup codes

Ne pas stocker les codes en clair.

Approche conseillee:

- generation aleatoire serveur
- affichage une seule fois a l'utilisateur
- stockage hash par hash

### Desactivation du 2FA

La desactivation doit demander au minimum:

- le mot de passe actuel
- et idealement un code 2FA valide si l'utilisateur a encore acces a son application

### Recuperation de compte

Le vrai sujet sensible n'est pas l'activation du 2FA, c'est la procedure de recuperation quand l'utilisateur perd son appareil.

Il faut definir une regle metier claire:

- verification par email seule: trop faible
- verification support + controles sur le compte: acceptable
- delai de securite avant desactivation manuelle: recommande

## 6. Strategie de rollout

Je conseille un deploiement en 3 etapes.

### Etape 1

2FA optionnel pour les comptes internes et admin.

Objectif:

- valider le parcours
- ajuster les messages d'erreur
- verifier la compatibilite mobile

### Etape 2

2FA optionnel pour tous les utilisateurs.

Objectif:

- mesurer l'adoption
- verifier le taux d'echec
- roder le support

### Etape 3

2FA obligatoire pour les comptes sensibles:

- administrateurs
- comptes vendeurs / societes
- comptes ayant des informations de paiement

## 7. Points d'attention propres a TrustandMarket

### 1. Stockage utilisateur hybride

Le projet repose sur `wp_users` / `wp_usermeta`. C'est pratique pour brancher vite le 2FA, mais il faut garder une couche propre entre le bundle Symfony et ce stockage historique.

### 2. Page Parametres deja chargee

La page `templates/profile/parameters.html.twig` est deja dense. Je recommande un onglet `Securite` dedie plutot que de surcharger encore `Mot de passe`.

### 3. Comptes a prioriser

Vu les flux presents dans le projet, il faut prioriser:

- `ROLE_SUPER_ADMIN`
- `ROLE_COMMERCE`
- `ROLE_CONTRIBUTEUR`
- `ROLE_SOCIETE`
- `ROLE_AUTO_ENTREPRENEUR`

### 4. Support client

Avant de rendre le 2FA obligatoire, il faut cadrer la procedure support:

- qui desactive
- sur quelle preuve
- avec quel delai
- avec quelle trace

## 8. Plan de mise en oeuvre concret

### Sprint 1

- installer les packages 2FA
- brancher le firewall
- afficher la page de challenge 2FA
- stocker le secret TOTP chiffre
- permettre activation / desactivation

### Sprint 2

- ajouter les backup codes
- ajouter les trusted devices
- ajouter les traductions FR
- journaliser les evenements critiques

### Sprint 3

- rendre le 2FA obligatoire sur certains roles
- ajouter monitoring et alertes de taux d'echec
- documenter la procedure support

## 9. Conclusion

Pour TrustandMarket, la bonne approche est:

- TOTP comme second facteur principal
- stockage dans `wp_usermeta`
- integration dans la page `Parametres`
- activation optionnelle au debut
- obligation progressive sur les roles sensibles

Techniquement, c'est faisable proprement sans refonte du systeme de login actuel.

## Sources officielles

- https://symfony.com/bundles/SchebTwoFactorBundle/current/index.html
- https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html
- https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/totp.html
- https://symfony.com/bundles/SchebTwoFactorBundle/current/backup_codes.html
- https://symfony.com/bundles/SchebTwoFactorBundle/current/trusted_device.html
