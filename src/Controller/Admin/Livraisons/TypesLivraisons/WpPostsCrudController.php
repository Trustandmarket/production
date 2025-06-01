<?php

namespace App\Controller\Admin\Livraisons\TypesLivraisons;

use App\Entity\WpPosts;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpTerms;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ServiceManager;

class WpPostsCrudController extends AbstractCrudController
{
    private $em;
    private $adminUrlGenerator;
    public function __construct(ServiceManager $service_manager, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des critères')
            ->setPageTitle('edit', 'Modifier un critère')
        ;
    }

    public static function getEntityFqcn(): string
    {
        return WpPosts::class;
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
        $livraisons = $this->em->getRepository(WpPosts::class)->findBy(['postType' => 'post_livraison'], ['id' => 'DESC']);
        $criteres = $this->em->getRepository(WpPosts::class)->findBy(['postType' => 'critere_livraison'], ['guid' => 'ASC']);
        return $this->render('admin/Livraisons/TypesLivraisons/all_deliveries_type.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'livraisons' => $livraisons,
            'criteres' => $criteres,
            'state' => 'create',
        ]);
    }

    public function edit(AdminContext $context)
    {
        $livraison = $this->em->getRepository(WpPosts::class)
            ->findOneBy([
                'postType' => 'post_livraison',
                'id' => $context->getRequest()->query->get('entityId'),
            ]);
        $criteres = $this->em->getRepository(WpPosts::class)->findBy(['postType' => 'critere_livraison'], ['guid' => 'ASC']);

        return $this->render(
            'admin/Livraisons/TypesLivraisons/edit_deliveries_type.html.twig',
            [
                'categories' => $this->service_manager->postCategorieWithMultilang(
                    'product_cat',
                    0
                ),
                'criteres' => $criteres,
                'livraison' => $livraison,
                'lang' => $context->getRequest()->getLocale(),
                'state' => 'edit',
            ]
        );
    }
}
