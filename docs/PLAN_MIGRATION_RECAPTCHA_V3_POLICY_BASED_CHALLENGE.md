# Plan de migration concret - reCAPTCHA V3 policy-based challenge

## Objet

Definir la specification de migration du projet vers `reCAPTCHA Enterprise V3` en mode `policy-based challenge`, pour 6 formulaires deja presents dans le repo.

La migration doit introduire une decision backend unifiee en 3 etats metier:

- `allow`
- `challenge`
- `technical_error`

## Perimetre

Formulaires concernes:

- `login`
- `register`
- `reset password request`
- `newsletter`
- `contact us`
- `feedback`

Actions reCAPTCHA actuellement identifiees dans le code:

- `TRUST_LOGIN`
- `TRUST_REGISTER`
- `TRUST_RESET_PASSWORD`
- `TRUST_NEWSLETTER`
- `TRUST_CONTACT_US`
- `TRUST_FEEDBACKS`

## Cible d'architecture

### Principe general

Le projet migre vers une architecture unique:

- le front execute `reCAPTCHA Enterprise` avec une `action` explicite
- le formulaire soumet un token standardise
- le backend appelle un service unique d'evaluation
- le service normalise le resultat en un etat metier
- chaque flux applique ensuite sa politique fonctionnelle

### Etats metier

Les 3 etats metier attendus sont:

- `allow`: la verification permet de poursuivre le flux
- `challenge`: la verification n'est pas suffisante pour laisser passer immediatement
- `technical_error`: la verification n'a pas pu aboutir pour une raison technique

Point important:

- un `technical_error` ne doit jamais etre assimile a un bot
- un `challenge` ne doit jamais etre assimile a une erreur technique

### Contrat front standard

Chaque formulaire cible doit soumettre un payload homogenise:

- `recaptcha_token`
- `recaptcha_action`

Contraintes:

- l'action front doit etre strictement alignee avec l'action attendue cote backend
- le chargement du script Google doit etre unifie
- les widgets visibles legacy doivent etre retires progressivement
- si le token ne peut pas etre obtenu, le front doit remonter proprement l'echec

### Contrat backend standard

Le service backend central doit renvoyer une structure normalisee contenant au minimum:

- `state`
- `message`
- `score`
- `hostname`
- `reasons`
- `error_type`

Le service ne doit plus se limiter a retourner un simple booleen `response`.

### Politique fonctionnelle par type de formulaire

#### Login

- `allow`: authentification continue
- `challenge`: blocage controle avec message dedie
- `technical_error`: blocage controle avec message de verification indisponible

#### Register

- `allow`: creation du compte
- `challenge`: refus de soumission avec message dedie
- `technical_error`: creation interrompue, demande de retry

#### Reset password request

- `allow`: traitement normal de la demande
- `challenge`: blocage
- `technical_error`: blocage propre

#### Newsletter

- `allow`: inscription effectuee
- `challenge`: retour fonctionnel distinct
- `technical_error`: retour distinct du cas "robot" ou email deja connu

#### Contact us

- `allow`: envoi effectue
- `challenge`: retour d'erreur metier controle
- `technical_error`: message de reessai

#### Feedback

- `allow`: envoi effectue
- `challenge`: retour d'erreur metier controle
- `technical_error`: message de reessai

## Etat actuel du code

Le backend est deja partiellement branche sur `Recaptcha Enterprise`:

- [Recaptcha.php](C:\Users\kalbr\Documents\GitHub\production\src\Service\Recaptcha\Recaptcha.php:18)
- [AppAuthenticator.php](C:\Users\kalbr\Documents\GitHub\production\src\Security\AppAuthenticator.php:55)
- [RegistrationController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\RegistrationController.php:60)
- [ResetPasswordController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ResetPasswordController.php:64)
- [NewsletterController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\NewsletterController.php:74)
- [ExperienceController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ExperienceController.php:85)
- [ExperienceController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ExperienceController.php:181)

Le front reste heterogene:

