<?php

namespace App\Controller\Admin;

use App\Entity\WpComments;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WpCommentsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WpComments::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Commentaires')
            ->setPageTitle('detail', 'Détails Commentaire')
            ->setPageTitle('edit', 'Modifier Commentaire')
            ->setEntityLabelInSingular('Commentaire')
            ->setEntityLabelInPlural('Commentaires')
            ->setDefaultSort(['commentId' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('comment_ID', 'Id')->hideOnForm(),
            TextField::new('comment_author', 'Auteur'),
            TextEditorField::new('comment_content', 'Contenu'),
            ChoiceField::new('comment_approved', 'Statut')->setChoices([
                'Approuvé' => '1',
                'Indésirable' => 'trash',
                'En modération' => 'moderation',
                'Désactivé' => '0',
                'Corbeille' => 'post-trashed',
            ]),
            DateTimeField::new('comment_date', 'Date'),
            TextField::new('comment_author_IP', 'Ip Auteur')->onlyOnDetail(),
            TextField::new('comment_agent', 'Informations Supplémentaires')->onlyOnDetail(),
        ];
    }
}
