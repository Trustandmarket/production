<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class ProfileRoleFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'roles', $label = 'Roles'): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('choices', [
                'ROLE_ABONNE' => 'ROLE_ABONNE',
                'ROLE_AUTO_ENTREPRENEUR' => 'ROLE_AUTO_ENTREPRENEUR',
                'ROLE_SOCIETE' => 'ROLE_SOCIETE',
                'ROLE_COMMERCE' => 'ROLE_COMMERCE',
                'ROLE_CONTRIBUTEUR' => 'ROLE_CONTRIBUTEUR',
                'ROLE_SUPER_ADMIN' => 'ROLE_SUPER_ADMIN',
                'ROLE_USER' => 'ROLE_USER',
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (null === $value || '' === $value) {
            return;
        }

        $queryBuilder
            ->andWhere('entity.roles LIKE :profileRoleFilter')
            ->setParameter('profileRoleFilter', '%' . $value . '%');
    }
}