- certains ecrans utilisent encore `div.g-recaptcha`
- certains layouts historiques injectent aussi un token via `api.js?render=...`
- la newsletter existe a plusieurs endroits avec de la logique JS dupliquee

## Specification detaillee par couche

### 1. Couche service reCAPTCHA

Le coeur de migration se situe dans le service:

- [Recaptcha.php](C:\Users\kalbr\Documents\GitHub\production\src\Service\Recaptcha\Recaptcha.php:18)

Evolution attendue:

- sortir du modele `response=true/false`
- retourner un resultat metier normalise
- distinguer explicitement:
  - validation fonctionnelle
  - challenge
  - panne technique
- journaliser les informations utiles a l'exploitation

### 2. Couche configuration

La configuration doit etre sortie du code en dur.

Parametres a centraliser:

- `RECAPTCHA_SITE_KEY`
- `RECAPTCHA_PROJECT_ID`
- domaines autorises
- mapping des actions

Emplacements cibles a verifier:

- `config/services.yaml`
- `.env`
- `.env.local`

### 3. Couche front commune

Un helper ou partial Twig commun doit etre introduit pour standardiser:

- le chargement du script Enterprise
- l'execution par action
- l'alimentation de `recaptcha_token`
- l'alimentation de `recaptcha_action`
- le traitement des erreurs de chargement

Recommandation:

- creer un partial du type `partials/recaptcha_enterprise.html.twig`

### 4. Couche controleurs

Chaque controleur doit appliquer le meme contrat de lecture et la meme interpretation du resultat.

Ils ne doivent plus contenir de logique ad hoc basee sur un simple `if ($recaptcha['response'])`.

### 5. Couche templates

Chaque template de formulaire doit:

- supprimer le widget visible legacy
- declarer l'action metier attendue
- utiliser le helper commun
- afficher proprement les messages relies a `challenge` et `technical_error`

### 6. Couche legacy et nettoyage

Les anciens layouts qui portent une logique newsletter ou reCAPTCHA historique doivent etre nettoyes apres migration des flux cibles.

## Tableau operationnel de migration

