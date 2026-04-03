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
        $currentActivityId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'activite_principale');

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

        return $this->render('profile/fournisseurs.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
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
            'activities' => $this->service_manager->postCategorie1('product_activity'),
            'current_activity_id' => $currentActivityId,
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
            'activities' => $this->service_manager->postCategorie1('product_activity'),
            'current_activity_id' => $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'activite_principale'),
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
            if (empty(trim((string) ($parameters->activite ?? '')))) {
                return new JsonResponse(['status' => 400, 'error' => 'Veuillez sélectionner votre activité principale.'], 400);
            }
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'activite_principale', trim((string) $parameters->activite));

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
     * @Route("/{_locale}/profil-utilisateur/update/billing", name="updateBillingProfileData")
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function updateBillingProfileData(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = $this->getUser()->getId();

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

        return new JsonResponse([
            'result' => 1,
            'message' => 'Informations mises a jour.'
        ]);
    }

}
