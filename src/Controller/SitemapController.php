<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ServiceManager;
use App\Entity\WpPosts;

class SitemapController extends AbstractController
{
    private $service_manager;
    private $entityManager;
    public function __construct(EntityManagerInterface $entityManager, ServiceManager $service_manager)
    {
        $this->entityManager = $entityManager;
        $this->service_manager = $service_manager;
    }
    /**
     * @Route("/sitemap.xml", name="sitemap", defaults={"_format"="xml"})
     */
    public function index(Request $request)
    {
        $hostname = $request->getSchemeAndHttpHost();
        $urls = [];
        //Urls statiques
        $urls[] = ['loc' => $this->generateUrl('index')];
        $urls[] = ['loc' => $this->generateUrl('app_register')];
        $urls[] = ['loc' => $this->generateUrl('app_login')];
        // Others statics pages
        $urls[] = ['loc' => $this->generateUrl('experience_index')];
        $urls[] = ['loc' => $this->generateUrl('project_index')];
        //Newsletter
        $urls[] = ['loc' => $this->generateUrl('app_newsletter')];
        //Les categories d'articles
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'photographe', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'musiciens', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'studio-denregistrement', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'groupes-de-musique', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'chorale-gospel', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'studio-photo', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'realisation-video', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'evenementiels', 'services_proposes' => '','code_postal_ville' => '' ])];
        $urls[] = ['loc' => $this->generateUrl('annonces_listeAnnonces', ['type_prestation' => 'studio-video', 'services_proposes' => '','code_postal_ville' => '' ])];
        //Menus du footer
        $footers = $this->service_manager->naveMenuItem(18);
        foreach ($footers as $footer) {
            if ($footer->getId() == 7453) {
                $urls[] = ['loc' => $this->generateUrl('blog_index')];
            } elseif ($footer->getId() == 4683) {
                $urls[] = ['loc' => $this->generateUrl('experience_envoyez_commentaires')];
            } else {
                $urls[] = ['loc' => $this->generateUrl('site_navigation', ['navigation' => $footer->getPostName()])];
            }
        }
        //Fin menu du footer
        //Urls dynamiques

        //Articles du blog
        foreach ($this->service_manager->getAllBlog() as $articles) {
            $images = [
                'loc' => $articles['imagePost'],
                'title' => $articles['title']
            ];

            $urls[] = [
                'loc' => $this->generateUrl('blog_details_postBlog', [
                    'slug' => $articles['post_name']
                ]),
                'image' => $images,
                'lastmod' => explode(' ', $articles['post_modified_gmt'])['0']
            ];
        }
        // FIN BLOG
        //Annonces publies
        $annonces = $this->service_manager->getAllAnnoncesForSitemap();
        foreach ($annonces as $product) {
            $img = $this->service_manager->getAllSecondImageForAnnouces($product->getId());
            $img = reset($img);
            if(!$img){
                $img = $this->service_manager->getAllImageForAnnouces($product->getId());
                $img = reset($img);
            }
            $images = [
                'loc' => '',
                'title' => $product->getPostTitle()
            ];
            if($img){
                $images = [
                    'loc' => $img->getGuid(),
                    'title' => $product->getPostTitle()
                ];
            }
            $urls[] = [
                'loc' => $this->generateUrl('annonces_detailsAnnonce', ['id' => $product->getPostName()]),
                'image' => $images,
                'lastmod' => $product->getPostDate()->format('Y-m-d')
            ];
        }
        //Marketing Ops speciales
        $ops = $this->entityManager->getRepository(WpPosts::class)->findOneBy(['postType' =>'marketing_actualite',
        'postStatus' => 'publish'], ['id' => 'DESC']);
        if ($ops) {
            $urls[] = ['loc' => str_replace($hostname,'',$ops->getGuid())];
        }
        //Marketing Promo et Com
        $promos_coms = $this->service_manager->getAllPromoCom();
        foreach ($promos_coms as $promos_com) {
            $urls[] = ['loc' => $this->generateUrl('marketing_details_promo_com', ['slug' => $promos_com['slug']])];
        }
        //Marketing Leads Gen
        $leads_gens = $this->service_manager->getAllLeadsGen();
        foreach ($leads_gens as $leads_gen) {
            $urls[] = ['loc' => $this->generateUrl('marketing_details_leads_gen', ['slug' => $leads_gen['slug']])];
        }
        //FIN Annonces publies

        //Sous Menus du footer A propos
        $idPage = $this->entityManager->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => 'a-propos',
                'postType' => 'nav_menu_item',
            ]);
        $ida = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => 'a-propos-de-trust',
                'postType' => 'nav_menu_item',
            ])->getId();
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
        foreach ($smenup as $menu) {
            if ($menu->getId() != 480 && $menu->getPostName() != 'a-propos-de-trust') {
                $urls[] = ['loc' => $this->generateUrl('page_navigation', ['idPage' => 'a-propos','id' => $menu->getPostName()])];
            }
        }
        //Fin Sous menu du footer a propos

        //Sous Menus du footer Aide et conditions
        $subMenuArrays = ['aide','nos-conditions'];
        foreach ($subMenuArrays as $subMenuArray) {
            $idPage = $this->entityManager->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => $subMenuArray,
                'postType' => 'nav_menu_item',
            ]);

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
            foreach ($smenup as $menu) {
                if ($menu->getId() != 3742 && $menu->getPostName() != 'le-centre-d-aide') {
                    $urls[] = ['loc' => $this->generateUrl('page_navigation', ['idPage' => $subMenuArray,'id' => $menu->getPostName()])];
                }
            }
        }

        //Fin Sous menu du footer aide
        $response = new Response(
            $this->renderView('sitemap/index.html.twig', [
                'urls' => $urls,
                'hostname' => $hostname
            ]),
            200
        );
        $response->headers->set('Content-Type', 'text/xml');
        return $response;
    }
}
