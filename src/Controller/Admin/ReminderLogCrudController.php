<?php

namespace App\Controller\Admin;

use App\Entity\ReminderLog;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

class ReminderLogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ReminderLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Historique des relances')
            ->setPageTitle('detail', 'Detail d une relance')
            ->setEntityLabelInSingular('Relance')
            ->setEntityLabelInPlural('Relances')
            ->setDefaultSort(['sentAt' => 'DESC', 'id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield DateTimeField::new('sentAt', 'Date d envoi')->hideOnForm();
        yield TextField::new('userDisplayName', 'Utilisateur')
            ->formatValue(function ($value, ReminderLog $reminderLog) {
                $user = $reminderLog->getUser();
                if ($user === null) {
                    return $reminderLog->getUserDisplayName() ?: '-';
                }

                $url = $this->adminUrlGenerator
                    ->setController(UserCrudController::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId($user->getId())
                    ->generateUrl();

                return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($reminderLog->getUserDisplayName() ?: (string) $user));
            })
            ->renderAsHtml()
            ->hideOnForm();
        yield TextField::new('userEmail', 'Email')->hideOnForm();
        yield TextField::new('type', 'Type')->hideOnForm();
        yield TextField::new('channel', 'Canal')->hideOnForm();
        yield TextField::new('status', 'Statut')->hideOnForm();
        yield IntegerField::new('templateId', 'Template')->hideOnForm();
        yield TextField::new('payloadSummary', 'Resume')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Cree le')->onlyOnDetail();
        yield TextareaField::new('contextJson', 'Contexte JSON')->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('userEmail', 'Email'))
            ->add(TextFilter::new('type', 'Type'))
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'sent' => 'sent',
                'failed' => 'failed',
                'dry_run' => 'dry_run',
            ]))
            ->add(DateTimeFilter::new('sentAt', 'Date d envoi'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $userId = $this->requestStack->getCurrentRequest()?->query->get('userId');

        if ($userId !== null && $userId !== '') {
            $qb->andWhere('IDENTITY(entity.user) = :userId')
                ->setParameter('userId', (int) $userId);
        }

        return $qb;
    }
}
