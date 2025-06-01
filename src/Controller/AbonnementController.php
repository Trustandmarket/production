<?php

namespace App\Controller;

use App\Entity\{Abonnement, InformationsFacturationUtilisateur, OffreInterne, WpOptions, User};
use App\Service\{ServiceManager, Payment};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Twig\Environment;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

class AbonnementController extends AbstractController
{

    use TargetPathTrait;

    private $service_manager;
    private $entityManager;
    private $twig;
    private $payment;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $entityManager,
        Environment $twig,
        Payment $payment
    )
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->twig = $twig;
        $this->payment = $payment;
    }

    #[Route('/{_locale}/abonnement/{page<\d+>}', name: 'app_abonnement')]
    public function index(Request $request, int $page = 1): Response
    {
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles()) || in_array('ROLE_SOCIETE', $this->getUser()->getRoles())) {
            if ($request->get("transactionId")) {
                $abonnement = $this->entityManager->getRepository(Abonnement::class)->find($request->get("abonnement_id"));
                $payInObject = $this->payment->getTransaction($request->get("transactionId"));
                if ($payInObject && $payInObject->Status == 'SUCCEEDED') {
                    $abonnement->setAbonnementActif(true);
                    $this->addFlash('paiement_reccurent_abonnement', true);
                    //Notification Service Client TRUST
                    $data = [
                        'to' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => 'Service Client'
                            ]
                        ],
                        'templateId' => 17,
                        'params' => [
                            "Abonnement" => $abonnement->getOffre()->getTitre(),
                            "compte_pro" => $this->getUser()->getEmailCanonical()
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
                    if ($this->getParameter('environnement') == 'prod') {
                        // Execute the request
                        $response = curl_exec($ch);
                    }

                    // Close cURL session
                    curl_close($ch);
                    //Notification Professionnel
                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName()
                            ]
                        ],
                        'templateId' => 16,
                        'params' => [
                            "Abonnement" => $abonnement->getOffre()->getTitre()
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
                    if ($this->getParameter('environnement') == 'prod') {
                        // Execute the request
                        $response = curl_exec($ch);
                    }

                    // Close cURL session
                    curl_close($ch);

                } else {
                    $abonnement->setAbonnementActif(false);
                    $this->addFlash('paiement_reccurent_abonnement', false);

                }
                $abonnement->setReccuringPaymentPayinId($request->get("transactionId"));
                $this->entityManager->persist($abonnement);
                $this->entityManager->flush();
                return $this->redirectToRoute('app_abonnement');
            }

            $queryBuilder = $this->entityManager->getRepository(Abonnement::class)
                ->findAbonnementByUserPaginate($this->getUser());
            $abonnements = new Pagerfanta(new QueryAdapter($queryBuilder));
            $abonnements->setMaxPerPage(6);
            $abonnements->setCurrentPage($page);
            //Recuperer la carte
            $card = null;
            $mangopayId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_card_id');
            if ($mangopayId != '') {
                $card = $this->payment->getCardRegistration($mangopayId);
            }
            $regPayment = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'reccurring_payment_id');
            if ($regPayment != '') {
                $accessToken = $this->payment->getAuthToken();
                $regPayment = $this->payment->viewRecurringRegistrationPayin($accessToken, $regPayment);
                //dd($regPayment);
            }
            $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
            return $this->render('abonnement/index.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'abonnements' => $abonnements,
                'user' => $user,
                'card' => $card,
                'page_name' => 'Mes abonnements'
            ]);
        } else {
            return $this->redirectToRoute('profile_dashboard');
        }
    }

    #[Route('/{_locale}/abonnement/facture/{abonnement<\d+>}', name: 'app_abonnement_facture')]
    public function app_abonnement_facture(Request $request): Response
    {
        $abonnement = $this->entityManager->getRepository(Abonnement::class)->find($request->get('abonnement'));
        $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
        return $this->render(
            'abonnement/facture.html.twig', array('abonnement' => $abonnement, 'user' => $user)
        );
        $html = $this->render(
            'abonnement/facture.html.twig', array(
                'abonnement' => $abonnement,
                'user' => $user
            )
        );
    }


    #[Route('/{_locale}/abonnement/mise-a-jour/downgrade', name: 'app_abonnement_update_downgrade', methods: 'post')]
    public function update(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $offre = $this->entityManager->getRepository(OffreInterne::class)->find($data['offreId']);
        if ($offre) {
            $abonnements = $this->entityManager->getRepository(Abonnement::class)->findAbonnementByUser($this->getUser());
            if ($abonnements) {
                $abonnements[0]->setTag($data['tag']);
                $this->entityManager->flush();
                $nom_pro = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'first_name') . ' ' .
                    $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'last_name');
                //Notification Admin
                $data = [
                    'to' => [
                        [
                            'email' => 'serviceclients@kbr-global.com',
                            'name' => "Trust & Market",
                        ]
                    ],
                    'templateId' => 22,
                    'params' => [
                        "email" => $this->getUser()->getEmailCanonical()
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
                if ($this->getParameter('environnement') == 'prod') {
                    // Execute the request
                    $response = curl_exec($ch);
                }
                // Close cURL session
                curl_close($ch);

                //Notification Utilisateur
                $data = [
                    'to' => [
                        [
                            'email' => $this->getUser()->getEmailCanonical(),
                            'name' => $this->getUser()->getDisplayName(),
                        ]
                    ],
                    'templateId' => 21,
                    'params' => [
                        "Abonnement" => $abonnements[0]->getOffre()->getTitre()
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
                if ($this->getParameter('environnement') == 'prod') {
                    // Execute the request
                    $response = curl_exec($ch);
                }
                // Close cURL session
                curl_close($ch);

            } else {
                return $this->json([
                    'result' => false,
                    'status' => 404
                ]);
            }
            return $this->json([
                'result' => true,
                'status' => 200
            ]);
        } else {
            return $this->json([
                'result' => false,
                'status' => 404
            ]);
        }
    }

    #[Route('/{_locale}/abonnement/pricing_data', name: 'app_abonnement_pricing_data', methods: 'post')]
    public function abonnementPricingData(Request $request): Response
    {
        $dateJour = new \DateTime();
        $data = json_decode($request->getContent(), true);
        $abonnement = $this->entityManager->getRepository(Abonnement::class)->findAbonnementByUser($this->getUser());

        $offre = $this->entityManager->getRepository(OffreInterne::class)->find($data['offreId']);

        $dateFin = $abonnement[0]->getDateFin();
        if ($dateFin) {
            $dateFin = $dateFin->setTime(0, 0);
        } else {
            $dateFin = $dateJour;
        }

        $dateDebut = $abonnement[0]->getCreatedAt();
        $dateDebut = $dateDebut->setTime(0, 0);

        $dateJour = $dateJour->setTime(0, 0);
        $differentielJour = date_diff($dateFin, $dateJour, true)->d;
        $differentielFin = date_diff($dateFin, $dateDebut, true)->d;
        if ($differentielFin == 0) {
            $differentielFin = 1;
        }

        $prorata = -1 * ($abonnement[0]->getTarif() * ($differentielJour) / ($differentielFin));

        $totalHt = $offre->getTarif() + $prorata;
        return $this->json([
            'result' => ['prorata' => $prorata, 'totalHt' => $totalHt,
                'offre' => $abonnement[0]->getOffre()->getTarif(),
                'differentielJour' => $differentielJour,
                'differentielFin' => $differentielFin],
            'status' => 200
        ]);
    }

    #[Route('/{_locale}/abonnement/facturation/create', name: 'app_abonnement_facturation_create', methods: 'post')]
    public function createFacturation(Request $request): Response
    {
        $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
        $offre = $this->entityManager->getRepository(OffreInterne::class)->find($request->get('offreId'));
        $dateFin = new \DateTime('+1 month');
        $dateFin = date_sub($dateFin, date_interval_create_from_date_string("30 days"));
        //Mise a jour des informations de facturation
        if (!$user->getInformationsFacturationUtilisateur()) {
            $infosFacturation = new InformationsFacturationUtilisateur();
            $infosFacturation->setNomOuSociete($request->get('nom_societe'));
            $infosFacturation->setAdresse($request->get('adresse'));
            $infosFacturation->setVille($request->get('ville'));
            $infosFacturation->setCodePostal($request->get('code_postal'));
            $infosFacturation->setPays($request->get('pays'));
            $infosFacturation->setNumeroTva($request->get('numero_tva'));
            $infosFacturation->setProfile($user);
            $this->entityManager->persist($infosFacturation);
            $this->entityManager->flush();
        } else {
            $infosFacturation = $this->entityManager->getRepository(InformationsFacturationUtilisateur::class)->find($request->get('id'));
            if ($infosFacturation) {
                $infosFacturation->setNomOuSociete($request->get('nom_societe'));
                $infosFacturation->setAdresse($request->get('adresse'));
                $infosFacturation->setVille($request->get('ville'));
                $infosFacturation->setCodePostal($request->get('code_postal'));
                $infosFacturation->setPays($request->get('pays'));
                $infosFacturation->setNumeroTva($request->get('numero_tva'));
                $infosFacturation->setProfile($user);
                $this->entityManager->flush();
            }
        }

        //Creation du nouvel abonnement
        $abonnement = new Abonnement();
        $abonnement->setOffre($offre);
        $abonnement->setUser($user);
        $abonnement->setTarif($request->get('totalHt'));
        $abonnement->setDateFin($dateFin);
        $abonnement->setNomOuSociete($request->get('nom_societe'));
        $abonnement->setAdresse($request->get('adresse'));
        $abonnement->setVille($request->get('ville'));
        $abonnement->setCodePostal($request->get('code_postal'));
        $abonnement->setPays($request->get('pays'));
        $abonnement->setNumeroTva($request->get('numero_tva'));
        $abonnement->setTag('STATIC');
        $this->entityManager->persist($abonnement);
        $this->entityManager->flush();

        $abonnements = $this->entityManager->getRepository(Abonnement::class)->findAbonnementByUser($this->getUser());
        $abboEnCours = 'Gratuit';
        if ($abonnements) {
            $abboEnCours = $abonnements[0]->getOffre()->getTitre();
        }


        return $this->redirectToRoute('app_abonnement');
    }

    #[Route('/{_locale}/abonnement/facturation/create_update', name: 'app_abonnement_facturation_update', methods: 'post')]
    public function createUpdateFacturation(Request $request): Response
    {
        $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
        if (!$user->getInformationsFacturationUtilisateur()) {
            $infosFacturation = new InformationsFacturationUtilisateur();
            $infosFacturation->setNomOuSociete($request->get('nom_societe'));
            $infosFacturation->setAdresse($request->get('adresse'));
            $infosFacturation->setVille($request->get('ville'));
            $infosFacturation->setCodePostal($request->get('code_postal'));
            $infosFacturation->setPays($request->get('pays'));
            $infosFacturation->setNumeroTva($request->get('numero_tva'));
            $infosFacturation->setProfile($user);
            $this->entityManager->persist($infosFacturation);
            $this->entityManager->flush();
            return $this->json([
                'result' => true,
                'data' => $infosFacturation->getId()
            ]);
        } else {
            $infosFacturation = $this->entityManager->getRepository(InformationsFacturationUtilisateur::class)->find($request->get('id'));
            if ($infosFacturation) {
                $infosFacturation->setNomOuSociete($request->get('nom_societe'));
                $infosFacturation->setAdresse($request->get('adresse'));
                $infosFacturation->setVille($request->get('ville'));
                $infosFacturation->setCodePostal($request->get('code_postal'));
                $infosFacturation->setPays($request->get('pays'));
                $infosFacturation->setNumeroTva($request->get('numero_tva'));
                $infosFacturation->setProfile($user);
                $this->entityManager->flush();
                return $this->json([
                    'result' => true,
                    'data' => $infosFacturation->getId()
                ]);
            } else {
                return $this->json([
                    'result' => false,
                    'data' => null
                ]);
            }
        }
    }

    #[Route('/{_locale}/abonnement/create_card', name: 'app_abonnement_create_card', methods: 'post')]
    public function abonnementCreateCard(Request $request): Response
    {
        $dateJour = new \DateTime();
        $data = json_decode($request->getContent(), true);
        $typeCarte = strtoupper($data['cardType']);
        $tag = $data['expirationDate'] . '|' . $data['alias'];
        if (strtoupper($data['cardType']) == 'VISA' || strtoupper($data['cardType']) == 'CB' || strtoupper($data['cardType']) == 'MASTERCARD') {
            $typeCarte = 'CB_VISA_MASTERCARD';
        }
        $mangopayId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_id_sandbox');
        $mangopayCard = $this->payment->createCardRegistration($mangopayId, $typeCarte, $tag);

        $bankUserCardId = $this->service_manager->updateUserMeta($this->getUser()->getId(), 'mp_user_card_id', $mangopayCard->Id);

        return $this->json([
            'result' => $mangopayCard,
            'typeCarte' => $typeCarte,
            'mangopayId' => $mangopayId,
            'status' => 200
        ]);
    }

#[Route('/{_locale}/abonnement/update_card', name: 'app_abonnement_update_card', methods: 'post')]
    public function abonnementUpdateCard(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $bankUserCardId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_card_id');
        $tag = $data['expirationDate'] . '|' . $data['alias'];
        $mangopayCard = $this->payment->updateCardRegistration($bankUserCardId, $data['data'], $tag);

        return $this->json([
            'result' => $mangopayCard,
            'status' => 200
        ]);
    }

    #[Route('/{_locale}/abonnement/create_recurring_payment', name: 'app_abonnement_create_recurring_payment', methods: 'post')]
    public function createRecurringPayment(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
        $date_jour = new \DateTime('now');
        $endDate = $date_jour->modify('+30 day');
        $endDateTimestamp = $endDate->getTimestamp() * 1000;
        //Auteur wallet data
        $authorId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_id_sandbox');
        $cardId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_card_id');
        $card = $this->payment->getCardRegistration($cardId);
        if ($card) {
            $cardId = $card->CardId;
        }
        //service client wallet data
        $serviceClientUser = $this->entityManager->getRepository(User::class)->findOneBy(['email_canonical' => 'serviceclients@kbr-global.com']);
        $creditedUserId = $this->service_manager->getUserStringDataValue($serviceClientUser->getId(), 'mp_user_id_sandbox');
        $creditedWalletId = '';
        if ($serviceClientUser) {
            $creditedWalletId = $this->payment->getOrCreateUserWallet($creditedUserId, '');
        }

        $accessToken = $this->payment->getAuthToken();
        $regPaymentId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'reccurring_payment_id');
        if ($regPaymentId != '') {
            $regPayment = $this->payment->viewRecurringRegistrationPayin($accessToken, $regPaymentId);
            //desactiver le paiement recurrent afin d'en creer un nouveau
            $regPayment = $this->payment->editRecurringPayinRegistration($accessToken, $regPaymentId, $authorId, $cardId, $user, 'ENDED');
        }
        $regPayment = $this->payment->createRecurringPayin($accessToken, $authorId, $creditedUserId, $creditedWalletId,
            $cardId, $data['prixTotal'] * 100, 0, $data['prixMensuel'] * 100,
            $user, $endDateTimestamp);
        if ($regPayment['Status'] === 'CREATED') {
            $this->service_manager->updateUserMeta($this->getUser()->getId(), 'reccurring_payment_id', $regPayment['Id']);
            //Creation du nouvel abonnement
            $offre = $this->entityManager->getRepository(OffreInterne::class)->find($data['forfaitId']);

            $abonnement = new Abonnement();
            $abonnement->setOffre($offre);
            $abonnement->setUser($user);
            $abonnement->setTarif($data['prixTotal']);
            $abonnement->setDateFin($endDate);
            $abonnement->setNomOuSociete($data['nom_societe']);
            $abonnement->setAdresse($data['adresse']);
            $abonnement->setVille($data['ville']);
            $abonnement->setCodePostal($data['code_postal']);
            $abonnement->setPays($data['pays']);
            $abonnement->setNumeroTva($data['numero_tva']);
            $abonnement->setTag('STATIC');
            $abonnement->setReccuringPaymentId($regPayment['Id']);
            $this->entityManager->persist($abonnement);
            $this->entityManager->flush();

            $abonnements = $this->entityManager->getRepository(Abonnement::class)->findAbonnementByUser($this->getUser());
            $abboEnCours = 'Gratuit';
            if ($abonnements) {
                $abboEnCours = $abonnements[0]->getOffre()->getTitre();
            }
            //Create MIT reccuring payment
            $returnUrl = $this->generateUrl('app_abonnement', ['abonnement_id' => $abonnement->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            $data['browserInfo']['AcceptHeader'] = $_SERVER['HTTP_ACCEPT'];

            $recPayin = $this->payment->createRecurringPayInCIT($accessToken, 'Initial-payment', $data['prixTotal'] * 100, 0,
                '', $regPayment['Id'], $returnUrl, $this->service_manager->getIPAddress(), $data['browserInfo']);

            return $this->json(['result' => $recPayin, 'abonnement_id' => $abonnement->getId()]);
        } else {
            //Email admin for error
            return $this->json(['result' => $regPayment]);
        }

    }

    #[Route('/{_locale}/server-action/renew-recurring-payment', name: 'app_abonnement_renew_recurring_payment', methods: 'get')]
    public function renewRecurringPayinMit(Request $request): Response
    {
        $date_jour = new \DateTime('now');
        $endDate = (clone $date_jour)->modify('+30 days'); // Correct to properly add 30 days
        $successArray = [];
        $errorArray = [];

        // Retrieve active user subscriptions that are about to expire
        $abonnements = $this->entityManager->getRepository(Abonnement::class)->findAllActiveAboEndedWithUser(true);
        foreach ($abonnements as $abonnement) {
            $user_email = $abonnement->getUser()->getEmailCanonical();
            $user_name = $abonnement->getUser()->getDisplayName();
            try {
                // Renew the subscription with Mangopay
                $payInMit = $this->payment->createRecurringPayInMit(
                    $abonnement->getOffre()->getTarif() * 100,
                    0,
                    '',
                    'Compte ' . $abonnement->getOffre()->getTitre(),
                    $abonnement->getReccuringPaymentId()
                );

                // Update subscription data locally
                if ($payInMit->Status == 'SUCCEEDED') {
                    // Deactivate the current active subscription
                    $abonnement->setAbonnementActif(false);
                    $this->entityManager->persist($abonnement);
                    $this->entityManager->flush();
                    $successArray[] = ['User' => $user_email, 'Status' => $payInMit->Status, 'PayInId' => $payInMit->Id];

                    // Create a new subscription
                    $newAbonnement = new Abonnement();
                    $newAbonnement->setAbonnementActif(true);
                    $newAbonnement->setOffre($abonnement->getOffre());
                    $newAbonnement->setUser($abonnement->getUser());
                    $newAbonnement->setTarif($abonnement->getOffre()->getTarif());
                    $newAbonnement->setDateFin($endDate);
                    $newAbonnement->setNomOuSociete($abonnement->getNomOuSociete());
                    $newAbonnement->setAdresse($abonnement->getAdresse());
                    $newAbonnement->setVille($abonnement->getVille());
                    $newAbonnement->setCodePostal($abonnement->getCodePostal());
                    $newAbonnement->setPays($abonnement->getPays());
                    $newAbonnement->setNumeroTva($abonnement->getNumeroTva());
                    $newAbonnement->setTag('STATIC');
                    $newAbonnement->setReccuringPaymentPayinId($payInMit->Id);
                    $newAbonnement->setReccuringPaymentId($abonnement->getReccuringPaymentId());
                    $this->entityManager->persist($newAbonnement);
                    $this->entityManager->flush();

                    // Send confirmation emails for renewed subscriptions
                    // Notification to the professional
                    $data = [
                        'to' => [
                            [
                                'email' => $user_email,
                                'name' => $user_name,
                            ]
                        ],
                        'templateId' => 18,
                        'params' => [
                            "Abonnement" => $abonnement->getOffre()->getTitre()
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
                    if ($this->getParameter('environnement') == 'prod') {
                        // Execute the request
                        $response = curl_exec($ch);
                    }
                    // Close cURL session
                    curl_close($ch);

                    // Email to customer service
                    $data = [
                        'to' => [
                            [
                                'email' => 'serviceclients@kbr-global.com',
                                'name' => "Trust & Market",
                            ]
                        ],
                        'templateId' => 19,
                        'params' => [
                            "Abonnement" => $abonnement->getOffre()->getTitre(),
                            "compte_pro" => $abonnement->getUser()->getEmailCanonical()
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
                    if ($this->getParameter('environnement') == 'prod') {
                        // Execute the request
                        $response = curl_exec($ch);
                    }
                    // Close cURL session
                    curl_close($ch);

                } else {
                    $errorArray[] = ['User' => $abonnement->getUser()->getEmailCanonical(), 'Status' => $payInMit->Status, 'PayInId' => $payInMit->Id];
                    // You can add logic here to send an email about the error
                }

            } catch (\Exception $e) {
                // Handle any errors that occur within this loop iteration
                $errorArray[] = ['message' => 'Abonnement processing failed: ' . $e->getMessage(), 'User' => $abonnement->getUser()->getEmailCanonical()];
                $data = [
                    'to' => [
                        [
                            'email' => $abonnement->getUser()->getEmailCanonical(),
                            'name' => $abonnement->getUser()->getDisplayName(),
                        ]
                    ],
                    'bcc' => [
                        [
                            'email' => 'serviceclients@kbr-global.com',
                            'name' => "Trust & Market"
                        ]
                    ],
                    'templateId' => 20,
                    'params' => [
                        "Abonnement" => $abonnement->getOffre()->getTitre()
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
                if ($this->getParameter('environnement') == 'prod') {
                    // Execute the request
                    $response = curl_exec($ch);
                }
                // Close cURL session
                curl_close($ch);
            }
        }

        // Convert errorArray to a string with || separator
        $errorArrayString = implode('||', array_map(function ($item) {
            if (isset($item['User'])) {
                return 'User: ' . $item['User'] . ', Message: ' . (isset($item['message']) ? $item['message'] : '');
            } else {
                return 'Message: ' . $item['message'];
            }
        }, $errorArray));

        // Convert successArray to a string with || separator
        $successArrayString = implode('||', array_map(function ($item) {
            return 'User: ' . $item['User'] . ', Status: ' . $item['Status'] . ', PayInId: ' . $item['PayInId'];
        }, $successArray));

        // Admin Notification
        $data = [
            'to' => [
                [
                    'email' => 'commerce@trustandmarket.com',
                    'name' => "Trust & Market",
                ]
            ],
            'templateId' => 23,
            'params' => [
                "date_jour" => $date_jour->format('d-m-Y H:i:s'),
                "errorArray" => $errorArrayString,
                "successArray" => $successArrayString
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
        if ($this->getParameter('environnement') == 'prod') {
            // Execute the request
            $response = curl_exec($ch);
        }
        // Close cURL session
        curl_close($ch);
        return $this->json(['result' => true, 'successArray' => $successArray, 'errorArray' => $errorArray], 200);
    }

}
