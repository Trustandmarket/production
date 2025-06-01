<?php

namespace App\Controller\Admin\Livraisons\GererCriteres;

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
        $criteres = $this->em->getRepository(WpPosts::class)->findBy(['postType' => 'critere_livraison'], ['guid' => 'ASC']);
        $livraisons = $this->em->getRepository(WpPosts::class)->findBy(['postType' => 'post_livraison'], ['id' => 'DESC']);
        //dd($criteres);
        return $this->render('admin/Livraisons/GererCriteres/all_criteres_type.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'livraisons' => $livraisons,
            'criteres' => $criteres,
            'lang' => $context->getRequest()->getLocale(),
            'state' => 'create',
        ]);
    }

    public function edit(AdminContext $context)
    {
        $critere = $this->em->getRepository(WpPosts::class)
        ->findOneBy([
            'postType' => 'critere_livraison',
            'id' => $context->getRequest()->query->get('entityId'),
        ]);
        //dd($critere);
        return $this->render('admin/Livraisons/GererCriteres/edit_criteres_type.html.twig', [
            'categories' => $this->service_manager->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'critere' => $critere,
            'lang' => $context->getRequest()->getLocale(),
            'state' => 'edit',
        ]);
    }
}
