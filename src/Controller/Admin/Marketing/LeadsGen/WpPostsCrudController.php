<?php

namespace App\Controller\Admin\Marketing\LeadsGen;

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
        return $this->render('admin/Marketing/LeadsGen/all_leads_gen.html.twig', [
            'ops' => $this->service_manager->getAllLeadsGen(),
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $ops = $this->service_manager->getOneLeadsGen($context->getRequest()->query->get('entityId'));
        return $this->render('admin/Marketing/LeadsGen/edit_leads_gen.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'ops' => $ops,
            'lang' => $context->getRequest()->getLocale()
        ]);
    }


    public function new(AdminContext $context)
    {
        return $this->render('admin/Marketing/LeadsGen/create_leads_gen.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'lang' => $context->getRequest()->getLocale()
        ]);
    }

    public function detail(AdminContext $context)
    {
        return $this->render('admin/Marketing/LeadsGen/details_leads_gen.html.twig', [
            'ops' => $this->service_manager->getOneLeadsGen($context->getRequest()->query->get('entityId')),
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'lang' => $context->getRequest()->getLocale()
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
