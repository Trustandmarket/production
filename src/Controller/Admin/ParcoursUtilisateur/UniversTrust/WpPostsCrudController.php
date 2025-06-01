<?php

namespace App\Controller\Admin\ParcoursUtilisateur\UniversTrust;

use App\Entity\WpPosts;
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

    public function index(AdminContext $context)
    {
        return $this->render('admin/ParcoursUtilisateur/UniversTrust/index.html.twig', [
            'ops' => $this->service_manager->getAllExperiences(),
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }

    public function details(AdminContext $context)
    {
        return $this->render('admin/ParcoursUtilisateur/UniversTrust/details.html.twig', [
            'ops' => $this->service_manager->getOneExperiences($context->getRequest()->query->get('entityId')),
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $ops = $this->service_manager->getOneExperiences($context->getRequest()->query->get('entityId'));
        return $this->render('admin/ParcoursUtilisateur/UniversTrust/edit.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'ops' => $ops,
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }


    public function new(AdminContext $context)
    {
        return $this->render('admin/ParcoursUtilisateur/UniversTrust/create.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'lang' => $context->getRequest()->getLocale(),
        ]);
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
}
