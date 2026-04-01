<?php

namespace App\Controller;

use App\Entity\{Abonnement,
    Departement,
    OffreInterne,
    UserUniqueData,
    WpPosts,
    WpOptions,
    User,
    WpTermTaxonomy,
    wpComments,
    WpUsermeta,
    WpTermRelationships
};
use App\Service\{AvatarManager, Payment, Panier, ProfileCompletionCalculator, ServiceManager, ToolsMeta};
use App\Service\DataAccessLayer\Annonces;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use MangoPay\Libraries\Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfileController extends AbstractController
{
    private $tools;
    private $service_manager;
    private $panier;
    private $annonces_access_layer;
    private $payment;
    private $requestStack;
    private $em;
    private $profileCompletionCalculator;
    private $avatarManager;

    public function __construct(
        ServiceManager $service_manager,
        Annonces $annonces_access_layer,
        ToolsMeta $tools,
        RequestStack $requestStack,
        Payment $payment,
        Panier $panier,
        ProfileCompletionCalculator $profileCompletionCalculator,
        AvatarManager $avatarManager,
        EntityManagerInterface $em
    )
    {
        $this->service_manager = $service_manager;
        $this->annonces_access_layer = $annonces_access_layer;
        $this->tools = $tools;
        $this->requestStack = $requestStack;
        $this->payment = $payment;
        $this->panier = $panier;
        $this->profileCompletionCalculator = $profileCompletionCalculator;
        $this->avatarManager = $avatarManager;
        $this->em = $em;
        $this->local = $_SERVER['APP_FILES_LOCAL_URL'];
    }


    /**
     * @Route("/{_locale}/profil-utilisateur/profil-abonne", name="home_profil", requirements={"_locale": "fr"})
     */
    public function profil()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        //First Name
        $first_name = $this->service_manager->readUserMeta($userId, 'first_name');
        //Last name
        $last_name = $this->service_manager->readUserMeta(
            $userId,
            'last_name'
        );
        //Birthday
        $bdaytime = $this->service_manager->getUserStringDataValue(
            $userId,
            'bdaytime'
        );
        //Sexe
        $sexe = $this->service_manager->readUserMeta($userId, 'sexe');
        //Birth Place
        $birth_place = $this->service_manager->readUserMeta(
            $userId,
            'nationalityCountry'
        );
        //Residence Place
        $residence = $this->service_manager->readUserMeta(
            $userId,
            'residenceCountry'
        );

        //Telephone
        $telephone = $this->service_manager->readUserMeta(
            $userId,
            'telephone'
        );
        //Raison Sociale
        $raison_sociale = $this->service_manager->readUserMeta(
            $userId,
            'raison_sociale'
        );
        //Code postal
        $code_postale = $this->service_manager->readUserMeta(
            $userId,
            'post_code'
        );
        //Avatar
        $avatars = $this->service_manager->readUserMeta(
            $userId,
            'basic_user_avatar'
        );
        //  die(var_dump($avatars));
        $avatar = '';
        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $avatar = end($img);
            $this->requestStack->getSession()->set('avatar', $avatar);
        }

        //Adresse de residence
        $prenom_domicile = $this->service_manager->readUserMeta($userId, 'prenom_domicile');
        $nom_domicile = $this->service_manager->readUserMeta($userId, 'nom_domicile');
        $nomEntreprise_domicile = $this->service_manager->readUserMeta($userId, 'nomEntreprise_domicile');
        $pays_domicile = $this->service_manager->readUserMeta($userId, 'pays_domicile');
        $numeroNomRue_domicile = $this->service_manager->readUserMeta($userId, 'numeroNomRue_domicile');
        $codePostal_domicile = $this->service_manager->readUserMeta($userId, 'codePostal_domicile');
        $ville_domicile = $this->service_manager->readUserMeta($userId, 'ville_domicile');
        //Adresse de livraison
        $prenom_livraison = $this->service_manager->readUserMeta($userId, 'prenom_livraison');
        $nom_livraison = $this->service_manager->readUserMeta($userId, 'nom_livraison');
        $nomEntreprise_livraison = $this->service_manager->readUserMeta($userId, 'nomEntreprise_livraison');
        $pays_livraison = $this->service_manager->readUserMeta($userId, 'pays_livraison');
        $numeroNomRue_livraison = $this->service_manager->readUserMeta($userId, 'numeroNomRue_livraison');
        $codePostal_livraison = $this->service_manager->readUserMeta($userId, 'codePostal_livraison');
        $ville_livraison = $this->service_manager->readUserMeta($userId, 'ville_livraison');
        //CompÃ©tences
        $cmp = $this->service_manager->readUserMeta($userId, 'competence');
        $competence = array();
        if ($cmp) {
            $competence = explode(',', $cmp->getMetaValue());
        }
        //RÃ©gion
        $region = $this->service_manager->getUserStringDataValue($userId, 'region');
        $region_livraison = $this->service_manager->getUserStringDataValue($userId, 'region_livraison');
        $region_domicile = $this->service_manager->getUserStringDataValue($userId, 'region_domicile');
        return $this->render('profile/profil_abonne.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'first_name' => $first_name, 'last_name' => $last_name, 'bdaytime' => $bdaytime,
            'sexe' => $sexe, 'birth_place' => $birth_place, 'residence' => $residence,
            'telephone' => $telephone, 'avatar' => $avatar, 'raison_sociale' => $raison_sociale,
            'code_postale' => $code_postale, 'competence' => $competence, 'region' => $region, 'region_livraison' => $region_livraison, 'region_domicile' => $region_domicile,
            'id' => $userId, 'prenom_domicile' => $prenom_domicile, 'prenom_livraison' => $prenom_livraison,
            'nom_domicile' => $nom_domicile, 'nom_livraison' => $nom_livraison,
            'nomEntreprise_domicile' => $nomEntreprise_domicile, 'nomEntreprise_livraison' => $nomEntreprise_livraison,
            'pays_domicile' => $pays_domicile, 'pays_livraison' => $pays_livraison,
            'numeroNomRue_domicile' => $numeroNomRue_domicile, 'numeroNomRue_livraison' => $numeroNomRue_livraison,
            'codePostal_domicile' => $codePostal_domicile, 'codePostal_livraison' => $codePostal_livraison,
            'ville_domicile' => $ville_domicile, 'ville_livraison' => $ville_livraison,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'page_name' => 'Profil et informations personnelles'
        ]);
    }


    /**
     * @Route("/{_locale}/profil-utilisateur/profil", name="profile")
     */
    public function index()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('profile_home_profil');
        }
        $userId = $this->getUser()->getId();
        $first_name = $this->service_manager->getUserStringDataValue($userId, 'first_name');
        $last_name = $this->service_manager->getUserStringDataValue($userId, 'last_name');
        $nom_commercial = $this->service_manager->getUserStringDataValue($userId, 'nom_commercial');
        $bdaytime = $this->service_manager->getUserStringDataValue($userId, 'bdaytime');
        $sexe = $this->service_manager->getUserStringDataValue($userId, 'sexe');

        $birth_place = $this->service_manager->getUserStringDataValue($userId, 'nationalityCountry');
        $residence = $this->service_manager->getUserStringDataValue($userId, 'residenceCountry');
        $billing_email = $this->service_manager->getUserStringDataValue($userId, 'billing_email');
        $telephone = $this->service_manager->getUserStringDataValue($userId, 'telephone');
        $raison_sociale = $this->service_manager->getUserStringDataValue($userId, 'raison_sociale');
        $code_postale = $this->service_manager->getUserStringDataValue($userId, 'post_code');
        $siret = $this->service_manager->getUserStringDataValue($userId, 'siret');
        //Activite
        $principal_activity = $this->service_manager->readUserMeta($userId, 'activite_principale');
        if ($principal_activity) {
            //get TermTaxonomy
            $principal_activity = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy(['termTaxonomyId' => $principal_activity->getMetaValue()]);
        }
        //Adresse de residence
        $pays_domicile = $this->service_manager->getUserStringDataValue($userId, 'pays_domicile');
        $numeroNomRue_domicile = $this->service_manager->getUserStringDataValue($userId, 'numeroNomRue_domicile');
        $codePostal_domicile = $this->service_manager->getUserStringDataValue($userId, 'codePostal_domicile');
        $ville_domicile = $this->service_manager->getUserStringDataValue($userId, 'ville_domicile');
        //Adresse de livraison
        $pays_livraison = $this->service_manager->getUserStringDataValue($userId, 'pays_livraison');
        $numeroNomRue_livraison = $this->service_manager->getUserStringDataValue($userId, 'numeroNomRue_livraison');
        $codePostal_livraison = $this->service_manager->getUserStringDataValue($userId, 'codePostal_livraison');
        $ville_livraison = $this->service_manager->getUserStringDataValue($userId, 'ville_livraison');
        //Avatar
        $avatars = $this->service_manager->readUserMeta($userId, 'basic_user_avatar');

        $avatar = '';
        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $avatar = end($img);
            $this->requestStack->getSession()->set('avatar', $avatar);
            $avatars = @unserialize($avatars->getMetaValue());
        }
        $titre = $this->service_manager->getUserStringDataValue($userId, 'titre');
        //TITRE
        $description = $this->service_manager->getUserStringDataValue($userId, 'description');
        //DESCRIPTION
        $competences = $this->service_manager->readUserMeta($userId, 'competence');
        //COMPETENCE
        $competence = array();
        if ($competences) {
            $competence = explode(',', $competences->getMetaValue());
        }
        //REGION
        $region_livraison = $this->service_manager->getUserStringDataValue($userId, 'region_livraison');
        $region_domicile = $this->service_manager->getUserStringDataValue($userId, 'region_domicile');
        //reference
        $reference = $this->service_manager->getUserStringDataValue($userId, 'reference');
        //portfolio
        $port = $this->service_manager->readUserMeta($userId, 'portfolio');

        $portfolio = array();
        if ($port) {
            $ids = explode(',', $port->getMetaValue());
            $portfolio = $this->em->getRepository(WpPosts::class)->findById($ids);
        }
        $vid = $this->service_manager->getUserStringDataValue($userId, 'video');
        //Videos
        $video = array();
        $imgid = array();
        if ($vid != '') {
            $video = @unserialize($vid);
            for ($i = 0; $i < sizeof($video); $i++) {
                $imgid[$i] = $this->service_manager->getYouTubeId($video[$i]);
            }
        }
        //Departement
        $departement = $this->service_manager->getUserStringDataValue($userId, 'departement');
        $departements = $this->em->getRepository(Departement::class)->findAll();
        $profileCompletionRate = $this->recomputeAndPersistProfileCompletion($this->getUser());

        return $this->render('profile/index.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'user' => $this->getUser(),
            'first_name' => $first_name, 'last_name' => $last_name, 'bdaytime' => $bdaytime,
            'nom_commercial' => $nom_commercial, 'billing_email' => $billing_email,
            'sexe' => $sexe, 'birth_place' => $birth_place, 'residence' => $residence, 'telephone' => $telephone,
            'raison_sociale' => $raison_sociale, 'code_postale' => $code_postale, 'siret' => $siret,
            'avatar' => $avatar, 'avatars' => $avatars, 'titre' => $titre,
            'description' => $description, 'competence' => $competence, 'competences' => $competences,
            'region_livraison' => $region_livraison, 'region_domicile' => $region_domicile, 'reference' => $reference, 'portfolio' => $portfolio,
            'video' => $video, 'imgid' => $imgid, 'id' => $userId,
            'pays_domicile' => $pays_domicile, 'pays_livraison' => $pays_livraison,
            'numeroNomRue_domicile' => $numeroNomRue_domicile, 'numeroNomRue_livraison' => $numeroNomRue_livraison,
            'codePostal_domicile' => $codePostal_domicile, 'codePostal_livraison' => $codePostal_livraison,
            'ville_domicile' => $ville_domicile, 'ville_livraison' => $ville_livraison,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'activities' => $this->service_manager->postCategorie1('product_activity'), 'principal_activity' => $principal_activity,
            'departements' => $departements, 'user_departement' => $departement,
            'profile_completion_rate' => $profileCompletionRate,
            'page_name' => 'Profil et informations personnelles'
        ]);
        // code...
    }

    /**
     * updates profile datas
     * @Route("/{_locale}/profil-utilisateur/updateProfil", name="updateProfile")
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function updateUserData(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $stripeData = '';
        if ($request->get('userId') > 0) {
            $userId = $request->get('userId');
        }
        $dataOfUser = array();
        //email
        $user = $this->em->getRepository(User::class)->find($userId);
        $userData = $user->getUserUniqueData();
        if ($request->get('email')) {
            $user->setEmailCanonical($request->get('email'));
            $user->setUsernameCanonical($request->get('email'));
            $user->setUserEmail($request->get('email'));
        }
        if ($request->get('password') && $request->get('password') != '') {
            $this->service_manager->updateUserPassword(
                $userId,
                password_hash(
                    $request->get('password'),
                    PASSWORD_DEFAULT
                )
            );
        }
        if ($request->get('first_name') && $request->get('last_name')) {
            $user->setDisplayName(trim($request->get('first_name')) . ' ' . trim($request->get('last_name')));
            $user->setUserNicename(trim($request->get('first_name')));
        }
        //$this->em->persist($user);
        $this->em->flush();
        //First Name
        $this->service_manager->updateUserMeta($userId, 'first_name', trim($request->get('first_name')));
        $this->service_manager->updateUserMeta($userId, 'billing_first_name', trim($request->get('first_name')));
        if ($request->get('nom_commercial')) {
            $this->service_manager->updateUserMeta($userId, 'nom_commercial', trim($request->get('nom_commercial')));
        }
        //Last name
        $this->service_manager->updateUserMeta($userId, 'last_name', trim($request->get('last_name')));
        $this->service_manager->updateUserMeta($userId, 'billing_last_name', trim($request->get('last_name')));
        //Birthday
        if ($request->get('bdaytime')) {
            $this->service_manager->updateUserMeta($userId, 'bdaytime', $request->get('bdaytime'));
        }
        //Sexe
        $this->service_manager->updateUserMeta($userId, 'sexe', trim($request->get('sexe')));
        //Birth Place
        $this->service_manager->updateUserMeta($userId, 'nationalityCountry', trim($request->get('birth_place')));
        //Telephone
        $this->service_manager->updateUserMeta($userId, 'telephone', trim($request->get('telephone')));
        //Raison Sociale
        $this->service_manager->updateUserMeta($userId, 'raison_sociale', trim($request->get('raison_sociale')));
        //REGION
        $this->service_manager->updateUserMeta($userId, 'region', trim($request->get('region')));
        $this->service_manager->updateUserMeta($userId, 'region_livraison', trim($request->get('region_livraison')));
        //Activite principale
        $this->service_manager->updateUserMeta($userId, 'activite_principale', trim($request->get('activite')));
        //Adresse de residence
        $this->service_manager->updateUserMeta($userId, 'pays_domicile', trim($request->get('pays_domicile')));
        $this->service_manager->updateUserMeta($userId, 'numeroNomRue_domicile', trim($request->get('numeroNomRue_domicile')));
        $this->service_manager->updateUserMeta($userId, 'codePostal_domicile', trim($request->get('codePostal_domicile')));
        $this->service_manager->updateUserMeta($userId, 'ville_domicile', trim($request->get('ville_domicile')));
        $this->service_manager->updateUserMeta($userId, 'region_domicile', trim($request->get('region_domicile')));
        //Adresse de livraison
        $this->service_manager->updateUserMeta($userId, 'pays_livraison', trim($request->get('pays_livraison')));
        $this->service_manager->updateUserMeta($userId, 'numeroNomRue_livraison', trim($request->get('numeroNomRue_livraison')));
        $this->service_manager->updateUserMeta($userId, 'codePostal_livraison', trim($request->get('codePostal_livraison')));
        $this->service_manager->updateUserMeta($userId, 'ville_livraison', trim($request->get('ville_livraison')));
        $this->service_manager->updateUserMeta($userId, 'region_livraison', trim($request->get('region_livraison')));
        //Save departement
        $department = $this->em->getRepository(Departement::class)->find(trim($request->get('departement')));

        if ($userData) {
            if ($department) {
                $userData->setDepartement($department);
            }
            $userData->setNomCommercial(trim($request->get('nom_commercial')));
            $this->em->flush();
        } else {
            $userData = new UserUniqueData();
            $userData->setUser($user);
            if ($department) {
                $userData->setDepartement($department);
            }
            $userData->setNomCommercial(trim($request->get('nom_commercial')));
            $this->em->persist($userData);
            $this->em->flush();
        }

        if ($request->get('bdaytime')) {
            $dataOfUser["birthday"] = $this->service_manager->verifierDate(trim($request->get('bdaytime')));
        }
        $dataOfUser["lastname"] = trim($request->get('last_name'));
        $dataOfUser["firstname"] = trim($request->get('first_name'));
        $dataOfUser["titre"] = trim($request->get('first_name'));
        $dataOfUser["email"] = trim($request->get('email'));
        $dataOfUser["birth_place"] = trim($request->get('birth_place'));
        $dataOfUser["siret"] = trim(str_replace(' ', '', $request->get('siret')));
        $dataOfUser["compagny_number"] = trim(str_replace(' ', '', $request->get('siret')));
        $dataOfUser["compagny_name"] = trim($request->get('nomEntreprise_domicile'));


        //Create account if null
        $userApiId = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
        $stripePersonAccount = $this->service_manager->getUserStringDataValue($userId, 'stripe_person_user');
        $mangopayUser = null;
        $dataOfUser["mpAccount"] = $userApiId;
        $stripeAccount = '';
        $stripePerson = null;
        $accountToken = '';
        $stripePersonToken = null;
        $data = $this->service_manager->getMangopayUserData($this->getUser()->getId(), $this->getUser()->getEmailCanonical());
        $userType = null;
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            $userType = 'ROLE_AUTO_ENTREPRENEUR';
        } elseif (in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_SOCIETE';
        } elseif (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_ABONNE';
        }

        // Avatar
        $avatarUrl = $this->avatarManager->saveCroppedAvatar($userId, $request->get('crop_image'));
        if ($avatarUrl) {
            $this->requestStack->getSession()->set('avatar', $avatarUrl);
        }
        //DESCRIPTION
        $this->service_manager->updateUserMeta($userId, 'description', trim($request->get('description')));
        if ($request->get('competence1')) {
            $this->service_manager->updateUserMeta($userId, 'competence', implode(',', array_column(json_decode($request->get('competence1')), 'value')));
        } else {
            $this->service_manager->updateUserMeta($userId, 'competence', '');
        }
        //REFERENCE
        $this->service_manager->updateUserMeta($userId, 'reference', trim($request->get('reference')));
        //VIDEO
        if (!in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            $video = $this->service_manager->getUserStringDataValue($userId, 'video');
            if ($video != '' && $request->get('new_vid')[0] != '') {
                if (sizeof(@unserialize($video)) > 0) {
                    $tabeauVideos = @unserialize($video);
                    $tabeauVideos = $this->trierTableau($tabeauVideos);
                    $this->service_manager->updateUserMeta(
                        $userId,
                        'video',
                        @serialize(
                            $this->trierTableau(
                                array_merge(
                                    $tabeauVideos,
                                    $this->trierTableau($request->get('new_vid'))
                                )
                            )
                        )
                    );
                } else {
                    $this->service_manager->updateUserMeta($userId, 'video', @serialize($this->trierTableau($request->get('new_vid'))));
                }
            } elseif ($request->get('new_vid')[0] != '') {
                $this->service_manager->updateUserMeta($userId, 'video', @serialize($this->trierTableau($request->get('new_vid'))));
            }
        }
        //portfolio
        if (!in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            $file = $request->files->get('file');
            $id = $this->service_manager->portfolio($file, $this->getParameter('portfolio_directory'), $userId);
            $port = $this->service_manager->readUserMeta($userId, 'portfolio');
            if ($port && $port->getMetaValue() != '') {
                $id = $id . ',' . $port->getMetaValue();
            }
            if ($id) {
                $this->service_manager->updateUserMeta($userId, 'portfolio', $id);
            }
        }

        if ($request->get('bank')) {
            //First Name
            $this->service_manager->updateUserMeta($userId, 'vendor_account_type', $request->get('bank'));
        }
        if ($request->get('accountHolder')) {
            //Last name
            $this->service_manager->updateUserMeta($userId, 'vendor_account_name', $request->get('accountHolder'));
        }
        if ($request->get('addressHolder')) {
            //Birthday
            $this->service_manager->updateUserMeta($userId, 'vendor_account_address1', $request->get('addressHolder'));
        }
        if ($request->get('cityHolder')) {
            //Sexe
            $this->service_manager->updateUserMeta($userId, 'vendor_account_city', $request->get('cityHolder'));
        }
        if ($request->get('codePostalHolder')) {
            //Birth Place
            $this->service_manager->updateUserMeta($userId, 'vendor_account_postcode', $request->get('codePostalHolder'));
        }
        if ($request->get('countryHolder')) {
            //Telephone
            $this->service_manager->updateUserMeta($userId, 'vendor_account_country', $request->get('countryHolder'));
        }
        if ($request->get('regionHolder')) {
            //Raison Sociale
            $this->service_manager->updateUserMeta($userId, 'vendor_account_region', $request->get('regionHolder'));
        }
        //Billing
        if ($request->get('billing_last_name')) {
            //REFERENCE
            $this->service_manager->updateUserMeta($userId, 'billing_last_name', trim($request->get('billing_last_name')));
            //VIDEO
        }
        if ($request->get('billing_first_name')) {
            //REFERENCE
            $this->service_manager->updateUserMeta($userId, 'billing_first_name', trim($request->get('billing_first_name')));
        }
        if ($request->get('billing_company')) {
            //REFERENCE
            $this->service_manager->updateUserMeta($userId, 'billing_company', trim($request->get('billing_company')));
        }
        if ($request->get('billing_city')) {
            $this->service_manager->updateUserMeta($userId, 'billing_city', trim($request->get('billing_city')));
        }
        if ($request->get('billing_state')) {
            $this->service_manager->updateUserMeta($userId, 'billing_state', trim($request->get('billing_state')));
        }
        if ($request->get('billing_address_1')) {
            $this->service_manager->updateUserMeta($userId, 'billing_address_1', trim($request->get('billing_address_1')));
        }
        if ($request->get('billing_postcode')) {
            $this->service_manager->updateUserMeta($userId, 'billing_postcode', trim($request->get('billing_postcode')));
        }
        if ($request->get('billing_country')) {
            $this->service_manager->updateUserMeta($userId, 'billing_country', trim($request->get('billing_country')));
        }
        if ($request->get('billing_email')) {
            $this->service_manager->updateUserMeta($userId, 'billing_email', trim($request->get('billing_email')));
        }
        if ($request->get('billing_phone')) {
            $this->service_manager->updateUserMeta($userId, 'billing_phone', trim($request->get('billing_phone')));
        }

        //Mailjet Updates
        if ($this->getParameter('environnement') == 'prod') {
            $role = 'Abonne';
            $kyc = $this->service_manager->getUserStringDataValue($user->getId(), 'kyc_doc_status');
            if ($kyc) {
                $kyc = 'Oui';
            } else {
                $kyc = 'Non';
            }
            if (in_array('ROLE_SOCIETE', $user->getRoles())) {
                $role = 'Societe';
            } elseif (in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles())) {
                $role = 'Autoent';
            }
        }

        $this->recomputeAndPersistProfileCompletion($user);

        return $this->json([
            'result' => true,
            'data' => $stripeData,
            'token' => $accountToken,
        ]);
    }
    public function trierTableau($tabeauVideos)
    {
        $tab = array_unique($tabeauVideos);
        $tab = array_filter($tab);
        return $tab;
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/delete_profil/{id}", name="delete_profil")
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param TokenStorageInterface $tokenStorage
     * @return Response
     */
    public function supprimerProfil(Request $request, TranslatorInterface $translator, TokenStorageInterface $tokenStorage)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $r = 0;
        if ($this->getUser() && $this->getUser()->getId() == $request->get('id')) {
            $session = $this->requestStack->getSession();
            $user_email = $this->getUser()->getEmailCanonical();
            $user_name = $this->getUser()->getDisplayName();
            $url_home_redirection = $this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL);

            //Supprimmer le compte Stripe
            $stripeAccountId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_id_sandbox');
            $stripeAccountPersonId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'stripe_person_user');
            if ($stripeAccountPersonId != '') {
                $this->payment->deleteStripePerson($stripeAccountId, $stripeAccountPersonId);
            }
            if ($stripeAccountId != '') {
                $this->payment->deleteStripeUser($stripeAccountId);
            }
            $r = $this->service_manager->deleteProfilAll($request->get('id'));
            $session->getFlashBag()->add('info', $translator->trans('desactivation-compte.message-succes', [], 'security'));

            $tokenStorage->setToken(null);
            //Send Email to user
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => $user_email,
                        'name' => $user_name
                    ]
                ],
                'templateId' => 5,
                'params' => [
                    'url_sondage' => "https://forms.office.com/pages/responsepage.aspx?id=LJnsnaO1UEW9yLNyDDRfgE6QPZ9jJjxKiMLFflxieTpUQ0hWQ0ZYUllVWjI3REFaTjJLQlVYT05RVS4u"
                ]
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set the cURL options
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            // Execute the request
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);

            //Send Email to admin
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => "commerce@trustandmarket.com",
                        'name' => "Trust & Market"
                    ]
                ],
                'templateId' => 7,
                'params' => [
                    "email_user" => $user_email,
                    "url_activate" => $url_home_redirection
                ]
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set the cURL options
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            // Execute the request
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/delete_profil_all/{id}", name="delete_profil_all")
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param TokenStorageInterface $tokenStorage
     * @return Response
     */
    public function supprimerProfilDefinitivement(Request $request, TranslatorInterface $translator, TokenStorageInterface $tokenStorage)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $r = 0;
        if ($this->getUser()->getId() == $request->get('id')) {
            $session = $this->requestStack->getSession();
            $user_email = $this->getUser()->getEmailCanonical();
            $user_name = $this->getUser()->getDisplayName();
            $url_home_redirection = $this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL);

            //Supprimer le compte Stripe
            $stripeAccountId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_id_sandbox');
            $stripeAccountPersonId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'stripe_person_user');
            if ($stripeAccountPersonId != '') {
                $this->payment->deleteStripePerson($stripeAccountId, $stripeAccountPersonId);
            }
            if ($stripeAccountId != '') {
                $this->payment->deleteStripeUser($stripeAccountId);
            }

            $r = $this->service_manager->deleteProfilAll($request->get('id'));
            $session->getFlashBag()->add('info', $translator->trans('desactivation-compte.message-succes', [], 'security'));

            // Clear the user authentication token
            $tokenStorage->setToken(null);
            //Send Email to user
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => $user_email,
                        'name' => $user_name
                    ]
                ],
                'templateId' => 5,
                'params' => [
                    'url_sondage' => "https://forms.office.com/pages/responsepage.aspx?id=LJnsnaO1UEW9yLNyDDRfgE6QPZ9jJjxKiMLFflxieTpUQ0hWQ0ZYUllVWjI3REFaTjJLQlVYT05RVS4u"
                ]
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set the cURL options
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            // Execute the request
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);

            //Send Email to admin
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => "commerce@trustandmarket.com",
                        'name' => "Trust & Market"
                    ]
                ],
                'templateId' => 7,
                'params' => [
                    "email_user" => $user_email,
                    "url_activate" => $url_home_redirection
                ]
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set the cURL options
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            // Execute the request
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }
    private function supportsProfileCompletion(User $user): bool
    {
        return in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles(), true)
            || in_array('ROLE_SOCIETE', $user->getRoles(), true);
    }

    private function recomputeAndPersistProfileCompletion(User $user): ?int
    {
        if (!$this->supportsProfileCompletion($user)) {
            return null;
        }

        $rate = $this->profileCompletionCalculator->calculateForUser($user);
        $this->service_manager->updateUserMeta((int) $user->getId(), 'profile_completion_rate', (string) $rate);

        return $rate;
    }
}



