<?php

namespace App\Controller\Admin\ToutesCategories;

use App\Entity\WpTermTaxonomy;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Entity\WpTerms;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class WpTermTaxonomyCrudController extends AbstractCrudController
{
    private $em;
    private $adminUrlGenerator;
    private $service_manager;
    public function __construct(ServiceManager $service_manager, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }
    public static function getEntityFqcn(): string
    {
        return WpTermTaxonomy::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DETAIL);
    }

    public function index(AdminContext $context)
    {
        $result = $this->service_manager->postCategorie1('product_cat');

        return $this->render('admin/ToutesCategories/index.html.twig', [
            'cat' => $result,
            'post_type' => 'product_cat',
        ]);
    }
}
