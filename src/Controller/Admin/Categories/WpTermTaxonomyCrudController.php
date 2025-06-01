<?php

namespace App\Controller\Admin\Categories;

use App\Entity\WpTermTaxonomy;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ServiceManager;

class WpTermTaxonomyCrudController extends AbstractCrudController
{
    private $em;
    private $adminUrlGenerator;
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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Types de prestations')
            ->setPageTitle('edit', 'Modifier une Prestation')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DETAIL)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('term_taxonomy_id', 'id'),
            TextField::new('taxonomy')
        ];
    }

    public function index(AdminContext $context)
    {
        return $this->render('admin/Categories/all_prestation_type.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'prestations' => $this->service_manager->postCategorieWithMultilang('prestation_type', 0),
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }
}
