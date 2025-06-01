<?php

namespace App\Controller;

use App\Entity\WpOptions;
use App\Entity\WpPosts;
use App\Entity\User;
use App\Service\Panier;
use App\Service\Payment;
use App\Service\ServiceManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\ToolsMeta;

/**
 * @Route("/paiement", requirements={"_locale": "fr"}, name="payment_")
 */
class PaymentController extends AbstractController
{
    private $tools;
    private $payment;
    private $panier;
    private $service_manager;
    private $em;

    /**
     * PaymentController constructor.
     * @param ServiceManager $service_manager
     * @param ToolsMeta $tools
     * @param Payment $payment
     * @param Panier $panier
     * @param EntityManagerInterface $em
     */
    public function __construct(
        ServiceManager $service_manager,
        ToolsMeta $tools,
        Payment $payment,
        Panier $panier,
        EntityManagerInterface $em
    )
    {
        $this->service_manager = $service_manager;
        $this->tools = $tools;
        $this->payment = $payment;
        $this->panier = $panier;
        $this->em = $em;
    }

    /**
     * @Route("/{_locale}", name="payment")
     */
    public function index()
    {
        $mangopayUserId = "";
        $userWallets = "";
        $userId = $this->getUser()->getId();
        $userData = $this->service_manager->getAllUserdata($this->getUser()->getId());
        $userVendeur = $this->service_manager->getAllUserdata($this->panier->listeProduit()['0']['userId']);
        $description = '';
        $walletId = '';
        //Adresse de residence
        $prenom_domicile = $this->service_manager->getUserStringDataValue($userId, 'prenom_domicile');
        $nom_domicile = $this->service_manager->getUserStringDataValue($userId, 'nom_domicile');
        $nomEntreprise_domicile = $this->service_manager->getUserStringDataValue($userId, 'nomEntreprise_domicile');
        $pays_domicile = $this->service_manager->getUserStringDataValue($userId, 'pays_domicile');
        $numeroNomRue_domicile = $this->service_manager->getUserStringDataValue($userId, 'numeroNomRue_domicile');
        $codePostal_domicile = $this->service_manager->getUserStringDataValue($userId, 'codePostal_domicile');
        $ville_domicile = $this->service_manager->getUserStringDataValue($userId, 'ville_domicile');
        //Adresse de livraison
        $prenom_livraison = $this->service_manager->getUserStringDataValue($userId, 'prenom_livraison');
        $nom_livraison = $this->service_manager->getUserStringDataValue($userId, 'nom_livraison');
        $nomEntreprise_livraison = $this->service_manager->getUserStringDataValue($userId, 'nomEntreprise_livraison');
        $pays_livraison = $this->service_manager->getUserStringDataValue($userId, 'pays_livraison');
        $numeroNomRue_livraison = $this->service_manager->getUserStringDataValue($userId, 'numeroNomRue_livraison');
        $codePostal_livraison = $this->service_manager->getUserStringDataValue($userId, 'codePostal_livraison');
        $ville_livraison = $this->service_manager->getUserStringDataValue($userId, 'ville_livraison');

        if (in_array('ROLE_ABONNE', $this->getUser()->getRoles())) {
            $description = 'Abonne';
            //Données utiles
            $prenom = $this->service_manager->getUserStringDataValue($userId, 'first_name');
            $nom = $this->service_manager->getUserStringDataValue($userId, 'last_name');
            $pays = 'FR';

            $email = $this->getUser()->getEmailCanonical();
            $birthday = $this->service_manager->getUserStringDataValue($userId, 'bdaytime');
            $user_nationality = 'Fr';
            $countryresidence = $this->service_manager->getUserStringDataValue($userId, 'region');
            $user_nationality = $this->service_manager->getUserStringDataValue($userId, 'nationalityCountry');
            $pays = $this->service_manager->getUserStringDataValue($userId, 'residenceCountry');
            if ($user_nationality == '' && $pays != '') {
                $user_nationality = $pays;
            }
            if ($user_nationality == '' && $pays == '') {
                $user_nationality = 'FR';
                $pays = 'FR';
            }
            //Create user if not exist
            $mangopayUserId = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
            if ($prenom == '' || $nom == '' || $birthday == '' || $user_nationality == '' || $pays == '') {
                $this->addFlash('notice', 'Vous devriez mettre a jour votre profil pour procéder aux transactions.');
                return $this->redirectToRoute('home_profil');
            }

            if ($mangopayUserId == '') {
                try {
                    $userM = $this->payment->createMangoUser(
                        'UserNatural',
                        '',
                        $prenom,
                        $nom,
                        strtotime($birthday),
                        $user_nationality,
                        $pays,
                        $email
                    );
                    $mangopayUserId = $userM->Id;
                    $idUserMangopay = $this->service_manager->updateUserMeta($userId, 'mp_user_id_sandbox', $userM->Id);
                } catch (Exception $e) {
                    $userM == '';
                }
            }
            //Check if user has wallet
            $walletId = $this->payment->getOrCreateUserWallet($mangopayUserId, $description);
            //Make PayIn
        }
        if (
            in_array('ROLE_SOCIETE', $this->getUser()->getRoles()) ||
            in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())
        ) {
            $description = 'Professionnel';
            $userId = $this->getUser()->getId();
            $userData = $this->service_manager->getAllUserdata($this->getUser()->getId());
            $walletId = '';
            //Données utiles
            $prenom = $this->service_manager->getUserStringDataValue($userId, 'first_name');
            $nom = $this->service_manager->getUserStringDataValue($userId, 'last_name');
            $pays = $this->service_manager->getUserStringDataValue($userId, 'nationalityCountry');
            //$this->service_manager->getUserStringDataValue($userId, 'billing_country');
            $email = $this->getUser()->getEmailCanonical();
            $birthday = $this->service_manager->getUserStringDataValue($userId, 'bdaytime');
            $nationality = $this->service_manager->getUserStringDataValue($userId, 'residenceCountry');

            if ($nationality == '') {
                $nationality = 'FR';
            }
            $countryresidence = $this->service_manager->getUserStringDataValue($userId, 'residenceCountry');
            if ($countryresidence == '') {
                $countryresidence = 'FR';
            }
            if (
                $prenom == '' || $nom == '' || $birthday == '' ||
                $nationality == '' || $pays == ''
            ) {
                $this->addFlash(
                    'notice',
                    'Vous devriez mettre a jour votre profil fournisseur pour procéder aux transactions.'
                );
                return $this->redirectToRoute('profile_fournisseurs');
            }
            //Create user if not exist
            $mangopayUserId = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
            if ($mangopayUserId == '') {
                try {
                    $userM = $this->payment->createMangoUser(
                        'UserLegal',
                        'Soletrader',
                        $prenom,
                        $nom,
                        strtotime($birthday),
                        $nationality,
                        $countryresidence,
                        $email
                    );
                    $mangopayUserId = $userM->Id;
                    $idUserMangopay = $this->service_manager->updateUserMeta($userId, 'mp_user_id_sandbox', $userM->Id);
                } catch (Exception $e) {
                    $userM == '';
                }
            }
            //Check if user has wallet
/*            dd($mangopayUserId . ' / ' . $description);
            $walletId = $this->payment->getOrCreateUserWallet($mangopayUserId, $description);*/
        }
        //Create user wallet if not exist
        $criteresLivraisons = [];
        if ($this->panier->MontantGlobalProduits() > 0) {
            $criteresLivraisons = $this->service_manager->getDeliveryType($this->panier->MontantGlobalProduits());
        }
        //dd(str_replace(' ', '', $this->panier->MontantGlobal()));
        return $this->render('payment/index.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'userData' => $userData, 'panierData' => $this->panier->listeProduit(),
            'prixTotal' => str_replace(' ', '', $this->panier->MontantGlobal()),
            'prixTotalProduits' => str_replace(' ', '', $this->panier->MontantGlobalProduits()),
            'idUser' => $mangopayUserId, 'vendeur' => $userVendeur,
            'prenom_domicile' => $prenom_domicile, 'prenom_livraison' => $prenom_livraison,
            'nom_domicile' => $nom_domicile, 'nom_livraison' => $nom_livraison,
            'nomEntreprise_domicile' => $nomEntreprise_domicile, 'nomEntreprise_livraison' => $nomEntreprise_livraison,
            'pays_domicile' => $pays_domicile, 'pays_livraison' => $pays_livraison,
            'numeroNomRue_domicile' => $numeroNomRue_domicile, 'numeroNomRue_livraison' => $numeroNomRue_livraison,
            'codePostal_domicile' => $codePostal_domicile, 'codePostal_livraison' => $codePostal_livraison,
            'ville_domicile' => $ville_domicile, 'ville_livraison' => $ville_livraison, 'criteresLivraisons' => $criteresLivraisons,
            'criteresFreeOps' => $this->service_manager->getAllActiveOpsFree(),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
        ]);
    }

    /**
     * @Route("/commande/{_locale}", name="pre_command",methods={"POST"})
     * @param Request $request
     * @return
     */
    public function commande(Request $request)
    {
        $responseMessage = '';
        $produits = $this->panier->listeProduit();
        $postCommande = "";
        $devisePrixTotal = "";
        $donnees_vendeur = '';
        for ($i = 0; $i < sizeof($produits); $i++) {
            $data = [];
            if ($produits[$i]['categorie'] == 'ops_speciale_article') {
                $data[] = 'Trust&Market - KBR GLOBAL - serviceclients@kbr-global.com';
            } else {
                for ($j = 0; $j < sizeof($produits[$i]['donnees_vendeur']); $j++) {
                    $data[] = $produits[$i]['donnees_vendeur'][$j];
                }
            }
            foreach ($data as $value) {
                $donnees_vendeur = '|' . $value . '|';
            }
        }
        //Store data if not exist
        $userData = $this->service_manager->getAllUserdata($this->getUser()->getId());
        if ($userData['rue'] == '') {
            $this->service_manager->updateUserMeta(
                $this->getUser()->getId(),
                'billing_address_1',
                $request->get('rue')
            );
        }
        if ($userData['code_postale'] == '') {
            $this->service_manager->updateUserMeta(
                $this->getUser()->getId(),
                'billing_postcode',
                $request->get('codePostal')
            );
        }
        if ($userData['ville'] == '') {
            $this->service_manager->updateUserMeta(
                $this->getUser()->getId(),
                'billing_city',
                $request->get('ville')
            );
        }
        if ($userData['pays'] == '') {
            $this->service_manager->updateUserMeta(
                $this->getUser()->getId(),
                'billing_country',
                $request->get('pays')
            );
        }
        if ($userData['telephone'] == '') {
            $this->service_manager->updateUserMeta(
                $this->getUser()->getId(),
                'telephone',
                $request->get('telephone')
            );
        }
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'prenom_livraison', $request->get('prenom'));
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'nom_livraison', $request->get('nom'));
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'ville_livraison', $request->get('ville'));
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'numeroNomRue_livraison', $request->get('rue'));
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'nomEntreprise_livraison', $request->get('entreprise'));
        $this->service_manager->updateUserMeta($this->getUser()->getId(), 'pays_livraison', $request->get('pays'));
        //End store data
        $dateCommande = new DateTime();
        $idAuthor = $request->get('idUser');

        if ($idAuthor != "") {
            $idWallet = $this->payment->getOrCreateUserWallet($idAuthor,@serialize($donnees_vendeur));
            $returnUrl = $this->generateUrl('profile_parameters', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $payIn = $this->payment->doPayInCardWeb(
                $idWallet,
                $idAuthor,
                $request->get('cardOption'),
                'EUR',
                trim(str_replace(' ', '', $request->get('total-price'))) * 100,
                'FR',
                $returnUrl,
                $donnees_vendeur,
                trim(str_replace(' ', '', $request->get('frais_livraison'))) * 100
            );
            $session = $request->getSession();
            $session->set('PayInCardWebId', $payIn->Id);
            if ($payIn->Status == 'CREATED' || $payIn->Status == 'SUCCEEDED') {
                $redirecturl = $payIn->ExecutionDetails->RedirectURL;
                $commandeContent = @serialize($this->panier->listeProduit());
                $client = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'first_name') . ' ' .
                    $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'last_name');
                $auteur = $this->service_manager->getUserStringDataValue($this->panier->listeProduit()['0']['userId'], 'first_name') .
                    ' ' . $this->service_manager->getUserStringDataValue($this->panier->listeProduit()['0']['userId'], 'last_name');
                $titre = $this->panier->listeProduit()['0']['libelle'];
                $adresseEvenement = $this->service_manager->getPostStringDataValue(
                        $this->panier->listeProduit()['0']['id'],
                        '_product_country'
                    ) . ' ' .
                    $this->service_manager->getPostStringDataValue($this->panier->listeProduit()['0']['id'], '_product_city') .
                    ' ' . $this->service_manager->getPostStringDataValue($this->panier->listeProduit()['0']['id'], '_product_adress');
                $prixTotalCommande = trim(str_replace(' ', '', $request->get('total-price')));

                $postCommande = $this->service_manager->createPosts1(
                    $this->getUser()->getId(),
                    $dateCommande,
                    $dateCommande,
                    $commandeContent,
                    $titre,
                    $payIn->Id,
                    'wc-pending',
                    '',
                    '',
                    '',
                    $request->get('crit-check-input'),
                    $request->get('total-price'),
                    'product',
                    $dateCommande,
                    $dateCommande,
                    $adresseEvenement,
                    $request->get('total-price'),
                    $client,
                    0,
                    'shop_order',
                    $auteur,
                    sizeof($this->panier->listeProduit()),
                    0
                );
                //Stocker l'objet de transaction
                $transaction = $this->service_manager->createPostMeta($postCommande, 'product_transaction', @serialize($payIn), 'fr');
                //Update devis Commande status Yes
                $CommandeEtat = $this->em->getRepository(WpPosts::class)->findOneById($postCommande);
                $CommandeEtat->setPostStatus('wc-in-progress');
                $this->em->flush();
                return $this->redirect($redirecturl);
            }
            if ($payIn->Status == 'FAILED') {
                $responseMessage = $this->convertErrorCodeToMessage($payIn->ResultCode);
                $this->addFlash('notice', $responseMessage);
                return $this->redirectToRoute('annonces_panier_user');
            } else {
                $responseMessage = 'Un probleme est survenu, veuillez contacter le support';
                $this->addFlash('notice', $responseMessage);
                return $this->redirectToRoute('annonces_detailsAnnonce', [
                    'id' => $this->panier->listeProduit()['0']['id'],
                ]);
            }
        } else {
            $responseMessage = 'Vous ne possedez pas de compte electronique. Mettez a jour votre profil pour en obtenir un.';
            $this->addFlash('notice', $responseMessage);
            return $this->redirectToRoute('profile_fournisseurs');
        }
    }
    // Annulation d'annonces

    /**
     * @Route("/commande/annuler/{_locale}/{idPost}/{idCommande}", name="annulerCommande",methods={"GET"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function annulerCommande(Request $request)
    {
        // Get list of reservation
        $commande = $this->em->getRepository(WpPosts::class)->find($request->get('idCommande'));
        $commande->setPostStatus('wc-cancelled');
        // Store removed data with new status
        $produits = @unserialize($commande->getPostContent());
        $MontantGlobal = $commande->getPostParent();
        $dateCommande = $commande->getPostDate();

        $this->em->flush();

        // Email Admin and pro
        $emailVendeur = $this->em->getRepository(User::class)->find($produits['0']['userId'])->getEmailCanonical();

/*        $this->tools->sendEmailsToMultiples('admin@kbr-global.com', $destinataires, 'Commande n°' . $request->get('idCommande') . ' annulée sur Trust and Market', 'emails/emailAnnulationCommande.html.twig', [
            'panier' => $produits,
            'prixTotal' => $MontantGlobal,
            'no' => $request->get('idCommande'),
            'user' => $this->service_manager->getAllUserdata(
                $this->getUser()->getId()
            ),
            'dateCommande' => $dateCommande->format('d-m-Y'),
            'devisePrixTotal' => $produits['0']['devise'],
        ]);*/

        return $this->render('admin/resultat.html.twig', ['result' => 1]);
    }



    public function convertErrorCodeToMessage($code)
    {
        //Messages d'erreurs
        if ($code == '001999') {
            return 'Un probleme de connexion est survenu et a interrompu toutes les transactions.';
        }
        if ($code == '001001') {
            return 'Vous n\'avez pas assez de liquidités dans votre compte.';
        }
        if ($code == '001002') {
            return 'L\'auteur n\'est pas le propriétaire de la mallette.';
        }
        if ($code == '001011') {
            return 'Montant de la transaction supérieur au montant autorisé.';
        }
        if ($code == '001012') {
            return 'Montant de la transaction inférieur au montant minimum autorisé.';
        }
        if ($code == '001013') {
            return 'Montant de la transaction invalide.';
        }
        if ($code == '001014') {
            return 'Le montant a transférer doit etre supérieur a 0.';
        } else {
            return 'Un probleme est survenu, veuillez contacter le support.';
        }
    }
}
