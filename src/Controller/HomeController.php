<?php

namespace App\Controller;

use App\Entity\ExtTranslationWpTerms;
use App\Entity\WpOptions;
use App\Entity\WpPosts;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpUsermeta;
use App\Service\Payment;
use App\Service\ServiceManager;
use App\Service\ToolsMeta;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class HomeController extends AbstractController
{
    private $service_manager;
    private $entityManager;
    private $tools;
    private $twig;
    private $requestStack;
    private $payment;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $entityManager,
        Environment $twig,
        RequestStack $requestStack,
        Payment $payment,
        ToolsMeta $tools
    )
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->payment = $payment;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->tools = $tools;
    }

    /**
     * @Route("/", name="home")
     */
    public function index()
    {
        return $this->redirectToRoute('index', ['_locale' => 'fr']);
    }

    /**
     * @Route("/{_locale}", name="index", requirements={"_locale": "fr"})
     */
    public function accueil()
    {
        $actualite = $this->entityManager->getRepository(WpPosts::class)->findOneBy([
            'postType' => 'marketing_actualite',
            'postStatus' => 'publish'
        ], ['id' => 'DESC']);


        return $this->render('home/index.html.twig', [
            'meta_page' => $this->service_manager->getPageStatique('home'),
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'blog' => $this->service_manager->getNumberBlog(3),
            'bloc1' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_1'),
            'bloc2' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_2'),
            'bloc3' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_3'),
            'bloc4' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_4'),
            'bloc5' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_5'),
            'bloc6' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_6'),
            'bloc7' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_7'),
            'bloc8' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_8'),
            'bloc9' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_9'),
            'bloc10' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_10'),
            'bloc11' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_11'),
            'bloc12' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('accueil_bloc_12'),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'slogan_homepage' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('slogan_homepage'),
            'encart_homepage_1' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('encart_homepage_1'),
            'encart_homepage_2' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('encart_homepage_2'),
            'actualite' => $actualite,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    /**
     * @Route("/{_locale}/{idPage}/{id}", name="page_navigation", requirements={
     *     "_locale": "fr",
     *     "idPage": "aide|nos-conditions|a-propos"
     * })
     * @param Request $request
     * @return Response
     */
    public function page_navigation(Request $request)
    {
        $idPage = $this->entityManager->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => $request->get('idPage'),
                'postType' => 'nav_menu_item',
            ]);
        $ida = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => $request->get('id'),
                'postType' => 'nav_menu_item',
            ])
            ->getId();

        $menu = $this->service_manager->readPosts($idPage->getId());
        $menuMeta = $this->service_manager->readPostMeta(
            $menu->getId(),
            '_menu_item_menu_item_parent'
        );

        if ($menuMeta->getMetaValue() > 0) {
            $sousMenu = $this->service_manager->naveMenuItem(
                $menuMeta->getMetaValue()
            );
        } else {
            $sousMenu = $this->service_manager->naveMenuItem($idPage->getId());
        }
        $smenup = array();
        for ($i = 0; $i < sizeof($sousMenu); $i++) {
            $idp = $this->service_manager
                ->readPostMeta(
                    $sousMenu[$i]->getId(),
                    '_menu_item_menu_item_parent'
                )
                ->getMetaValue();
            if ($idp == 0) {
                $smenup[$i] = $sousMenu[$i];
            } else {
                $sousMenu[$i]->setPostParent($idp);
            }
        }

        $IdContenu = $this->service_manager
            ->readPostMeta($ida, '_menu_item_object_id')
            ->getMetaValue();

        //Contenu de la page
        $contenu = $this->service_manager->readPosts($IdContenu);
        //dd($contenu);
        $meta_desc = $this->service_manager->readPostMeta(
            $IdContenu,
            'meta_desc'
        );
        //Menu Parent
        $menuParent = $this->service_manager->readPosts($idPage->getId());
        //Image de la page;

        $pageImage = $this->service_manager->readPostsViaParentAndPostType(
            'attachment',
            $this->service_manager
                ->readPostMeta($sousMenu[0]->getId(), '_menu_item_object_id')
                ->getMetaValue()
        );
        //dd($sousMenu);
        return $this->render('home/content.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'sous_menu' => $sousMenu,
            'sous_menu_parent' => $smenup,
            'tsm' => sizeof($sousMenu),
            'tsmp' => sizeof($smenup),
            'menu' => $menu,
            'contenu' => $contenu,
            'meta_desc' => $meta_desc,
            'idPage' => $idPage,
            'id' => $sousMenu[0]->getId(),
            'pageImage' => $pageImage,
            'menuParent' => $menuParent,
            'pageName' => $contenu->getPostTitle(),
            'navigation' => $request->get('id'),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube')
        ]);
    }

    /**
     * @Route("/{_locale}/{navigation}", name="site_navigation", requirements={"navigation": "^((?!connexion|login|creer-un-compte|register|votre-projet|contactez-nous|envoyez-nous-vos-commentaires|login_check|logout|confirmation_email|confirm|confirmed|request|reset|lostpassword_confirmation_email|send-email|change-password|blog|annonces|paiement|utilisateur|chat|admin|dashboard|devenir-pro|profil|devis|recherche|photographes|videastes|studio-audio|musiciens|chorales|studio-de-tournage|studio-photo-video|evenementiels|sitemap.xml|operations-speciales|promo-et-com|leads-gen|univers-trust|creer-une-experience|operations|annonces|newsletter|souscription|abonnement|server-action).)*$", "_locale": "fr"})
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function nos_prestations_page(Request $request)
    {
        if ($request->get('navigation') != '') {
            $menu = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findOneBy([
                    'postTitle' => ucfirst(
                        str_replace('-', ' ', $request->get('navigation'))
                    ),
                    'postType' => 'nav_menu_item',
                ]);
            $menuMeta = $this->service_manager->readPostMeta(
                $menu->getId(),
                '_menu_item_menu_item_parent'
            );

            if ($menuMeta != null) {
                $sousMenu = $this->service_manager->naveMenuItem(
                    $menuMeta->getMetaValue()
                );
            } else {
                $sousMenu = $this->entityManager
                    ->getRepository(WpPosts::class)
                    ->findOneBy([
                        'postTitle' => ucfirst(
                            str_replace('-', ' ', $request->get('navigation'))
                        ),
                    ]);
            }
            $smenup = array();
            for ($i = 0; $i < sizeof($sousMenu); $i++) {
                $idp = $this->service_manager
                    ->readPostMeta(
                        $sousMenu[$i]->getId(),
                        '_menu_item_menu_item_parent'
                    )
                    ->getMetaValue();
                if ($idp == 0) {
                    $smenup[$i] = $sousMenu[$i];
                } else {
                    $sousMenu[$i]->setPostParent($idp);
                }
            }
        }
        // Ma modification Faire tres attention merde
        if ($request->get('navigation') == 'a-propos') {
            return $this->redirectToRoute('page_navigation', ['idPage' => 'a-propos', 'id' => 'a-propos-de-trust'], 302);
        } elseif ($request->get('navigation') == 'nos-conditions') {
            $IdContenu = $this->service_manager
                ->readPostMeta($menu->getId(), '_menu_item_object_id')
                ->getMetaValue();
        } else {
            $IdContenu = $this->service_manager
                ->readPostMeta($sousMenu[0]->getId(), '_menu_item_object_id')
                ->getMetaValue();
        }

        $contenu = $this->service_manager->readPosts($IdContenu);
        $meta_desc = $this->service_manager->readPostMeta(
            $IdContenu,
            'meta_desc'
        );

        //Menu Parent
        $menuParent = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy(['postName' => $request->get('navigation')]);

        //Image de la page;
        $postTypeImage = 'attachment';

        $pageImage = $this->service_manager->readPostsViaParentAndPostType(
            $postTypeImage,
            $IdContenu
        );

        return $this->render('home/content.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'sous_menu' => $sousMenu,
            'sous_menu_parent' => $smenup,
            'tsm' => sizeof($sousMenu),
            'tsmp' => sizeof($smenup),
            'menu' => $menu,
            'contenu' => $contenu,
            'meta_desc' => $meta_desc,
            'idPage' => $menuParent,
            'id' => $sousMenu[0]->getId(),
            'pageName' => $menuParent->getPostTitle(),
            'pageImage' => $pageImage,
            'menuParent' => $menuParent,
            'navigation' => $request->get('navigation'),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }



    /**
     * @Route("/{_locale}/home/sous_categorie", name="sous_categorie")
     * @param Request $request
     * @return Response
     */
    public function sousCategorie(Request $request)
    {
        return $this->render('admin/sous_categorie.html.twig', [
            'categorie' => $this->service_manager->postCategorie('product_cat', $request->get('id')),
        ]);
    }

    /**
     * @Route("/{_locale}/home/sous_categorie1", name="sous_categorie1")
     */
    public function sousCategorie1(Request $request)
    {
        return $this->render('admin/sous_categorie1.html.twig', [
            'categorie' => $this->service_manager->postCategorie(
                'product_cat',
                $request->get('id')
            ),
        ]);
    }


    /**
     * @Route("/{_locale}/recherche", name="recherche")
     * @param Request $request
     * @return JsonResponse
     */
    public function recherche(Request $request)
    {
        return new JsonResponse(
            $this->service_manager->search($request->get('term'))
        );
    }

    /**
     * @Route("/{_locale}/recherche-user", name="recherche-user")
     * @param Request $request
     * @return JsonResponse
     */
    public function recherche_user(Request $request)
    {
        return new JsonResponse($this->service_manager->search_user($request->get('term')));
    }

    //modification de l'abonné

    /**
     * @Route("/{_locale}/updateProfil", name="updateProfileAbonne", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function updateUserData(Request $request)
    {
        $userId = $this->getUser()->getId();

        //First Name
        $this->service_manager->updateUserMeta(
            $userId,
            'first_name',
            $request->get('first_name')
        );

        //Last name
        $this->service_manager->updateUserMeta(
            $userId,
            'last_name',
            $request->get('last_name')
        );
        //Birthday
        $this->service_manager->updateUserMeta(
            $userId,
            'bdaytime',
            $request->get('bdaytime')
        );
        //Sexe
        $this->service_manager->updateUserMeta(
            $userId,
            'sexe',
            $request->get('sexe')
        );
        //Birth Place
        $this->service_manager->updateUserMeta(
            $userId,
            'birth_place',
            $request->get('birth_place')
        );
        //Telephone
        $this->service_manager->updateUserMeta(
            $userId,
            'telephone',
            $request->get('telephone')
        );
        // Avatar
        $avatarUrl = $this->service_manager->uploadAvatar(
            $userId,
            $request->files->get('avatar'),
            $this->getParameter('avatar_directory')
        );
        if ($avatarUrl) {
            $this->service_manager->updateUserMeta(
                $userId,
                'basic_user_avatar',
                $avatarUrl
            );
            $this->requestStack->getSession()->set('avatar', $avatarUrl);
        }

        //Raison Sociale
        $this->service_manager->updateUserMeta(
            $userId,
            'raison_sociale',
            $request->get('raison_sociale')
        );
        //SIRET
        $this->service_manager->updateUserMeta(
            $userId,
            'siret',
            $request->get('siret')
        );
        //TVA
        $this->service_manager->updateUserMeta(
            $userId,
            'tva',
            $request->get('tva')
        );
        //TITRE
        $this->service_manager->updateUserMeta(
            $userId,
            'titre',
            $request->get('titre')
        );
        //Compétences
        if ($request->get('competence')) {
            $this->service_manager->updateUserMeta(
                $userId,
                'competence',
                implode(
                    ',',
                    array_column(
                        json_decode($request->get('competence')),
                        'value'
                    )
                )
            );
        }
        //region
        $this->service_manager->updateUserMeta(
            $userId,
            'region',
            $request->get('region')
        );

        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }


    public function getCategoryFromAnnounce($annonce, $locale = null)
    {
        $x = 0;
        $tempType = null;
        $categorie = $this->service_manager->getCategoryFromAnnounce($annonce);
        //dd($locale);
        if (count($categorie) > 0) {
            $cat = $this->entityManager->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy(['foreign_key' => $categorie[0]['termId'], 'field' => 'name', 'locale' => $locale]);
            if ($cat) {
                $cat = $cat->getContent();
            }
            //dd($cat);
            $parentId = $categorie[0]['parent'];
            $tempType = '';
            while ($parentId != 0) {
                $temp = $this->entityManager->getRepository(WpTermTaxonomy::class)->findOneByTermTaxonomyId($parentId);
                if ($temp) {
                    $tempType = $temp->getTaxonomy();
                }
                if ($temp && $parentId != 0) {
                    $tempTerm = $this->entityManager->getRepository(ExtTranslationWpTerms::class)
                        ->findOneBy(['foreign_key' => $temp->getTermId(), 'field' => 'name', 'locale' => $locale]);
                    if ($tempTerm) {
                        $cat = $tempTerm->getContent() . ' | ' . $cat;
                    }
                    $parentId = $temp->getParent();
                } elseif ($parentId == 0 && $temp) {
                    $cat = $temp->getDescription() . ' | ' . $cat;
                } elseif ($tempType = 'product_cat') {
                    $cat = 'Services' . ' | ' . $cat;
                }
                $x++;
            }
            return new Response($cat);
        } else {
            return new Response('Pas de Categorie');
        }
    }

    public function getAnnounceSubCategoryData($announceId)
    {
        $detailsAnnounce = $this->service_manager->readPartialAnnonceData($announceId);
        if ($detailsAnnounce) {
            return new Response($detailsAnnounce['sousCategorie']);
        } else {
            return new Response('');
        }
    }

    public function getAnnounceCategoryData($announceId)
    {
        $detailsAnnounce = $this->service_manager->readPartialAnnonceData($announceId);
        if ($detailsAnnounce) {
            return new Response($detailsAnnounce['categorieParent']);
        } else {
            return new Response('');
        }
    }

    public function getLoginRegisterImage($code)
    {
        $page_background_image = $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName($code);
        if ($page_background_image) {
            return new Response($page_background_image->getOptionValue());
        } else {
            return new Response('');
        }
    }
}
