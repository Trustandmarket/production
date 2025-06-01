<?php

namespace App\Controller\Admin;

use App\Entity\{Departement, User, WpPosts, WpTermTaxonomy, WpUsermeta};
use App\Security\EmailVerifier;
use App\Service\Export\CsvExporter;
use App\Service\{Payment, ServiceManager};
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\{ArrayField,
    AssociationField,
    BooleanField,
    ChoiceField,
    IdField,
    TextField,
    DateTimeField,
    FormField,
    CountryField
};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UserCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $requestStack;
    private $emailVerifier;
    private $payment;
    /**
     * @var ServiceManager
     */
    private $service_manager;

    public function __construct(ServiceManager $service_manager,
                                EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator,
                                RequestStack $requestStack, EmailVerifier $emailVerifier, Payment $payment)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
        $this->emailVerifier = $emailVerifier;
        $this->payment = $payment;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des utilisateurs')
            ->setPageTitle('detail', 'Détails Utilisateur')
            ->setPageTitle('edit', 'Modifier Utilisateur')
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [

            FormField::addTab('Donnees de base')->setIcon('fa fa-users')->onlyOnDetail(),
            IdField::new('id')->hideOnForm(),
            TextField::new('display_name', 'Noms'),
            TextField::new('email_canonical', 'Email'),
            ArrayField::new('roles', 'Roles'),
            //TextField::new('last_activity_at', 'Dernière Connexion'),
            BooleanField::new('enabled', 'Compte Actif?'),
            //AssociationField::new('abonnements', 'Abonnements')->hideOnForm(),
            BooleanField::new('is_verified', 'Email vérifié?'),
            IdField::new('id', 'Portefeuille?')->setTemplatePath('admin/user/Fields/mangopay.html.twig'),
            IdField::new('id', 'KYC')->setTemplatePath('admin/user/Fields/kyc.html.twig'),
            TextField::new('date_naissance', 'Date de naissance')->onlyOnDetail(),
            DateTimeField::new('userRegistered', 'Date de creation')->onlyOnIndex(),
            //DateTimeField::new('last_activity_at', 'Dernière Connexion'),
            DateTimeField::new('updatedAt', 'Date de MAJ')->onlyOnIndex(),

            FormField::addTab('Donnees uniques utilisateur')->onlyOnDetail(),
            TextField::new('userUniqueData.firstname', 'Prenom')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.lastName', 'Nom')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.nomCommercial', 'Nom commercial')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.departement.nom', 'Departement')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.bdaytime', 'Date de naissance')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.sexe', 'Genre')->hideOnIndex()->hideOnForm(),
            CountryField::new('userUniqueData.nationalityCountry', 'Nationalite')->hideOnIndex()->hideOnForm(),
            CountryField::new('userUniqueData.residenceCountry', 'Residence')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.billingEmail', 'Email facture')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.telephone', 'Telephone')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.raisonSociale', 'Raison sociale')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.postCode', 'Code postal')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.siret', 'Siret')->hideOnIndex()->hideOnForm(),


            FormField::addTab('Adresse domicile')->onlyOnDetail(),
            CountryField::new('userUniqueData.paysDomicile', 'Pays')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.numeroNomRueDomicile', 'Numero nom rue')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.codePostalDomicile', 'Code postal')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.villeDomicile', 'Ville')->hideOnIndex()->hideOnForm(),

            FormField::addTab('Adresse de livraison')->onlyOnDetail(),
            CountryField::new('userUniqueData.paysLivraison', 'Pays')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.numeroNomRueLivraison', 'Numero nom rue')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.codePostalLivraison', 'Code postal')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.villeLivraison', 'Ville')->hideOnIndex()->hideOnForm(),

            FormField::addTab('Informations de facturation')->onlyOnDetail(),
            TextField::new('informationsFacturationUtilisateur.nomOuSociete', 'Nom ou Societe')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.adresse', 'Adresse')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.ville', 'Ville')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.codePostal', 'Code Postal')->setColumns(4)->hideOnIndex()->hideOnForm(),
            CountryField::new('informationsFacturationUtilisateur.pays', 'Pays')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.numeroTva', 'Numero Tva')->setColumns(4)->hideOnIndex()->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $export = Action::new('export')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('export')
                    ->generateUrl();
            })
            ->setIcon('fas fa-download')
            ->linkToCrudAction('export')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        // New Action Button to Open the Stripe Form
        $openStripeForm = Action::new('openStripeForm', 'Supprimer Compte Stripe')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('stripeForm')
                    ->generateUrl();
            })
            ->setIcon('fas fa-credit-card')
            ->linkToCrudAction('stripeForm')
            ->setCssClass('btn btn-danger')
            ->createAsGlobalAction();

        $deleteStripe = Action::new('deleteStripe', 'Supprimer Stripe')
            ->linkToCrudAction('deleteStripeAction')
            ->setIcon('fas fa-unlink') // More relevant icon
            ->setCssClass('btn btn-warning text-red') // Yellow to differentiate from Delete
            ->displayAsLink();



        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $export)
            ->add(Crud::PAGE_INDEX, $openStripeForm)
            ->add(Crud::PAGE_INDEX, $deleteStripe);
    }


    public function export(AdminContext $context, CsvExporter $csvExporter)
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, 'utilisateurs.csv');
    }

    public function stripeForm(AdminContext $context)
    {
        return $this->render('admin/user/stripe_form.html.twig', []);
    }

    /**
     * @Route("/admin/user/delete-stripe-id", name="admin_user_delete_stripe_id", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteStripeId(Request $request): JsonResponse
    {
        $stripeId = $request->request->get('stripe_id');

        if (!$stripeId) {
            return new JsonResponse(['error' => 'Stripe ID is required'], 400);
        }

        // Call the Stripe API to delete the account
        $deletedItem = $this->payment->deleteStripeUser($stripeId);

        // Ensure response is valid and the account was actually deleted
        if (isset($deletedItem['deleted']) && $deletedItem['deleted'] === true) {

            // Find and remove the user metadata related to this Stripe account
            $userMetaStripe = $this->em->getRepository(WpUsermeta::class)->findOneByMetaValue($stripeId);

            if ($userMetaStripe) {
                $this->em->remove($userMetaStripe);
                $this->em->flush();
            }

            return new JsonResponse(['message' => 'Compte Stripe supprimé avec succès', 'delete' => $deletedItem], 200);
        }

        // If the account was not deleted, return an error message
        return new JsonResponse(['error' => 'Échec de la suppression du compte Stripe', 'delete' => $deletedItem], 400);
    }


    public function deleteStripeAction(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        if (!$user) {
            $this->addFlash('danger', "User not found.");
            return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $stripeAccountId = $this->service_manager->getUserStringDataValue($user->getId(), 'mp_user_id_sandbox');
        $stripePersonId = $this->service_manager->getUserStringDataValue($user->getId(), 'stripe_person_user');

        if (!empty($stripePersonId)) {
            //$response = $this->payment->deleteStripePerson($stripeAccountId, $stripePersonId);
            $this->service_manager->deleteAllUsermetaByUserIdKey($user->getId(), 'stripe_person_user');
            /*if (isset($response['error'])) {
                $this->addFlash('danger', "Error deleting Stripe person: " . $response['error']['message']);
                return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
            }*/
        }

        if (!empty($stripeAccountId)) {
            $response = $this->payment->deleteStripeUser($stripeAccountId);
            if (isset($response['error'])) {
                $this->addFlash('danger', "Error deleting Stripe account: " . $response['error']['message']);
                //return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
            }

            $this->service_manager->deleteAllUsermetaByUserIdKey($user->getId(), 'mp_user_id_sandbox');
        }

        $this->addFlash('success', "Stripe account deleted successfully.");
        return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }



    public function new(AdminContext $context)
    {
        $url = $this->adminUrlGenerator
            ->setAction(Action::INDEX)
            ->setController(UserCrudController::class)
            ->removeReferrer()
            ->generateUrl();
        return $this->render('admin/user/add_user.html.twig', ['url' => $url]);
    }


    public function edit(AdminContext $context)
    {
        $url = $this->adminUrlGenerator
            ->setAction(Action::INDEX)
            ->setController(UserCrudController::class)
            ->removeReferrer()
            ->generateUrl();

        $userId = $context->getRequest()->query->get('entityId');
        $user = $this->em->getRepository(User::class)->find($userId);
        //First Name
        $first_name = $this->service_manager->readUserMeta($userId, 'first_name');
        //Last name
        $last_name = $this->service_manager->readUserMeta($userId, 'last_name');
        $nom_commercial = $this->service_manager->getUserStringDataValue($userId, 'nom_commercial');
        if ($nom_commercial == '') {
            $nom_commercial = $this->service_manager->getUserStringDataValue($userId, 'first_name');
        }
        //Birthday
        $bdaytime = $this->service_manager->readUserMeta($userId, 'bdaytime');
        //Sexe
        $sexe = $this->service_manager->readUserMeta($userId, 'sexe');
        //Birth Place
        $birth_place = $this->service_manager->readUserMeta($userId, 'nationalityCountry');
        //Telephone
        $telephone = $this->service_manager->readUserMeta($userId, 'telephone');
        //Raison Sociale
        $raison_sociale = $this->service_manager->readUserMeta($userId, 'raison_sociale');
        //SIRET
        $siret = $this->service_manager->readUserMeta($userId, 'siret');
        //TVA
        $tva = $this->service_manager->readUserMeta($userId, 'tva');
        //Avatar
        $avatars = $this->service_manager->readUserMeta(
            $userId,
            'basic_user_avatar'
        );
        $avatar = '';
        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $avatar = array_values($img)[0];
        }

        //TITRE
        $titre = $this->service_manager->readUserMeta($userId, 'titre');

        //DESCRIPTION
        $description = $this->service_manager->readUserMeta(
            $userId,
            'description'
        );

        //COMPETENCE
        $competences = $this->service_manager->readUserMeta(
            $userId,
            'competence'
        );
        $competence = [];
        if ($competences) {
            $competence = explode(',', $competences->getMetaValue());
        }

        //REGION
        $region = $this->service_manager->readUserMeta($userId, 'region');

        //reference
        $reference = $this->service_manager->readUserMeta($userId, 'reference');

        //portfolio
        $port = $this->service_manager->readUserMeta($userId, 'portfolio');

        $portfolio = [];
        if ($port) {
            $ids = explode(',', $port->getMetaValue());
            $portfolio = $this->em->getRepository(WpPosts::class)->findById($ids);
        }

        //Videos
        $vid = $this->service_manager->readUserMeta($userId, 'video');
        $video = [];
        $imgid = [];
        if ($vid) {
            $video = @unserialize($vid->getMetaValue());
            for ($i = 0; $i < sizeof($video); $i++) {
                $imgid[$i] = $this->service_manager->getYouTubeId($video[$i]);
            }
        }

        //Information bancaire
        $typeCompte = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_type'
        );
        //Information bancaire: nom
        $nomCompte = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_name'
        );
        //Information bancaire: adresse
        $adresseDetenteur = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_address1'
        );
        //Information bancaire: ville
        $villeCompte = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_city'
        );
        //Information bancaire: Code postale
        $codePostaleCompte = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_postcode'
        );
        //Information bancaire: Pays
        $paysCompte = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_country'
        );
        //Information bancaire: Région
        $regionCompte = $this->service_manager->readUserMeta(
            $userId,
            'vendor_account_region'
        );

        $prenom = $this->service_manager->readUserMeta(
            $userId,
            'billing_first_name'
        );
        $nom = $this->service_manager->readUserMeta(
            $userId,
            'billing_last_name'
        );
        $nomEntreprise = $this->service_manager->readUserMeta(
            $userId,
            'billing_company'
        );
        $pays = $this->service_manager->readUserMeta(
            $userId,
            'billing_country'
        );
        $numeroNomRue = $this->service_manager->readUserMeta(
            $userId,
            'billing_address_1'
        );
        $codePostal = $this->service_manager->readUserMeta(
            $userId,
            'billing_postcode'
        );
        $ville = $this->service_manager->readUserMeta($userId, 'billing_city');

        $etatComte = $this->service_manager->readUserMeta(
            $userId,
            'billing_state'
        );
        $telephone1 = $this->service_manager->readUserMeta(
            $userId,
            'billing_phone'
        );
        $email = $this->service_manager->readUserMeta($userId, 'billing_email');
        //Donnees fichiers d'identification
        $identite = $this->service_manager->readIdentityFiles(
            $userId,
            'kyc_document'
        );
        $enregistrement = $this->service_manager->readSubscribeFiles(
            $userId,
            'kyc_document'
        );
        //Activite
        $principal_activity = $this->service_manager->readUserMeta($userId, 'activite_principale');
        if ($principal_activity) {
            $principal_activity = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy(['termTaxonomyId' => $principal_activity->getMetaValue()]);
        }
        //Departement
        $departement = $this->service_manager->getUserStringDataValue($userId, 'departement');
        $departements = $this->em->getRepository(Departement::class)->findAll();
        if ($context->getRequest()->query->get('fieldName') == 'enabled') {
            if (!$user->getEnabled()) {
                $user->setEnabled(1);
                $this->em->flush();
            }
        }
        $mpAccount = null;
        $stripeAccount = null;
        if ($context->getRequest()->query->get('fieldName') == 'is_verified') {
            if (!$user->isVerified()) {
                $user->setIsVerified($context->getRequest()->query->get('newValue'));
                $user->setEnabled(1);
                $this->em->flush();
                
            } elseif ($user->isVerified() == true) {
                $user->setIsVerified(false);
                $user->setEnabled(0);
                $this->em->flush();
            }

            return $this->json(['success' => 'Account update successfully', 'response' => $stripeAccount], 200);
        }

        return $this->render('admin/user/edit_user.html.twig', [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'nom_commercial' => $nom_commercial,
            'bdaytime' => $bdaytime,
            'sexe' => $sexe,
            'birth_place' => $birth_place,
            'telephone' => $telephone,
            'raison_sociale' => $raison_sociale,
            'siret' => $siret,
            'tva' => $tva,
            'avatar' => $avatar,
            'titre' => $titre,
            'description' => $description,
            'competence' => $competence,
            'competences' => $competences,
            'region' => $region,
            'reference' => $reference,
            'portfolio' => $portfolio,
            'video' => $video,
            'imgid' => $imgid,
            'id' => $userId,
            'typeCompte' => $typeCompte,
            'nomCompte' => $nomCompte,
            'adresseDetenteur' => $adresseDetenteur,
            'villeCompte' => $villeCompte,
            'codePostaleCompte' => $codePostaleCompte,
            'paysCompte' => $paysCompte,
            'regionCompte' => $regionCompte,
            'activities' => $this->service_manager->postCategorie1('product_activity'),
            'principal_activity' => $principal_activity,
            //Données Facture
            'user' => $this->service_manager->userById($userId),
            'prenom' => $prenom,
            'nom' => $nom,
            'nomEntreprise' => $nomEntreprise,
            'pays' => $pays,
            'numeroNomRue' => $numeroNomRue,
            'codePostal' => $codePostal,
            'ville' => $ville,
            'etatComte' => $etatComte,
            'telephone1' => $telephone1,
            'email' => $email,
            'identite' => $identite,
            'enregistrement' => $enregistrement,
            'departements' => $departements,
            'user_departement' => $departement,
        ]);
    }
    
    public function delete(AdminContext $context)
    {
        $entity = $context->getEntity()->getInstance(); // Retrieve the entity instance
        if (!$entity) {
            throw new \Exception('Entity not found.');
        }

        $userId = $entity->getId(); // Get the user ID

        // Fetch Stripe account details
        $stripeAccountId = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
        $stripeAccountPersonId = $this->service_manager->getUserStringDataValue($userId, 'stripe_person_user');

        // Delete Stripe Person if exists
        if (!empty($stripeAccountPersonId)) {
            $this->payment->deleteStripePerson($stripeAccountId, $stripeAccountPersonId);
        }

        // Delete Stripe User if exists
        if (!empty($stripeAccountId)) {
            $this->payment->deleteStripeUser($stripeAccountId);
        }

        // Remove all related profile data before deleting the user
        $this->service_manager->deleteProfilAll($userId);

        return parent::delete($context);
    }
    public function getDataToUpdateMangopayUser($id, $email)
    {
        // Define all the keys you need to fetch
        $keys = [
            'first_name', 'last_name', 'telephone', 'sexe', 'billing_email', 'residenceCountry',
            'nationalityCountry', 'bdaytime', 'numeroNomRue_domicile',
            'pays_domicile', 'codePostal_domicile',
            'ville_domicile', 'region_domicile', 'numeroNomRue_livraison', 'pays_livraison',
            'codePostal_livraison', 'ville_livraison', 'region_livraison',
            //Compagny Datas
            'siret', 'tva'
        ];

        // Fetch all metadata for the user in a single query
        $userMetadata = $this->service_manager->getUserMetadata($id, $keys);

        // Map the metadata to the desired output structure
        $data = [
            "firstname" => $userMetadata['first_name'] ?? '',
            "lastname" => $userMetadata['last_name'] ?? '',
            "phone" => $userMetadata['telephone'] ?? '',
            "sexe" => isset($userMetadata['sexe']) ? ($userMetadata['sexe'] == 'femme' ? 'female' : 'male') : '',
            "user_email" => $email,
            "countryOfResidence" => $userMetadata['residenceCountry'] ?? '',
            "nationality" => $userMetadata['nationalityCountry'] ?? '',
            "birthday" => $userMetadata['bdaytime'] ?? '',
            "user_address_1" => $userMetadata['numeroNomRue_domicile'] ?? '',
            "user_country" => $userMetadata['pays_domicile'] ?? '',
            "user_postcode" => $userMetadata['codePostal_domicile'] ?? '',
            "user_city" => $userMetadata['ville_domicile'] ?? '',
            "region_domicile" => $userMetadata['region_domicile'] ?? '',
            "user_address_1_livraison" => $userMetadata['numeroNomRue_livraison'] ?? '',
            "user_country_livraison" => $userMetadata['pays_livraison'] ?? '',
            "user_postcode_livraison" => $userMetadata['codePostal_livraison'] ?? '',
            "user_city_livraison" => $userMetadata['ville_livraison'] ?? '',
            "region_livraison" => $userMetadata['region_livraison'] ?? '',
            //Company Datas
            "siret" => $userMetadata['siret'] ?? '',
            "tva" => $userMetadata['tva'] ?? ''
        ];

        // Fallback for billing email
        if (empty($data["billing_email"])) {
            $data["billing_email"] = $data["user_email"];
        }

        return $data;
    }

    /**
     * Handles Stripe account and person creation.
     * @param $userType
     * @param $data
     * @param $userId
     * @return mixed|null
     */
    private function handleStripeAccountCreation($userType, $data, $userId)
    {
        $accountToken = $this->payment->createStripeAccountToken($userType, $data);
        if (empty($accountToken['id'])) return ['token' => $accountToken, 'data' => $data];

        // 🔹 Create Stripe Account from Token
        $stripeAccount = $this->payment->createStripeUserFromToken($accountToken['id']);
        if (empty($stripeAccount['id'])) return ['token' => $accountToken, 'data' => $data];

        $this->service_manager->updateUserMeta($userId, 'mp_user_id_sandbox', $stripeAccount['id']);
        $this->payment->updateStripeUser($stripeAccount['id'], $userType, $data);

        // 🔹 Create Stripe Person if required
        if($userType != 'ROLE_ABONNE'){
            $stripePersonToken = $this->payment->createStripePersonToken($data);
            if (!empty($stripePersonToken['id'])) {
                $stripePerson = $this->payment->createStripePersonFromToken($stripeAccount['id'], $stripePersonToken['id']);
                if (!empty($stripePerson['id'])) {
                    $this->service_manager->updateUserMeta($userId, 'stripe_person_user', $stripePerson['id']);
                    $this->payment->updateStripePerson($stripeAccount['id'], $stripePerson['id'], $data);
                }
            }
        }
        return ['stripeAccount' => $stripeAccount, 'token' => $accountToken];
    }

}
