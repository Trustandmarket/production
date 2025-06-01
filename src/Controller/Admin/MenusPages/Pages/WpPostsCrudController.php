<?php

namespace App\Controller\Admin\MenusPages\Pages;

use App\Entity\WpPostmeta;
use App\Entity\WpPosts;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

class WpPostsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $requestStack;
    private $service_manager;

    public function __construct(ServiceManager $service_manager, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }
    public static function getEntityFqcn(): string
    {
        return WpPosts::class;
    }

    public function index(AdminContext $context)
    {
        if ($context->getRequest()->query->get('id') > 0) {
            $menu = $this->service_manager->readPosts($context->getRequest()->query->get('id'));
            $menuMeta = $this->service_manager->readPostMeta(
                $menu->getId(),
                '_menu_item_menu_item_parent'
            );
            if ($menuMeta && $menuMeta->getMetaValue() > 0) {
                $sousMenu = $this->service_manager->naveMenuItem($menuMeta->getMetaValue());
            } else {
                $sousMenu = $this->service_manager->naveMenuItem($context->getRequest()->query->get('id'));
            }
            $this->smenup = [];
            for ($i = 0; $i < sizeof($sousMenu); $i++) {
                if (
                    $this->service_manager->readPostMeta(
                        $sousMenu[$i]->getId(),
                        '_menu_item_menu_item_parent'
                    )
                ) {
                    $idp = $this->service_manager
                        ->readPostMeta(
                            $sousMenu[$i]->getId(),
                            '_menu_item_menu_item_parent'
                        )
                        ->getMetaValue();
                } else {
                    $idp = 0;
                }
                if ($idp == 0) {
                    $this->smenup[$i] = $sousMenu[$i];
                } else {
                    $sousMenu[$i]->setPostParent($idp);
                }
            }
            if ($context->getRequest()->query->get('id') == 1221) {
                $this->smenup = array_merge([$menu], $this->smenup);
            }
        }
        return $this->render('admin/MenusPages/sous_menu.html.twig', [
            'sous_menu' => $sousMenu,
            'sous_menu_parent' => $this->smenup,
            'tsm' => sizeof($sousMenu),
            'tsmp' => sizeof($this->smenup),
            'menu' => $menu,
        ]);
    }

    public function new(AdminContext $context)
    {
        if ($context->getRequest()->query->get('id') > 0) {
            $menuMeta = $this->service_manager->readPostMeta(
                $context->getRequest()->query->get('id'),
                '_menu_item_menu_item_parent'
            );
            if ($menuMeta && $menuMeta->getMetaValue() > 0) {
                $sousMenu = $this->service_manager->naveMenuItem($menuMeta->getMetaValue());
            } else {
                $sousMenu = $this->service_manager->naveMenuItem($context->getRequest()->query->get('id'));
            }
            $this->smenup = [];
            for ($i = 0; $i < sizeof($sousMenu); $i++) {
                $p = $this->service_manager->readPostMeta(
                    $sousMenu[$i]->getId(),
                    '_menu_item_menu_item_parent'
                );
                if ($p) {
                    $idp = $p->getMetaValue();
                } else {
                    $idp = 0;
                }

                if ($idp == 0) {
                    $this->smenup[$i] = $sousMenu[$i];
                } else {
                    $sousMenu[$i]->setPostParent($idp);
                }
            }
        }
        return $this->render('admin/MenusPages/Pages/new_page.html.twig', [
            'id_nav_menu' => $menuMeta->getMetaValue(),
            'sous_menu_parent' => $this->smenup,
            'idmenu' => $context->getRequest()->query->get('id'),
            'menu_order' => sizeof($sousMenu) + 1,
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $image_page_dynamique = null;
        if ($context->getRequest()->query->get('id') > 0) {
            $pageId = $this->service_manager
                ->readPostMeta($context->getRequest()->query->get('id'), '_menu_item_object_id')
                ->getMetaValue();
            $page = $this->service_manager->readPosts($pageId);
            $menu = $this->service_manager->readPosts($context->getRequest()->query->get('id'));
            $meta_desc = $this->service_manager->readPostMeta($pageId, 'meta_desc');
            $image_page_dynamique = $this->service_manager->readPostMeta(
                $page->getId(),
                'page_dynamique_image'
            );
        } else {
            $page = $menu = new WpPosts();
            $meta_desc = $image_page_dynamique = new WpPostMeta();
        }

        return $this->render('admin/MenusPages/Pages/page.html.twig', [
            'page' => $page,
            'meta_desc' => $meta_desc,
            'image_page_dynamique' => $image_page_dynamique,
            'menu' => $menu,
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }
}
