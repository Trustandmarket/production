<?php

namespace App\Controller\Admin\ParcoursUtilisateur\Experiences;

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
        $experiences = $this->service_manager->getAllExperiencesProcess();
        return $this->render('admin/ParcoursUtilisateur/Experiences/list.html.twig', [
            'experiences' => $experiences,
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $exp = $this->service_manager->getOneUserExperiencesProcess($context->getRequest()->query->get('entityId'));

        return $this->render('admin/ParcoursUtilisateur/Experiences/edit.html.twig', [
            'exp' => $exp,
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
