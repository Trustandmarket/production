<?php

namespace App\Controller\Admin\Configurations;

use App\Entity\OffreInterne;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use FOS\CKEditorBundle\Form\Type\CKEditorType;

class OffreInterneCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OffreInterne::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Offres Pros')
            ->setPageTitle('detail', 'Détails Offre Pro')
            ->setPageTitle('edit', 'Modifier Offre Pro')
            ->setEntityLabelInSingular('Offre Pro')
            ->setEntityLabelInPlural('Offres Pros')
            ->setDefaultSort(['id' => 'DESC'])
            ->addFormTheme('@FOSCKEditor/Form/ckeditor_widget.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('titre', 'Titre offre'),
            TextEditorField::new('enteteDescriptif', 'Entete'), //->setFormType(CKEditorType::class)
            TextEditorField::new('descriptif', 'Descriptif'),
            NumberField::new('tarif', 'Tarif'),
            TextField::new('slug', 'Slug')->hideOnForm(),
            
            BooleanField::new('referencement', 'Referencement'),
            BooleanField::new('creerAnnonce', 'Creer une annonce?'),
            BooleanField::new('creerDevis', 'Creer un devis?'),
            BooleanField::new('packsProduction', 'Pack production'),

            DateTimeField::new('created_at', 'Cree le')->onlyOnDetail(),
            DateTimeField::new('updated_at', 'MAJ le')->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }
}
