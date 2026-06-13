# Cadrage 2FA obligatoire pour admins et comptes professionnels

## Objet

Mettre en place une authentification a double facteur obligatoire par TOTP pour les comptes a risque eleve de TrustandMarket, sans imposer ce dispositif a l'ensemble des utilisateurs dans un premier temps.

## Objectif

Reduire le risque de compromission des comptes sensibles:

- comptes administrateurs
- comptes internes
- comptes professionnels pouvant gerer un profil, des annonces, des paiements ou des informations business

## Perimetre V1

Le 2FA est obligatoire pour les comptes ayant au moins un des roles suivants:

- `ROLE_SUPER_ADMIN`
- `ROLE_COMMERCE`
- `ROLE_CONTRIBUTEUR`
- `ROLE_SOCIETE`
- `ROLE_AUTO_ENTREPRENEUR`

Les comptes hors perimetre ne sont pas concernes en V1:

- `ROLE_ABONNE`
- comptes standards non professionnels

## Methode retenue

Second facteur retenu:

- `TOTP` via application d'authentification

Exemples d'applications compatibles:

- Google Authenticator
- Microsoft Authenticator
- 1Password
- Authy

Composants obligatoires des la V1:

- activation TOTP via QR code
- saisie d'un code a 6 chiffres
- backup codes a usage unique

Composants hors perimetre V1:

- trusted devices
- SMS
- code par email en second facteur principal
- obligation pour tous les utilisateurs

## Principe de fonctionnement

### Cas 1: utilisateur du perimetre sans 2FA configure

Apres saisie correcte de l'email et du mot de passe:

- l'utilisateur n'obtient pas un acces complet a l'application
- il est redirige vers un parcours d'enrolement 2FA obligatoire
- tant que ce parcours n'est pas termine, il ne peut pas acceder aux ecrans sensibles

### Cas 2: utilisateur du perimetre avec 2FA configure

Apres saisie correcte de l'email et du mot de passe:

- l'utilisateur doit saisir un code TOTP valide
- l'acces complet n'est accorde qu'apres validation du code

### Cas 3: utilisateur hors perimetre

- aucun changement en V1
- login inchange

## Regles metier

### Regle 1: obligation par role

Le caractere obligatoire du 2FA est determine par le role de l'utilisateur.

Si un utilisateur possede au moins un role cible, le 2FA est obligatoire.

### Regle 2: pas de contournement utilisateur

Un utilisateur soumis au 2FA obligatoire ne peut pas desactiver librement son 2FA sans repasser par une verification forte ou une procedure support.

### Regle 3: backup codes obligatoires

Lors de l'activation:

- un lot de backup codes est genere
- ces codes sont affiches une seule fois
- l'utilisateur est invite a les conserver

### Regle 4: un backup code = une seule utilisation

Chaque backup code:

- ne peut servir qu'une fois
- devient invalide apres usage

### Regle 5: regeneration invalide l'ancien lot

Si l'utilisateur regenere ses backup codes:

- l'ancien lot est revoque integralement

### Regle 6: securite avant confort en V1

En V1, on privilegie un parcours simple et robuste:

- pas de trusted device
- pas d'exception par appareil
- pas de bypass lie au poste de travail

## Parcours utilisateur

### 1. Premier login d'un compte concerne

1. L'utilisateur saisit email + mot de passe.
2. Le systeme detecte que son role impose le 2FA.
3. Si aucun TOTP n'est configure, il est redirige vers l'ecran d'activation.
4. Le systeme affiche un QR code.
5. L'utilisateur scanne le QR code avec son application.
6. L'utilisateur saisit un premier code TOTP.
7. Si le code est valide, le 2FA est active.
8. Le systeme affiche les backup codes.
9. L'utilisateur accede ensuite a l'application.

### 2. Login suivant

1. Email + mot de passe.
2. Ecran de saisie du code TOTP.
3. Validation du code.
4. Acces complet.

### 3. Perte de l'appareil

Deux chemins possibles:

