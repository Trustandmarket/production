<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\OffreInterne;
use App\Entity\User;
use App\Entity\WpOptions;
use App\Entity\WpUsermeta;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfileProfessionalController extends AbstractController
{
    private $service_manager;
    private $em;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $em
    ) {
        $this->service_manager = $service_manager;
        $this->em = $em;
    }

    /**
     * Show seller details page
     * @Route("/{_locale}/profil-utilisateur/fournisseurs", name="fournisseurs")
     */
    public function fournisseurs()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('profile_fournisseursAbonne');
        }

        $typeCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_type');
        $nomCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_name');
        $adresseDetenteur = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_address1');
        $villeCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_city');
        $codePostaleCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_postcode');
        $paysCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_country');
        $regionCompte = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'vendor_account_region');

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

        $identite = null;
        $enregistrement = null;
        $statuts = null;
        $shareholder = null;
        $bankUserId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_id_sandbox');

        return $this->render('profile/fournisseurs.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'typeCompte' => $typeCompte,
            'nomCompte' => $nomCompte,
            'adresseDetenteur' => $adresseDetenteur,
            'villeCompte' => $villeCompte,
            'codePostaleCompte' => $codePostaleCompte,
            'paysCompte' => $paysCompte,
            'regionCompte' => $regionCompte,
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
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles()) || in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('profile_fournisseurs');
        }
        return $this->render('profile/fournisseursAbonne.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'numeroNomRue' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_address_1'),
            'ville' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_city'),
            'codePostal' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_postcode'),
            'etatComte' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_state'),
            'siret' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'siret'),
            'nomEntreprise' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_company'),
            'pays' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_country'),
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
    public function devenirPro(Request $request)
    {
        try {
            $mangoPayAccount = $this->em
                ->getRepository(WpUsermeta::class)
                ->findOneBy([
                    'userId' => $this->getUser()->getId(),
                    'metaKey' => 'mp_user_id_sandbox',
                ]);

            $parameters = json_decode($request->getContent());
            if (!$parameters) {
                return new JsonResponse(['status' => 400, 'error' => 'Payload JSON invalide.']);
            }

            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'siret', $parameters->compagny_number);
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_company', $parameters->compagny_name);
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_country', $parameters->pays);
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_address_1', $parameters->adresse);
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_postcode', $parameters->postal_code);
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_city', $parameters->ville);
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'billing_state', trim($parameters->region));

            $user_new_role = $parameters->user_new_role;
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
                if ($mangoPayAccount) {
                    $this->em->remove($mangoPayAccount);
                    $this->em->flush();
                }
                $this->service_manager->devenirPro($this->getUser()->getId(), $user_new_role);
                return new JsonResponse(['data' => null, 'status' => 200]);
            }

            if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
                $this->service_manager->devenirPro(
                    $this->getUser()->getId(),
                    'ROLE_SOCIETE'
                );
                return new JsonResponse(['data' => null, 'status' => 200]);
            }
        } catch (\Throwable $e) {
            error_log('[profile_app_switch] ' . $e->getMessage());
            return new JsonResponse([
                'status' => 500,
                'error' => 'Le changement de profil est temporairement indisponible. Contactez le support si le problème persiste.',
            ], 500);
        }

        return new JsonResponse(['status' => 400, 'error' => 'Changement de profil impossible.'], 400);
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
        $mpAccount = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
        $stripePersonAccount = $this->service_manager->getUserStringDataValue($userId, 'stripe_person_user');

        $userType = '';
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
                'result' => 11,
                'error' => 'Des informations nécessaires sont introuvables, mettez à jour votre profil.'
            ]);
        }

        $data = $this->getDataToUpdateMangopayUser();

        $existingCards = null;
        $card = null;
        $this->service_manager->updateUserMeta($userId, 'vendor_account_type', $request->get('bank'));
        $this->service_manager->updateUserMeta($userId, 'vendor_account_name', $request->get('accountHolder'));
        $this->service_manager->updateUserMeta($userId, 'vendor_account_address1', $request->get('addressHolder'));
        $this->service_manager->updateUserMeta($userId, 'vendor_account_city', $request->get('cityHolder'));
        $this->service_manager->updateUserMeta($userId, 'vendor_account_postcode', $request->get('codePostalHolder'));
        $this->service_manager->updateUserMeta($userId, 'vendor_account_country', $request->get('countryHolder'));
        $this->service_manager->updateUserMeta($userId, 'vendor_account_region', $request->get('regionHolder'));

        if ($request->get('bank') == 'IBAN') {
            $userData = $this->getDataToUpdateMangopayUser();
            $accountNumber = $request->get('iban');
            $detailsBic = $request->get('bic');
        }
        return new JsonResponse([
            'result' => 1, 'existingCards' => $existingCards, 'card' => $card
        ]);
    }

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
        $userType = '';
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())) {
            $userType = 'ROLE_AUTO_ENTREPRENEUR';
        }
        if (in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            $userType = 'ROLE_SOCIETE';
        }
        $this->service_manager->updateUserMeta($userId, 'tva', $request->get('tva'));
        $this->service_manager->updateUserMeta($userId, 'siret', $request->get('siret'));
        $this->service_manager->updateUserMeta($userId, 'billing_company', $request->get('nomEntreprise'));
        $this->service_manager->updateUserMeta($userId, 'billing_country', $request->get('pays'));
        $this->service_manager->updateUserMeta($userId, 'billing_address_1', $request->get('numeroNomRue'));
        $this->service_manager->updateUserMeta($userId, 'billing_postcode', $request->get('codePostal'));
        $this->service_manager->updateUserMeta($userId, 'billing_city', $request->get('ville'));
        $this->service_manager->updateUserMeta($userId, 'billing_state', trim($request->get('etatComte')));
        $this->service_manager->updateUserMeta($userId, 'billing_phone', $request->get('telephone'));
        $this->service_manager->updateUserMeta($userId, 'billing_email', $request->get('email'));
        $user_nationality = $this->service_manager->getUserStringDataValue($userId, 'vendor_account_country');

        $data = $this->getDataToUpdateMangopayUser();

        if ($request->get('doc') == 'identite' && !is_null($request->files->get('fileDoc'))) {
        }
        if ($request->get('doc') == 'enregistrement' && !is_null($request->files->get('fileDoc'))) {
        }
        if ($request->get('doc') == 'kbis' && !is_null($request->files->get('fileDoc'))) {
        }
        if ($request->get('doc') == 'statuts' && !is_null($request->files->get('fileDoc'))) {
        }
        if ($request->get('doc') == 'shareholder' && !is_null($request->files->get('fileDoc'))) {
        }

        return new JsonResponse([
            'result' => 1,
            'stripeUserUpdate' => $stripePersonData,
            'datas' => $stripeData,
            'token' => $accountToken,
            'updateStripeData' => $updateStripeData
        ]);
    }

    private function getDataToUpdateMangopayUser()
    {
        $keys = [
            'first_name', 'last_name', 'telephone', 'sexe', 'billing_email', 'residenceCountry',
            'nationalityCountry', 'mp_user_id_sandbox', 'bdaytime', 'numeroNomRue_domicile',
            'pays_domicile', 'codePostal_domicile', 'telephone',
            'ville_domicile', 'region_domicile', 'numeroNomRue_livraison', 'pays_livraison',
            'codePostal_livraison', 'ville_livraison', 'region_livraison',
            'siret', 'tva', 'billing_address_1', 'billing_company', 'billing_country', 'billing_postcode',
            'billing_state', 'billing_city', 'billing_phone', 'billing_email', 'nom_commercial',
            'vendor_account_type', 'vendor_account_name', 'vendor_account_address1', 'vendor_account_city', 'vendor_account_postcode', 'vendor_account_country',
            'vendor_account_region'
        ];

        $userMetadata = $this->service_manager->getUserMetadata($this->getUser()->getId(), $keys);

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
            "region" => $userMetadata['region_domicile'] ?? '',
            "region_domicile" => $userMetadata['region_domicile'] ?? '',
            "user_address_1_livraison" => $userMetadata['numeroNomRue_livraison'] ?? '',
            "user_country_livraison" => $userMetadata['pays_livraison'] ?? '',
            "user_postcode_livraison" => $userMetadata['codePostal_livraison'] ?? '',
            "user_city_livraison" => $userMetadata['ville_livraison'] ?? '',
            "region_livraison" => $userMetadata['region_livraison'] ?? '',
            "siret" => $userMetadata['siret'] ?? '',
            "tva" => $userMetadata['tva'] ?? '',
            "compagny_name" => $userMetadata['billing_company'] ?? '',
            "billing_company" => $userMetadata['billing_company'] ?? '',
            "billing_address_1" => $userMetadata['billing_address_1'] ?? '',
            "billing_country" => $userMetadata['billing_country'] ?? '',
            "billing_postcode" => $userMetadata['billing_postcode'] ?? '',
            "billing_state" => $userMetadata['billing_state'] ?? '',
            "billing_city" => $userMetadata['billing_city'] ?? '',
            "billing_phone" => $userMetadata['billing_phone'] ?? '',
            "billing_email" => $userMetadata['billing_email'] ?? '',
            "nom_commercial" => $userMetadata['nom_commercial'] ?? '',
            "vendor_account_type" => $userMetadata['vendor_account_type'] ?? '',
            "vendor_account_name" => $userMetadata['vendor_account_name'] ?? '',
            "vendor_account_address1" => $userMetadata['vendor_account_address1'] ?? '',
            "vendor_account_city" => $userMetadata['vendor_account_city'] ?? '',
            "vendor_account_postcode" => $userMetadata['vendor_account_postcode'] ?? '',
            "vendor_account_country" => $userMetadata['vendor_account_country'] ?? '',
            "vendor_account_region" => $userMetadata['vendor_account_region'] ?? '',
        ];

        if (empty($data["billing_email"])) {
            $data["billing_email"] = $data["user_email"];
        }

        return $data;
    }
}
