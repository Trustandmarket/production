<?php

namespace App\Controller;

use App\Entity\WpPosts;
use App\Entity\WpOptions;
use App\Service\Panier;
use App\Service\Payment;
use App\Service\ServiceManager;
use App\Service\ToolsMeta;
use App\Service\DataAccessLayer\Annonces;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("", requirements={"locale": "fr"}, name="devis_")
 */
class DevisController extends AbstractController
{
    private $entityManager;
    private $service_manager;
    private $mailer;
    private $annonces_access_layer;
    private $payment;
    private $panier;
    private $tools;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $entityManager,
        Annonces $annonces_access_layer,
        MailerInterface $mailer,
        Payment $payment,
        Panier $panier,
        ToolsMeta $tools
    ) {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->annonces_access_layer = $annonces_access_layer;
        $this->mailer = $mailer;
        $this->payment = $payment;
        $this->panier = $panier;
        $this->tools = $tools;
    }

    /**
     * @Route("/{_locale}/devis", name="devis")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\Exception
     */
    public function index(Request $request)
    {
        if ($this->getUser()) {
            $userId = $this->getUser()->getId();
            $port = $this->service_manager->readUserMeta($userId, 'portfolio');
            //REFERENCE

            $portfolio = array();
            if ($port) {
                $ids = explode(',', $port->getMetaValue());
                $portfolio = $this->entityManager->getRepository(WpPosts::class)->findById($ids);
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
            return $this->render('devis/index.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'categorie' => $this->service_manager->postCategorie(
                    'product_cat',
                    0
                ),
                'portfolio' => $portfolio,
                'video' => $video,
                'imgid' => $imgid,
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
                    'wc-pending'
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
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
            ]);
        } else {
            return $this->redirect('/' . $request->getLocale() . '/login');
        }
    }

    //Ajout d'un devis

    /**
     * @Route("/{_locale}/profil-utilisateur/devis/ajouter_devis", name="ajouter_devis",methods={"POST"})
     */
    public function ajouterDevis(Request $request)
    {
        /* dd($request->get('state'));*/
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
            //Pour la creation complete
            if ($state == 'creation') {
                if ($request->get('idPostEdited')) {
                    $oldAnnounce = $this->entityManager->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                    $this->entityManager->remove($oldAnnounce);
                    $this->entityManager->flush();
                }
                $id = $this->service_manager->createPosts(
                    $uid,
                    $date,
                    $date,
                    $request->get('description'),
                    $request->get('titre'),
                    '',
                    'devis-pending',
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
                    'devis',
                    '',
                    0,
                    $idc
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
                $oldAnnounce = $this->entityManager->getRepository(WpPosts::class)
                    ->find($request->get('idPostEdited'));
                $this->entityManager->remove($oldAnnounce);
                $this->entityManager->flush();
                $statut = 'devis-pending';
                if ($oldAnnounce->getPostStatus() != 'devis-pending') {
                    $statut = 'devis-pending';
                } elseif ($request->get('sender') == 'admin') {
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
                    'devis',
                    '',
                    0,
                    $idc,
                    $request->getLocale()
                );
            }

            $detailsAnnonce = null;
            if ($state == 'edition_admin') {
                $statut = $request->get('status');
                $id = $this->service_manager->updatePosts(
                    $request->get('idPostEdited'),
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
                    'devis',
                    '',
                    0,
                    $idc,
                    $request->getLocale()
                );
            }

            // Sauvegarde brouillon
            if ($state == 'brouillon') {
                if ($request->get('idPostEdited') and $request->get('souscategorie') > 0) {
                    $oldAnnounce = $this->entityManager->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                    $this->entityManager->remove($oldAnnounce);
                    $this->entityManager->flush();

                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'devis-draft',
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
                }
                if ($request->get('idPostEdited') and $request->get('souscategorie') == 'devis') {
                    $oldAnnounce = $this->entityManager->getRepository(WpPosts::class)->find($request->get('idPostEdited'));
                    $this->entityManager->remove($oldAnnounce);
                    $this->entityManager->flush();

                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'devis-draft',
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
                }
                if ($request->get('souscategorie') == 'devis') {
                    $id = $this->service_manager->createPosts(
                        $uid,
                        $date,
                        $date,
                        $description,
                        $titre,
                        '',
                        'devis-draft',
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
                        'devis-draft',
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
                }
            }
            // meta post
            if ($state == 'edition_admin') {
                if (!empty($request->get('prix'))) {
                    $meta = $this->service_manager->readPostMeta($id, '_price');
                    $this->service_manager->updatePostMeta(
                        $meta->getMetaId(),
                        $id,
                        '_price',
                        $request->get('prix'),
                        $request->getLocale()
                    );
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
            if (
                $request->get('portfolio') &&
                sizeof($request->get('portfolio')) > 0
            ) {
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
                if ($portImg && $portImg->getMetaValue() != '') {
                    $images_annonce = $this->service_manager->updatePostMeta($portImg->getMetaId(), $id, 'images_annonces', $idImg . ',' . $portImg->getMetaValue(), $request->getLocale());
                } else {
                    $images_annonce = $this->service_manager->createPostMeta($id, 'images_annonces', $idImg, $request->getLocale());
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
            // For devis
            if (!empty($request->get('nom_prenom'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    'client',
                    trim($request->get('idClient')),
                    $request->getLocale()
                );
            }
            if (!empty($request->get('dates_horaires'))) {
                $this->service_manager->createPostMeta(
                    $id,
                    'dates_horaires',
                    $request->get('dates_horaires'),
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
                        $vid = $this->service_manager->updateUserMeta(
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
                        $vid = $this->service_manager->updateUserMeta(
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
                    $vid = $this->service_manager->updateUserMeta(
                        $uid,
                        'video',
                        @serialize(
                            $this->trierTableau($request->get('new_vid'))
                        )
                    );
                }
                if ($request->get('new_vid')[0] != '') {
                    $video = array();
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
                        $portfolio = $this->service_manager->updateUserMeta(
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
        $detailsAnnonce = $this->entityManager->getRepository(WpPosts::class)->find($id);
        $body = 'emails/emailStateDevis.html.twig';
        $body_data = [
            'user' => $this->getUser(),
            'post' => $detailsAnnonce,
            'dateCreate' => $date,
            'state' => $state,
        ];
        $subject = '';

        if ($detailsAnnonce->getPostStatus() == 'pending' || $detailsAnnonce->getPostStatus() == 'devis-pending') {
            //Email Fournisseur
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
                'templateId' => 26,
                'params' => [
                    'email' => $this->getUser()->getEmailCanonical(),
                    'titre_devis' => $detailsAnnonce->getPostTitle(),
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

            //Email au client
            $idClient = $this->service_manager->getPostStringDataValue($detailsAnnonce->getId(), 'client');
            $clientData = $this->service_manager->getAllUserdata(trim($idClient));

            $data = [
                'to' => [
                    [
                        'email' => $clientData['email'],
                        'name' => $clientData['display_name'],
                    ]
                ],
                'bcc' => [
                    [
                        'email' => 'serviceclients@kbr-global.com',
                        'name' => 'Trust & Market',
                    ]
                ],
                'templateId' => 25,
                'params' => [
                    'email' => $this->getUser()->getEmailCanonical(),
                    'titre_devis' => $detailsAnnonce->getPostTitle(),
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
        if ($detailsAnnonce->getPostStatus() == 'draft' || $detailsAnnonce->getPostStatus() == 'devis-draft') {
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
                'templateId' => 24,
                'params' => [
                    'email' => $this->getUser()->getEmailCanonical(),
                    'titre_devis' => $detailsAnnonce->getPostTitle(),
                    "statut_devis" => 'Brouillon'
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
     * @Route("/{_locale}/profil-utilisateur/devis/editer-devis/{id}", name="EditDraftDevis",methods={"GET"})
     */
    public function EditDraftAnnounce(Request $request)
    {
        if ($this->getUser()) {
            $userId = $this->getUser()->getId();
            $port = $this->service_manager->readUserMeta($userId, 'portfolio');
            //REFERENCE
            $portfolio = array();
            if ($port) {
                $ids = explode(',', $port->getMetaValue());
                $portfolio = $this->entityManager->getRepository(WpPosts::class)->findById($ids);
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

            return $this->render('devis/index.html.twig', [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'categorie' => $this->service_manager->postCategorie(
                    'product_cat',
                    0
                ),
                'portfolio' => $portfolio,
                'video' => $video,
                'imgid' => $imgid,
                'annoncesBrouillon' => $this->service_manager->readListAnnonceDataOfUser(
                    $userId,
                    'draft',
                    'devis-draft'
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
                    'wc-pending'
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
                'editedData' => $this->service_manager->readAllAnnonceData(
                    $request->get('id')
                ),
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
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
            ]);
        } else {
            return $this->redirect('/' . $request->getLocale() . '/login');
        }
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/devis/{tag}", name="devisTag",methods={"GET"})
     */
    public function mesannoncesTag($tag, Request $request)
    {
        if ($this->getUser()) {
            $userId = $this->getUser()->getId();
            return $this->render('profile/annoncesTag.html.twig', [
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
                'tag' => $request->get('tag'),
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
            ]);
        } else {
            return $this->redirect('/' . $request->getLocale() . '/login');
        }
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/devis/annuler-devis/{id}", name="annulerDevis",methods={"GET"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function annulerDevis(Request $request)
    {
        $devis = $this->entityManager->getRepository(WpPosts::class)->find($request->get('id'));
        // Envoie du du mail
        $client = $this->service_manager->getAllUserdata($this->getUser()->getId());
        $prestataire = $this->service_manager->getAllUserdata($devis->getPostAuthor());

        $devis->setPostStatus('trash');
        $devis->setPinged('devis');

        $this->entityManager->flush();
        //Create command cancelled
        $commande = $this->service_manager->getAllDevisData($request->get('id'));
        $commandeContent = @serialize($commande);
        $dateCommande = new DateTime($commande["post_date"]);

        $auteur = $this->service_manager->getUserStringDataValue($commande['post_author'], 'first_name'
            ) . ' ' . $this->service_manager->getUserStringDataValue($commande['post_author'], 'last_name');

        $titre = $commande['titre'];
        $adresseEvenement =
            $this->service_manager->getPostStringDataValue($request->get('id'), '_product_country') .
            ' ' .
            $this->service_manager->getPostStringDataValue($request->get('id'), '_product_city') .
            ' ' .
            $this->service_manager->getPostStringDataValue($request->get('id'), '_product_adress');

        $prixTotalCommande = $this->service_manager->getPostStringDataValue($request->get('id'),'_price');

        $postCommande = $this->service_manager->createPosts1(
            $this->getUser()->getId(),
            $dateCommande,
            $dateCommande,
            $commandeContent,
            $titre,
            '',
            'wc-cancelled',
            '',
            '',
            '',
            '',
            $prixTotalCommande,
            'devis',
            $dateCommande,
            $dateCommande,
            $adresseEvenement,
            round($prixTotalCommande),
            $client['first_name'] . ' ' . $client['last_name'],
            0,
            'shop_order',
            $auteur,
            1,
            0
        );
        $this->entityManager->flush();

        //Email Fournisseur
        $data = [
            'to' => [
                [
                    'email' => $prestataire['email_canonical'],
                    'name' => $prestataire['display_name'],
                ]
            ],
            'bcc' => [
                [
                    'email' => 'serviceclients@kbr-global.com',
                    'name' => "Trust & Market"
                    ]
                ],
            'templateId' => 37,
            'params' => [
                "titre_devis" => $devis->getPostTitle(),
                "identite_client" => $client['first_name'] . ' ' . $client['last_name']
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

        return new JsonResponse([
            'result' => 1,
            'data' => $response,
        ]);
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/devis/details/{id}", name="detailsDevis",methods={"GET"})
     */
    public function detailsDevis(Request $request)
    {
        $userId = $this->getUser()->getId();
        $infos_bulle = $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('infos_bulle_' . $request->getLocale());
        $limit = 6;
        $noPage = 1;
        $offset = 0;
        $devis = $this->service_manager->getAllDevisData($request->get('id'));

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

        return $this->render('devis/detailsDevis.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'infos_bulle' => $infos_bulle,
            'categorie' => $this->service_manager->postCategorie('product_cat', 0),
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
            'devis' => $devis,
            'page_name' => 'Devis',
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    /**
     * @Route("{_locale}/devis/", name="payment_devis",methods={"POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function devisPayment(Request $request)
    {
        $responseMessage = '';
        //Check reauired data
        $userData = $this->service_manager->getAllUserdata($this->getUser()->getId());
        $produits = $this->service_manager->getAllDevisData($request->get('id_devis'));
        if (trim($userData['adresse_livraison']) == '') {
            $userData['adresse_livraison'] = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_city') . ' ' .
                $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'billing_address_1');
        }
        $idWallet = '';
        $description = $this->setUserDescription();
        //Return error message if one data in null
        if ($userData['code_postale'] == '') {
            $userData['code_postale'] = "77777";
        }

        if ($userData['pays'] == '') {
            $this->addFlash(
                'notice',
                'Votre pays n\'est pas enregistré, Vous devriez mettre a jour votre profil pour procéder aux transactions.'
            );
            return $this->redirectToRoute('profile_profile');
        }

        $mangopayUserId = $this->service_manager->getUserStringDataValue(
            $this->getUser()->getId(),
            'mp_user_id_sandbox'
        );
        if ($mangopayUserId == '') {
            $this->addFlash(
                'notice',
                'Vous ne possédez pas de compte. Vous devriez mettre a jour votre profil pour procéder aux transactions.'
            );
            return $this->redirectToRoute('profile_profile');
        }
        //End Check
        //Check user Wallet
        $idWallet = $this->payment->getOrCreateUserWallet($mangopayUserId, $description);
        //End check user Wallet
        $dateCommande = new DateTime();
        $urlVirement = '';
        $idDeLaCommande = '';
        $creditedUserId = $idWallet;
        $returnUrl = $this->generateUrl(
            'profile_parameters',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $payIn = $this->payment->doPayinCardWeb(
            $idWallet,
            $mangopayUserId,
            'CB_VISA_MASTERCARD',
            $request->get('devise'),
            trim($request->get('prix_devis')) * 100,
            'FR',
            $returnUrl
        );
        $session = $request->getSession();
        $session->set('PayInCardWebId', $payIn->Id);
        if ($payIn->Status == 'CREATED' || $payIn->Status == 'SUCCEEDED') {
            $redirecturl = $payIn->ExecutionDetails->RedirectURL;
            //Id transaction stocke dans post_excerpt
            $commandeContent = @serialize($this->service_manager->getAllDevisData($request->get('id_devis')));
            $client = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'first_name') . ' ' .
            $this->service_manager->getUserStringDataValue($this->getUser()->getId(),'last_name');
            
            $auteur = $this->service_manager->getUserStringDataValue(
                    $request->get('id_prestataire'),
                    'first_name'
                ) .
                ' ' .
                $this->service_manager->getUserStringDataValue(
                    $request->get('id_prestataire'),
                    'last_name'
                );
            $titre = $request->get('libelle_devis');
            $adresseEvenement = $this->service_manager->getPostStringDataValue(
                    $request->get('id_devis'),
                    '_product_country'
                ) .
                ' ' .
                $this->service_manager->getPostStringDataValue(
                    $request->get('id_devis'),
                    '_product_city'
                ) .
                ' ' .
                $this->service_manager->getPostStringDataValue(
                    $request->get('id_devis'),
                    '_product_adress'
                );
            $prixTotalCommande = trim($request->get('prix_devis'));
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
                '',
                '',
                'devis',
                $dateCommande,
                $dateCommande,
                $adresseEvenement,
                $prixTotalCommande,
                $client,
                0,
                'shop_order',
                $auteur,
                sizeof($this->panier->listeProduit()),
                0
            );
            //Stocker l'objet de transaction
            $transaction = $this->service_manager->createPostMeta(
                $postCommande,
                'devis_transaction',
                @serialize($payIn),
                'fr'
            );
            //Update devis Commande status Yes
            $devisCommandeEtat = $this->entityManager->getRepository(WpPosts::class)->findOneById($postCommande);
            $devisCommandeEtat->setPostStatus('wc-in-progress');
            $this->entityManager->flush();

            $devisEtat = $this->entityManager->getRepository(WpPosts::class)->findOneById($request->get('id_devis'));
            $devisEtat->setPostStatus('wc-in-progress');
            $this->entityManager->flush();
            //END Update devis status
            $idDeLaCommande = $postCommande;


            return $this->redirect($redirecturl);
        } elseif ($payIn->Status == 'FAILED') {
            $responseMessage = 'Paiement rejeté';
            $this->addFlash('notice', $responseMessage);

            //Update devis Commande status Yes
            $devisCommandeEtat = $this->entityManager->getRepository(WpPosts::class)->findOneById($produits["id"]);
            $devisCommandeEtat->setPostStatus('devis-pending');
            $this->entityManager->flush();

            return $this->redirectToRoute('devis_detailsDevis', [
                'id' => $produits['id_devis'],
            ]);
        }
    }

    public function quotePayment()
    {
    }

    public function trierTableau($tabeauVideos)
    {
        $tab = array_unique($tabeauVideos);
        $tab = array_filter($tab);
        return $tab;
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
        }
        //Paiement en ligne
        if ($code == '001030') {
            return 'L\'utilisateur ne reçoit jamais la page de paiement et n\'ouvre jamais la session Payline.';
        }
        if ($code == '001031') {
            return 'L\'Utilisateur clique sur "Annulé" sur la page de paiement';
        }
        if ($code == '101002') {
            return 'L\'Utilisateur clique sur "Annulé" sur la page de paiement.';
        }
        if ($code == '001032') {
            return 'L\'utilisateur est toujours sur la page de paiement (session Payline).';
        }
        if ($code == '001033') {
            return 'La session a expiré et Payin Web a échoué. L\'utilisateur est allé sur la page de paiement.';
        }
        if ($code == '001034') {
            return 'L\'utilisateur est allé sur la page de paiement mais a laissé la session expirée. Le Payin Web a donc échoué.';
        }
        if ($code == '101001') {
            return 'L\'utilisateur n\'a pas saisi certaines informations obligatoires. Tous les champs doivent être remplis dans les premières minutes du paiement.';
        }

        //Fin messages d'erreur;
        return '';
    }
    public function setUserDescription()
    {
        if (
            in_array('ROLE_SOCIETE', $this->getUser()->getRoles()) ||
            in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles())
        ) {
            return 'Professionnel';
        } else {
            return 'Abonne TrustAndMarket';
        }
    }
}
