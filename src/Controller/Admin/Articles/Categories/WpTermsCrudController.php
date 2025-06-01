<?php

namespace App\Controller\Admin\Articles\Categories;

use App\Entity\WpTerms;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Entity\ExtTranslationWpPosts;
use App\Entity\WpPostmeta;
use App\Entity\User;
use App\Entity\WpUsermeta;

class WpTermsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $requestStack;
    private $service_manager;

    public function __construct(ServiceManager $service_manager, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return WpTerms::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('termId', 'Id'),
            TextField::new('name', 'Titre'),
            TextField::new('slug', 'Slug'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Catégories')
            ->setPageTitle('detail', 'Détails Catégorie')
            ->setPageTitle('edit', 'Modifier Catégorie')
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function index(AdminContext $context)
    {
        $result = $this->service_manager->postCategorie('category', '');
        return $this->render('admin/Articles/Categories/index.html.twig', [
            'cat' => $result,
            'post_type' => 'category',
        ]);
    }
}
