<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WpOptions;
use App\Entity\WpPosts;
use App\Service\Payment;
use App\Service\Panier;
use App\Service\ServiceManager;
use App\Service\ToolsMeta;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfileDashboardController extends AbstractController
{
    private $service_manager;
    private $tools;
    private $payment;
    private $panier;
    private $em;
    private $requestStack;

    public function __construct(
        ServiceManager $service_manager,
        ToolsMeta $tools,
        Payment $payment,
        Panier $panier,
        EntityManagerInterface $em,
        RequestStack $requestStack
    ) {
        $this->service_manager = $service_manager;
        $this->tools = $tools;
        $this->payment = $payment;
        $this->panier = $panier;
        $this->em = $em;
        $this->requestStack = $requestStack;
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
                $devisePrixTotal = 'â‚¬';
                $panierData['email'] = $this->em->getRepository(User::class)->find($panierData['post_author']);
                if ($panierData['email']) {
                    $panierData['email'] = $panierData['email']->getEmailCanonical();
                } else {
                    $panierData['email'] = '';
                }
            } else {
                for ($i = 0; $i < sizeof($panierData); $i++) {
                    $panierData[$i]["nom_adresse_vendeur"] = trim($panierData[$i]["donnees_vendeur"][0] . ' ' . $panierData[$i]["donnees_vendeur"][1]);
                    $prixTotal += $panierData[$i]["prix"];
                }
                //$devisePrixTotal = $panierData["0"]["devise"];
                $devisePrixTotal = 'â‚¬';
            }

            if ($transaction->Status == "SUCCEEDED" && $this->requestStack->getSession()->get('PayInCardWebId') == $transaction->Id) {
                $responseMessage = 'Paiement crée';
                $this->addFlash('notice', $responseMessage);
                $this->em->flush();
                $idAuthor = $this->service_manager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
                $idWallet = $this->payment->getOrCreateUserWallet($idAuthor, $user_client['first_name'] . ' ' . $user_client['last_name']);
                $allWallets = null;
                if ($allData->getPinged() == "product") {
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
                    $data = [
                        'to' => array_unique($emailsVendeurs, SORT_REGULAR),
                        'bcc' => [
                            [
                                'email' => 'commerce@trustandmarket.com',
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
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName()
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'commerce@trustandmarket.com',
                                'name' => "Trust & Market"
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
                            'tag' => 'client',
                            'livraison' => $livraison,
                            'frais' => $frais
                        ]
                    ];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
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
                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName(),
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'commerce@trustandmarket.com',
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
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $data = [
                        'to' => [
                            [
                                'email' => $panierData['email'],
                                'name' => $panierData['first_name'] . ' ' . $panierData['last_name']
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'commerce@trustandmarket.com',
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
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    curl_close($ch);
                }
                $this->panier->supprimePanier();
            } elseif ($transaction->Status == "FAILED") {
                $responseMessage = 'Erreur lors du transfert, Veuillez reessayer ou contacter le support';
                $this->addFlash('notice', $responseMessage);
                if ($allData->getPinged() == 'product') {
                    foreach ($panierData as $key => $value) {
                        $emailsVendeurs[] = ['email' => $value['email'], 'name' => 'Prestataire'];
                    }
                    $data = [
                        'to' => array_unique($emailsVendeurs, SORT_REGULAR),
                        'bcc' => [
                            [
                                'email' => 'commerce@trustandmarket.com',
                                'name' => "Trust & Market"
                            ]
                        ],
                        'templateId' => 34,
                        'params' => [
                            "date_achat" => $dateCommande->format('d-m-Y'),
                            "numro_cmde" => $allData->getId()
                        ]
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $data = [
                        'to' => [
                            [
                                'email' => $this->getUser()->getEmailCanonical(),
                                'name' => $this->getUser()->getDisplayName()
                            ]
                        ],
                        'bcc' => [
                            [
                                'email' => 'commerce@trustandmarket.com',
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

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    curl_close($ch);
                }
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

        if ($request->get('reservationServiceClient') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_reservation_service_client', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_reservation_service_client', 0);
        }
        if ($request->get('reservationServiceAnnonceur') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_reservation_service_annonceur', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_reservation_service_annonceur', 0);
        }
        if ($request->get('annulationReservation') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_annulation_reservation', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_annulation_reservation', 0);
        }
        if ($request->get('annonceBrouillon') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_en_brouillon', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_en_brouillon', 0);
        }
        if ($request->get('annonceModeration') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_en_moderation', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_en_moderation', 0);
        }
        if ($request->get('annonceRejete') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_rejete', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_rejete', 0);
        }
        if ($request->get('annoncePublie') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_publie', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_annonce_publie', 0);
        }
        if ($request->get('adhesionNewsletter') == 'on') {
            $this->service_manager->updateUserMeta($userId, '_email_newsletter', 1);
        } else {
            $this->service_manager->updateUserMeta($userId, '_email_newsletter', 0);
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
}
