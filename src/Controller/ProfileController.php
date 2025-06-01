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
use App\Service\{Payment, Panier, ServiceManager, ToolsMeta};
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

    public function __construct(
        ServiceManager $service_manager,
        Annonces $annonces_access_layer,
        ToolsMeta $tools,
        RequestStack $requestStack,
        Payment $payment,
        Panier $panier,
        EntityManagerInterface $em
    )
    {
        $this->service_manager = $service_manager;
        $this->annonces_access_layer = $annonces_access_layer;
        $this->tools = $tools;
        $this->requestStack = $requestStack;
        $this->payment = $payment;
        $this->panier = $panier;
        $this->em = $em;
        $this->local = $_SERVER['APP_FILES_LOCAL_URL'];
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/details/{id}", name="detailsProfessionnel", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function detailsProfessionnel(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $arr = explode('-', $request->get('id'));
        $user_id = $arr[array_key_last($arr)];
        $user = $this->em->getRepository(User::class)->find($user_id);
        $noPage = 1;
        if ($request->get('noPage')) {
            $noPage = $request->get('noPage');
        }
        $detailsPro = $this->annonces_access_layer->readAllProData($user_id, $noPage);
        //Commentaires récents
        $lastComment = '';
        $idDernierPost = $this->em->getRepository(wpPosts::class)->findBy(['postAuthor' => $user_id], ['id' => 'DESC']);
        if ($idDernierPost != null) {
            $idDernierPost = $idDernierPost['0']->getId();
            $lastComment = $this->em
                ->getRepository(wpComments::class)
                ->findBy(['commentPostId' => $idDernierPost]);
            if ($lastComment != null) {
                $lastComment = $lastComment['0']->getCommentContent();
            } else {
                $lastComment = '';
            }
        }

        $competences = $this->service_manager->readUserMeta($user_id, 'competence');
        $raison_sociale = $this->service_manager->readUserMeta($user_id, 'raison_sociale');
        //Activite
        $principal_activity = $this->service_manager->readUserMeta($user_id, 'activite_principale');
        if ($principal_activity) {
            $principal_activity = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy(['termTaxonomyId' => $principal_activity->getMetaValue()]);
        }
        //COMPETENCE
        $competence = array();
        if ($competences) {
            $competence = explode(',', $competences->getMetaValue());
        }
        //Departement
        $departements = $this->em->getRepository(Departement::class)->findAll();

        //Donnees Documents kyc
        $statut_kyc = null;
        $bankUserId = $this->service_manager->getUserStringDataValue($user_id, 'mp_user_id_sandbox');
        if ($bankUserId) {
            $statut_kyc = $this->payment->isUserKycValidated($bankUserId, $user->getRoles());
            $this->service_manager->updateUserMeta($user_id, 'kyc_doc_status', $statut_kyc);
        }
        $avatar = '';
        $avatars = $this->service_manager->readUserMeta($user_id, 'basic_user_avatar');
        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $avatar = end($img);
        }
        return $this->render('annonces/detailsProfil.html.twig', [
            'user' => $user,
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'detailsPro' => $detailsPro['data'],
            'lastComment' => $lastComment,
            'noPage' => $noPage,
            'pages' => $detailsPro['pages'],
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'competence' => $competence,
            'raison_sociale' => $raison_sociale,
            'principal_activity' => $principal_activity,
            'statut_kyc' => $statut_kyc,
            'avatar' => $avatar,
            'departements' => $departements
        ]);
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
        //Compétences
        $cmp = $this->service_manager->readUserMeta($userId, 'competence');
        $competence = array();
        if ($cmp) {
            $competence = explode(',', $cmp->getMetaValue());
        }
        //Région
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
        $data = $this->getDataToUpdateMangopayUser();
        $userType = null;
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            $userType = 'ROLE_AUTO_ENTREPRENEUR';
        } elseif (in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_SOCIETE';
        } elseif (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_ABONNE';
        }

        // Avatar
        $avatarUrl = null;
        if ($request->get('crop_image')) {
            $image_array_1 = explode(";", $request->get('crop_image'));
            $image_array_2 = explode(",", $image_array_1[1]);
            $avatarUrl = base64_decode($image_array_2[1]);
            $base_name = time() . '.png';
            file_put_contents($this->getParameter('avatar_directory') . '/' . $base_name, $avatarUrl);
            $ids = '';
            if ($avatarUrl != '' && $avatarUrl) {
                $avatars = $this->service_manager->readUserMeta($userId, 'basic_user_avatar');
                if ($avatars && $avatars->getMetaValue()) {
                    $img = @unserialize($avatars->getMetaValue());
                    $img[sizeof($img)] = $this->local . '/avatars/' . $base_name;
                    $ids = @serialize($img);
                } else {
                    $img = [$this->local . '/avatars/' . $base_name];
                    $ids = @serialize($img);
                }
                $this->service_manager->updateUserMeta($userId, 'basic_user_avatar', $ids);
                $this->requestStack->getSession()->set('avatar', $this->local . '/avatars/' . $base_name);
            }
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
     * Update portfolio datas
     * @Route("/{_locale}/profil-utilisateur/portfolio", name="portfolio")
     * @param Request $request
     * @return Response
     */
    public function portfolio(Request $request)
    {
        $file = $request->files->get('file');
        $u = $this->getUser();
        $id = $this->service_manager->portfolio($file, $this->getParameter('portfolio_directory'), $u->getId());
        $port = $this->service_manager->readUserMeta($u->getId(), 'portfolio');

        if ($port && $port->getMetaValue() != '') {
            $id = $id . ',' . $port->getMetaValue();
        }
        if ($id) {
            $this->service_manager->updateUserMeta($u->getId(), 'portfolio', $id);
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/delete_portfolio", name="delete_portfolio")
     * @param Request $request
     * @return Response
     */
    public
    function deletePortfolio(Request $request)
    {
        $u = $this->getUser();
        $port = $this->service_manager->readUserMeta($u->getId(), 'portfolio');
        $id = '';
        if ($port && $port->getMetaValue() != '') {
            $ids = explode(',', $port->getMetaValue());
            for ($i = 0; $i < sizeof($ids); $i++) {
                if ($ids[$i] != $request->get('id')) {
                    if ($id) {
                        $id = $id . ',' . $ids[$i];
                    } else {
                        $id = $ids[$i];
                    }
                }
            }
        }

        if ($id) {
            $this->service_manager->updateUserMeta(
                $u->getId(),
                'portfolio',
                $id
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/deleteImagesSecondaires/{id}/{postId}", name="delete_images_secondaires")
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function deleteImagesSecondaires(Request $request, $id)
    {
        $port = $this->service_manager->readPostMeta($request->get('postId'), 'images_annonces');
        $idnew = [];
        if ($port) {
            $ids = explode(',', $port->getMetaValue());
            for ($i = 0; $i < sizeof($ids); $i++) {
                if ($ids[$i] == $request->get('id')) {
                    $img = $this->em->getRepository(WpPosts::class)->findOneById($request->get('id'));
                    $this->em->remove($img);
                    $this->em->flush();
                } else {
                    $idnew[] = $ids[$i];
                }
            }
            $this->service_manager->deletePostMeta($port->getMetaId());
        }
        $this->service_manager->createPostMeta(
            $request->get('postId'),
            'images_annonces',
            implode(',', array_unique($idnew)),
            $request->getLocale()
        );

        return $this->render('admin/resultat.html.twig', ['result' => 1]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/delete_video", name="delete_video")
     * @param Request $request
     * @return Response
     */
    public function deleteVideo(Request $request)
    {
        $u = $this->getUser();
        $port = $this->service_manager->readUserMeta($u->getId(), 'video');
        $vid = array();
        if ($port && $port->getMetaValue() != '') {
            $ids = @unserialize($port->getMetaValue());

            for ($i = 0; $i < sizeof($ids); $i++) {
                $id = $this->service_manager->getYouTubeId($ids[$i]);
                if (trim($id) != trim($request->get('id'))) {
                    $vid[$i] = $ids[$i];
                }
            }
        }

        $this->service_manager->updateUserMeta($u->getId(), 'video', @serialize(array_filter($vid)));
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/sous_categorie/{id}", name="liste_sous_categorie")
     * @param Request $request
     * @return Response
     */
    public function sousCategorie(Request $request)
    {
        $o = 0;
        if ($request->get('o')) {
            $o = $request->get('o');
        }
        return $this->render('profile/sous_categorie.html.twig', [
            'categorie' => $this->service_manager->postCategorieWithMultilang('product_cat', $request->get('id')),
            'option' => $o,
        ]);
    }

    /**
     * Show seller details page
     * @Route("/{_locale}/profil-utilisateur/fournisseurs", name="fournisseurs")
     */
    public function fournisseurs()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        //dd($this->getDataToUpdateMangopayUser());
        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('profile_fournisseursAbonne');
        }
        //Information bancaire
        $typeCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_type');
        //Information bancaire: nom
        $nomCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_name');
        //Information bancaire: adresse
        $adresseDetenteur = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_address1');
        //Information bancaire: ville
        $villeCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_city');
        //Information bancaire: Code postale
        $codePostaleCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_postcode');
        //Information bancaire: Pays
        $paysCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_country');
        //Information bancaire: Région
        $regionCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_region');
        //Données De la facture:
        $nomEntreprise = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_company');
        $pays = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_country');
        $numeroNomRue = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_address_1');
        $codePostal = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_postcode');
        $ville = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_city');

        $etatComte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_state');
        $telephone = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_phone');
        $email = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_email');
        $siret = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'siret');
        $tva = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'tva');

        //Donnees fichiers d'identification
        $identite = null;
        $enregistrement = null;
        $statuts = null;
        $shareholder = null;
        $bankUserId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_id_sandbox');

        return $this->render('profile/fournisseurs.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            //Données Compte
            'typeCompte' => $typeCompte,
            'nomCompte' => $nomCompte,
            'adresseDetenteur' => $adresseDetenteur,
            'villeCompte' => $villeCompte,
            'codePostaleCompte' => $codePostaleCompte,
            'paysCompte' => $paysCompte,
            'regionCompte' => $regionCompte,
            //Données Facture

            'siret' => $siret,
            'tva' => $tva,
            'nomEntreprise' => $nomEntreprise,
            'pays' => $pays,
            'numeroNomRue' => $numeroNomRue,
            'codePostal' => $codePostal,
            'ville' => $ville,
            'etatComte' => $etatComte,
            'telephone' => $telephone,
            'email' => $email,
            'identite' => $identite,
            'enregistrement' => $enregistrement,
            'statuts' => $statuts,
            'shareholder' => $shareholder,
            'bankUserId' => $bankUserId,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'page_name' => 'Fournisseur de service'
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/abonne/fournisseurs", name="fournisseursAbonne")
     */
    public function fournisseursAbonne()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles()) || in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('profile_fournisseurs');
        }
        return $this->render('profile/fournisseursAbonne.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'page_name' => 'Fournisseur de service'
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/devenir-pro",name="app_switch", requirements={"_locale": "fr"},methods={"POST","PUT"})
     * @param Request $request
     * @return JsonResponse
     */
    public
    function devenirPro(Request $request)
    {
        $r = 0;
        $mangoPayAccount = $this->em
            ->getRepository(WpUsermeta::class)
            ->findOneBy([
                'userId' => $this->getUser()->getId(),
                'metaKey' => 'mp_user_id_sandbox',
            ]);
        $legalPersonType = "";
        $newAccount = null;
        $email = $this->getUser()->getEmailCanonical();
        $firstName = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'first_name');
        $lastName = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'last_name');

        $countryOfResidence = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'residenceCountry');
        if ($countryOfResidence == "") {
            $countryOfResidence = "FR";
        }

        $nationality = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'nationalityCountry');
        if ($nationality == "") {
            $nationality = "FR";
        }

        $birthday = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'bdaytime');
        if ($birthday == "") {
            $birthday = strtotime("01/01/2000");
        } else {
            $birthday = strtotime($birthday);
        }

        if ($request->get('role') == 'ROLE_AUTO_ENTREPRENEUR') {
            $legalPersonType = "Soletrader";
        } elseif ($request->get('role') == 'ROLE_SOCIETE') {
            $legalPersonType = 'Organization';
        }

        $parameters = json_decode($request->getContent());
        //Siret
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'siret', $parameters->compagny_number);
        //nomEntreprise
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_company', $parameters->compagny_name);
        //pays
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_country', $parameters->pays);
        //numeroNomRue
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_address_1', $parameters->adresse);
        //codePostale
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_postcode', $parameters->postal_code);
        //ville
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_city', $parameters->ville);
        //etatCompte
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_state', trim($parameters->region));
        $user_new_role = $parameters->user_new_role;
        if ($user_new_role == 'ROLE_AUTO_ENTREPRENEUR') {
            $legalPersonType = "Soletrader";
        } elseif ($user_new_role == 'ROLE_SOCIETE') {
            $legalPersonType = 'Organization';
        }
        $user = $this->em->getRepository(User::class)->find($this->getUser()->getId());
        if (sizeof($user->getAbonnements()) == 0) {
            $forfait = $this->em->getRepository(OffreInterne::class)->findOneBySlug('gratuit');
            $abonnement = new Abonnement();
            $abonnement->setOffre($forfait);
            $abonnement->setTarif(0);
            $abonnement->setAbonnementActif(true);
            $abonnement->setUser($user);
            $this->em->persist($abonnement);
            $this->em->flush();
        }
        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            //Create Mp Account
            if ($mangoPayAccount) {
                $this->em->remove($mangoPayAccount);
                $this->em->flush();
            }
            $data = $this->getDataToUpdateMangopayUser();
            $newAccount = $this->payment->createMangoUserLegal($data);
            //Create Wallet
            if ($newAccount) {
                $this->service_manager->createUserMeta(
                    $this->getUser()->getId(),
                    'mp_user_id_sandbox',
                    $newAccount->Id
                );
                $userWallets = $this->payment->getUserAbonneWalletsObjects($newAccount->Id);
                if (sizeof($userWallets) == 0) {
                    $this->payment->createWallet(
                        $newAccount->Id,
                        "Utilisateur professionnel",
                        'EUR'
                    );
                    $userWallets = $this->payment->getUserAbonneWalletsObjects($newAccount->Id);
                }
                $r = $this->service_manager->devenirPro($this->getUser()->getId(), $user_new_role);
                //Update user
                $data = $this->getDataToUpdateMangopayUser();
                $this->payment->updateUserLegal($newAccount->Id, 'UserLegal', $data);
                return new JsonResponse(['data' => $newAccount, 'status' => 200]);
            } else {
                return new JsonResponse(['data' => $newAccount, 'status' => 500]);
            }
        }
        // Update Userlegal identity to became society...

        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            if ($mangoPayAccount) {
                $data = $this->getDataToUpdateMangopayUser();
                $this->payment->updateUserLegal($mangoPayAccount->getMetaValue(), 'UserLegal', $data);
                $r = $this->service_manager->devenirPro(
                    $this->getUser()->getId(),
                    'ROLE_SOCIETE'
                );
            }
            return new JsonResponse(['data' => $mangoPayAccount, 'status' => 200]);
        }
    }

    public function getDataToUpdateMangopayUser()
    {
        // Define all the keys you need to fetch
        $keys = [
            'first_name', 'last_name', 'telephone', 'sexe', 'billing_email', 'residenceCountry',
            'nationalityCountry', 'mp_user_id_sandbox', 'bdaytime', 'numeroNomRue_domicile',
            'pays_domicile', 'codePostal_domicile', 'telephone',
            'ville_domicile', 'region_domicile', 'numeroNomRue_livraison', 'pays_livraison',
            'codePostal_livraison', 'ville_livraison', 'region_livraison',
            //Compagny Datas
            'siret', 'tva', 'billing_address_1', 'billing_company', 'billing_country', 'billing_postcode',
            'billing_state', 'billing_city', 'billing_phone', 'billing_email', 'nom_commercial',
            //Bank informations
            'vendor_account_type', 'vendor_account_name', 'vendor_account_address1', 'vendor_account_city', 'vendor_account_postcode', 'vendor_account_country',
            'vendor_account_region'
        ];

        // Fetch all metadata for the user in a single query
        $userMetadata = $this->service_manager->getUserMetadata($this->getUser()->getId(), $keys);

        // Map the metadata to the desired output structure
        $data = [
            "firstname" => $userMetadata['first_name'] ?? '',
            "lastname" => $userMetadata['last_name'] ?? '',
            "phone" => $userMetadata['telephone'] ?? '',
            "sexe" => isset($userMetadata['sexe']) ? ($userMetadata['sexe'] == 'femme' ? 'female' : 'male') : '',
            "user_email" => $this->getUser()->getEmailCanonical(),
            "countryOfResidence" => $userMetadata['residenceCountry'] ?? '',
            "nationality" => $userMetadata['nationalityCountry'] ?? '',
            "mpAccount" => $userMetadata['mp_user_id_sandbox'] ?? '',
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
            "tva" => $userMetadata['tva'] ?? '',
            "billing_company" => $userMetadata['billing_company'] ?? '',
            "billing_address_1" => $userMetadata['billing_address_1'] ?? '',
            "billing_country" => $userMetadata['billing_country'] ?? '',
            "billing_postcode" => $userMetadata['billing_postcode'] ?? '',
            "billing_state" => $userMetadata['billing_state'] ?? '',
            "billing_city" => $userMetadata['billing_city'] ?? '',
            "billing_phone" => $userMetadata['billing_phone'] ?? '',
            "billing_email" => $userMetadata['billing_email'] ?? '',
            "nom_commercial" => $userMetadata['nom_commercial'] ?? '',
            //Banking datas
            "vendor_account_type" => $userMetadata['vendor_account_type'] ?? '',
            "vendor_account_name" => $userMetadata['vendor_account_name'] ?? '',
            "vendor_account_address1" => $userMetadata['vendor_account_address1'] ?? '',
            "vendor_account_city" => $userMetadata['vendor_account_city'] ?? '',
            "vendor_account_postcode" => $userMetadata['vendor_account_postcode'] ?? '',
            "vendor_account_country" => $userMetadata['vendor_account_country'] ?? '',
            "vendor_account_region" => $userMetadata['vendor_account_region'] ?? '',
        ];

        // Fallback for billing email
        if (empty($data["billing_email"])) {
            $data["billing_email"] = $data["user_email"];
        }

        return $data;
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/add_date", name="calendrier_ajouter_date")
     * @param Request $request
     * @return Response
     */
    public function addDate(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        //Recuperer toutes les dates disponibles sur un an
        $dateCommande = new DateTime();
        $date = explode(',', $request->get('date'));
        $lateDate = date('d-m-Y', strtotime($dateCommande->format('d-m-Y') . ' + 1 years'));
        $availabilityRangeDates = $this->tools->getBetweenDates($dateCommande->format('d-m-Y'), $lateDate);
        $userNotAvailabilityDates = array_diff($availabilityRangeDates, $date);
        $this->service_manager->updateUserMeta($userId, 'disponibilite', implode(',', $userNotAvailabilityDates));
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/updateBankingProfileData", name="updateBankingProfileData")
     * @param Request $request
     * @return Response
     */
    public function updateBankingProfileData(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $description = null;
        //Données nécessaires aux traitements
        $mpAccount = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
        $stripePersonAccount = $this->service_manager->getUserStringDataValue($userId, 'stripe_person_user');

        $userType = '';
        $legalPersonType = '';
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            $userType = 'ROLE_AUTO_ENTREPRENEUR';
        }
        if (in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_SOCIETE';
        }
        $prenom = $this->service_manager->getUserStringDataValue($userId, 'billing_first_name');
        $nom = $this->service_manager->getUserStringDataValue($userId, 'billing_last_name');
        $pays = $this->service_manager->getUserStringDataValue($userId, 'billing_country');
        $user_nationality = $this->service_manager->getUserStringDataValue($userId, 'vendor_account_country');

        if ($user_nationality == '') {
            $user_nationality = $pays;
        }
        $email = $this->service_manager->getUserStringDataValue($userId, 'billing_email');
        $birthday = $this->service_manager->getUserStringDataValue($userId, 'bdaytime');
        if (empty($nom) || empty($prenom) || empty($pays) || empty($user_nationality)) {
            return new JsonResponse([
                'result' => 11, // Error: Missing necessary information
                'error' => 'Des informations nécessaires sont introuvables, mettez à jour votre profil.'
            ]);
        }

        $data = $this->getDataToUpdateMangopayUser();

        $existingCards = null;
        $card = null;
        //First Name
        $this->service_manager->updateUserMeta($userId, 'vendor_account_type', $request->get('bank'));
        //Last name
        $this->service_manager->updateUserMeta($userId, 'vendor_account_name', $request->get('accountHolder'));
        //Birthday
        $this->service_manager->updateUserMeta($userId, 'vendor_account_address1', $request->get('addressHolder'));
        //Sexe
        $this->service_manager->updateUserMeta($userId, 'vendor_account_city', $request->get('cityHolder'));
        //Birth Place
        $this->service_manager->updateUserMeta($userId, 'vendor_account_postcode', $request->get('codePostalHolder'));
        //Telephone
        $this->service_manager->updateUserMeta($userId, 'vendor_account_country', $request->get('countryHolder'));
        //Raison Sociale
        $this->service_manager->updateUserMeta($userId, 'vendor_account_region', $request->get('regionHolder'));
        //Create user Bank Account
        //Prepare account number and bic Deatails
        if ($request->get('bank') == 'IBAN') {
            $userData = $this->getDataToUpdateMangopayUser();
            $accountNumber = $request->get('iban');
            $detailsBic = $request->get('bic');
            //Get all external card array

        }
        return new JsonResponse([
            'result' => 1, 'existingCards' => $existingCards, 'card' => $card
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/deleteDraft/{id}", name="deleteDraftAnnounce")
     * @param Request $request
     * @return Response
     */
    public function deleteDraftAnnounce(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $r = $this->em->getRepository(WpPosts::class)->find($request->get('id'));
        if ($r->getPostType() == 'product') {
            $r->setPostStatus('trash');
        } else {
            $r->setPostType('draft-delete');
        }
        $this->em->persist($r);
        $this->em->flush();
        //Email annulation devis
        //Fin Email devis
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/cancelReservation/{id}", name="cancelledReservationAnnounce")
     * @param Request $request
     * @return Response
     */
    public function cancelReservationAnnounce(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $r = $this->em->getRepository(WpPosts::class)->find($request->get('id'));
        if ($r->getPostType() == 'shop_order') {
            $r->setPostStatus('wc-cancelled');
        }
        $this->em->persist($r);
        $this->em->flush();
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    //Parametres

    /**
     * @Route("/{_locale}/profil-utilisateur/update/billing", name="updateBillingProfileData")
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function updateBillingProfileData(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $stripeData = '';
        $stripePersonData = '';
        $accountToken = '';
        $updateStripeData = '';
        //Type d'utilisateur
        $userType = '';
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            $userType = 'ROLE_AUTO_ENTREPRENEUR';
        }
        if (in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_SOCIETE';
        }
        //tva
        $this->service_manager->updateUserMeta($userId, 'tva', $request->get('tva'));
        //siret
        $this->service_manager->updateUserMeta($userId, 'siret', $request->get('siret'));
        //nomEntreprise
        $this->service_manager->updateUserMeta($userId, 'billing_company', $request->get('nomEntreprise'));
        //pays
        $this->service_manager->updateUserMeta($userId, 'billing_country', $request->get('pays'));
        //numeroNomRue
        $this->service_manager->updateUserMeta($userId, 'billing_address_1', $request->get('numeroNomRue'));
        //codePostale
        $this->service_manager->updateUserMeta($userId, 'billing_postcode', $request->get('codePostal'));
        //ville
        $this->service_manager->updateUserMeta($userId, 'billing_city', $request->get('ville'));
        //etatCompte
        $this->service_manager->updateUserMeta($userId, 'billing_state', trim($request->get('etatComte')));
        //telephone
        $this->service_manager->updateUserMeta($userId, 'billing_phone', $request->get('telephone'));
        //email
        $this->service_manager->updateUserMeta($userId, 'billing_email', $request->get('email'));
        //Update proof files
        // files user_nationality
        $user_nationality = $this->service_manager->getUserStringDataValue($userId, 'vendor_account_country');

        //Update Stripe User
        $data = $this->getDataToUpdateMangopayUser();

        // Identity proof
        if ($request->get('doc') == 'identite' && !is_null($request->files->get('fileDoc'))) {
        }
        // proof_registration_directory
        if ($request->get('doc') == 'enregistrement' && !is_null($request->files->get('fileDoc'))) {
        }
        // ARTICLES_OF_ASSOCIATION for society
        if ($request->get('doc') == 'kbis' && !is_null($request->files->get('fileDoc'))) {
        }
        if ($request->get('doc') == 'statuts' && !is_null($request->files->get('fileDoc'))) {
        }
        // Shareholder for society  ADDRESS_PROOF
        if ($request->get('doc') == 'shareholder' && !is_null($request->files->get('fileDoc'))) {
        }

        return new JsonResponse(['result' => 1, 'stripeUserUpdate' => $stripePersonData,
            'datas' => $stripeData,
            'token' => $accountToken, 'updateStripeData' => $updateStripeData]);
    }

    //Parametres Abonne

    /**
     * @Route("/{_locale}/profil-utilisateur/dashboard", name="dashboard")
     */
    public function dashboard()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user_name = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'first_name') . ' ' .
            $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'last_name');
        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            return $this->render('profile/dashboardAbonne.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
                'user_name' => $user_name
            ]);
        } else {
            return $this->render('profile/dashboard.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
                'user_name' => $user_name
            ]);
        }
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/parametres", name="parameters")
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function parametersForPageRender(Request $request)
    {
        $dateCommande = new DateTime();
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $potentiel = $this->service_manager->getCommandesRecues($userId, $this->getUser()->getEmailCanonical());
        $user_client = $this->service_manager->getAllUserdata($userId);
        $infos_bulle = $this->em->getRepository(WpOptions::class)->findOneByOptionName('infos_bulle_' . $request->getLocale());
        if (trim($user_client['adresse_livraison']) == '') {
            $user_client['adresse_livraison'] = $this->service_manager->getUserStringDataValue($userId, 'billing_city') . ' ' . $this->service_manager->getUserStringDataValue($userId, 'billing_address_1');
        }
        $revenueGeneres = 0;
        for ($i = 0; $i < sizeof($potentiel); $i++) {
            if ($potentiel[$i]['post_status'] == "wc-in-progress") {
                $revenueGeneres = $revenueGeneres + $potentiel[$i]['post_parent'];
            }
        }

        $date = array();
        $d1 = $this->service_manager->readUserMeta($userId, 'disponibilite');
        if ($d1) {
            $date = explode(',', $d1->getMetaValue());
        }
        $lateDate = date('d-m-Y', strtotime($dateCommande->format('d-m-Y') . ' + 1 years'));
        $availabilityRangeDates = $this->tools->getBetweenDates($dateCommande->format('d-m-Y'), $lateDate);
        $userAvailabilityDates = array_diff($availabilityRangeDates, $date);
        //Check Transaction status and send response
        if ($request->get("transactionId")) {
            $transaction = $this->payment->getTransaction($request->get("transactionId"));

            $allData = $this->em->getRepository(WpPosts::class)->findOneByPostExcerpt($request->get("transactionId"));
            $this->em->flush();
            $panierData = @unserialize($allData->getPostContent());
            $critere = '';
            $livraison = 'Frais de livraison';
            $frais = 0;
            $prixTotal = 0;
            $devisePrixTotal = "";
            $emailsVendeurs = [];
            if ($allData->getPostName() != '') {
                $livraison_data = $this->em->getRepository(WpPosts::class)->find($allData->getPostName());
                if ($livraison_data) {
                    $temp_critere = $this->em->getRepository(WpPosts::class)->find($livraison_data->getPostParent());
                    $critere = ($temp_critere != null ? $temp_critere : '');
                    $frais = $livraison_data->getGuid();
                    $livraison = $livraison_data->getPostContentFiltered();
                }
            }

            if ($allData->getPinged() == "devis") {
                $prixTotal = $panierData["prix_devis"];
                //$devisePrixTotal = $panierData["devise"];
                $devisePrixTotal = '€';
                $panierData['email'] = $this->em->getRepository(User::class)->find($panierData['post_author']);
                if ($panierData['email']) {
                    $panierData['email'] = $panierData['email']->getEmailCanonical();
                } else {
                    $panierData['email'] = '';
                }
            } elseif ($allData->getPinged() == "product") {
                for ($i = 0; $i < sizeof($panierData); $i++) {
                    $panierData[$i]["nom_adresse_vendeur"] = trim($panierData[$i]["donnees_vendeur"][0] . ' ' . $panierData[$i]["donnees_vendeur"][1]);
                    $prixTotal += $panierData[$i]["prix"];
                }
                //$devisePrixTotal = $panierData["0"]["devise"];
                $devisePrixTotal = '€';
            }

            if ($transaction->Status == "SUCCEEDED" && $this->requestStack->getSession()->get('PayInCardWebId') == $transaction->Id) {
                $responseMessage = 'Paiement crée';
                $this->addFlash('notice', $responseMessage);
                //Update Command status
                $this->em->flush();
                //Do transfert between wallets
                $idAuthor = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
                $idWallet = $this->payment->getOrCreateUserWallet($idAuthor, $user_client['first_name'] . ' ' . $user_client['last_name']);
                //Loop on all product to send money in authors wallets
                $allWallets = null;
                //dd([$prixTotal, $panierData]);

                if ($allData->getPinged() == 'product') {
                    foreach ($panierData as $key => $value) {
                        $creditedUserId = $this->service_manager->getUserStringDataValue($value['userId'], 'mp_user_id_sandbox');
                        if ($creditedUserId != '') {
                            $creditedWalletId = $this->payment->getOrCreateUserWallet($creditedUserId, @serialize($value['donnees_vendeur']));
                            if ($creditedWalletId != $idWallet) {
                                $transfert = $this->payment->transferBetweenWallet($idAuthor, $creditedUserId, 'EUR',
                                    trim(str_replace(' ', '', 0.9 * $value['prix'])) * 100, 'EUR', $idWallet, $creditedWalletId);
                            }
                        }
                        $emailsVendeurs[] = ['email' => $value['email'], 'name' => 'Prestataire'];
                    }
                    //End transfert;
                    //dd(array_unique($emailsVendeurs, SORT_REGULAR));
                    //Envoyer une notification aux vendeurs
                    $data = [
                        'to' => array_unique($emailsVendeurs, SORT_REGULAR),
                        'bcc' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => 'Trust & Market',
                            ]
                        ],
                        'templateId' => 31,
                        'params' => [
                            "date_achat" => $dateCommande->format('d-m-Y'),
                            "numro_cmde" => $allData->getId(),
                            "panier" => $panierData
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
                    $response = curl_exec($ch);
                    // Close cURL session
                    curl_close($ch);

                    //Email user
                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName(),
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => 'Trust & Market',
                            ]
                        ],
                        'templateId' => 30,
                        'params' => [
                            "date_achat" => $dateCommande->format('d-m-Y'),
                            "numro_cmde" => $allData->getId(),
                            'mode' => 'Paiement en ligne',
                            'adresse_facturation' => $user_client['pays_livraison'] . ' ' . $user_client['adresse_livraison'],
                            'confitions_annulation' => $infos_bulle->getOptionValue(),
                            'panier' => $panierData,
                            'pix_total' => number_format($prixTotal + $frais, 2, ',', '.'),
                            'prix' => number_format($prixTotal, 2, ',', '.'),
                            'critere' => $critere,
                            'user_client' => $user_client,
                            'dateCommande' => $dateCommande->format('d-m-Y'),
                            'devise' => $devisePrixTotal,
                            'livraison' => $livraison,
                            'frais' => $frais
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
                    $response = curl_exec($ch);
                    // Close cURL session
                    curl_close($ch);
                } elseif ($allData->getPinged() == "devis") {
                    $creditedUserId = $this->service_manager->getUserStringDataValue($panierData['post_author'], 'mp_user_id_sandbox');
                    if ($creditedUserId != '') {
                        $creditedWalletId = $this->payment->getOrCreateUserWallet($creditedUserId, @serialize($panierData['email']));
                        if ($creditedWalletId != $idWallet) {
                            $transfert = $this->payment->transferBetweenWallet($idAuthor, $creditedUserId, 'EUR',
                                trim(str_replace(' ', '', 0.9 * $panierData['prix_devis'])) * 100, 'EUR', $idWallet, $creditedWalletId);
                        }
                    }

                    $emailsVendeurs[] = ['email' => $panierData['email'], 'name' => $panierData['first_name'] . ' ' . $panierData['last_name']];
                    //Notification client
                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName(),
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => 'Trust & Market',
                            ]
                        ],
                        'templateId' => 35,
                        'params' => [
                            "numro_cmde" => $allData->getId(),
                            'mode' => 'Paiement en ligne',
                            'adresse_facturation' => $user_client['pays_livraison'] . ' ' . $user_client['adresse_livraison'],
                            'confitions_annulation' => $infos_bulle->getOptionValue(),
                            'titrre_prestation' => $panierData['titre'],
                            'vendeur' => $panierData['first_name'] . ' ' . $panierData['last_name'],
                            'email' => $panierData['email'],
                            'quantite' => 1,
                            'prix' => $prixTotal,
                            'pix_total' => $prixTotal,
                            'date_achat' => $dateCommande->format('d-m-Y'),
                            'devise' => $devisePrixTotal,
                            'livraison' => $livraison
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
                    $response = curl_exec($ch);
                    // Close cURL session
                    curl_close($ch);

                    //Notification Fournisseur
                    $data = [
                        'to' => [
                            [
                                'email' => $panierData['email'],
                                'name' => $panierData['first_name'] . ' ' . $panierData['last_name']
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => 'Trust & Market',
                            ]
                        ],
                        'templateId' => 36,
                        'params' => [
                            "date_achat" => $dateCommande->format('d-m-Y'),
                            "numro_cmde" => $allData->getId(),
                            'mode' => 'Paiement en ligne',
                            'nom_client' => $user_client['first_name'] . ' ' . $user_client['last_name'],
                            'adresse_facturation' => $user_client['pays_livraison'] . ' ' . $user_client['adresse_livraison'],
                            'confitions_annulation' => $infos_bulle->getOptionValue(),
                            'titrre_prestation' => $panierData['titre'],
                            'vendeur' => $panierData['first_name'] . ' ' . $panierData['last_name'],
                            'email' => $panierData['email'],
                            'quantite' => 1,
                            'prix' => $prixTotal,
                            'pix_total' => $prixTotal,
                            'devise' => $devisePrixTotal
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
                    $response = curl_exec($ch);
                    // Close cURL session
                    curl_close($ch);
                }
                //Vider le panier
                $this->panier->supprimePanier();
            } elseif ($transaction->Status == "FAILED") {
                //Email User
                $responseMessage = 'Erreur lors du transfert, Veuillez reessayer ou contacter le support';
                $this->addFlash('notice', $responseMessage);
                if ($allData->getPinged() == 'product') {
                    foreach ($panierData as $key => $value) {
                        $emailsVendeurs[] = ['email' => $value['email'], 'name' => 'Prestataire'];
                    }
                    //Email sellers
                    $data = [
                        'to' => array_unique($emailsVendeurs, SORT_REGULAR),
                        'bcc' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => "Trust & Market"
                            ]
                        ],
                        'templateId' => 34,
                        'params' => [
                            "date_achat" => $dateCommande->format('d-m-Y'),
                            "numro_cmde" => $allData->getId()
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
                    $response = curl_exec($ch);
                    // Close cURL session
                    curl_close($ch);

                    //Email Client
                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName()
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => "Trust & Market"
                            ]
                        ],
                        'templateId' => 33,
                        'params' => [
                            "date_achat" => $dateCommande->format('d-m-Y'),
                            "numro_cmde" => $allData->getId(),
                            'mode' => 'Paiement en ligne',
                            'adresse_facturation' => $user_client['pays_livraison'] . ' ' . $user_client['adresse_livraison'],
                            'confitions_annulation' => $infos_bulle->getOptionValue(),
                            'panier' => $panierData,
                            'pix_total' => number_format($prixTotal + $frais, 2, ',', '.'),
                            'prix' => number_format($prixTotal, 2, ',', '.'),
                            'critere' => $critere,
                            'user_client' => $user_client,
                            'dateCommande' => $dateCommande->format('d-m-Y'),
                            'devise' => $devisePrixTotal,
                            'tag' => 'client',
                            'livraison' => $livraison,
                            'frais' => $frais
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
                    $response = curl_exec($ch);
                    // Close cURL session
                    curl_close($ch);
                }
                //update commande
                $allData->setPostStatus("wc-cancelled");
                $this->em->flush();
                return $this->redirectToRoute('annonces_panier_user');
            }
        }

        return $this->render('profile/parameters.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'd' => new DateTime(), 'date' => $date,
            'reservationServiceClient' => $this->service_manager->readUserMeta($userId, '_email_reservation_service_client'),
            'reservationServiceAnnonceur' => $this->service_manager->readUserMeta($userId, '_email_reservation_service_annonceur'),
            'annulationReservation' => $this->service_manager->readUserMeta($userId, '_email_annulation_reservation'),
            'annonceBrouillon' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_en_brouillon'
            ),
            'annonceModeration' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_en_moderation'
            ),
            'annonceRejete' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_rejete'
            ),
            'annoncePublie' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_publie'
            ),
            'adhesionNewsletter' => $this->service_manager->readUserMeta(
                $userId,
                '_email_newsletter'
            ),
            'transactionsRecues' => $potentiel,
            'transactionsVersees' => $this->service_manager->getCommandesEffectues($userId),
            'revenueGeneres' => $revenueGeneres,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'userAvailabilityDates' => array_values(array_reverse($userAvailabilityDates)),
            'page_name' => 'Paramètres'
        ]);
    }

    // EndParameters

    //Begin Comments

    /**
     * @Route("/{_locale}/profil-utilisateur/abonne/parametres", name="parameters_abonne")
     */
    public function parametersAbonneForPageRender()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $date = array();
        $d1 = $this->service_manager->readUserMeta($userId, 'disponibilite');
        if ($d1) {
            $date = explode(',', $d1->getMetaValue());
        }
        return $this->render('profile/parametersAbonne.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'd' => new DateTime(),
            'date' => $date,

            'reservationServiceClient' => $this->service_manager->readUserMeta(
                $userId,
                '_email_reservation_service_client'
            ),
            'reservationServiceAnnonceur' => $this->service_manager->readUserMeta(
                $userId,
                '_email_reservation_service_annonceur'
            ),
            'annulationReservation' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annulation_reservation'
            ),
            'annonceBrouillon' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_en_brouillon'
            ),
            'annonceModeration' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_en_moderation'
            ),
            'annonceRejete' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_rejete'
            ),
            'annoncePublie' => $this->service_manager->readUserMeta(
                $userId,
                '_email_annonce_publie'
            ),
            'adhesionNewsletter' => $this->service_manager->readUserMeta(
                $userId,
                '_email_newsletter'
            ),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'page_name' => 'Paramètres'
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/update/userNotification", name="userNotificationData")
     * @param Request $request
     * @return Response
     */
    public function userNotificationData(Request $request)
    {
        $userId = $this->getUser()->getId();

        //Réservation de services(s) (Client)
        if ($request->get('reservationServiceClient') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_reservation_service_client',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_reservation_service_client',
                0
            );
        }
        //Réservation de service(s) (Fournisseur)
        if ($request->get('reservationServiceAnnonceur') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_reservation_service_annonceur',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_reservation_service_annonceur',
                0
            );
        }
        //Annulation de réservation
        if ($request->get('annulationReservation') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annulation_reservation',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annulation_reservation',
                0
            );
        }
        //Annonce en brouillon
        if ($request->get('annonceBrouillon') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_en_brouillon',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_en_brouillon',
                0
            );
        }
        //Annonce en modération
        if ($request->get('annonceModeration') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_en_moderation',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_en_moderation',
                0
            );
        }
        //Annonce rejeté
        if ($request->get('annonceRejete') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_rejete',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_rejete',
                0
            );
        }
        //Annonce publiée
        if ($request->get('annoncePublie') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_publie',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_annonce_publie',
                0
            );
        }
        //Adhésion à la newsletter
        if ($request->get('adhesionNewsletter') == 'on') {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_newsletter',
                1
            );
        } else {
            $this->service_manager->updateUserMeta(
                $userId,
                '_email_newsletter',
                0
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/update/userPassword", name="updateUserPassword")
     * @param Request $request
     * @return Response
     */
    public function updateUserPassword(Request $request)
    {
        if (password_verify($request->get('oldUserPassword'), $this->getUser()->getPassword()) && $request->get('newUserPassword') == $request->get('newUserPasswordRetype')) {
            $this->service_manager->updateUserPassword(
                $this->getUser()->getId(),
                password_hash(
                    $request->get('newUserPassword'),
                    PASSWORD_DEFAULT
                )
            );

            return $this->render('admin/resultat.html.twig', [
                'result' => 1,
            ]);
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => 0,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/comments", name="comments")
     */
    public function comments()
    {
        $commentPosted = $this->service_manager->countUserCommentPosted(
            $this->getUser()->getId()
        );
        $commentReceived = $this->service_manager->countUserCommentReceived(
            $this->getUser()->getEmailCanonical()
        );
        $DetailsAnnonceCommentaireRecus = $this->service_manager->readAllProAnnouncesWithComments(
            $this->getUser()->getId()
        );
        $DetailsAnnonceCommentairePostes = $this->service_manager->readAllProAnnouncesWithCommentsPosted(
            $this->getUser()->getId()
        );
        return $this->render('profile/comments.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'nombreCommentairesPostes' => $commentPosted,
            'nombreCommentairesRecus' => $commentReceived,
            'DetailsAnnonceCommentairePostes' => $DetailsAnnonceCommentairePostes,
            'DetailsAnnonceCommentaireRecus' => $DetailsAnnonceCommentaireRecus,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/annonces/publier-annonce", name="creerAnnonces",methods={"GET"})
     */
    public function annonces()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $port = $this->service_manager->readUserMeta($userId, 'portfolio');
        //REFERENCE
        $portfolio = array();
        if ($port) {
            $ids = explode(',', $port->getMetaValue());
            $portfolio = $this->em->getRepository(WpPosts::class)->findById($ids);
        }
        $vid = $this->service_manager->readUserMeta($userId, 'video');
        //REFERENCE
        $video = array();
        $imgid = array();
        if ($vid) {
            $video = @unserialize($vid->getMetaValue());
            for ($i = 0; $i < sizeof($video); $i++) {
                $imgid[$i] = $this->service_manager->getYouTubeId(
                    $video[$i]
                );
            }
        }
        //Annonces Brouillons
        $annoncesBrouillonCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'draft');
        //Fin brouillons
        //Annonces Moderation
        $annoncesModerationCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'moderation');
        //Fin Moderation
        //Annonces rejetes
        $annoncesRejeteesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'trash');
        //Fin rejetes
        //Annonces Publiees
        $annoncesPublieesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'publish');
        //Fin publiees
        //Annonces Terminees
        $annoncesTermineesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'ended');
        //Fin Terminees
        //Annonces Annulees
        $annoncesAnnuleesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'drop');
        //Fin Annulees
        //Devis en attente
        $devisEnAttenteCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-pending');
        //Fin Devis en attente
        //Devis en Brouillon
        $devisEnBrouillonCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-draft');
        //Fin Devis brouillon
        // Reservations
        //reservationsEnCours
        $reservationsEnCoursCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-in-progress');
        //Fin reservationsEnCours
        //reservationsTerminees
        $reservationsTermineesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-completed');
        //Fin reservationsTerminees
        //reservationsAnnulees
        $reservationsAnnuleesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-cancelled');
        //Fin reservationsAnnulees
        //reservationsDevisEnAttente
        $reservationsDevisEnAttenteCount = $this->annonces_access_layer->readListReservationDevisOfUserCount($userId, 'devis-pending');
        //Fin reservationsDevisEnAttente

        return $this->render('profile/creerAnnonces.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'categorie' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'portfolio' => $portfolio,
            'video' => $video,
            'imgid' => $imgid,
            'annoncesBrouillonCount' => $annoncesBrouillonCount,
            'annoncesModerationCount' => $annoncesModerationCount,
            'annoncesRejeteesCount' => $annoncesRejeteesCount,
            'annoncesPublieesCount' => $annoncesPublieesCount,
            'annoncesTermineesCount' => $annoncesTermineesCount,
            'annoncesAnnuleesCount' => $annoncesAnnuleesCount,
            'devisEnAttenteCount' => $devisEnAttenteCount,
            'devisEnBrouillonCount' => $devisEnBrouillonCount,
            // For clients reservations
            'reservationsEnCoursCount' => $reservationsEnCoursCount,
            'reservationsTermineesCount' => $reservationsTermineesCount,
            'reservationsAnnuleesCount' => $reservationsAnnuleesCount,
            'reservationsDevisEnAttenteCount' => $reservationsDevisEnAttenteCount,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'page_name' => 'Publier une annonce'
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/annonces/editer-annonce/{id}", name="EditDraftAnnounce",methods={"GET"})
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function EditDraftAnnounce(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($this->getUser()) {
            $userId = $this->getUser()->getId();
            $port = $this->service_manager->readUserMeta($userId, 'portfolio');
            //REFERENCE

            $portfolio = array();
            if ($port) {
                $ids = explode(',', $port->getMetaValue());
                $portfolio = $this->em->getRepository(WpPosts::class)->findById($ids);
            }

            $vid = $this->service_manager->readUserMeta($userId, 'video');
            //REFERENCE

            $video = array();
            $imgid = array();
            if ($vid) {
                $video = @unserialize($vid->getMetaValue());
                for ($i = 0; $i < sizeof($video); $i++) {
                    $imgid[$i] = $this->service_manager->getYouTubeId(
                        $video[$i]
                    );
                }
            }
            $id = $request->get('id');
            //dd($request->get('id'));
            //Donnees supplementaires post a editer
            $pays = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_country'
            );
            $adresse_postale = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_adress'
            );
            $code_postal = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_code_postal'
            );
            $precision = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_precision'
            );
            $ville = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_city'
            );
            $bureau = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_has_equipments_bureau'
            );
            $wifi = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_has_equipments_wifi'
            );
            $cafe = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_has_equipments_cofe'
            );
            $other = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_other_equipments'
            );
            $imagePost = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_image_gallery'
            );
            $imagesSecondaires = $this->service_manager->getAllSecondImageForAnnouces($id);
            $videoTab = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_video'
            );
            $devise = $this->service_manager->getPostStringDataValue(
                $id,
                '_product_devise'
            );
            $prix = $this->service_manager->getPostStringDataValue(
                $id,
                '_price'
            );
            $client = $this->service_manager->getPostStringDataValue(
                $id,
                'client'
            );
            $nom_prenom_email = '';
            $editedData = '';
            if ($client != '') {
                $nom_prenom_email =
                    $this->service_manager->getUserStringDataValue(
                        trim($client),
                        'first_name'
                    ) .
                    ' ' .
                    $this->service_manager->getUserStringDataValue(
                        trim($client),
                        'last_name'
                    ) . ' ' .
                    $this->em->getRepository(User::class)->find(trim($client))->getEmailCanonical();
                $editedData = $this->service_manager->readAllDevisData($request->get('id'));
            } else {
                $editedData = $this->service_manager->readAllAnnonceData($request->get('id'));
            }
            $dates_horaires = $this->service_manager->getPostStringDataValue($id, 'dates_horaires');
            //Annonces Brouillons
            $annoncesBrouillonCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'draft');
            //Fin brouillons
            //Annonces Moderation
            $annoncesModerationCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'moderation');
            //Fin Moderation
            //Annonces rejetes
            $annoncesRejeteesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'trash');
            //Fin rejetes
            //Annonces Publiees
            $annoncesPublieesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'publish');
            //Fin publiees
            //Annonces Terminees
            $annoncesTermineesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'ended');
            //Fin Terminees
            //Annonces Annulees
            $annoncesAnnuleesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'drop');
            //Fin Annulees
            //Devis en attente
            $devisEnAttenteCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-pending');
            //Fin Devis en attente
            //Devis en Brouillon
            $devisEnBrouillonCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-draft');
            //Fin Devis brouillon
            // Reservations
            //reservationsEnCours
            $reservationsEnCoursCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-in-progress');
            //Fin reservationsEnCours
            //reservationsTerminees
            $reservationsTermineesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-completed');
            //Fin reservationsTerminees
            //reservationsAnnulees
            $reservationsAnnuleesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-cancelled');
            //Fin reservationsAnnulees
            //reservationsDevisEnAttente
            $reservationsDevisEnAttenteCount = $this->annonces_access_layer->readListReservationDevisOfUserCount($userId, 'devis-pending');
            //Fin reservationsDevisEnAttente
            return $this->render('profile/creerAnnonces.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'categorie' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'portfolio' => $portfolio, 'video' => $video, 'imgid' => $imgid,
                'annoncesBrouillonCount' => $annoncesBrouillonCount,
                'annoncesModerationCount' => $annoncesModerationCount,
                'annoncesRejeteesCount' => $annoncesRejeteesCount,
                'annoncesPublieesCount' => $annoncesPublieesCount,
                'annoncesTermineesCount' => $annoncesTermineesCount,
                'annoncesAnnuleesCount' => $annoncesAnnuleesCount,
                'devisEnAttenteCount' => $devisEnAttenteCount,
                'devisEnBrouillonCount' => $devisEnBrouillonCount,
                // For clients reservations
                'reservationsEnCoursCount' => $reservationsEnCoursCount,
                'reservationsTermineesCount' => $reservationsTermineesCount,
                'reservationsAnnuleesCount' => $reservationsAnnuleesCount,
                'reservationsDevisEnAttenteCount' => $reservationsDevisEnAttenteCount,
                'editedData' => $editedData,
                'editedDataPays' => $pays,
                'editedDataAdressePostale' => $adresse_postale,
                'editedDataCodePostale' => $code_postal,
                'editedDataPrecision' => $precision,
                'editedDataVille' => $ville,
                'editedDataBureau' => $bureau,
                'editedDataWifi' => $wifi,
                'editedDataCafe' => $cafe,
                'editedDataOther' => $other,
                'editedDataImage' => $imagePost,
                'editedDataVideo' => $videoTab,
                'editedDataDevise' => $devise,
                'editedDataPrix' => $prix,
                'state' => 'edition',
                'nom_prenom_email' => $nom_prenom_email,
                'dates_horaires' => $dates_horaires,
                'client' => $client,
                'imagesSecondaires' => $imagesSecondaires,
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
                'page_name' => 'Editer une annonce'
            ]);
        } else {
            return $this->redirect('/' . $request->getLocale() . '/login');
        }
    }

    // End Comments
    //Ajout d'une annonce

    /**
     * @Route("/{_locale}/profil-utilisateur/annonces", name="annonces",methods={"GET"})
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function mesannonces(Request $request)
    {
        $userId = $this->getUser()->getId();
        $infos_bulle = $this->em->getRepository(WpOptions::class)->findOneByOptionName('infos_bulle_' . $request->getLocale());
        $limit = 6;
        $noPage = 1;
        $offset = 0;

        //Annonces Brouillons
        $annoncesBrouillonCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'draft');
        $annoncesBrouillon = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'draft', $offset, $limit);
        $pages_draft = 0;
        if ($annoncesBrouillonCount > 0) {
            $pages_draft = ceil($annoncesBrouillonCount / $limit);
        }
        //Fin brouillons

        //Annonces Moderation
        $annoncesModerationCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'moderation');
        $annoncesModeration = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'moderation', $offset, $limit);
        $pages_moderation = 0;
        if ($annoncesModerationCount > 0) {
            $pages_moderation = ceil($annoncesModerationCount / $limit);
        }
        //Fin Moderation

        //Annonces rejetes
        $annoncesRejeteesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'trash');
        $annoncesRejetees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'trash', $offset, $limit);
        $pages_trash = 0;
        if ($annoncesRejeteesCount > 0) {
            $pages_trash = ceil($annoncesRejeteesCount / $limit);
        }
        //Fin rejetes

        //Annonces Publiees
        $annoncesPublieesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'publish');
        $annoncesPubliees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'publish', $offset, $limit);
        $pages_publish = 0;
        if ($annoncesPublieesCount > 0) {
            $pages_publish = ceil($annoncesPublieesCount / $limit);
        }
        //Fin publiees

        //Annonces Terminees
        $annoncesTermineesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'ended');
        $annoncesTerminees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'ended', $offset, $limit);
        $pages_ended = 0;
        if ($annoncesTermineesCount > 0) {
            $pages_ended = ceil($annoncesTermineesCount / $limit);
        }
        //Fin Terminees

        //Annonces Annulees
        $annoncesAnnuleesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'drop');
        $annoncesAnnulees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'drop', $offset, $limit);
        $pages_drop = 0;
        if ($annoncesAnnuleesCount > 0) {
            $pages_drop = ceil($annoncesAnnuleesCount / $limit);
        }
        //Fin Annulees

        //Devis en attente
        $devisEnAttenteCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-pending');
        $devisEnAttente = $this->annonces_access_layer->readListDevisDataOfUserPaginate($userId, 'devis-pending', $offset, $limit);
        $pages_devis_pending = 0;
        if ($devisEnAttenteCount > 0) {
            $pages_devis_pending = ceil($devisEnAttenteCount / $limit);
        }
        //Fin Devis en attente

        //Devis en Brouillon
        $devisEnBrouillonCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-draft');
        $devisEnBrouillon = $this->annonces_access_layer->readListDevisDataOfUserPaginate($userId, 'devis-draft', $offset, $limit);
        $pages_devis_draft = 0;
        if ($devisEnBrouillonCount > 0) {
            $pages_devis_draft = ceil($devisEnBrouillonCount / $limit);
        }
        //Fin Devis brouillon
        // Reservations

        //reservationsEnCours
        $reservationsEnCoursCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-in-progress');
        $reservationsEnCours = $this->annonces_access_layer->readListReservationOfUserPaginate($userId, 'wc-in-progress', $offset, $limit);
        $pages_reservations_pending = 0;
        if ($reservationsEnCoursCount > 0) {
            $pages_reservations_pending = ceil($reservationsEnCoursCount / $limit);
        }
        //Fin reservationsEnCours

        //reservationsTerminees
        $reservationsTermineesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-completed');
        $reservationsTerminees = $this->annonces_access_layer->readListReservationOfUserPaginate($userId, 'wc-completed', $offset, $limit);
        $pages_reservations_completed = 0;
        if ($reservationsTermineesCount > 0) {
            $pages_reservations_completed = ceil($reservationsTermineesCount / $limit);
        }
        //Fin reservationsTerminees

        //reservationsAnnulees
        $reservationsAnnuleesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-cancelled');
        $reservationsAnnulees = $this->annonces_access_layer->readListReservationOfUserPaginate($userId, 'wc-cancelled', $offset, $limit);
        $pages_reservations_cancelled = 0;
        if ($reservationsAnnuleesCount > 0) {
            $pages_reservations_cancelled = ceil($reservationsAnnuleesCount / $limit);
        }
        //Fin reservationsAnnulees

        //reservationsDevisEnAttente
        $reservationsDevisEnAttenteCount = $this->annonces_access_layer->readListReservationDevisOfUserCount($userId, 'devis-pending');
        $reservationsDevisEnAttente = $this->annonces_access_layer->readListReservationDevisOfUserPaginate($userId, 'devis-pending', $offset, $limit);
        $pages_reservation_devis_pending = 0;
        if ($reservationsDevisEnAttenteCount > 0) {
            $pages_reservation_devis_pending = ceil($reservationsDevisEnAttenteCount / $limit);
        }

        //Fin reservationsDevisEnAttente
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles()) || in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            return $this->render('profile/annonces.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'infos_bulle' => $infos_bulle,
                'tag' => null,

                'annoncesBrouillon' => $annoncesBrouillon,
                'annoncesBrouillonCount' => $annoncesBrouillonCount,
                'pages_draft' => $pages_draft,

                'annoncesModeration' => $annoncesModeration,
                'annoncesModerationCount' => $annoncesModerationCount,
                'pages_moderation' => $pages_moderation,

                'annoncesRejetees' => $annoncesRejetees,
                'annoncesRejeteesCount' => $annoncesRejeteesCount,
                'pages_trash' => $pages_trash,

                'annoncesPubliees' => $annoncesPubliees,
                'annoncesPublieesCount' => $annoncesPublieesCount,
                'pages_publish' => $pages_publish,

                'annoncesTerminees' => $annoncesTerminees,
                'annoncesTermineesCount' => $annoncesTermineesCount,
                'pages_ended' => $pages_ended,

                'annoncesAnnulees' => $annoncesAnnulees,
                'annoncesAnnuleesCount' => $annoncesAnnuleesCount,
                'pages_drop' => $pages_drop,

                'devisEnAttente' => $devisEnAttente,
                'devisEnAttenteCount' => $devisEnAttenteCount,
                'pages_devis_pending' => $pages_devis_pending,

                'devisEnBrouillon' => $devisEnBrouillon,
                'devisEnBrouillonCount' => $devisEnBrouillonCount,
                'pages_devis_draft' => $pages_devis_draft,
                // For clients reservations
                'reservationsEnCours' => $reservationsEnCours,
                'reservationsEnCoursCount' => $reservationsEnCoursCount,
                'pages_reservations_pending' => $pages_reservations_pending,

                'reservationsTerminees' => $reservationsTerminees,
                'reservationsTermineesCount' => $reservationsTermineesCount,
                'pages_reservations_completed' => $pages_reservations_completed,

                'reservationsAnnulees' => $reservationsAnnulees,
                'reservationsAnnuleesCount' => $reservationsAnnuleesCount,
                'pages_reservations_cancelled' => $pages_reservations_cancelled,

                'reservationsDevisEnAttente' => $reservationsDevisEnAttente,
                'reservationsDevisEnAttenteCount' => $reservationsDevisEnAttenteCount,
                'pages_reservation_devis_pending' => $pages_reservation_devis_pending,
                'noPage' => $noPage,

                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
                'page_name' => 'Annonces'
            ]);
        }
        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            return $this->render('profile/annoncesAbonne.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                // For clients reservations
                'reservationsEnCours' => $reservationsEnCours,
                'reservationsEnCoursCount' => $reservationsEnCoursCount,
                'pages_reservations_pending' => $pages_reservations_pending,

                'reservationsTerminees' => $reservationsTerminees,
                'reservationsTermineesCount' => $reservationsTermineesCount,
                'pages_reservations_completed' => $pages_reservations_completed,

                'reservationsAnnulees' => $reservationsAnnulees,
                'reservationsAnnuleesCount' => $reservationsAnnuleesCount,
                'pages_reservations_cancelled' => $pages_reservations_cancelled,

                'reservationsDevisEnAttente' => $reservationsDevisEnAttente,
                'reservationsDevisEnAttenteCount' => $reservationsDevisEnAttenteCount,
                'pages_reservation_devis_pending' => $pages_reservation_devis_pending,
                'noPage' => $noPage,
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'page_name' => 'Annonces'
            ]);
        } else {
            return $this->redirectToRoute('app_login');
        }
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/annonces/{tag}", name="annoncesTag",methods={"GET"})
     * @param $tag
     * @param Request $request
     * @return Response
     */
    public function mesannoncesTag($tag, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();
        $infos_bulle = $this->em->getRepository(WpOptions::class)->findOneByOptionName('infos_bulle_' . $request->getLocale());

        $limit = 6;
        $noPage = 1;
        $offset = 0;
        //Annonces Brouillons
        $annoncesBrouillon = [];
        $annoncesBrouillonCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'draft');
        $pages_draft = 0;
        if ($tag == 'annonces-en-brouillon') {
            $annoncesBrouillon = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'draft', $offset, $limit);
        }
        if ($annoncesBrouillonCount > 0) {
            $pages_draft = ceil($annoncesBrouillonCount / $limit);
        }
        //Fin brouillons

        //Annonces Moderation
        $annoncesModeration = [];
        $annoncesModerationCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'moderation');
        $pages_moderation = 0;
        if ($tag == 'annonces-en-moderation') {
            $annoncesModeration = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'moderation', $offset, $limit);
        }
        if ($annoncesModerationCount > 0) {
            $pages_moderation = ceil($annoncesModerationCount / $limit);
        }
        //Fin Moderation

        //Annonces rejetes
        $annoncesRejetees = [];
        $annoncesRejeteesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'trash');
        $pages_trash = 0;
        if ($tag == 'annonces-rejetees') {
            $annoncesRejetees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'trash', $offset, $limit);
        }
        if ($annoncesRejeteesCount > 0) {
            $pages_trash = ceil($annoncesRejeteesCount / $limit);
        }
        //Fin rejetes

        //Annonces Publiees
        $annoncesPubliees = [];
        $annoncesPublieesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'publish');
        $pages_publish = 0;
        if ($tag == 'annonces-publiees') {
            $annoncesPubliees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'publish', $offset, $limit);
        }
        if ($annoncesPublieesCount > 0) {
            $pages_publish = ceil($annoncesPublieesCount / $limit);
        }
        //Fin publiees

        //Annonces Terminees
        $annoncesTerminees = [];
        $annoncesTermineesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'ended');
        $pages_ended = 0;
        if ($tag == 'ended') {
            $annoncesTerminees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'ended', $offset, $limit);
        }
        if ($annoncesTermineesCount > 0) {
            $pages_ended = ceil($annoncesTermineesCount / $limit);
        }
        //Fin Terminees

        //Annonces Annulees
        $annoncesAnnulees = [];
        $annoncesAnnuleesCount = $this->annonces_access_layer->readListAnnonceDataOfUserCount($userId, 'drop');
        $pages_drop = 0;
        if ($tag == 'drop') {
            $annoncesAnnulees = $this->annonces_access_layer->readListAnnonceDataOfUserPaginate($userId, 'drop', $offset, $limit);
        }
        if ($annoncesAnnuleesCount > 0) {
            $pages_drop = ceil($annoncesAnnuleesCount / $limit);
        }
        //Fin Annulees

        //Devis en attente
        $devisEnAttente = [];
        $devisEnAttenteCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-pending');
        $pages_devis_pending = 0;
        if ($tag == 'devis-en-attente') {
            $devisEnAttente = $this->annonces_access_layer->readListDevisDataOfUserPaginate($userId, 'devis-pending', $offset, $limit);
        }
        if ($devisEnAttenteCount > 0) {
            $pages_devis_pending = ceil($devisEnAttenteCount / $limit);
        }
        //Fin Devis en attente

        //Devis en Brouillon
        $devisEnBrouillon = [];
        $devisEnBrouillonCount = $this->annonces_access_layer->readListDevisDataOfUserCount($userId, 'devis-draft');
        $pages_devis_draft = 0;
        if ($tag == 'devis-en-brouillon') {
            $devisEnBrouillon = $this->annonces_access_layer->readListDevisDataOfUserPaginate($userId, 'devis-draft', $offset, $limit);
        }
        if ($devisEnBrouillonCount > 0) {
            $pages_devis_draft = ceil($devisEnBrouillonCount / $limit);
        }
        //Fin Devis brouillon
        // Reservations

        //reservationsEnCours
        $reservationsEnCours = [];
        $reservationsEnCoursCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-in-progress');
        $pages_reservations_pending = 0;
        if ($tag == 'reservations-en-cours') {
            $reservationsEnCours = $this->annonces_access_layer->readListReservationOfUserPaginate($userId, 'wc-in-progress', $offset, $limit);
        }
        if ($reservationsEnCoursCount > 0) {
            $pages_reservations_pending = ceil($reservationsEnCoursCount / $limit);
        }
        //Fin reservationsEnCours

        //reservationsTerminees
        $reservationsTerminees = [];
        $reservationsTermineesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-completed');
        $pages_reservations_completed = 0;
        if ($tag == 'reservations-terminees') {
            $reservationsTerminees = $this->annonces_access_layer->readListReservationOfUserPaginate($userId, 'wc-completed', $offset, $limit);
        }
        if ($reservationsTermineesCount > 0) {
            $pages_reservations_completed = ceil($reservationsTermineesCount / $limit);
        }
        //Fin reservationsTerminees

        //reservationsAnnulees
        $reservationsAnnulees = [];
        $reservationsAnnuleesCount = $this->annonces_access_layer->readListReservationOfUserCount($userId, 'wc-cancelled');
        $pages_reservations_cancelled = 0;
        if ($tag == 'reservations-annulees') {
            $reservationsAnnulees = $this->annonces_access_layer->readListReservationOfUserPaginate($userId, 'wc-cancelled', $offset, $limit);
        }
        if ($reservationsAnnuleesCount > 0) {
            $pages_reservations_cancelled = ceil($reservationsAnnuleesCount / $limit);
        }
        //Fin reservationsAnnulees

        //reservationsDevisEnAttente
        $reservationsDevisEnAttente = [];
        $reservationsDevisEnAttenteCount = $this->annonces_access_layer->readListReservationDevisOfUserCount($userId, 'devis-pending');
        $pages_reservation_devis_pending = 0;
        if ($tag == 'reservation-devis-en-attente') {
            $reservationsDevisEnAttente = $this->annonces_access_layer->readListReservationDevisOfUserPaginate($userId, 'devis-pending', $offset, $limit);
        }
        if ($reservationsDevisEnAttenteCount > 0) {
            $pages_reservation_devis_pending = ceil($reservationsDevisEnAttenteCount / $limit);
        }
        //Fin reservationsDevisEnAttente

        return $this->render('profile/annoncesTag.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'infos_bulle' => $infos_bulle,
            'tag' => $request->get('tag'),
            'noPage' => $noPage,
            'annoncesBrouillon' => $annoncesBrouillon,
            'annoncesBrouillonCount' => $annoncesBrouillonCount,
            'pages_draft' => $pages_draft,

            'annoncesModeration' => $annoncesModeration,
            'annoncesModerationCount' => $annoncesModerationCount,
            'pages_moderation' => $pages_moderation,

            'annoncesRejetees' => $annoncesRejetees,
            'annoncesRejeteesCount' => $annoncesRejeteesCount,
            'pages_trash' => $pages_trash,

            'annoncesPubliees' => $annoncesPubliees,
            'annoncesPublieesCount' => $annoncesPublieesCount,
            'pages_publish' => $pages_publish,

            'annoncesTerminees' => $annoncesTerminees,
            'annoncesTermineesCount' => $annoncesTermineesCount,
            'pages_ended' => $pages_ended,

            'annoncesAnnulees' => $annoncesAnnulees,
            'annoncesAnnuleesCount' => $annoncesAnnuleesCount,
            'pages_drop' => $pages_drop,

            'devisEnAttente' => $devisEnAttente,
            'devisEnAttenteCount' => $devisEnAttenteCount,
            'pages_devis_pending' => $pages_devis_pending,

            'devisEnBrouillon' => $devisEnBrouillon,
            'devisEnBrouillonCount' => $devisEnBrouillonCount,
            'pages_devis_draft' => $pages_devis_draft,
            // For clients reservations
            'reservationsEnCours' => $reservationsEnCours,
            'reservationsEnCoursCount' => $reservationsEnCoursCount,
            'pages_reservations_pending' => $pages_reservations_pending,

            'reservationsTerminees' => $reservationsTerminees,
            'reservationsTermineesCount' => $reservationsTermineesCount,
            'pages_reservations_completed' => $pages_reservations_completed,

            'reservationsAnnulees' => $reservationsAnnulees,
            'reservationsAnnuleesCount' => $reservationsAnnuleesCount,
            'pages_reservations_cancelled' => $pages_reservations_cancelled,

            'reservationsDevisEnAttente' => $reservationsDevisEnAttente,
            'reservationsDevisEnAttenteCount' => $reservationsDevisEnAttenteCount,
            'pages_reservation_devis_pending' => $pages_reservation_devis_pending,

            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'page_name' => 'Annonces'
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/annoncesPagine", name="listeAnnoncesPagine", requirements={"_locale": "en|fr"}, methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function annoncesPagine(Request $request)
    {
        $pages = 0;
        $limit = 6;
        $page = 1;
        $nombreDeLignes = $request->get('nombreDeLignes');
        if ($request->get('noPage')) {
            $page = $request->get('noPage');
        }
        if ($nombreDeLignes > 0) {
            $pages = ceil($nombreDeLignes / $limit);
        }
        $offset = ($page - 1) * $limit;
        $start = $offset + 1;
        $end = min(($offset + $limit), $nombreDeLignes);
        $pagination = null;
        $html = '';
        $reservations = [];
        $devis = [];
        $reservations_devis = [];
        $tag = $request->get('tag');
        if ($tag == 'moderation') {
            $annonces = $this->annonces_access_layer
                ->readListAnnonceDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/annonces_tag.html.twig', [
                'annonces' => $annonces,
                'annoncesModerationCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_annonces_moderation.html.twig', [
                'noPage' => $page,
                'pages_moderation' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'annoncesModerationCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'draft') {
            $annonces = $this->annonces_access_layer
                ->readListAnnonceDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/annonces_tag.html.twig', [
                'annonces' => $annonces,
                'annoncesBrouillonCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_annonces_brouillon.html.twig', [
                'noPage' => $page,
                'pages_draft' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'annoncesBrouillonCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'publish') {
            $annonces = $this->annonces_access_layer
                ->readListAnnonceDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/annonces_tag.html.twig', [
                'annonces' => $annonces,
                'annoncesPublieesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_annonces_publiees.html.twig', [
                'noPage' => $page,
                'pages_publish' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'annoncesPublieesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'trash') {
            $annonces = $this->annonces_access_layer
                ->readListAnnonceDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/annonces_tag.html.twig', [
                'annonces' => $annonces,
                'annoncesRejeteesCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_annonces_rejetees.html.twig', [
                'noPage' => $page,
                'pages' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'annoncesRejeteesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'ended') {
            $annonces = $this->annonces_access_layer
                ->readListAnnonceDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/annonces_tag.html.twig', [
                'annonces' => $annonces,
                'annoncesTermineesCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_annonces_terminees.html.twig', [
                'noPage' => $page,
                'pages_ended' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'annoncesTermineesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'drop') {
            $annonces = $this->annonces_access_layer
                ->readListAnnonceDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/annonces_tag.html.twig', [
                'annonces' => $annonces,
                'annoncesAnnuleesCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_annonces_annulees.html.twig', [
                'noPage' => $page,
                'pages_drop' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'annoncesAnnuleesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } //reservations
        elseif ($tag == 'wc-in-progress') {
            $reservations = $this->annonces_access_layer
                ->readListReservationOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/reservations_tag.html.twig', [
                'reservations' => $reservations,
                'reservationsEnCoursCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_reservations_cours.html.twig', [
                'noPage' => $page,
                'pages_reservations_pending' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'reservationsEnCoursCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'wc-completed') {
            $reservations = $this->annonces_access_layer
                ->readListReservationOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/reservations_tag.html.twig', [
                'reservations' => $reservations,
                'reservationsTermineesCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_reservations_terminees.html.twig', [
                'noPage' => $page,
                'pages_reservations_completed' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'reservationsTermineesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } elseif ($tag == 'wc-cancelled') {
            $reservations = $this->annonces_access_layer
                ->readListReservationOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/reservations_tag.html.twig', [
                'reservations' => $reservations,
                'reservationsAnnuleesCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_reservations_annulees.html.twig', [
                'noPage' => $page,
                'pages_reservations_cancelled' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'reservationsAnnuleesCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } //devis reservations en attente
        elseif ($tag == 'devis-pending' && $request->get('type') == 'reservation') {
            $reservations_devis = $this->annonces_access_layer
                ->readListReservationDevisOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/devis_reservations_tag.html.twig', [
                'reservations_devis' => $reservations_devis,
                'reservationsDevisEnAttenteCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_reservations_devis_attente.html.twig', [
                'noPage' => $page,
                'pages_reservation_devis_pending' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'reservationsDevisEnAttenteCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } //Devis
        elseif ($tag == 'devis-pending' && $request->get('type') == 'devis') {
            $devis = $this->annonces_access_layer
                ->readListDevisDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/devis_tag.html.twig', [
                'devis' => $devis,
                'devisEnAttenteCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_devis_attente.html.twig', [
                'noPage' => $page,
                'pages_devis_pending' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'devisEnAttenteCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        } else {
            $devis = $this->annonces_access_layer
                ->readListDevisDataOfUserPaginate(
                    $this->getUser()->getId(),
                    $request->get('tag'),
                    $offset,
                    $limit = 6
                );
            $html = $this->renderView('profile/partials/devis_tag.html.twig', [
                'devis' => $devis,
                'devisEnBrouillonCount' => $request->get('nombreDeLignes')
            ]);
            $pagination = $this->renderView('profile/partials/pagination_bloc_devis_brouillon.html.twig', [
                'noPage' => $page,
                'pages_devis_draft' => $pages,
                'offset' => $offset,
                'start' => $start,
                'end' => $end,
                'devisEnBrouillonCount' => $request->get('nombreDeLignes'),
                'tag' => $tag
            ]);
        }
        return new JsonResponse(['html' => $html, 'pagination' => $pagination]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/reservations", name="reservations",methods={"GET"})
     */
    public function reservations()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $userId = $this->getUser()->getId();
        return $this->render('profile/reservations.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'annoncesBrouillon' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'draft'
            ),
            'annoncesModeration' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'moderation'
            ),
            'annoncesRejetees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'trash'
            ),
            'annoncesPubliees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'publish'
            ),
            'annoncesReserves' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'reserved'
            ),
            'annoncesTerminees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'ended'
            ),
            'annoncesAnnulees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'drop'
            ),
            'devisEnAttente' => $this->service_manager->readListDevisDataOfUser(
                $userId,
                'devis-pending'
            ),
            'devisEnBrouillon' => $this->service_manager->readListDevisDataOfUser(
                $userId,
                'devis-draft'
            ),

            // For clients reservations
            'reservationsEnCours' => $this->service_manager->readListReservationOfUser(
                $userId,
                'wc-in-progress'
            ),
            'reservationsTerminees' => $this->service_manager->readListReservationOfUser(
                $userId,
                'wc-completed'
            ),
            'reservationsAnnulees' => $this->service_manager->readListReservationOfUser(
                $userId,
                'wc-cancelled'
            ),
            'reservationsDevisEnAttente' => $this->service_manager->readListReservationDevisOfUser(
                $userId,
                'devis-pending'
            ),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/annonces/publier_annonce", name="ajouter_annonce",methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function ajouterAnnonce(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $uid = 0;
        if ($request->get('user_id')) {
            $uid = $request->get('user_id');
            $u = $this->service_manager->userById($uid);
        } else {
            $u = $this->getUser();
            $uid = $u->getId();
        }
        $date = new DateTime();
        $id = 0;
        $state = $request->get('state');
        if ($request->get('titre') != '') {
            $ids = explode(', ', $request->get('titre'));
            $name = $ids[0];
            for ($i = 1; $i < sizeof($ids); $i++) {
                $name = $name . '-' . $ids[$i];
            }
            $idc = 0;
            if ($request->get('souscategorie') > 0) {
                $idc = $request->get('souscategorie');
            } else {
                $idc = $request->get('categorie');
            }
            //Pour la creation complete
            $oldImages = 0;

            if ($state == 'creation') {
                if ($request->get('idPostEdited')) {
                    $oldImages = $this->em->getRepository(WpPosts::class)
                        ->findBy(['postParent' => $request->get('idPostEdited'), 'postType' => 'attachment']);
                    $oldAnnounce = $this->em->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                    $this->em->remove($oldAnnounce);
                    $this->em->flush();
                }
                $id = $this->service_manager->createPosts(
                    $uid,
                    $date,
                    $date,
                    $request->get('description'),
                    $request->get('titre'),
                    '',
                    'moderation',
                    'open',
                    'closed',
                    '',
                    $this->service_manager->slugify($request->get('titre')),
                    '',
                    '',
                    $date,
                    $date,
                    '',
                    0,
                    $request->get('titre'),
                    0,
                    'product',
                    '',
                    0,
                    $idc,
                    $request->getLocale()
                );
            }
            $titre = '';
            $description = '';
            if (empty($request->get('titre'))) {
                $titre = '--';
            } else {
                $titre = $request->get('titre');
            }
            if (empty($request->get('description'))) {
                $description = '--';
            } else {
                $description = $request->get('description');
            }

            if ($state == 'edition') {
                $oldAnnounce = $this->em->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                $this->em->remove($oldAnnounce);
                $this->em->flush();
                $statut = 'moderation';
                if ($request->get('sender') == 'admin') {
                    $statut = $request->get('status');
                }
                $id = $this->service_manager->createPosts(
                    $uid,
                    $date,
                    $date,
                    $description,
                    $titre,
                    '',
                    $statut,
                    'open',
                    'closed',
                    '',
                    $this->service_manager->slugify($titre),
                    '',
                    '',
                    $date,
                    $date,
                    '',
                    0,
                    $titre,
                    0,
                    'product',
                    '',
                    0,
                    $idc,
                    $request->getLocale()
                );
            }

            $DetailsAnnonce = null;
            if ($state == 'edition_admin') {
                $statut = $request->get('status');
                $id = $this->service_manager->updatePostsAdsContent(
                    $request->get('idPostEdited'),
                    $uid,
                    $date,
                    $date,
                    $description,
                    $titre,
                    $titre,
                    $statut,
                    'open',
                    'closed',
                    $this->service_manager->slugify($titre),
                    $this->service_manager->slugify($titre),
                    '',
                    '',
                    $date,
                    $date,
                    $description,
                    0,
                    $titre,
                    0,
                    'product',
                    '',
                    0,
                    $idc,
                    $request->getLocale()
                );
            }

            // Sauvegarde brouillon
            if ($state == 'brouillon') {
                if ($request->get('idPostEdited') and $request->get('souscategorie') > 0) {
                    $oldAnnounce = $this->em->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                    $this->em->remove($oldAnnounce);
                    $this->em->flush();

                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'draft',
                        'open',
                        'closed',
                        '',
                        $this->service_manager->slugify($titre),
                        '',
                        '',
                        $date,
                        $date,
                        '',
                        0,
                        $titre,
                        0,
                        'product',
                        '',
                        0,
                        $idc,
                        $request->getLocale()
                    );
                } elseif ($request->get('idPostEdited') and $request->get('souscategorie') == 'devis') {
                    $oldAnnounce = $this->em->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                    $this->em->remove($oldAnnounce);
                    $this->em->flush();

                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'devis-en-brouillon',
                        'open',
                        'closed',
                        '',
                        $this->service_manager->slugify($titre),
                        '',
                        '',
                        $date,
                        $date,
                        '',
                        0,
                        $titre,
                        0,
                        'devis',
                        '',
                        0,
                        $idc,
                        $request->getLocale()
                    );
                } elseif ($request->get('souscategorie') == 'devis') {
                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'devis-en-brouillon',
                        'open',
                        'closed',
                        '',
                        $this->service_manager->slugify($titre),
                        '',
                        '',
                        $date,
                        $date,
                        '',
                        0,
                        $titre,
                        0,
                        'devis',
                        '',
                        0,
                        $idc,
                        $request->getLocale()
                    );
                } else {
                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'draft',
                        'open',
                        'closed',
                        '',
                        $this->service_manager->slugify($titre),
                        '',
                        '',
                        $date,
                        $date,
                        '',
                        0,
                        $titre,
                        0,
                        'product',
                        '',
                        0,
                        $idc,
                        $request->getLocale()
                    );
                }
            }
            // meta post
            if ($state == 'edition_admin') {
                if (!empty($request->get('prix'))) {
                    $meta = $this->service_manager->readPostMeta($id, '_price');
                    if ($meta) {
                        $this->service_manager->updatePostMeta(
                            $meta->getMetaId(),
                            $id,
                            '_price',
                            $request->get('prix'),
                            $request->getLocale()
                        );
                    } else {
                        $this->service_manager->createPostMeta(
                            $id,
                            '_price',
                            $request->get('prix'),
                            $request->getLocale()
                        );
                    }
                }
            }
            if (!empty($request->get('pays'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_country',
                    $request->get('pays'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('adresse_postale'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_adress',
                    $request->get('adresse_postale'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('code_postal'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_code_postal',
                    $request->get('code_postal'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('precision'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_precision',
                    $request->get('precision'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('ville'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_city',
                    trim($request->get('ville')),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('bureau'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_has_equipments_bureau',
                    1,
                    $request->getLocale()
                );
            }
            if (!empty($request->get('wifi'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_has_equipments_wifi',
                    1,
                    $request->getLocale()
                );
            }
            if (!empty($request->get('cafe'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_has_equipments_cofe',
                    1,
                    $request->getLocale()
                );
            }
            if (!empty($request->get('autre_equipement'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_other_equipments',
                    $request->get('autre_equipement'),
                    $request->getLocale()
                );
            }
            if ($request->get('portfolio') && sizeof($request->get('portfolio')) > 0) {
                $tabeauImages = $this->trierTableau(
                    $request->get('portfolio')
                );

                $this->service_manager->createPostMeta(
                    $id,
                    '_product_image_gallery',
                    implode(',', $tabeauImages),
                    $request->getLocale()
                );
            }
            //Upload Image Announce
            if ($request->files->get('files_annonce')) {
                $idImg = $this->service_manager->imagesAnnoncesUpload($request->files->get('files_annonce'), $this->getParameter('announces_directory'), $id);
                $portImg = $this->service_manager->readPostMeta($id, 'images_annonces');
                if ($portImg) {
                    $idImg = $idImg . ',' . $portImg->getMetaValue();
                }
                $images_annonce = $this->service_manager->createPostMeta($id, 'images_annonces', $idImg, $request->getLocale());
            } else {
                if ($oldImages != 0) {
                    $imgConcat = [];
                    foreach ($oldImages as $key => $value) {
                        $imgConcat[] = $value["id"];
                    }
                    $images_annonce = $this->service_manager->createPostMeta($id, 'images_annonces', implode(',', $imgConcat), $request->getLocale());
                }
            }
            // End Upload Image Announce
            if ($request->get('videos') && sizeof($request->get('videos')) > 0) {
                $tabeauVideos = $this->trierTableau(
                    $request->get('videos')
                );
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_video',
                    @serialize($request->get('videos')),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('devise'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_devise',
                    $request->get('devise'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('prix'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_price',
                    $request->get('prix'),
                    $request->getLocale()
                );
            }

            if ($request->get('sender') == 'admin') {
                // cas ou c'est l'admin qui ajoute une annonce

                $video = $this->service_manager->readUserMeta($uid, 'video');

                if ($video && $request->get('new_vid')[0] != '') {
                    if (sizeof(@unserialize($video->getMetaValue())) > 0) {
                        $tabeauVideos = @unserialize($video->getMetaValue());
                        $tabeauVideos = $this->trierTableau($tabeauVideos);
                        $this->service_manager->updateUserMeta(
                            $uid,
                            'video',
                            @serialize(
                                array_merge(
                                    $tabeauVideos,
                                    $this->trierTableau(
                                        $request->get('new_vid')
                                    )
                                )
                            )
                        );
                    } else {
                        $this->service_manager->updateUserMeta(
                            $uid,
                            'video',
                            @serialize(
                                $this->trierTableau(
                                    $this->trierTableau(
                                        $request->get('new_vid')
                                    )
                                )
                            )
                        );
                    }
                } elseif ($request->get('new_vid')[0] != '') {
                    $this->service_manager->updateUserMeta(
                        $uid,
                        'video',
                        @serialize(
                            $this->trierTableau($request->get('new_vid'))
                        )
                    );
                }
                if ($request->get('new_vid')[0] != '') {
                    $imgid = array();

                    $video = $request->get('new_vid');
                    for ($i = 0; $i < sizeof($video); $i++) {
                        $imgid[$i] = $this->service_manager->getYouTubeId(
                            $video[$i]
                        );
                    }
                    $tabeauVideos = $imgid;
                    $this->service_manager->createPostMeta(
                        $id,
                        '_product_video',
                        @serialize($tabeauVideos),
                        $request->getLocale()
                    );
                }
                //portfolio
                if ($request->files->get('file')) {
                    $file = $request->files->get('file');
                    $idp = $this->service_manager->portfolio(
                        $file,
                        $this->getParameter('portfolio_directory'),
                        $uid
                    );
                    $port = $this->service_manager->readUserMeta(
                        $uid,
                        'portfolio'
                    );
                    $idpt = $idp;

                    if ($port && $port->getMetaValue() != '') {
                        $idp = $idp . ',' . $port->getMetaValue();
                    }
                    if ($idp) {
                        $this->service_manager->updateUserMeta(
                            $uid,
                            'portfolio',
                            $idp
                        );
                    } // code...

                    $tabeauImages = $idpt;

                    $this->service_manager->createPostMeta(
                        $id,
                        '_product_image_gallery',
                        $tabeauImages,
                        $request->getLocale()
                    );
                } // code...
            }
        }
        //Notifiations Emails
        $detailsAnnonce = $this->em->getRepository(WpPosts::class)->find($id);
        if ($detailsAnnonce->getPostStatus() == 'moderation' || $detailsAnnonce->getPostStatus() == 'publish' || $detailsAnnonce->getPostStatus() == 'draft' ||
            $detailsAnnonce->getPostStatus() == 'trash') {
            $setEmailSubject = '';
            $statut_annonce = '';
            $email_code = '';
            if ($detailsAnnonce->getPostStatus() == 'moderation') {
                $statut_annonce = 'Modération';
                $setEmailSubject = 'Création d\'annonce sur Trust & Market';
                $email_code = 27;
            } elseif ($detailsAnnonce->getPostStatus() == 'publish') {
                $statut_annonce = 'Publiée';
                $setEmailSubject = 'Nouvelle annonce publiée sur Trust & Market';
                $email_code = 28;
            } elseif ($detailsAnnonce->getPostStatus() == 'draft') {
                $statut_annonce = 'En brouillon';
                $setEmailSubject = 'Création d\'annonce sur Trust & Market';
                $email_code = 27;
            } elseif ($detailsAnnonce->getPostStatus() == 'trash') {
                $statut_annonce = 'Rejetée';
                $setEmailSubject = 'Annonce rejetée sur Trust & Market';
                $email_code = 29;
            }

            $data = [
                'to' => [
                    [
                        'email' => $u->getEmailCanonical(),
                        'name' => $u->getDisplayName(),
                    ]
                ],
                'bcc' => [
                    [
                        'email' => 'serviceclients@kbr-global.com',
                        'name' => "Trust & Market"
                    ]
                ],
                'templateId' => $email_code,
                'params' => [
                    "titre_annonce" => $detailsAnnonce->getPostTitle(),
                    "email" => $u->getEmailCanonical(), "statut_annonce" => $statut_annonce
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
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);

        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/annonces/editer_annonce", name="editer_annonce",methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function editerAnnonce(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($request->get('user_id')) {
            $this->service_manager->userById($request->get('user_id'));
        }
        $id = 1;
        $idc = 0;
        $annonce = null;
        $state = $request->get('state');
        if ($request->get('titre') != '') {
            $ids = explode(', ', $request->get('titre'));
            $name = $ids[0];
            for ($i = 1; $i < sizeof($ids); $i++) {
                $name = $name . '-' . $ids[$i];
            }
            if ($request->get('souscategorie') > 0) {
                $idc = $request->get('souscategorie');
            } else {
                $idc = $request->get('categorie');
            }

            $titre = '';
            $description = '';
            if (empty($request->get('titre'))) {
                $titre = '--';
            } else {
                $titre = $request->get('titre');
            }
            if (empty($request->get('description'))) {
                $description = '--';
            } else {
                $description = $request->get('description');
            }

            if ($state == 'edition') {
                $statut = 'moderation';
                $annonce = $this->em->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                $id = $annonce->getId();
                if ($request->get('sender') == 'admin') {
                    $statut = $request->get('status');
                }
                if ($idc > 0) {
                    $query = $this->em->createQuery('DELETE FROM App\Entity\WpTermRelationships p WHERE p.objectId =:id')->setParameter('id', $id);
                    $query->getResult();

                    $relationPost_SousCategorie = new WpTermRelationships();
                    $relationPost_SousCategorie->setTermTaxonomyId($idc);
                    $relationPost_SousCategorie->setObjectId($id);

                    $this->em->persist($relationPost_SousCategorie);
                    $this->em->flush();
                }
                if ($request->getLocale() == 'en') {
                    $annonce->setPostExcerpt($titre);
                    $annonce->setPostContentFiltered($description);
                    $annonce->setTranslatableLocale($request->getLocale());
                } else {
                    $annonce->setPostTitle($titre);
                    $annonce->setPostContent($description);
                    //Generate unique name
                    $size = sizeof($this->service_manager->readPostsByName($annonce->getPostName()));
                    if ($size > 1) {
                        $annonce->setPostName($this->service_manager->slugify($titre) . '-' . $annonce->getId());
                    }
                    //End Generate unique name
                }
                $annonce->setPostStatus('moderation');
                $this->em->persist($annonce);
                $this->em->flush();
                //End store data
            } elseif ($state == 'brouillon') {
                $statut = 'draft';
                $annonce = $this->em->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                $id = $annonce->getId();
                if ($request->get('sender') == 'admin') {
                    $statut = $request->get('status');
                }
                if ($idc > 0) {
                    $query = $this->em->createQuery('DELETE FROM App\Entity\WpTermRelationships p WHERE p.objectId =:id')->setParameter('id', $id);
                    $r = $query->getResult();
                    $relationPost_SousCategorie = new WpTermRelationships();
                    $relationPost_SousCategorie->setTermTaxonomyId($idc);
                    $relationPost_SousCategorie->setObjectId($id);

                    $this->em->persist($relationPost_SousCategorie);
                    $this->em->flush();
                }
                if ($request->getLocale() == 'en') {
                    $annonce->setPostExcerpt($titre);
                    $annonce->setPostContentFiltered($description);
                    $annonce->setTranslatableLocale($request->getLocale());
                } else {
                    $annonce->setPostTitle($titre);
                    $annonce->setPostContent($description);
                    $annonce->setPostName($this->service_manager->slugify($titre));
                }
                $annonce->setPostStatus($statut);

                $this->em->persist($annonce);
                $this->em->flush();
                //End store data
            }
            $portImg = $this->service_manager->readPostMeta($id, 'images_annonces');
            //Delete all other meta
            $query = $this->em->createQuery('DELETE FROM App\Entity\WpPostmeta p WHERE p.postId =:id')->setParameter('id', $id);
            $query->getResult();
            //End delete
            //Create new one
            if (!empty($request->get('pays'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_country',
                    $request->get('pays'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('adresse_postale'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_adress',
                    $request->get('adresse_postale'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('code_postal'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_code_postal',
                    $request->get('code_postal'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('precision'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_precision',
                    $request->get('precision'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('ville'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_city',
                    trim($request->get('ville')),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('bureau'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_has_equipments_bureau',
                    1,
                    $request->getLocale()
                );
            }
            if (!empty($request->get('wifi'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_has_equipments_wifi',
                    1,
                    $request->getLocale()
                );
            }
            if (!empty($request->get('cafe'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_has_equipments_cofe',
                    1,
                    $request->getLocale()
                );
            }
            if (!empty($request->get('autre_equipement'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_other_equipments',
                    $request->get('autre_equipement'),
                    $request->getLocale()
                );
            }
            //portfolio
            if ($request->get('portfolio') && sizeof($request->get('portfolio')) > 0) {
                $tabeauImages = $this->trierTableau($request->get('portfolio'));
                $this->service_manager->createPostMeta($id, '_product_image_gallery', implode(',', $tabeauImages), $request->getLocale());
            }
            //Upload Image Announce
            if ($request->files->get('files_annonce')) {
                $idImg = $this->service_manager->imagesAnnoncesUpload($request->files->get('files_annonce'), $this->getParameter('announces_directory'), $id);
                if ($portImg) {
                    $idImg = $idImg . ',' . $portImg->getMetaValue();
                }
                $this->service_manager->createPostMeta($id, 'images_annonces', $idImg, $request->getLocale());
            } elseif ($portImg) {
                $this->service_manager->createPostMeta($id, 'images_annonces', $portImg->getMetaValue(), $request->getLocale());
            }
            // End Upload Image Announce

            if ($request->get('videos') && sizeof($request->get('videos')) > 0) {
                $this->trierTableau($request->get('videos'));
                $this->service_manager->createPostMeta($id, '_product_video', @serialize($request->get('videos')), $request->getLocale());
            }
            if (!empty($request->get('devise'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_product_devise',
                    $request->get('devise'),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('prix'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    '_price',
                    $request->get('prix'),
                    $request->getLocale()
                );
            }
        }

        //Notifiations Emails
        $statut_annonce = '';
        $email_code = '';
        if ($annonce->getPostStatus() == 'moderation') {
            $statut_annonce = 'Modération';
            $email_code = 27;
        } elseif ($annonce->getPostStatus() == 'publish') {
            $statut_annonce = 'Publiée';
            $email_code = 28;
        } elseif ($annonce->getPostStatus() == 'draft') {
            $statut_annonce = 'En brouillon';
            $email_code = 27;
        }

        $data = [
            'to' => [
                [
                    'email' => $this->getUser()->getEmailCanonical(),
                    'name' => $this->getUser()->getDisplayName(),
                ]
            ],
            'bcc' => [
                [
                    'email' => 'serviceclients@kbr-global.com',
                    'name' => "Trust & Market"
                ]
            ],
            'templateId' => $email_code,
            'params' => [
                "titre_annonce" => $annonce->getPostTitle(),
                "email" => $this->getUser()->getEmailCanonical(),
                "statut_annonce" => $statut_annonce
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
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
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
                        'email' => "serviceclients@kbr-global.com",
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
                        'email' => "serviceclients@kbr-global.com",
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
}
