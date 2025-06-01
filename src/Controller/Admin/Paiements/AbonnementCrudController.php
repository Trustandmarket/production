<?php

namespace App\Controller\Admin\Paiements;

use App\Entity\Abonnement;
use App\Security\EmailVerifier;
use App\Service\Export\AbonnementsCsvExporter;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\HttpFoundation\RequestStack;;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class AbonnementCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $requestStack;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }
    public static function getEntityFqcn(): string
    {
        return Abonnement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Abonnements')
            ->setPageTitle('detail', 'Détails Abonnement')
            ->setPageTitle('edit', 'Modifier Abonnement')
            ->setEntityLabelInSingular('Abonnement')
            ->setEntityLabelInPlural('Abonnements')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['user.email_canonical','offre.titre']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addTab('Abonnement')->hideOnIndex(),
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Utilisateur')->autocomplete(),
            AssociationField::new('offre', 'Offre')->renderAsNativeWidget()->setRequired(true),
            NumberField::new('tarif', 'Tarif')->hideOnForm(),
            BooleanField::new('abonnementActif', 'Actif?'),
            DateTimeField::new('created_at', 'Date debut'),
            DateTimeField::new('dateFin', 'Date fin'),
            DateTimeField::new('updated_at', 'MAJ le')->hideOnForm(),
            ChoiceField::new('tag', 'Tag')->setChoices([
                'UPGRADE' => 'UPGRADE',
                'STATIC' => 'STATIC',
                'DOWNGRADE' => 'DOWNGRADE',
                'PARTNER' => 'PARTNER',
            ])->renderAsBadges([
                'UPGRADE' => 'success',
                'STATIC' => 'warning',
                'DOWNGRADE' => 'danger',
                'PARTNER' => 'primary',
            ]),
            NumberField::new('id', 'Jours restants?')
                ->setTemplatePath('admin/Paiements/Fields/abonnementDaysLeft.html.twig')
                ->setTextAlign('center')
                ->setDisabled()->hideWhenCreating(),

            FormField::addTab('Adresse de facturation')->hideOnIndex(),
            TextField::new('nomOuSociete', 'Nom ou Societe')->hideOnIndex(),
            TextField::new('adresse', 'Adresse')->hideOnIndex(),
            TextField::new('ville', 'Ville')->hideOnIndex(),
            TextField::new('codePostal', 'Code Postal')->hideOnIndex(),
            CountryField::new('pays', 'Pays')->hideOnIndex(),
            TextField::new('numeroTva', 'Numero Tva')->hideOnIndex(),
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

        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $export);
    }

    public function export(AdminContext $context, AbonnementsCsvExporter $csvExporter)
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, 'abonnements.csv');
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if($entityInstance->getOffre()){
            $entityInstance->setTarif($entityInstance->getOffre()->getTarif());
            //$entityManager->flush();
        }
        parent::updateEntity($entityManager, $entityInstance); // TODO: Change the autogenerated stub
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if($entityInstance->getOffre()){
            $entityInstance->setTarif($entityInstance->getOffre()->getTarif());
            //$entityManager->flush();
        }
        parent::persistEntity($entityManager, $entityInstance); // TODO: Change the autogenerated stub
    }
}
