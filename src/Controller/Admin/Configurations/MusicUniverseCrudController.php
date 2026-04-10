<?php

namespace App\Controller\Admin\Configurations;

use App\Entity\MusicUniverse;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MusicUniverseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MusicUniverse::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Univers musicaux')
            ->setPageTitle('detail', 'Details univers musical')
            ->setPageTitle('edit', 'Modifier univers musical')
            ->setEntityLabelInSingular('Univers musical')
            ->setEntityLabelInPlural('Univers musicaux')
            ->setDefaultSort(['position' => 'ASC', 'id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('label', 'Libelle'),
            TextField::new('slug', 'Slug')->hideOnForm(),
            IntegerField::new('position', 'Position'),
            BooleanField::new('isActive', 'Actif'),
            DateTimeField::new('createdAt', 'Cree le')->onlyOnIndex(),
            DateTimeField::new('updatedAt', 'MAJ le')->onlyOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }
}

