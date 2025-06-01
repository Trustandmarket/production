<?php

namespace App\Controller\Admin\Livraisons\OpeFraisGratuits;

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
        $criteres =  $this->service_manager->getAllOpsFree();

        return $this->render('admin/Livraisons/OpFraisGratuits/all_free_operation.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'criteres' => $criteres,
            'lang' => $context->getRequest()->getLocale(),
            'state' => 'create',
        ]);
    }

    public function edit(AdminContext $context)
    {
        $critere = $this->service_manager->getOneOpsFree($context->getRequest()->query->get('entityId'));
        return $this->render(
            'admin/Livraisons/OpFraisGratuits/edit_critere_free_op.html.twig',
            [
                'categories' => $this->service_manager->postCategorieWithMultilang(
                    'product_cat',
                    0
                ),
                'critere' => $critere,
                'lang' => $context->getRequest()->getLocale(),
                'state' => 'edit',
            ]
        );
    }
}
