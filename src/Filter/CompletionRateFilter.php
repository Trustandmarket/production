<?php

namespace App\Filter;

use App\Entity\WpUsermeta;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class CompletionRateFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'completionRate', $label = 'Completion Rate'): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('choices', [
                'Moins de 50%' => 'lt50',
                '50% a 79%' => 'between50and79',
                '80% et plus' => 'gte80',
                '100%' => 'eq100',
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (null === $value || '' === $value) {
            return;
        }

        if (!$this->hasJoinAlias($queryBuilder, 'umCompletion')) {
            $queryBuilder->leftJoin(
                WpUsermeta::class,
                'umCompletion',
                'WITH',
                'umCompletion.userId = entity.id AND umCompletion.metaKey = :completionMetaKey'
            );
        }

        $queryBuilder->setParameter('completionMetaKey', 'profile_completion_rate');

        $completionExpr = "(CASE WHEN umCompletion.metaValue IS NULL OR umCompletion.metaValue = '' THEN 0 ELSE umCompletion.metaValue END)";

        switch ($value) {
            case 'lt50':
                $queryBuilder->andWhere($completionExpr . ' < 50');
                break;
            case 'between50and79':
                $queryBuilder->andWhere($completionExpr . ' >= 50 AND ' . $completionExpr . ' < 80');
                break;
            case 'gte80':
                $queryBuilder->andWhere($completionExpr . ' >= 80');
                break;
            case 'eq100':
                $queryBuilder->andWhere($completionExpr . ' = 100');
                break;
            default:
                break;
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
}
