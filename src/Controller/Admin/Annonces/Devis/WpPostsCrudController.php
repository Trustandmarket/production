<?php

namespace App\Controller\Admin\Annonces\Devis;

use App\Entity\WpPosts;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;

class WpPostsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $service_manager;

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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Devis')
            ->setPageTitle('detail', 'Détails Devis')
            ->setEntityLabelInSingular('Devis')
            ->setEntityLabelInPlural('Devis')
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'Identifiant')->hideOnForm(),
            IdField::new('postAuthor', 'Auteur')->setTemplatePath('admin/Annonces/Annonces/Fields/auteur.html.twig')->hideOnForm(),
            IdField::new('id', 'Client')->setTemplatePath('admin/Annonces/Devis/Fields/client.html.twig')->hideOnForm(),
            TextField::new('postTitle', 'Titre'),
            ChoiceField::new('postStatus', 'Statut')->setTemplatePath('admin/Annonces/Annonces/Fields/status.html.twig')->setChoices([
                'Brouillon' => 'devis-draft',
                'En attente' => 'devis-pending',
                'En Cours' => 'in-progress',
                'Terminé' => 'ended',
                'Rejeté' => 'rejected',
            ]),
            DateField::new('postDate', 'Date'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $response = $this->get(EntityRepository::class)->createQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response->where("entity.postType = 'devis' and entity.postParent = '0' and entity.pinged = 'devis'");

        return $response;
    }

    public function detail(AdminContext $context)
    {
        return $this->redirectToRoute('annonces_detailsDevis', ['id' => $context->getRequest()->query->get('entityId')]);
        /*$detailsAnnonce = $this->service_manager->readAllDevisData($context->getRequest()->query->get('entityId'));
        return $this->render('annonces/detailsDevis.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'detailsAnnonce' => $detailsAnnonce,
        ]);*/
    }
}