| Fichier | Changement | Priorite | Risque |
|---|---|---:|---:|
| [src/Service/Recaptcha/Recaptcha.php](C:\Users\kalbr\Documents\GitHub\production\src\Service\Recaptcha\Recaptcha.php:18) | Transformer le service en moteur de decision `allow/challenge/technical_error`, enrichir logs et metadonnees | Haute | Haute |
| `config/services.yaml` / `.env*` | Centraliser `site key`, `project id`, actions, options reCAPTCHA | Haute | Moyenne |
| [translations/recaptcha.fr.yaml](C:\Users\kalbr\Documents\GitHub\production\translations\recaptcha.fr.yaml:1) | Ajouter messages `challenge` et `technical_error` | Moyenne | Faible |
| [translations/recaptcha.en.yaml](C:\Users\kalbr\Documents\GitHub\production\translations\recaptcha.en.yaml:1) | Ajouter messages `challenge` et `technical_error` | Moyenne | Faible |
| `partials/recaptcha_enterprise.html.twig` | Creer un partial commun pour charger script, token et action | Haute | Faible |
| [src/Security/AppAuthenticator.php](C:\Users\kalbr\Documents\GitHub\production\src\Security\AppAuthenticator.php:55) | Consommer le nouveau contrat metier pour `login` | Haute | Haute |
| [templates/security/login.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\security\login.html.twig:577) | Remplacer widget visible par token/action standardises | Haute | Moyenne |
| [src/Controller/RegistrationController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\RegistrationController.php:60) | Consommer `allow/challenge/technical_error` pour `register` | Haute | Haute |
| [templates/registration/register.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\registration\register.html.twig:1574) | Migrer le front `register` vers le helper commun Enterprise | Haute | Moyenne |
| [src/Controller/ResetPasswordController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ResetPasswordController.php:64) | Appliquer la nouvelle politique sur `reset password` | Haute | Moyenne |
| [templates/reset_password/request.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\reset_password\request.html.twig:433) | Remplacer le widget par token/action standardises | Haute | Faible |
| [src/Controller/NewsletterController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\NewsletterController.php:74) | Distinguer clairement `challenge` et `technical_error` en AJAX | Haute | Moyenne |
| [templates/newsletter/subscribe.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\newsletter\subscribe.html.twig:267) | Unifier le formulaire newsletter avec payload standard | Haute | Moyenne |
| [src/Controller/ExperienceController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ExperienceController.php:85) | Adapter `contact us` au nouveau service | Moyenne | Moyenne |
| [templates/experience/index.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\experience\index.html.twig:153) | Migrer le front `contact us` | Moyenne | Faible |
| [src/Controller/ExperienceController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\ExperienceController.php:181) | Adapter `feedback` au nouveau service | Moyenne | Moyenne |
| [templates/experience/envoyez_commentaires.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\experience\envoyez_commentaires.html.twig:167) | Migrer le front `feedback` | Moyenne | Faible |
| [templates/temp.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\temp.html.twig:890) | Nettoyer ou realigner le formulaire newsletter footer legacy | Moyenne | Moyenne |
| [templates/generalLayout.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\generalLayout.html.twig:153) | Supprimer la logique `recaptchaResponse` legacy | Moyenne | Moyenne |
| [templates/homeLayout.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\homeLayout.html.twig:234) | Meme nettoyage newsletter legacy | Moyenne | Moyenne |
| [templates/descriptiveContent.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\descriptiveContent.html.twig:254) | Meme nettoyage newsletter legacy | Moyenne | Moyenne |
| [templates/profileTemplate.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\profileTemplate.html.twig:128) | Nettoyer la logique de token legacy | Basse | Faible |
| [src/Controller/Admin/AdminController.php](C:\Users\kalbr\Documents\GitHub\production\src\Controller\Admin\AdminController.php:1025) | Repenser ou retirer le reglage admin centre sur un score unique | Basse | Moyenne |
| [templates/admin/MenusPages/recaptcha.html.twig](C:\Users\kalbr\Documents\GitHub\production\templates\admin\MenusPages\recaptcha.html.twig:54) | Aligner l'UI admin avec le nouveau modele | Basse | Faible |

## Ordre d'execution recommande

### Phase 1 - Socle

- refactor du service reCAPTCHA
- centralisation de la configuration
- ajout des messages de traduction
- creation du helper front commun

### Phase 2 - Formulaires simples

- migration `newsletter`
- migration `contact us`
- migration `feedback`

### Phase 3 - Flux sensibles

- migration `reset password`
- migration `login`
- migration `register`

### Phase 4 - Nettoyage legacy

- suppression des logiques newsletter dupliquees
- suppression des injections legacy `recaptchaResponse`
- refonte ou retrait du reglage admin historique

## Criteres d'acceptation

La migration sera consideree comme acceptable si:

- les 6 formulaires utilisent le meme contrat front
- les 6 flux backend consomment le meme resultat metier
- aucun controleur ne depend encore uniquement de `response=true/false`
- les etats `allow`, `challenge` et `technical_error` sont distingues fonctionnellement
- les scripts et widgets legacy inutiles sont identifies puis retires
- les messages utilisateur sont differencies selon le type d'echec

## Notes de mise en oeuvre

Points a surveiller pendant l'implementation:

- alignement strict des noms d'action entre front et back
- gestion des formulaires AJAX
- coexistence temporaire eventuelle avec les anciens layouts
- regression sur le login et l'inscription
- comportement explicite en cas d'indisponibilite Google

## Conclusion

La bonne strategie pour ce repo est de:

- unifier d'abord toute la logique backend autour des 3 etats metier
- standardiser ensuite les 6 formulaires autour d'un helper Enterprise commun
- migrer enfin les flux sensibles apres validation sur les flux simples

Cette approche reduit la dette technique, simplifie la maintenance et cadre proprement le passage vers `reCAPTCHA V3 policy-based challenge`.
