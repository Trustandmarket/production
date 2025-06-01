<?php

namespace App\Controller\Admin\PagesStatiques;

use App\Entity\WpPosts;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use DateTime;

class WpPostsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WpPosts::class;
    }

    public function createEntity(string $entityFqcn)
    {

        $date_now = new DateTime();
        $page = new WpPosts();
        $page->setPostDate($date_now);
        $page->setPostDateGmt($date_now);
        $page->setPostModified($date_now);
        $page->setPostModifiedGmt($date_now);
        $page->setToPing('page-statique');
        $page->setPinged('page-statique');
        $page->setPostType('page-statique');
        $page->setPostContentFiltered('page-statique');
        $page->setPostStatus('publish');
        $page->setPostAuthor($this->getUser()->getId());

        return $page;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Pages statiques')
            ->setPageTitle('detail', 'Détails Page')
            ->setPageTitle('edit', 'Modifier Page')
            ->setEntityLabelInSingular('Page Statique')
            ->setEntityLabelInPlural('Pages Statiques')
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'Id')->hideOnForm(),
            TextField::new('postTitle', 'admin.page.titre'),
            TextField::new('postExcerpt', 'Clé'),
            TextEditorField::new('postContent', 'admin.page.desc'),
        ];
    }
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response = $this->get(EntityRepository::class)->createQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $response->andWhere("entity.postType = 'page-statique' AND entity.postStatus != 'trash'");
        return $response;
    }

}
