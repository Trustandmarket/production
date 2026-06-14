<?php

namespace App\Controller\Admin;

use App\Entity\AnnouncementAiModerationJob;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementAiModerationJobCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AnnouncementAiModerationJob::class;
    }

    public function index(AdminContext $context): Response
    {
        $this->denyAiModerationAccess();

        return parent::index($context);
    }

    public function detail(AdminContext $context): Response
    {
        $this->denyAiModerationAccess();

        return parent::detail($context);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Jobs de moderation IA')
            ->setPageTitle('detail', 'Detail du job de moderation IA')
            ->setEntityLabelInSingular('Job IA')
            ->setEntityLabelInPlural('Jobs IA')
            ->setDefaultSort(['createdAt' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields([
                'id',
                'status',
                'decisionCode',
                'decisionSummary',
                'announcement.postTitle',
                'announcement.postName',
                'user.displayName',
                'user.email_canonical',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'Job')->hideOnForm();
        yield TextField::new('announcementLabel', 'Annonce')
            ->setTemplatePath('admin/announcement_ai_moderation/Fields/announcement.html.twig')
            ->hideOnForm();
        yield TextField::new('userLabel', 'Utilisateur')
            ->setTemplatePath('admin/announcement_ai_moderation/Fields/user.html.twig')
            ->hideOnForm();
        yield TextField::new('status', 'Statut')
            ->setTemplatePath('admin/announcement_ai_moderation/Fields/status.html.twig')
            ->hideOnForm();
        yield TextField::new('decisionLabel', 'Decision')->hideOnForm();
        yield TextField::new('checksSummaryLabel', 'Checks')
            ->setTemplatePath('admin/announcement_ai_moderation/Fields/checks_summary.html.twig')
            ->hideOnForm();
        yield IntegerField::new('attemptCount', 'Tentatives')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Cree le')->hideOnForm();
        yield DateTimeField::new('processedAt', 'Traite le')->hideOnForm();

        if ($pageName !== Crud::PAGE_DETAIL) {
            return;
        }

        yield FormField::addTab('Synthese');
        yield TextField::new('sourceTransition', 'Transition source')->hideOnForm();
        yield TextField::new('postStatusSnapshot', 'Statut capture')->hideOnForm();
        yield TextField::new('decisionSource', 'Source decision')->hideOnForm();
        yield TextField::new('decisionCode', 'Code decision')->hideOnForm();
        yield TextareaField::new('decisionSummary', 'Resume')->hideOnForm();
        yield TextField::new('aiModel', 'Modele IA')->hideOnForm();
        yield TextField::new('aiConfidenceDisplay', 'Confiance IA')->hideOnForm();
        yield BooleanField::new('hardRulesPass', 'Hard rules OK')->renderAsSwitch(false)->hideOnForm();
        yield BooleanField::new('aiPass', 'IA OK')->renderAsSwitch(false)->hideOnForm();
        yield TextareaField::new('lastError', 'Erreur technique')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Maj le')->hideOnForm();

        yield FormField::addTab('Snapshot annonce');
        yield TextField::new('payloadSnapshotPretty', 'Payload')
            ->setTemplatePath('admin/announcement_ai_moderation/Fields/payload_snapshot.html.twig')
            ->hideOnForm();

        yield FormField::addTab('Checks');
        yield TextField::new('checksSummaryLabel', 'Details checks')
            ->setTemplatePath('admin/announcement_ai_moderation/Fields/checks_detail.html.twig')
            ->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'pending' => AnnouncementAiModerationJob::STATUS_PENDING,
                'processing' => AnnouncementAiModerationJob::STATUS_PROCESSING,
                'approved' => AnnouncementAiModerationJob::STATUS_APPROVED,
                'manual_review' => AnnouncementAiModerationJob::STATUS_MANUAL_REVIEW,
                'failed' => AnnouncementAiModerationJob::STATUS_FAILED,
                'cancelled' => AnnouncementAiModerationJob::STATUS_CANCELLED,
            ]))
            ->add(ChoiceFilter::new('decisionSource', 'Source decision')->setChoices([
                'rules_only' => 'rules_only',
                'rules_and_ai' => 'rules_and_ai',
                'technical_failure' => 'technical_failure',
            ]))
            ->add(TextFilter::new('decisionCode', 'Code decision'))
            ->add(DateTimeFilter::new('createdAt', 'Cree le'))
            ->add(DateTimeFilter::new('processedAt', 'Traite le'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $retry = Action::new('retry', 'Retry')
            ->setIcon('fa fa-rotate-right')
            ->linkToCrudAction('retry')
            ->displayIf(static fn (AnnouncementAiModerationJob $job) => $job->canRetry())
            ->setCssClass('btn btn-warning btn-sm');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $retry)
            ->add(Crud::PAGE_DETAIL, $retry);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $qb
            ->leftJoin('entity.announcement', 'announcement')->addSelect('announcement')
            ->leftJoin('entity.user', 'user')->addSelect('user');
    }

    public function retry(AdminContext $context): RedirectResponse
    {
        $this->denyAiModerationAccess();

        $job = $context->getEntity()->getInstance();
        if (!$job instanceof AnnouncementAiModerationJob || $job->getId() === null) {
            $this->addFlash('danger', 'Job introuvable.');

            return $this->redirect($this->buildCrudUrl(Action::INDEX));
        }

        if (!$job->canRetry()) {
            $this->addFlash('warning', sprintf('Le job #%d ne peut pas etre relance.', $job->getId()));

            return $this->redirect($this->buildCrudUrl(Action::DETAIL, $job->getId()));
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $conn->delete('announcement_ai_moderation_checks', [
                'moderation_job_id' => $job->getId(),
            ]);

            $conn->update('announcement_ai_moderation_jobs', [
                'status' => AnnouncementAiModerationJob::STATUS_PENDING,
                'decision_source' => null,
                'decision_code' => null,
                'decision_summary' => null,
                'ai_model' => null,
                'ai_confidence' => null,
                'hard_rules_pass' => null,
                'ai_pass' => null,
                'last_error' => null,
                'processed_at' => null,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $job->getId(),
            ]);

            $conn->commit();
            $this->addFlash('success', sprintf('Le job #%d a ete remis en file pending.', $job->getId()));
        } catch (\Throwable $exception) {
            $conn->rollBack();
            $this->addFlash('danger', sprintf('Echec du retry du job #%d: %s', $job->getId(), $exception->getMessage()));
        }

        return $this->redirect($this->buildCrudUrl(Action::DETAIL, $job->getId()));
    }

    private function denyAiModerationAccess(): void
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && !$this->isGranted('ROLE_COMMERCE')) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }

    private function buildCrudUrl(string $action, ?int $entityId = null): string
    {
        $urlGenerator = clone $this->adminUrlGenerator;
        $urlGenerator
            ->setController(self::class)
            ->setAction($action);

        if ($entityId !== null) {
            $urlGenerator->setEntityId($entityId);
        }

        return $urlGenerator->generateUrl();
    }
}
