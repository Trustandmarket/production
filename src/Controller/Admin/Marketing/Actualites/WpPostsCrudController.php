<?php

namespace App\Controller\Admin\Marketing\Actualites;

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
        $actualites = $this->em->getRepository(WpPosts::class)->findBy(['postType' => 'marketing_actualite','postStatus' => 'publish'], ['id' => 'DESC']);
        //dd($actualites);
        return $this->render('admin/Marketing/Actualites/all_actualite.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'actualites' => $actualites,
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $actualite = $this->em->getRepository(WpPosts::class)->find($context->getRequest()->query->get('entityId'));
        return $this->render('admin/Marketing/Actualites/edit_actualite.html.twig', [
            'actualite' => $actualite,
            'lang' => $context->getRequest()->getLocale()
        ]);
    }


    public function new(AdminContext $context)
    {
        return $this->render('admin/Marketing/Actualites/create_actualite.html.twig', ['lang' => $context->getRequest()->getLocale()]);
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