- utilisation d'un backup code
- procedure support si l'utilisateur n'a plus ni appareil ni backup code

## Ecrans a prevoir

### Ecran d'enrolement obligatoire

Contenu attendu:

- message expliquant que le 2FA est obligatoire pour ce type de compte
- QR code
- champ de saisie du code a 6 chiffres
- message d'aide "utilisez une application d'authentification"
- bouton de validation
- bouton de deconnexion

### Ecran de challenge 2FA

Contenu attendu:

- champ code TOTP
- lien ou option pour utiliser un backup code
- message d'erreur simple si le code est invalide

### Ecran de gestion du 2FA dans les parametres

Contenu attendu:

- statut du 2FA: active / inactive
- date d'activation si disponible
- bouton regenerer les backup codes
- bouton desactiver le 2FA

## Ecrans / routes accessibles pendant l'enrolement force

Pendant qu'un utilisateur du perimetre est bloque dans le parcours d'activation, il doit pouvoir acceder seulement a:

- la page d'activation 2FA
- la page de challenge 2FA
- la deconnexion
- une eventuelle page d'aide ou contact support

Tout le reste doit etre considere comme non accessible tant que le 2FA n'est pas configure.

## Politique de desactivation

### Desactivation par l'utilisateur

La desactivation ne doit pas etre triviale.

Regle recommandee:

- demander le mot de passe actuel
- demander un code TOTP valide ou un backup code valide

### Desactivation par support / admin

A encadrer proceduralement:

- identite verifiee
- trace de l'action
- motif renseigne

## Politique de recuperation de compte

Procedure V1 recommandee:

- si l'utilisateur a un backup code, il l'utilise
- sinon, passage par le support

Le support doit disposer d'une procedure formelle avant toute reinitialisation 2FA.

## Experience utilisateur et communication

Le 2FA obligatoire est un changement sensible. Il faut donc prevoir:

- un message d'annonce en amont
- une FAQ courte
- une aide simple sur "comment scanner le QR code"
- une consigne claire sur la conservation des backup codes

## Strategie de deploiement recommandee

### Phase 1

Activation obligatoire pour les comptes internes:

- `ROLE_SUPER_ADMIN`
- `ROLE_COMMERCE`
- `ROLE_CONTRIBUTEUR`

Objectif:

- valider le parcours avec un petit groupe
- roder le support

### Phase 2

Extension aux comptes professionnels:

- `ROLE_SOCIETE`
- `ROLE_AUTO_ENTREPRENEUR`

Objectif:

- etendre la protection aux comptes metier
- confirmer la robustesse du parcours

## Decision de rollout recommandee

Pour limiter le risque projet, la recommandation est:

- obligation immediate pour les comptes internes
- obligation progressive pour les comptes professionnels

Cette progressivite peut prendre 2 formes:

- activation par lots
- date butoir communiquee a l'avance

## Critere de succes

Le cadrage sera considere reussi si:

- tous les comptes internes utilisent le 2FA
- les comptes professionnels cibles ne peuvent plus acceder a leurs espaces sans 2FA
- le volume de tickets support reste maitrisable
- aucun contournement fonctionnel evident n'existe

## Hors perimetre V1

Ne pas inclure dans la premiere version:

- obligation du 2FA pour tous les utilisateurs
- trusted device
- passkeys
- exemption par IP ou appareil
- automatisation complexe de recuperation de compte

## Questions a figer avant dev

- veut-on une date butoir pour les comptes professionnels ou une activation immediate?
- qui peut desactiver un 2FA cote support?
- souhaite-t-on garder une possibilite de bypass temporaire interne en cas d'incident?
- veut-on journaliser tous les evenements 2FA des la V1?

## Recommandation finale

Le meilleur cadrage V1 pour TrustandMarket est:

- TOTP obligatoire
- perimetre limite aux admins et comptes professionnels
- backup codes obligatoires
- pas de trusted device au debut
- rollout en deux phases: interne puis professionnel
