<?php

namespace App\Controller\Admin\Annonces\Annonces;

use App\Entity\User;
use App\Entity\WpPosts;
use App\Service\Export\CsvAnnoncesExporter;
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
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

class WpPostsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $requestStack;
    private $service_manager;

    public function __construct(ServiceManager $service_manager, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return WpPosts::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Annonces')
            ->setPageTitle('detail', 'Détails Annonce')
            ->setPageTitle('edit', 'Modifier Annonce')
            ->setEntityLabelInSingular('Annonce')
            ->setEntityLabelInPlural('Annonces')
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'Id'),
            IdField::new('postAuthor', 'Auteur')->setTemplatePath('admin/Annonces/Annonces/Fields/auteur.html.twig'),
            IdField::new('id', 'Prestation')->setTemplatePath('admin/Annonces/Annonces/Fields/type_prestation.html.twig'),
            IdField::new('id', 'Catégorie')->setTemplatePath('admin/Annonces/Annonces/Fields/categorie.html.twig'),
            TextField::new('postTitle', 'Titre'),
            TextField::new('postName', 'Prix')->setTemplatePath('admin/Annonces/Annonces/Fields/prix.html.twig'),
            TextField::new('postStatus', 'Statut')->setTemplatePath('admin/Annonces/Annonces/Fields/status.html.twig'),
            DateField::new('postDate', 'Date'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $export = Action::new('export')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('export')
                    ->generateUrl();
            })
            ->setIcon('fa fa-download')
            ->linkToCrudAction('export')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL)->add(Crud::PAGE_INDEX, $export);
    }

    public function export(AdminContext $context, CsvAnnoncesExporter $csvExporter)
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, 'annonces.csv');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response = $this->get(EntityRepository::class)->createQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response->andWhere("entity.postType = 'product' and entity.postParent = '0'");
        return $response;
    }

    public function new(AdminContext $context)
    {
        $index_url = $this->adminUrlGenerator->setController(WpPostsCrudController::class)->setAction(Action::INDEX)->generateUrl();
        $user = $this->em->getRepository(User::class)->findAll();
        return $this->render('admin/Annonces/Annonces/new.html.twig', [
            'categorie' => $this->service_manager->postCategorieWithMultilang("product_cat", 0),
            'user'=> $user,
            'index_url' => $index_url
        ]);
    }

    public function edit(AdminContext $context)
    {
        $index_url = $this->adminUrlGenerator->setController(WpPostsCrudController::class)->setAction(Action::INDEX)->generateUrl();
        $name = $this->em->getRepository(WpPosts::class)->find($context->getRequest()->query->get('entityId'))->getPostName();
        $user = $this->em->getRepository(User::class)->findAll();
        $detailsAnnonce = $this->service_manager->readAllAnnonceDataByName($name);

        return $this->render('admin/Annonces/Annonces/edit.html.twig', [
            'a'=>$detailsAnnonce,
            'categorie' => $this->service_manager->postCategorieWithMultilang("product_cat", 0),
            'user'=> $user,
            'scategorie' => $this->service_manager->postCategorieWithMultilang("product_cat", $detailsAnnonce['parent']),
            'index_url' => $index_url
        ]);
    }

    public function detail(AdminContext $context)
    {
        $name = $this->em->getRepository(WpPosts::class)->find($context->getRequest()->query->get('entityId'))->getPostName();
        $url= $this->generateUrl('annonces_detailsAnnonce', array('id' => $name));
        return $this->redirect($url);
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
