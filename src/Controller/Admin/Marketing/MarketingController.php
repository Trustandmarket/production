<?php

// src/App/Controller/AdminController.php

namespace App\Controller\Admin\Marketing;

use Cocur\Slugify\Slugify;
use DateTime;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use App\Entity\{wp_users, WpPosts, WpPostmeta, WpOptions, WpUsermeta, WpTerms, WpTermTaxonomy};
use App\Service\ServiceManager;
use App\Service\Payment;
use App\Controller\Admin\Marketing\Actualites\WpPostsCrudController as ActualitesCrudController;
use App\Controller\Admin\Marketing\OpsSpeciales\WpPostsCrudController as OpsSpecialesCrudController;
use App\Controller\Admin\Marketing\LeadsGen\WpPostsCrudController as LeadsGenCrudController;
use App\Controller\Admin\Marketing\PromoComs\WpPostsCrudController as PromoComsCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;

class MarketingController extends AbstractController
{
    private $entityManager;
    private $sm;
    private $payment;
    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $sm,
        Payment $payment
    ) {
        $this->entityManager = $entityManager;
        $this->sm = $sm;
        $this->payment = $payment;
    }


    /**
     * @Route("/marketing/all_actualite/{etat}", name="marketing_actualite_list")
     */
    public function all_actualite(Request $request)
    {
        $actualites = $this->entityManager->getRepository(WpPosts::class)->findBy(['postType' => 'marketing_actualite','postStatus' => $request->get('etat')], ['id' => 'DESC']);
        //dd($actualites);
        return $this->render('admin/Marketing/Actualites/all_actualite.html.twig', [
            'categories' => $this->sm->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'actualites' => $actualites,
            'lang' => $request->getLocale(),
        ]);
    }

    /**
     * @Route("/marketing/update_actualite", name="marketing_actualite_update", methods="POST")
     * @param Request $request
     * @param AdminUrlGenerator $adminUrlGenerator
     * @return
     */
    public function marketing_update_actualite(Request $request, AdminUrlGenerator $adminUrlGenerator)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $actualite = $this->entityManager->getRepository(WpPosts::class)->find($request->get('id'));
        $actualite->setPostTitle($request->get('titre'));
        $actualite->setPostContent($request->get('description'));
        $actualite->setPostModified($date);
        $actualite->setPostModifiedGmt($date);
        $actualite->setPostStatus($request->get('statut'));
        $actualite->setPostName($slugify->slugify($request->get('titre')));
        $actualite->setPingStatus($request->get('type'));
        $actualite->setGuid($request->get('recherche_contenu_url'));
        $actualite->setToPing($request->get('recherche_contenu'));

        $this->entityManager->persist($actualite);
        $this->entityManager->flush();

        $url = $adminUrlGenerator
            ->setController(ActualitesCrudController::class)
            ->setAction('index')
            ->generateUrl();
        return $this->redirect($url);
    }

    /**
     * @Route("/marketing/create_actualite", name="marketing_actualite_create", methods="POST")
     * @param Request $request
     * @param AdminUrlGenerator $adminUrlGenerator
     * @return
     */
    public function marketing_create_actualite(Request $request, AdminUrlGenerator $adminUrlGenerator)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $this->sm->createPosts1(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('description'),
            $request->get('titre'),
            $request->get('titre'),
            $request->get('statut'),
            'open',
            $request->get('type'),
            $request->get('titre'),
            $slugify->slugify($request->get('titre')),
            $request->get('recherche_contenu'),
            $slugify->slugify($request->get('titre')),
            $date,
            $date,
            $slugify->slugify($request->get('titre')),
            0,
            $request->get('recherche_contenu_url'),
            0,
            'marketing_actualite',
            'marketing_actualite',
            0,
            0
        );

        $url = $adminUrlGenerator
        ->setController(ActualitesCrudController::class)
        ->setAction('index')
        ->generateUrl();
        return $this->redirect($url);
    }

    //Ops Speciales

    /**
     *
     * @Route("/marketing/create_ops_speciales", name="marketing_ops_speciales_create", methods="POST")
     * libelle => titre, meta_description => post_excerpt
     * @param Request $request
     * @return Response
     */
    public function marketing_create_ops_speciales(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $this->sm->createPosts(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('description'),
            $request->get('libelle'),
            $request->get('meta_description'),
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('libelle')),
            0,
            0,
            $date,
            $date,
            '',
            0,
            '',
            0,
            'ops_speciales_post',
            '',
            0,
            0,
            $request->getLocale()
        );
        //Mettre a jour les Images liees
        $id_images = explode(',', $request->get('annonces_id'));
        for ($i = 0; $i < sizeof($id_images); $i++) {
            $image = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id_images[$i]);
            if ($image) {
                $image->setPostParent($id);
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }
        //Stocker les metas categories a afficher
        $lesCategories = [];
        if ($request->get('cat_afficher')) {
            for ($i = 0; $i < sizeof($request->get('cat_afficher')); $i++) {
                $this->sm->createPostMeta(
                    $id,
                    'ops_speciales_categories_show',
                    $request->get('cat_afficher')[$i],
                    $request->getLocale()
                );
            }
        }

        //Stocker chapeau
        $this->sm->createPostMeta(
            $id,
            'ops_speciales_chapeau',
            $request->get('chapeau'),
            $request->getLocale()
        );
        //Stocker les metas descriptions
        $this->sm->createPostMeta(
            $id,
            'ops_speciales_meta_description',
            $request->get('meta_description'),
            $request->getLocale()
        );
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     *
     * @Route("/marketing/edit_ops_speciales", name="marketing_ops_speciales_edit", methods="POST")
     * libelle => titre, meta_description => post_excerpt
     *
     */
    public function marketing_edit_ops_speciales(Request $request)
    {
        $date = new DateTime();
        $id = $request->get('id');
        $slugify = new Slugify();

        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if ($post) {
            $post->setPostTitle($request->get('libelle'));
            $post->setPostName($slugify->slugify($request->get('libelle')));
            $post->setPostContent($request->get('description'));
            $post->setPostModified($date);
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }
        //Mettre a jour les Images liees
        $id_images = explode(',', $request->get('annonces_id'));
        for ($i = 0; $i < sizeof($id_images); $i++) {
            $image = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id_images[$i]);
            if ($image) {
                $image->setPostParent($id);
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }
        //Stocker les metas categories a afficher
        //Categories
        $lesCategories = [];
        if ($request->get('cat_afficher')) {
            $lesCategories = $request->get('cat_afficher');
        }
        //Supprimmer les anciennes categories
        $del = $this->entityManager
        ->getRepository(WpPostmeta::class)
        ->findBy([
            'postId' => $id,
            'metaKey' => 'ops_speciales_categories_show',
        ]);
        for ($i=0; $i < sizeof($del); $i++) {
            $this->entityManager->remove($del[$i]);
            $this->entityManager->flush();
        }
        for ($i = 0; $i < sizeof($lesCategories); $i++) {
            $meta = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findOneBy([
                    'postId' => $id,
                    'metaValue' => $lesCategories[$i],
                    'metaKey' => 'ops_speciales_categories_show',
                ]);
            if ($meta) {
                $this->sm->updatePostMeta(
                    $meta->getMetaId(),
                    $id,
                    'ops_speciales_categories_show',
                    $lesCategories[$i],
                    $request->getLocale()
                );
            } else {
                $this->sm->createPostMeta(
                    $id,
                    'ops_speciales_categories_show',
                    $lesCategories[$i],
                    $request->getLocale()
                );
            }
        }
        //Meta description
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'ops_speciales_meta_description',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'ops_speciales_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'ops_speciales_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        }
        //Chapeau
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'ops_speciales_chapeau',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'ops_speciales_chapeau',
                $request->get('chapeau'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'ops_speciales_chapeau',
                $request->get('chapeau'),
                $request->getLocale()
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/marketing/create_ops_speciales/add_announce", name="marketing_ops_speciales_add_announce", methods="POST")
     */
    public function marketing_ops_speciales_add_announce(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $r = 0;
        $src = '';
        $annonces = [];

        //portfolio
        if ($request->files->get('file')) {
            $file = $request->files->get('file');
            $src = $this->sm->uploadImageInFolder(
                $file,
                $this->getParameter('ops_speciales_images_directory')
            );
        }

        // Prix => toPing; Vendeur = post_excert, livrable => pinged
        $id = $this->sm->createPosts1(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('titre'),
            $request->get('titre'),
            $request->get('vendeur'),
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('titre')),
            $request->get('prix'),
            $request->get('livrable'),
            $date,
            $date,
            '',
            0,
            $src,
            0,
            'ops_speciales_image',
            '',
            0,
            0,
            $request->getLocale()
        );

        $html = $this->renderView(
            'admin/Marketing/Partials/result_add_announce.html.twig',
            [
                'titre' => $request->get('titre'),
                'slug' => $slugify->slugify($request->get('titre')),
                'src' => $src,
                'id' => $id,
            ]
        );

        $r = 1;
        return new JsonResponse([
            'html' => $html,
            'response' => $r,
            'url' => $src,
            'id' => $id,
        ]);
    }
    //End Ops

    /**
     *
     * @Route("/marketing/create_promo_com", name="marketing_promo_com_create", methods="POST")
     * libelle => titre, meta_description => post_excerpt
     * @param Request $request
     * @return Response
     */
    public function marketing_create_promo_com(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $this->sm->createPosts(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('description'),
            $request->get('libelle'),
            $request->get('meta_description'),
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('libelle')),
            0,
            0,
            $date,
            $date,
            '',
            0,
            '',
            0,
            'promo_com_post',
            '',
            0,
            0,
            $request->getLocale()
        );
        //Mettre a jour les Images liees
        $id_images = explode(',', $request->get('annonces_id'));
        for ($i = 0; $i < sizeof($id_images); $i++) {
            $image = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id_images[$i]);
            if ($image) {
                $image->setPostParent($id);
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }
        //Stocker les metas categories a afficher
        $lesCategories = [];
        if ($request->get('cat_afficher')) {
            $lesCategories = $request->get('cat_afficher');
        }
        for ($i = 0; $i < sizeof($lesCategories); $i++) {
            $this->sm->createPostMeta(
                $id,
                'promo_com_categories_show',
                $lesCategories[$i],
                $request->getLocale()
            );
        }
        //Stocker chapeau
        $this->sm->createPostMeta(
            $id,
            'promo_com_chapeau',
            $request->get('chapeau'),
            $request->getLocale()
        );
        //Stocker les metas descriptions
        $this->sm->createPostMeta(
            $id,
            'promo_com_meta_description',
            $request->get('meta_description'),
            $request->getLocale()
        );
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     *
     * @Route("/marketing/edit_promo_com", name="marketing_promo_com_edit", methods="POST")
     * libelle => titre, meta_description => post_excerpt
     *
     */
    public function marketing_edit_promo_com(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $request->get('id');
        //Mettre a jour le post
        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if ($post) {
            $post->setPostTitle($request->get('libelle'));
            $post->setPostName($slugify->slugify($request->get('libelle')));
            $post->setPostContent($request->get('description'));
            $post->setPostModified($date);
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }
        //Mettre a jour les Images liees
        $id_images = explode(',', $request->get('annonces_id'));
        for ($i = 0; $i < sizeof($id_images); $i++) {
            $image = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id_images[$i]);
            if ($image) {
                $image->setPostParent($id);
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }
        //Stocker les metas categories a afficher
        //Categories
        $lesCategories = [];
        if ($request->get('cat_afficher')) {
            $lesCategories = $request->get('cat_afficher');
        }
        //Supprimmer les anciennes categories
        $del = $this->sm->deleteAllPostmetaByPostIdKey(
            $id,
            'promo_com_categories_show'
        );
        for ($i = 0; $i < sizeof($lesCategories); $i++) {
            $meta = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findOneBy([
                    'postId' => $id,
                    'metaValue' => $lesCategories[$i],
                    'metaKey' => 'promo_com_categories_show',
                ]);
            if ($meta) {
                $this->sm->updatePostMeta(
                    $meta->getMetaId(),
                    $id,
                    'promo_com_categories_show',
                    $lesCategories[$i],
                    $request->getLocale()
                );
            } else {
                $this->sm->createPostMeta(
                    $id,
                    'promo_com_categories_show',
                    $lesCategories[$i],
                    $request->getLocale()
                );
            }
        }
        //Meta description
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'promo_com_meta_description',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'promo_com_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'promo_com_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        }
        //Chapeau
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'promo_com_chapeau',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'promo_com_chapeau',
                $request->get('chapeau'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'promo_com_chapeau',
                $request->get('chapeau'),
                $request->getLocale()
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/marketing/create_promo_com/add_announce", name="marketing_promo_com_add_announce", methods="POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function marketing_promo_com_add_announce(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $r = 0;
        $src = '';
        $annonces = [];

        //portfolio
        if ($request->files->get('file')) {
            $file = $request->files->get('file');
            $src = $this->sm->uploadImageInFolder(
                $file,
                $this->getParameter('ops_speciales_images_directory')
            );
        }

        // Prix => toPing; Vendeur = post_excert, livrable => pinged
        $id = $this->sm->createPosts1(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('titre'),
            $request->get('titre'),
            '',
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('titre')),
            0,
            '',
            $date,
            $date,
            '',
            0,
            $src,
            0,
            'promo_com_image',
            '',
            0,
            0,
            $request->getLocale()
        );

        $html = $this->renderView(
            'admin/Marketing/Partials/result_add_announce.html.twig',
            [
                'titre' => $request->get('titre'),
                'slug' => $slugify->slugify($request->get('titre')),
                'src' => $src,
                'id' => $id,
            ]
        );

        $r = 1;
        return new JsonResponse([
            'html' => $html,
            'response' => $r,
            'url' => $src,
            'id' => $id,
        ]);
    }

    /**
     *
     * @Route("/{_locale}/admin/leads_gen/create", name="marketing_leads_gen_create", methods="POST")
     * libelle => titre, meta_description => post_excerpt
     *
     */
    public function marketing_create_leads_gen(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $this->sm->createPosts(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('description'),
            $request->get('libelle'),
            $request->get('meta_description'),
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('libelle')),
            0,
            0,
            $date,
            $date,
            '',
            0,
            '',
            0,
            'leads_gen_post',
            '',
            0,
            0,
            $request->getLocale()
        );
        //Mettre a jour les Images liees
        $id_images = explode(',', $request->get('annonces_id'));
        for ($i = 0; $i < sizeof($id_images); $i++) {
            $image = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id_images[$i]);
            if ($image) {
                $image->setPostParent($id);
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }
        //Stocker les metas categories a afficher
        $lesCategories = [];
        if ($request->get('cat_afficher')) {
            $lesCategories = $request->get('cat_afficher');
        }
        for ($i = 0; $i < sizeof($lesCategories); $i++) {
            $this->sm->createPostMeta(
                $id,
                'leads_gen_categories_show',
                $lesCategories[$i],
                $request->getLocale()
            );
        }
        //Stocker chapeau
        $this->sm->createPostMeta(
            $id,
            'leads_gen_chapeau',
            $request->get('chapeau'),
            $request->getLocale()
        );
        //Stocker les metas descriptions
        $this->sm->createPostMeta(
            $id,
            'leads_gen_meta_description',
            $request->get('meta_description'),
            $request->getLocale()
        );
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     *
     * @Route("/{_locale}/admin/leads_gen/edit", name="marketing_leads_gen_edit", methods="POST")
     * libelle => titre, meta_description => post_excerpt
     *
     */
    public function marketing_edit_leads_gen(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $request->get('id');
        //Mettre a jour le post
        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if ($post) {
            $post->setPostTitle($request->get('libelle'));
            $post->setPostName($slugify->slugify($request->get('libelle')));
            $post->setPostContent($request->get('description'));
            $post->setPostModified($date);
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }
        //Mettre a jour les Images liees
        $id_images = explode(',', $request->get('annonces_id'));
        for ($i = 0; $i < sizeof($id_images); $i++) {
            $image = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id_images[$i]);
            if ($image) {
                $image->setPostParent($id);
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }
        //Stocker les metas categories a afficher
        //Categories
        $lesCategories = [];
        if ($request->get('cat_afficher')) {
            $lesCategories = $request->get('cat_afficher');
        }
        //Supprimmer les anciennes categories
        $del = $this->sm->deleteAllPostmetaByPostIdKey(
            $id,
            'leads_gen_categories_show'
        );
        for ($i = 0; $i < sizeof($lesCategories); $i++) {
            $meta = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findOneBy([
                    'postId' => $id,
                    'metaValue' => $lesCategories[$i],
                    'metaKey' => 'leads_gen_categories_show',
                ]);
            if ($meta) {
                $this->sm->updatePostMeta(
                    $meta->getMetaId(),
                    $id,
                    'leads_gen_categories_show',
                    $lesCategories[$i],
                    $request->getLocale()
                );
            } else {
                $this->sm->createPostMeta(
                    $id,
                    'leads_gen_categories_show',
                    $lesCategories[$i],
                    $request->getLocale()
                );
            }
        }
        //Meta description
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'leads_gen_meta_description',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'leads_gen_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'leads_gen_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        }
        //Chapeau
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'leads_gen_chapeau',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'leads_gen_chapeau',
                $request->get('chapeau'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'leads_gen_chapeau',
                $request->get('chapeau'),
                $request->getLocale()
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/{_locale}/admin/leads_gen/add_announce", name="marketing_leads_gen_add_announce", methods="POST")
     */
    public function marketing_leads_gen_add_announce(Request $request)
    {
        $date = new DateTime();
        $slugify = new Slugify();
        $r = 0;
        $src = '';
        $annonces = [];

        //portfolio
        if ($request->files->get('file')) {
            $file = $request->files->get('file');
            $src = $this->sm->uploadImageInFolder(
                $file,
                $this->getParameter('ops_speciales_images_directory')
            );
        }

        $id = $this->sm->createPosts1(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('titre'),
            $request->get('titre'),
            '',
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('titre')),
            0,
            '',
            $date,
            $date,
            '',
            0,
            $src,
            0,
            'leads_gen_image',
            '',
            0,
            0,
            $request->getLocale()
        );

        $html = $this->renderView(
            'admin/Marketing/Partials/result_add_announce.html.twig',
            [
                'titre' => $request->get('titre'),
                'slug' => $slugify->slugify($request->get('titre')),
                'src' => $src,
                'id' => $id,
            ]
        );

        $r = 1;
        return new JsonResponse([
            'html' => $html,
            'response' => $r,
            'url' => $src,
            'id' => $id,
        ]);
    }
}
