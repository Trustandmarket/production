<?php

namespace App\Controller\Admin\Configurations;

use App\Entity\Departement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;

class DepartementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Departement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Departements')
            ->setPageTitle('detail', 'Détails Departements')
            ->setPageTitle('edit', 'Modifier Departement')
            ->setEntityLabelInSingular('Departement')
            ->setEntityLabelInPlural('Departements')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('region', 'Region'),
            TextField::new('departement', 'Departement'),
            TextField::new('departement_slug', 'Slug Departement')->hideOnForm(),
            DateTimeField::new('created_at', 'Cree le')->onlyOnIndex(),
            DateTimeField::new('updated_at', 'MAJ le')->onlyOnIndex(),
        ];
    }

}
