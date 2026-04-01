<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WpOptions;
use App\Entity\WpPosts;
use App\Entity\WpTermRelationships;
use App\Service\DataAccessLayer\Annonces;
use App\Service\ServiceManager;
use DateTime;
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
class ProfileAnnouncementController extends AbstractController
{
    private $service_manager;
    private $annonces_access_layer;
    private $em;

    public function __construct(
        ServiceManager $service_manager,
        Annonces $annonces_access_layer,
        EntityManagerInterface $em
    ) {
        $this->service_manager = $service_manager;
        $this->annonces_access_layer = $annonces_access_layer;
        $this->em = $em;
    }

    public function trierTableau($tabeauVideos)
    {
        $tab = array_unique($tabeauVideos);
        $tab = array_filter($tab);
        return $tab;
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
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
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
     * @Route("/profil-utilisateur/{_locale}/annonces/publier_annonce", name="ajouter_annonce",methods={"POST"})
     * @param Request $request
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
                $statut_annonce = 'ModÃ©ration';
                $setEmailSubject = 'CrÃ©ation d\'annonce sur Trust & Market';
                $email_code = 27;
            } elseif ($detailsAnnonce->getPostStatus() == 'publish') {
                $statut_annonce = 'PubliÃ©e';
                $setEmailSubject = 'Nouvelle annonce publiÃ©e sur Trust & Market';
                $email_code = 28;
            } elseif ($detailsAnnonce->getPostStatus() == 'draft') {
                $statut_annonce = 'En brouillon';
                $setEmailSubject = 'CrÃ©ation d\'annonce sur Trust & Market';
                $email_code = 27;
            } elseif ($detailsAnnonce->getPostStatus() == 'trash') {
                $statut_annonce = 'RejetÃ©e';
                $setEmailSubject = 'Annonce rejetÃ©e sur Trust & Market';
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
                        'email' => 'commerce@trustandmarket.com',
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
            $statut_annonce = 'ModÃ©ration';
            $email_code = 27;
        } elseif ($annonce->getPostStatus() == 'publish') {
            $statut_annonce = 'PubliÃ©e';
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
                    'email' => 'commerce@trustandmarket.com',
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

}
