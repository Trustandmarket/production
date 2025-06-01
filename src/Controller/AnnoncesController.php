<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WpPosts;
use App\Entity\WpPostmeta;
use App\Entity\WpOptions;
use App\Service\Panier;
use App\Service\ToolsMeta;
use App\Service\ServiceManager;
use App\Service\DataAccessLayer\Annonces;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\DocBlock\Tags\Example;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use function Symfony\Bundle\MakerBundle\Test\doesClassExistInApp;

/**
 * @Route("", requirements={"_locale": "fr"}, name="annonces_")
 */
class AnnoncesController extends AbstractController
{
    private $entityManager;
    private $service_manager;
    private $annonces_access_layer;
    private $tools;
    private $requestStack;
    private $panier;

    public function __construct(
        ServiceManager $service_manager,
        Annonces $annonces_access_layer,
        EntityManagerInterface $entityManager,
        ToolsMeta $tools,
        RequestStack $requestStack,
        Panier $panier
    )
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->annonces_access_layer = $annonces_access_layer;
        $this->tools = $tools;
        $this->requestStack = $requestStack;
        $this->panier = $panier;
    }

    /**
     * @Route("/{_locale}/annonces/liste", name="listeAnnonces", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function liste(Request $request)
    {
        $code_postal_ville = $request->get('code_postal_ville');
        $pages = 0;
        $offset = 0;
        $start = 0;
        $end = 0;
        $nombreDeLignes = 0;
        $limit = 9;
        $page = 1;
        $p_empty_search_text = null;
        if ($request->get('noPage')) {
            $page = $request->get('noPage');
        }
        if (!$request->get('code_postal_ville')) {
            $code_postal_ville = '';
        }
        $listeAnnonces = [];
        $text_wishlist = null;
        if ($this->getUser()) {
            $text_wishlist = implode(',', $this->service_manager->readListUserMeta($this->getUser()->getId(), 'annonce_wishlist'));
        }
        if ($request->get('type_prestation') == '' && $request->get('services_proposes') == '') {
            $listeAnnonces = [];
        } elseif ($request->get('type_prestation') && $request->get('services_proposes')) {
            $nombreDeLignes = $this->annonces_access_layer->readAllAnnoncesforAjaxSearchCount($request->get('services_proposes'), $request->get('code_postal_ville'));
            if ($nombreDeLignes > 0) {
                $pages = ceil($nombreDeLignes / $limit);
            }
            $offset = ($page - 1) * $limit;
            $start = $offset + 1;
            $end = min(($offset + $limit), $nombreDeLignes);
            $listeAnnonces = $this->annonces_access_layer
                ->readAllAnnoncesforAjaxSearchPaginate(
                    $request->get('services_proposes'),
                    $request->get('code_postal_ville'),
                    $page,
                    $pages,
                    $nombreDeLignes,
                    $offset,
                    $start,
                    $end
                );

            //dd($listeAnnonces);
        } else {
            if ($request->get('type_prestation')) {
                $nombreDeLignes = $this->annonces_access_layer->readAllAnnoncesParentforAjaxSearchCount($request->get('type_prestation'), $request->get('code_postal_ville'));
                if ($nombreDeLignes > 0) {
                    $pages = ceil($nombreDeLignes / $limit);
                }
                $offset = ($page - 1) * $limit;
                $start = $offset + 1;
                $end = min(($offset + $limit), $nombreDeLignes);

                $listeAnnonces = $this->annonces_access_layer
                    ->readAllAnnoncesParentforAjaxSearchPaginate(
                        $request->get('type_prestation'),
                        $request->get('code_postal_ville'),
                        $page,
                        $pages,
                        $nombreDeLignes,
                        $offset,
                        $start,
                        $end
                    );
                //dd($listeAnnonces);
            }
        }
        $sous_categories = $this->service_manager->postCategorieWithMultilangByName('product_cat', $request->get('type_prestation'));
        if (sizeof($listeAnnonces) == 0) {
            $listeAnnonces = $this->annonces_access_layer->readAllAnnoncesParentforAjaxSearchPaginate(
                'produits',
                '',
                $page,
                $pages,
                $nombreDeLignes,
                $offset,
                $start,
                $end
            );
            $p_empty_search_text = $this->entityManager->getRepository(WpPosts::class)->findOneByPostType('p_empty_search_text');
        }

        return $this->render('annonces/listeResultat.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'listeAnnonces' => $listeAnnonces,
            'param1' => $request->get('sous_categorie'),
            'villeA' => $request->get('villeA'),
            'sous_categories' => $sous_categories,
            'type_prestation' => $request->get('type_prestation'),
            'services_proposes' => $request->get('services_proposes'),
            'code_postal_ville' => $request->get('code_postal_ville'),
            'p_empty_search_text' => $p_empty_search_text,
            'noPage' => $page,
            'pages' => $pages,
            'offset' => $offset,
            'start' => $start,
            'end' => $end,
            'text_wishlist' => $text_wishlist,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'nom_commercial' => $request->get('nom_commercial', null)
        ]);
    }

    /**
     * @Route("/{_locale}/annonces/commercial/liste", name="listeAnnoncesCommercial", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function listeAnnoncesCommercial(Request $request)
    {
        $nomCommercial = $request->get('nom_commercial', null);
        $pages = 0; $offset = 0;
        $start = 0; $end = 0; $nombreDeLignes = 0;
        $limit = 9; $page = 1; $p_empty_search_text = null;
        if ($request->get('noPage')) {
            $page = $request->get('noPage');
        }

        $listeAnnonces = []; $text_wishlist = null;
        if ($this->getUser()) {
            $text_wishlist = implode(',', $this->service_manager->readListUserMeta($this->getUser()->getId(), 'annonce_wishlist'));
        }
        if ($request->get('nom_commercial')){
            $nombreDeLignes = $this->annonces_access_layer->readAllAnnoncesOfUserCount($request->get('id_commercial'));
            if ($nombreDeLignes > 0) {
                $pages = ceil($nombreDeLignes / $limit);
            }
            $offset = ($page - 1) * $limit;
            $start = $offset + 1;
            $end = min(($offset + $limit), $nombreDeLignes);
            $listeAnnonces = $this->annonces_access_layer
                ->readAllAnnoncesOfUserPaginate(
                    $request->get('id_commercial'),
                    $page,
                    $pages,
                    $nombreDeLignes,
                    $offset,
                    $start,
                    $end
                );

            //dd($listeAnnonces);
        }
        $sous_categories = $this->service_manager->postCategorieWithMultilangByName('product_cat', $request->get('type_prestation'));
        $p_empty_search_text = $this->entityManager->getRepository(WpPosts::class)->findOneBy(array('postType'=>'empty_nom_commercial'), array('id' => 'DESC'));

        return $this->render('annonces/listeResultat.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'listeAnnonces' => $listeAnnonces,
            'param1' => $request->get('sous_categorie'),
            'villeA' => $request->get('villeA'),
            'sous_categories' => $sous_categories,
            'type_prestation' => $request->get('type_prestation'),
            'services_proposes' => $request->get('services_proposes'),
            'code_postal_ville' => $request->get('code_postal_ville'),
            'p_empty_search_text' => $p_empty_search_text,
            'noPage' => $page,
            'pages' => $pages,
            'offset' => $offset,
            'start' => $start,
            'end' => $end,
            'text_wishlist' => $text_wishlist,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'nom_commercial' => $request->get('nom_commercial', null),
            'id_commercial' => $request->get('id_commercial', null)
        ]);
    }


    /**
     * @Route("/get/sous-categorie/xhr/{_locale}", name="get_sous_categories_xhr", requirements={"_locale": "fr"})
     * @param Request $request
     * @return JsonResponse
     */
    public function get_sous_categories_xhr(Request $request)
    {
        $sous_categories = [];
        if ($request->get('type_prestation')) {
            $sous_categories = $this->service_manager->postCategorieWithMultilangByName(
                'product_cat',
                $request->get('type_prestation')
            );
        }
        return new JsonResponse(['sous_categories' => $sous_categories]);
    }

    /**
     * @Route("/filtre/recherche/xhr/{_locale}", name="filtre_recherche_xhr", requirements={"_locale": "fr"})
     * @param Request $request
     * @return JsonResponse
     */
    public function filtre_recherche_xhr(Request $request)
    {
        $pages = 0;
        $offset = 0;
        $start = 0;
        $end = 0;
        $nombreDeLignes = 0;
        $limit = 9;
        $page = 1;
        $p_empty_search_text = null;
        if ($request->get('noPage')) {
            $page = $request->get('noPage');
        }

        $listeAnnonces = [];
        if ($request->get('type_prestation') == '' && $request->get('services_proposes') == '') {
            $listeAnnonces = [];
        }
        $text_wishlist = null;
        if ($this->getUser()) {
            $text_wishlist = implode(',', $this->service_manager->readListUserMeta($this->getUser()->getId(), 'annonce_wishlist'));
        }
        
        if ($request->get('type_prestation') != '' && $request->get('services_proposes') != '') {
            $nombreDeLignes = $this->annonces_access_layer->readAllAnnoncesforAjaxSearchCount($request->get('services_proposes'), $request->get('code_postal_ville'));
            if ($nombreDeLignes > 0) {
                $pages = ceil($nombreDeLignes / $limit);
            }
            $offset = ($page - 1) * $limit;
            $start = $offset + 1;
            $end = min(($offset + $limit), $nombreDeLignes);
            $listeAnnonces = $this->annonces_access_layer
                ->readAllAnnoncesforAjaxSearchPaginate(
                    $request->get('services_proposes'),
                    $request->get('code_postal_ville'),
                    $page,
                    $pages,
                    $nombreDeLignes,
                    $offset,
                    $start,
                    $end
                );

            //dd($listeAnnonces);
        } elseif ($request->get('type_prestation') != '' && $request->get('services_proposes') == '') {
            $nombreDeLignes = $this->annonces_access_layer->readAllAnnoncesParentforAjaxSearchCount($request->get('type_prestation'), $request->get('code_postal_ville'));
            if ($nombreDeLignes > 0) {
                $pages = ceil($nombreDeLignes / $limit);
            }
            $offset = ($page - 1) * $limit;
            $start = $offset + 1;
            $end = min(($offset + $limit), $nombreDeLignes);

            $listeAnnonces = $this->annonces_access_layer
                ->readAllAnnoncesParentforAjaxSearchPaginate(
                    $request->get('type_prestation'),
                    $request->get('code_postal_ville'),
                    $page,
                    $pages,
                    $nombreDeLignes,
                    $offset,
                    $start,
                    $end
                );
        }
        $sous_categories = $this->service_manager->postCategorieWithMultilangByName('product_cat', $request->get('type_prestation'));
        if (sizeof($listeAnnonces) == 0) {
            $listeAnnonces = $this->annonces_access_layer->readAllAnnoncesParentforAjaxSearchPaginate(
                'produits',
                '',
                $page,
                $pages,
                $nombreDeLignes,
                $offset,
                $start,
                $end
            );
            $p_empty_search_text = $this->entityManager->getRepository(WpPosts::class)->findOneByPostType('p_empty_search_text');
        }
        //dd($listeAnnonces);
        $html = $this->renderView('annonces/liste_resultat_content.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'listeAnnonces' => $listeAnnonces,
            'param1' => $request->get('sous_categorie'),
            'villeA' => $request->get('villeA'),
            'sous_categories' => $sous_categories,
            'type_prestation' => $request->get('type_prestation'),
            'services_proposes' => $request->get('services_proposes'),
            'code_postal_ville' => $request->get('code_postal_ville'),
            'p_empty_search_text' => $p_empty_search_text,
            'text_wishlist' => $text_wishlist,
            'noPage' => $page,
            'pages' => $pages,
            'offset' => $offset,
            'start' => $start,
            'end' => $end,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
        return new JsonResponse(['html' => $html]);
    }

    /**
     * @Route("/{_locale}/annonces/details/{id}", name="detailsAnnonce", requirements={"_locale": "fr","id":".{8,300}?"})
     * @param Request $request
     * @return Response
     */
    public function details(Request $request)
    {
        $dateCommande = new DateTime();
        $detailsAnnonce = $this->service_manager->readAllAnnonceDataByName($request->get('id'));
        //dd($detailsAnnonce);
        $rupture = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy(['postId' => $detailsAnnonce["id"], 'metaKey' => 'rupture']);
        if ($rupture) {
            $rupture = $rupture->getMetaValue();
        } else {
            $rupture = 1;
        }
        $date = array();
        $d1 = $this->service_manager->readUserMeta($detailsAnnonce['post_author'], 'disponibilite');
        if ($d1) {
            $date = explode(',', $d1->getMetaValue());
        }
        $professional = $this->entityManager->getRepository(User::class)->find($detailsAnnonce['post_author']);
        $lateDate = date('d-m-Y', strtotime($dateCommande->format('d-m-Y'). ' + 1 years'));
        $availabilityRangeDates = $this->tools->getBetweenDates($dateCommande->format('d-m-Y'), $lateDate);
        $userAvailabilityDates = array_diff($availabilityRangeDates, $date);
        $datep = array();
        $d2 = $this->panier->dates($detailsAnnonce["id"]);
        if ($d2) {
            $datep = explode('|', $d2);
        }

        $text_wishlist = null;
        if ($this->getUser()) {
            $text_wishlist = implode(',', $this->service_manager->readListUserMeta($this->getUser()->getId(), 'annonce_wishlist'));
        }

        return $this->render('annonces/details.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'detailsAnnonce' => $detailsAnnonce,
            'rupture' => $rupture,
            'date' => $userAvailabilityDates,
            'd' => new DateTime(),
            'datep' => $datep,
            'etat' => $request->get('etat'),
            'user' => $professional,
            'infos_bulle' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('infos_bulle_' . $request->getLocale()),
            'calendar_infos' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('calendar_infos_bulle_' . $request->getLocale()),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'text_wishlist' => $text_wishlist,
            'bloc1' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_1'),
            'bloc2' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_2'),
            'bloc3' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_3'),
            'bloc4' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_4'),
            'bloc5' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_5'),
            'bloc6' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_6'),
            'bloc7' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_7'),
            'bloc8' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_8'),
            'bloc9' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_9'),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }

    /**
     * @Route("/{_locale}/annonces/favoris", name="favoris", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function favoris(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $wishlist = $this->service_manager->readListUserMeta($this->getUser()->getId(), 'annonce_wishlist');
        $text_wishlist = implode(',',$wishlist);
        $listeAnnonces = [];
        if(count($wishlist) > 0){
            $listeAnnonces = $this->annonces_access_layer->readFavorisAnnonces($text_wishlist);
        }

        return $this->render('annonces/favoris.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'listeAnnonces' => $listeAnnonces,
            'param1' => $request->get('sous_categorie'),
            'villeA' => $request->get('villeA'),
            'wishlist' => $wishlist,
            'text_wishlist' => $text_wishlist,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }

    /**
     * @Route("/{_locale}/annonces/rupture/{slug}", name="rupture", requirements={"_locale": "fr","slug":".{8,300}?"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function rupture(Request $request)
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->findOneBy([
            'postName' => $request->get('slug')]);
        if ($post) {
            $rupture = $this->entityManager->getRepository(WpPostMeta::class)->findOneBy([
                'postId' => $post->getId(),
                'metaKey' => 'rupture']);
            if ($rupture) {
                if ($rupture->getMetaValue() == 1) {
                    $this->service_manager->updatePostMeta(
                        $rupture->getMetaId(),
                        $post->getId(),
                        'rupture',
                        0,
                        'fr'
                    );
                    $this->service_manager->updatePostMeta(
                        $rupture->getMetaId(),
                        $post->getId(),
                        'rupture',
                        0,
                        'en_US'
                    );
                } elseif ($rupture->getMetaValue() == 0) {
                    $this->service_manager->updatePostMeta(
                        $rupture->getMetaId(),
                        $post->getId(),
                        'rupture',
                        1,
                        'fr'
                    );
                    $this->service_manager->updatePostMeta(
                        $rupture->getMetaId(),
                        $post->getId(),
                        'rupture',
                        1,
                        'en_US'
                    );
                }
            } else {
                $rupture = $this->service_manager->createPostMeta(
                    $post->getId(),
                    'rupture',
                    1,
                    $request->getLocale()
                );
            }
        }
        return $this->redirectToRoute('profile_annonces');
    }

    /**
     * @Route("/{_locale}/devis/details/{id}", name="detailsDevis", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function detailsDevis(Request $request)
    {
        $detailsAnnonce = $this->service_manager->readAllDevisData($request->get('id'));
        return $this->render('annonces/detailsDevis.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'detailsAnnonce' => $detailsAnnonce,
        ]);
    }

    /**
     * @Route("/annonces/delete/{id}/{_locale}", name="deleteAnnounce", requirements={"_locale": "fr"})
     */
    public function deleteAnnounce($id)
    {
        $DetailsAnnonce = $this->entityManager->getRepository(WpPosts::class)->find($id);
        $this->service_manager->deleteProductAll($id);
        $dateDelete = new DateTime('now');
/*        $this->tools->sendEmail('serviceclients@kbr-global.com', 'admin@kbr-global.com', 'Annonce supprimée', 'emails/emailDeleteAnnounce.html.twig', [
            'user' => $this->getUser(),
            'post' => $DetailsAnnonce,
            'dateDelete' => $dateDelete,
        ]);*/
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/annonces/change/moderation/{tag}/{_locale}", name="change_status", requirements={"_locale": "fr"})
     */
    public function change_status($tag)
    {
        $DetailsAnnonce = $this->entityManager->getRepository(WpPosts::class)->find($id);
        $DetailsAnnonce->setPostStatus($tag);
        $this->entityManager->flush();
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/annonces/panier", name="panier_user", requirements={"_locale": "fr"})
     */
    public function gerer_panier()
    {
        return $this->render('annonces/panier.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'panier' => $this->panier->listeProduit(),
            'prixTotal' => $this->panier->MontantGlobal(),
            'prixTotalProduits' => $this->panier->montant(),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
        ]);
    }

    /**
     * @Route("/{_locale}/annonces/panier/nombre", name="panier_user_number", requirements={"_locale": "fr"})
     */
    public function getPanierNumber()
    {
        //get list of users
        return new Response($this->panier->nombreProduit());
    }


    //Add comment method

    /**
     * @Route("annonce/userComment/{_locale}", name="addAnnounceComment", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function userNotificationData(Request $request)
    {
        $userId = $this->getUser()->getId();
        $commentAuthor =
            $this->service_manager->getUserStringDataValue($userId, 'first_name') . ' ' .
            $this->service_manager->getUserStringDataValue($userId, 'last_name');
        $dateComment = new DateTime('now');
        //Creer commentaire
        $commentaire = $this->service_manager->createComments(
            $request->get('postId'),
            $commentAuthor,
            $this->getUser()->getEmailCanonical(),
            '',
            $request->getClientIp(),
            $dateComment,
            $dateComment,
            $request->get('comment-box'),
            0,
            'trash',
            '',
            '',
            0,
            $userId
        );
        $post = $this->entityManager->getRepository(WpPosts::class)
            ->find($request->get('postId'));
        //Notifier Le createur

        return $this->render('admin/resultat.html.twig', ['result' => 1,]);
    }

    /**
     * @Route("/annonces/add_panier/{_locale}/", name="panier", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function ajouterAnnoncePanier(Request $request)
    {
        //nombre d'annonce dans le panier
        $this->panier->ajouterProduit(
            $request->get('id'),
            $request->get('userId'),
            $request->get('libelle'),
            $request->get('qte'),
            $request->get('prixu'),
            $request->get('prixu') * $request->get('qte'),
            $request->get('devise'),
            $request->get('dates'),
            $request->get('image'),
            $request->get('adresse'),
            $request->get('email'),
            $this->service_manager->getVendeurData($request->get('userId')),
            $request->get('name'),
            $request->get('categorie'),
            $request->get('sousCategorie'),
            $request->get('livraison'),
            $request->get('etat')
        );
        $this->requestStack->getSession()->set('taille', $this->panier->nombreProduit());
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/annonces/deleteItem/{_locale}/", name="deleteitem", requirements={"_locale": "fr"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteItemInCart(Request $request)
    {
        //nombre d'annonce dans le panier
        $this->panier->supprimerArticle($request->get('id'));
        $this->requestStack->getSession()->set('taille', 0);
        return $this->redirectToRoute('annonces_panier_user');
    }

    /**
     * @Route("/{_locale}/annonces/wishlist/add", name="add_remove_to_user_wishlist", requirements={"_locale": "fr"})
     * @param Request $request
     * @return JsonResponse
     */
    public function add_to_user_wishlist(Request $request)
    {
        if ($this->getUser()) {
            $data = $this->service_manager->readUserMetaByAllKey($this->getUser()->getId(),
                'annonce_wishlist',
                $request->get('annonce_id'),
                $request->get('annonce_id'));
            if ($data) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
                return new JsonResponse(['status' => 'success',
                    'color' => 'black',
                    'message' => 'Cette annonce a été supprimée de la wishlist',
                    'data' => $data]);
            } else {
                $data = $this->service_manager->createUserMeta($this->getUser()->getId(), 'annonce_wishlist', $request->get('annonce_id'));
                return new JsonResponse(['status' => 'success',
                    'color' => 'red', 'message' => 'Enregistrement effectué avec succès', 'data' => $data]);
            }
        } else {
            return new JsonResponse(['status' => 401, 'color' => null,
                'message' => 'Une erreur est survenue', 'data' => null]);
        }
    }

}
