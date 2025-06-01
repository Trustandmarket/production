<?php

namespace App\Controller\Admin\CategoriesProduits;

use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

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
            ->setPageTitle('index', 'Catégories de produits')
            ->setPageTitle('edit', 'Modifier une Catégorie')
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
        ;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DETAIL);
    }

    public function index(AdminContext $context)
    {
        return $this->render(
            'admin/CategoriesProduits/all_category_product.html.twig',
            [
                'categories' => $this->service_manager->postCategorieWithMultilang('prestation_type', 0),
                'cat_produits' => $this->service_manager->postCategorieProductWithMultilang('prestation_type', 'product_cat', 0),
                'lang' => $context->getRequest()->getLocale(),
            ]
        );
    }

    public function edit(AdminContext $context)
    {
        $taxonomy_category = $this->em->getRepository(WpTermTaxonomy::class)->findOneByTermTaxonomyId($context->getRequest()->query->get('entityId'));
        $category = null;
        if ($taxonomy_category) {
            $category = $this->em->getRepository(WpTerms::class)->findOneByTermId($taxonomy_category->getTermId());
            return $this->render('admin/CategoriesProduits/edit_category.html.twig', [
                'category' => $category
            ]);
        } else {
            $route = $context->getRequest()->headers->get('referer');
            return $this->redirect($route);
        }
    }
}
