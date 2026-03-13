<?php

namespace App\Controller\Admin;

use App\Entity\{User, WpUsermeta};
use App\Filter\CompletionRateFilter;
use App\Filter\ProfileRoleFilter;
use App\Repository\ReminderLogRepository;
use App\Security\EmailVerifier;
use App\Service\Admin\UserEditViewBuilder;
use App\Service\Admin\UserStripeManager;
use App\Service\Export\UserCsvExporter;
use App\Service\{Payment, ServiceManager};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\{ArrayField,
    AssociationField,
    BooleanField,
    ChoiceField,
    IdField,
    TextField,
    DateTimeField,
    FormField,
    CountryField
};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UserCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $requestStack;
    private $emailVerifier;
    private $payment;
    private $logger;
    private UserEditViewBuilder $userEditViewBuilder;
    private UserStripeManager $userStripeManager;
    private ReminderLogRepository $reminderLogRepository;
    /**
     * @var ServiceManager
     */
    private $service_manager;

    public function __construct(ServiceManager $service_manager,
                                EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator,
                                RequestStack $requestStack, EmailVerifier $emailVerifier, Payment $payment,
                                LoggerInterface $logger, UserEditViewBuilder $userEditViewBuilder,
                                UserStripeManager $userStripeManager, ReminderLogRepository $reminderLogRepository)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
        $this->emailVerifier = $emailVerifier;
        $this->payment = $payment;
        $this->logger = $logger;
        $this->userEditViewBuilder = $userEditViewBuilder;
        $this->userStripeManager = $userStripeManager;
        $this->reminderLogRepository = $reminderLogRepository;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des utilisateurs')
            ->setPageTitle('detail', 'Details utilisateur')
            ->setPageTitle('edit', 'Modifier utilisateur')
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setHelp('index', 'Vue de triage: utilisez les filtres rapides pour traiter les profils incomplets, non verifies et inactifs.')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $this->logger->info('UserCrudController.configureFields called', ['page' => $pageName]);

        return [

            FormField::addTab('Donnees de base')->setIcon('fa fa-users')->onlyOnDetail(),
            IdField::new('id')->hideOnForm(),
            TextField::new('display_name', 'Utilisateur')->setTemplatePath('admin/user/Fields/display_name.html.twig'),
            TextField::new('email_canonical', 'Email')->setTemplatePath('admin/user/Fields/email_link.html.twig'),
            IdField::new('id', 'Annonces')->setTemplatePath('admin/user/Fields/published_annonces_count.html.twig')->onlyOnIndex(),
            ArrayField::new('roles', 'Roles')->setTemplatePath('admin/user/Fields/roles.html.twig'),
            BooleanField::new('enabled', 'Compte')->setTemplatePath('admin/user/Fields/account_status.html.twig')->renderAsSwitch(false),
            BooleanField::new('is_verified', 'Email')->setTemplatePath('admin/user/Fields/verification_status.html.twig')->renderAsSwitch(false)->hideOnIndex(),
            IdField::new('id', 'Completion')->setTemplatePath('admin/user/Fields/completion_rate.html.twig')->onlyOnIndex(),
            TextField::new('id', 'Derniere relance')->formatValue(function ($value, User $user) { return $this->formatLastReminder($user); })->hideOnForm(),
            TextField::new('id', 'Historique relances')->formatValue(function ($value, User $user) { return $this->buildReminderHistoryLink($user); })->renderAsHtml()->hideOnForm(),
            TextField::new('date_naissance', 'Date de naissance')->onlyOnDetail(),
            DateTimeField::new('userRegistered', 'Date de creation')->onlyOnIndex(),
            DateTimeField::new('updatedAt', 'Date de MAJ')->onlyOnIndex(),

            FormField::addTab('Donnees uniques utilisateur')->onlyOnDetail(),
            TextField::new('userUniqueData.firstname', 'Prenom')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.lastName', 'Nom')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.nomCommercial', 'Nom commercial')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.departement.nom', 'Departement')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.bdaytime', 'Date de naissance')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.sexe', 'Genre')->hideOnIndex()->hideOnForm(),
            CountryField::new('userUniqueData.nationalityCountry', 'Nationalite')->hideOnIndex()->hideOnForm(),
            CountryField::new('userUniqueData.residenceCountry', 'Residence')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.billingEmail', 'Email facture')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.telephone', 'Telephone')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.raisonSociale', 'Raison sociale')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.postCode', 'Code postal')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.siret', 'Siret')->hideOnIndex()->hideOnForm(),


            FormField::addTab('Adresse domicile')->onlyOnDetail(),
            CountryField::new('userUniqueData.paysDomicile', 'Pays')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.numeroNomRueDomicile', 'Numero nom rue')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.codePostalDomicile', 'Code postal')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.villeDomicile', 'Ville')->hideOnIndex()->hideOnForm(),

            FormField::addTab('Adresse de livraison')->onlyOnDetail(),
            CountryField::new('userUniqueData.paysLivraison', 'Pays')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.numeroNomRueLivraison', 'Numero nom rue')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.codePostalLivraison', 'Code postal')->hideOnIndex()->hideOnForm(),
            TextField::new('userUniqueData.villeLivraison', 'Ville')->hideOnIndex()->hideOnForm(),

            FormField::addTab('Informations de facturation')->onlyOnDetail(),
            TextField::new('informationsFacturationUtilisateur.nomOuSociete', 'Nom ou Societe')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.adresse', 'Adresse')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.ville', 'Ville')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.codePostal', 'Code Postal')->setColumns(4)->hideOnIndex()->hideOnForm(),
            CountryField::new('informationsFacturationUtilisateur.pays', 'Pays')->setColumns(4)->hideOnIndex()->hideOnForm(),
            TextField::new('informationsFacturationUtilisateur.numeroTva', 'Numero Tva')->setColumns(4)->hideOnIndex()->hideOnForm(),
        ];
    }
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email_canonical', 'Email'))
            ->add(ProfileRoleFilter::new('roles', 'Roles'))
            ->add(ChoiceFilter::new('enabled', 'Compte Actif?')->setChoices([
                'Oui' => 1,
                'Non' => 0,
            ]))
            ->add(CompletionRateFilter::new('completionRate', 'Completion Rate'));
    }
    public function configureActions(Actions $actions): Actions
    {
        $this->logger->info('UserCrudController.configureActions called');

        $export = Action::new('export')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('export')
                    ->generateUrl();
            })
            ->setIcon('fas fa-download')
            ->linkToCrudAction('export')
            ->setCssClass('btn btn-success btn-sm')
            ->createAsGlobalAction();

        $Activeruser = Action::new('Activeruser', 'Activation')
            ->linkToCrudAction('ActivercompteAction');

        $incompleteProfiles = Action::new(
            'incompleteProfiles',
            'Profils <80%'
        )
            ->linkToUrl($this->buildIndexUrl([
                'completionFilter' => 'lt80',
                'verificationFilter' => null,
                'statusFilter' => null,
            ]))
            ->createAsGlobalAction()
            ->setCssClass('btn btn-warning btn-sm');

        $unverifiedUsers = Action::new(
            'unverifiedUsers',
            'Non verifies'
        )
            ->linkToUrl($this->buildIndexUrl([
                'verificationFilter' => 'unverified',
                'completionFilter' => null,
                'statusFilter' => null,
            ]))
            ->createAsGlobalAction()
            ->setCssClass('btn btn-outline-secondary btn-sm');

        $inactiveUsers = Action::new(
            'inactiveUsers',
            'Inactifs'
        )
            ->linkToUrl($this->buildIndexUrl([
                'statusFilter' => 'inactive',
                'completionFilter' => null,
                'verificationFilter' => null,
            ]))
            ->createAsGlobalAction()
            ->setCssClass('btn btn-outline-dark btn-sm');

        $resetTriage = Action::new('resetTriage', 'Reset')
            ->linkToUrl($this->buildIndexUrl([
                'completionFilter' => null,
                'completionSort' => null,
                'verificationFilter' => null,
                'statusFilter' => null,
            ]))
            ->createAsGlobalAction()
            ->setCssClass('btn btn-light btn-sm');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $incompleteProfiles)
            ->add(Crud::PAGE_INDEX, $unverifiedUsers)
            ->add(Crud::PAGE_INDEX, $inactiveUsers)
            ->add(Crud::PAGE_INDEX, $resetTriage)
            ->add(Crud::PAGE_INDEX, $export)
            ->add(Crud::PAGE_INDEX, $Activeruser);
    }
    public function export(AdminContext $context, UserCsvExporter $csvExporter)
    {
        $this->logger->info('UserCrudController.export called');

        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, 'utilisateurs.csv');
    }

    private function buildCompletionUrl(?string $filter, ?string $sort): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $params = $request ? $request->query->all() : [];

        if ($filter === 'all') {
            unset($params['completionFilter']);
        } elseif ($filter !== null) {
            $params['completionFilter'] = $filter;
        }

        if ($sort === 'none') {
            unset($params['completionSort']);
        } elseif ($sort !== null) {
            $params['completionSort'] = $sort;
        }

        return $this->adminUrlGenerator
            ->setAll($params)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    private function buildIndexUrl(array $changes = []): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $params = $request ? $request->query->all() : [];

        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
                continue;
            }

            $params[$key] = $value;
        }

        return $this->adminUrlGenerator
            ->setAll($params)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    private function buildReminderHistoryUrl(User $user): string
    {
        return $this->adminUrlGenerator
            ->setController(ReminderLogCrudController::class)
            ->setAction(Action::INDEX)
            ->set('userId', $user->getId())
            ->generateUrl();
    }

    private function buildReminderHistoryLink(User $user): string
    {
        return sprintf("<a href=\"%s\">Voir l'historique des relances</a>", $this->buildReminderHistoryUrl($user));
    }

    private function formatLastReminder(User $user): string
    {
        $latestReminder = $this->reminderLogRepository->findLatestSentForUser($user);

        if ($latestReminder === null || $latestReminder->getSentAt() === null) {
            return 'Jamais';
        }

        return $latestReminder->getSentAt()->format('d/m/Y H:i');
    }


    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        try {
            $qb = $this->get(EntityRepository::class)->createQueryBuilder($searchDto, $entityDto, $fields, $filters);

            if (!$this->hasJoinAlias($qb, 'umCompletion')) {
                $qb->leftJoin(
                    WpUsermeta::class,
                    'umCompletion',
                    'WITH',
                    'umCompletion.userId = entity.id AND umCompletion.metaKey = :completionMetaKey'
                );
            }
            $qb->setParameter('completionMetaKey', 'profile_completion_rate');

            $request = $this->requestStack->getCurrentRequest();
            $completionFilter = $request ? $request->query->get('completionFilter') : null;
            $completionSort = $request ? $request->query->get('completionSort') : null;
            $verificationFilter = $request ? $request->query->get('verificationFilter') : null;
            $statusFilter = $request ? $request->query->get('statusFilter') : null;

            $completionExpr = "(CASE WHEN umCompletion.metaValue IS NULL OR umCompletion.metaValue = '' THEN 0 ELSE umCompletion.metaValue END)";

            if ($completionFilter === 'lt80') {
                $qb->andWhere($completionExpr . ' < 80');
            } elseif ($completionFilter === 'gte80') {
                $qb->andWhere($completionExpr . ' >= 80');
            }

            if ($verificationFilter === 'unverified') {
                $qb->andWhere('entity.isVerified = :verificationFilterFalse');
                $qb->setParameter('verificationFilterFalse', false);
            } elseif ($verificationFilter === 'verified') {
                $qb->andWhere('entity.isVerified = :verificationFilterTrue');
                $qb->setParameter('verificationFilterTrue', true);
            }

            if ($statusFilter === 'inactive') {
                $qb->andWhere('entity.enabled = :statusFilterInactive');
                $qb->setParameter('statusFilterInactive', 0);
            } elseif ($statusFilter === 'active') {
                $qb->andWhere('entity.enabled = :statusFilterActive');
                $qb->setParameter('statusFilterActive', 1);
            }

            if ($completionSort === 'asc') {
                $qb->addOrderBy($completionExpr, 'ASC');
            } elseif ($completionSort === 'desc') {
                $qb->addOrderBy($completionExpr, 'DESC');
            }

            $params = [];
            foreach ($qb->getParameters() as $parameter) {
                $params[$parameter->getName()] = $parameter->getValue();
            }

            $this->logger->info('UserCrudController.createIndexQueryBuilder DQL', [
                'completionFilter' => $completionFilter,
                'completionSort' => $completionSort,
                'verificationFilter' => $verificationFilter,
                'statusFilter' => $statusFilter,
                'dql' => $qb->getDQL(),
                'params' => $params,
            ]);

            return $qb;
        } catch (\Throwable $e) {
            $this->logger->error('UserCrudController.createIndexQueryBuilder failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    private function hasJoinAlias(QueryBuilder $queryBuilder, string $alias): bool
    {
        $joins = $queryBuilder->getDQLPart('join');

        foreach ($joins as $joinParts) {
            foreach ($joinParts as $join) {
                if ($join instanceof Join && $join->getAlias() === $alias) {
                    return true;
                }
            }
        }

        return false;
    }
    private function getProfileCompletionRate(User $user): int
    {
        try {
            $rate = (int) $this->service_manager->getUserStringDataValue((int) $user->getId(), 'profile_completion_rate');
        } catch (\Throwable $e) {
            $this->logger->error('Error reading profile_completion_rate', [
                'user_id' => $user->getId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return 0;
        }

        if ($rate < 0) {
            return 0;
        }

        if ($rate > 100) {
            return 100;
        }

        return $rate;
    }


    private function renderCompletionRateBadge(User $user): string
    {
        $rate = $this->getProfileCompletionRate($user);

        $class = 'badge badge-success';
        if ($rate < 50) {
            $class = 'badge badge-danger';
        } elseif ($rate < 80) {
            $class = 'badge badge-warning';
        }

        return sprintf('<span class="%s">%d%%</span>', $class, $rate);
    }

    private function countIncompleteProfiles(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(entity.id)')
            ->from(User::class, 'entity')
            ->leftJoin(
                WpUsermeta::class,
                'umCompletionCount',
                'WITH',
                'umCompletionCount.userId = entity.id AND umCompletionCount.metaKey = :completionMetaKeyCount'
            )
            ->where("(umCompletionCount.metaValue IS NULL OR umCompletionCount.metaValue = '' OR umCompletionCount.metaValue < :completionThreshold)")
            ->setParameter('completionMetaKeyCount', 'profile_completion_rate')
            ->setParameter('completionThreshold', 80)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countUnverifiedUsers(): int
    {
        return (int) $this->em->getRepository(User::class)->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->andWhere('entity.isVerified = :isVerified')
            ->setParameter('isVerified', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countInactiveUsers(): int
    {
        return (int) $this->em->getRepository(User::class)->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->andWhere('entity.enabled = :enabled')
            ->setParameter('enabled', 0)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function stripeForm(AdminContext $context)
    {
        return $this->render('admin/user/stripe_form.html.twig', []);
    }

    /**
     * @Route("/admin/user/delete-stripe-id", name="admin_user_delete_stripe_id", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteStripeId(Request $request): JsonResponse
    {
        $stripeId = $request->request->get('stripe_id');

        if (!$stripeId) {
            return new JsonResponse(['error' => 'Stripe ID is required'], 400);
        }

        $deletedItem = $this->userStripeManager->deleteStripeAccountById($stripeId);

        if (($deletedItem['deleted'] ?? false) === true) {
            return new JsonResponse(['message' => 'Compte Stripe supprimé avec succès', 'delete' => $deletedItem], 200);
        }

        return new JsonResponse(['error' => 'Échec de la suppression du compte Stripe', 'delete' => $deletedItem], 400);
    }


    public function deleteStripeAction(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        if (!$user) {
            $this->addFlash('danger', "User not found.");
            return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $this->userStripeManager->deleteStripeDataForUser((int) $user->getId());

        $this->addFlash('success', "Stripe account deleted successfully.");
        return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    //Renvoi email d'activation de compte 
    public function ActivercompteAction(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        if (!$user) {
           $this->addFlash('danger', "User not found.");
           return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }
        
        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user);
        $this->addFlash('success', "Activation email sent successfully.");
        return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function new(AdminContext $context)
    {
        $url = $this->adminUrlGenerator
            ->setAction(Action::INDEX)
            ->setController(UserCrudController::class)
            ->removeReferrer()
            ->generateUrl();
        return $this->render('admin/user/add_user.html.twig', ['url' => $url]);
    }


    public function edit(AdminContext $context)
    {
        $url = $this->adminUrlGenerator
            ->setAction(Action::INDEX)
            ->setController(UserCrudController::class)
            ->removeReferrer()
            ->generateUrl();

        $userId = $context->getRequest()->query->get('entityId');
        $user = $this->em->getRepository(User::class)->find($userId);
        if ($context->getRequest()->query->get('fieldName') == 'enabled') {
            if (!$user->getEnabled()) {
                $user->setEnabled(1);
                $this->em->flush();
            }
        }
        $mpAccount = null;
        $stripeAccount = null;
        if ($context->getRequest()->query->get('fieldName') == 'is_verified') {
            if (!$user->isVerified()) {
                $user->setIsVerified($context->getRequest()->query->get('newValue'));
                $user->setEnabled(1);
                $this->em->flush();
                
            } elseif ($user->isVerified() == true) {
                $user->setIsVerified(false);
                $user->setEnabled(0);
                $this->em->flush();
            }

            return $this->json(['success' => 'Account update successfully', 'response' => $stripeAccount], 200);
        }

        return $this->render('admin/user/edit_user.html.twig', $this->userEditViewBuilder->build((int) $userId));
    }
    
    public function delete(AdminContext $context)
    {
        $entity = $context->getEntity()->getInstance(); // Retrieve the entity instance
        if (!$entity) {
            throw new \Exception('Entity not found.');
        }

        $userId = $entity->getId(); // Get the user ID

        $this->userStripeManager->deleteStripeDataForUser((int) $userId);

        // Remove all related profile data before deleting the user
        $this->service_manager->deleteProfilAll($userId);

        return parent::delete($context);
    }
    public function getDataToUpdateMangopayUser($id, $email)
    {
        // Define all the keys you need to fetch
        $keys = [
            'first_name', 'last_name', 'telephone', 'sexe', 'billing_email', 'residenceCountry',
            'nationalityCountry', 'bdaytime', 'numeroNomRue_domicile',
            'pays_domicile', 'codePostal_domicile',
            'ville_domicile', 'region_domicile', 'numeroNomRue_livraison', 'pays_livraison',
            'codePostal_livraison', 'ville_livraison', 'region_livraison',
            //Compagny Datas
            'siret', 'tva'
        ];

        // Fetch all metadata for the user in a single query
        $userMetadata = $this->service_manager->getUserMetadata($id, $keys);

        // Map the metadata to the desired output structure
        $data = [
            "firstname" => $userMetadata['first_name'] ?? '',
            "lastname" => $userMetadata['last_name'] ?? '',
            "phone" => $userMetadata['telephone'] ?? '',
            "sexe" => isset($userMetadata['sexe']) ? ($userMetadata['sexe'] == 'femme' ? 'female' : 'male') : '',
            "user_email" => $email,
            "countryOfResidence" => $userMetadata['residenceCountry'] ?? '',
            "nationality" => $userMetadata['nationalityCountry'] ?? '',
            "birthday" => $userMetadata['bdaytime'] ?? '',
            "user_address_1" => $userMetadata['numeroNomRue_domicile'] ?? '',
            "user_country" => $userMetadata['pays_domicile'] ?? '',
            "user_postcode" => $userMetadata['codePostal_domicile'] ?? '',
            "user_city" => $userMetadata['ville_domicile'] ?? '',
            "region_domicile" => $userMetadata['region_domicile'] ?? '',
            "user_address_1_livraison" => $userMetadata['numeroNomRue_livraison'] ?? '',
            "user_country_livraison" => $userMetadata['pays_livraison'] ?? '',
            "user_postcode_livraison" => $userMetadata['codePostal_livraison'] ?? '',
            "user_city_livraison" => $userMetadata['ville_livraison'] ?? '',
            "region_livraison" => $userMetadata['region_livraison'] ?? '',
            //Company Datas
            "siret" => $userMetadata['siret'] ?? '',
            "tva" => $userMetadata['tva'] ?? ''
        ];

        // Fallback for billing email
        if (empty($data["billing_email"])) {
            $data["billing_email"] = $data["user_email"];
        }

        return $data;
    }

    /**
     * Handles Stripe account and person creation.
     * @param $userType
     * @param $data
     * @param $userId
     * @return mixed|null
     */
    private function handleStripeAccountCreation($userType, $data, $userId)
    {
        $accountToken = $this->payment->createStripeAccountToken($userType, $data);
        if (empty($accountToken['id'])) return ['token' => $accountToken, 'data' => $data];

        //Création du compte Stripe
        $stripeAccount = $this->payment->createStripeUserFromToken($accountToken['id']);
        if (empty($stripeAccount['id'])) return ['token' => $accountToken, 'data' => $data];

        $this->service_manager->updateUserMeta($userId, 'mp_user_id_sandbox', $stripeAccount['id']);
        $this->payment->updateStripeUser($stripeAccount['id'], $userType, $data);

        // Cr?ation de la personne Stripe pour les comptes non abonn?s
        if($userType != 'ROLE_ABONNE'){
            $stripePersonToken = $this->payment->createStripePersonToken($data);
            if (!empty($stripePersonToken['id'])) {
                $stripePerson = $this->payment->createStripePersonFromToken($stripeAccount['id'], $stripePersonToken['id']);
                if (!empty($stripePerson['id'])) {
                    $this->service_manager->updateUserMeta($userId, 'stripe_person_user', $stripePerson['id']);
                    $this->payment->updateStripePerson($stripeAccount['id'], $stripePerson['id'], $data);
                }
            }
        }
        return ['stripeAccount' => $stripeAccount, 'token' => $accountToken];
    }

}









