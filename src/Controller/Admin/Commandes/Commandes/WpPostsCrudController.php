<?php

namespace App\Controller\Admin\Commandes\Commandes;

use App\Entity\WpPosts;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Entity\User;
use App\Service\Export\CsvCommandesExporter;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\Payment;
use App\Entity\WpUsermeta;

class WpPostsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $requestStack;
    /**
     * @var ServiceManager
     */
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
            ->setPageTitle('index', 'Liste des Commandes')
            ->setPageTitle('detail', 'Détail Commande')
            ->setPageTitle('edit', 'Modifier Commande')
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'Id'),
            IdField::new('guid', 'Client')->setTemplatePath('admin/Commandes/Commandes/Fields/client.html.twig'),
            IdField::new('postMimeType', 'Vendeur')->setTemplatePath('admin/Commandes/Commandes/Fields/vendeur.html.twig'),
            TextField::new('reservationPriceTtc', 'Prix TTC en EUR'),
            TextField::new('postStatus', 'Statut')->setTemplatePath('admin/Commandes/Commandes/Fields/status.html.twig'),
            DateField::new('postDate', 'Date'),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('postStatus','Statut de la reservation')->setChoices(['En cours' => 'wc-in-progress',
                'En attente' => 'wc-pending',
                'Annule' => 'wc-cancelled',
                'Echoue' => 'wc-failed',
                'Termine' => 'wc-completed']))
            ->add(TextFilter::new('postMimeType','Vendeur'))
            ;
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
        return $actions->disable(Action::NEW)
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $export);
    }

    public function export(AdminContext $context, CsvCommandesExporter $csvExporter)
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, 'commandes.csv');
    }


    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response = $this->get(EntityRepository::class)->createQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response->andWhere("entity.postType = 'shop_order' and entity.postStatus != 'wc-pending-trash' and entity.postStatus != 'wc-completed-trash' and entity.postStatus != 'wc-in-progress-trash' and entity.postStatus != 'wc-cancelled-trash'");

        return $response;
    }

    public function edit(AdminContext $context)
    {
        $id = $context->getRequest()->query->get('entityId');
        $p = $this->service_manager->postById($id);
        if ($p->getPostStatus() != 'wc-completed' && $p->getPostStatus() != 'wc-cancelled') {
            $a = $this->service_manager->readAllPost();

            return $this->render('admin/Commandes/Commandes/edit.html.twig', [
                'p' => $p,
                'u' => $this->service_manager->userById($p->getPostAuthor()),
                'usermeta' => $this->em
                    ->getRepository(WpUsermeta::class)
                    ->findBy(['userId' => $p->getPostAuthor()]),
                //'user' => $this->service_manager->getAllUser(),
                'commande' => @unserialize($p->getPostContent()),
                'annonce' => $a,
                'lang' => $context->getRequest()->getLocale(),
            ]);
        } else {
            $a = $this->service_manager->readAllPost();
            $critere = null;
            $livraison = null;
            if ($p->getPostName() != '') {
                $livraison = $this->em
                    ->getRepository(WpPosts::class)
                    ->find($p->getPostName());
                if ($livraison) {
                    $critere = $this->em
                        ->getRepository(WpPosts::class)
                        ->find($livraison->getPostParent());
                }
            }
            return $this->render('admin/Commandes/Commandes/detail.html.twig', [
                'p' => $p,
                'u' => $this->service_manager->userById($p->getPostAuthor()),
                'usermeta' => $this->em
                    ->getRepository(WpUsermeta::class)
                    ->findBy(['userId' => $p->getPostAuthor()]),
                'user' => $this->service_manager->getAllUser(),
                'commande' => @unserialize($p->getPostContent()),
                'annonce' => $a,
                'critere' => $critere,
                'livraison' => $livraison,
                'lang' => $context->getRequest()->getLocale(),
            ]);
        }
    }

    public function detail(AdminContext $context)
    {
        $id = $context->getRequest()->query->get('entityId');
        $p = $this->service_manager->postById($id);
        $a = $this->service_manager->readAllPost();
        $commande = '';
        $critere = null;
        $livraison = null;
        if ($p->getPostContent() != '') {
            $commande = @unserialize($p->getPostContent());
        }
        if ($p->getPostName() != '') {
            $livraison = $this->em
                ->getRepository(WpPosts::class)
                ->find($p->getPostName());
            if ($livraison) {
                $critere = $this->em
                    ->getRepository(WpPosts::class)
                    ->find($livraison->getPostParent());
            }
        }

        return $this->render('admin/Commandes/Commandes/detail.html.twig', [
            'p' => $p,
            'u' => $this->service_manager->userById($p->getPostAuthor()),
            'usermeta' => $this->em
                ->getRepository(WpUsermeta::class)
                ->findBy(['userId' => $p->getPostAuthor()]),
            'user' => $this->service_manager->getAllUser(),
            'commande' => $commande,
            'critere' => $critere,
            'livraison' => $livraison,
            'lang' => $context->getRequest()->getLocale(),
        ]);
    }
}
