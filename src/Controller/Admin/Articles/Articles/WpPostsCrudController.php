<?php

namespace App\Controller\Admin\Articles\Articles;

use App\Entity\WpPosts;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Entity\ExtTranslationWpPosts;
use App\Entity\WpPostmeta;
use App\Entity\User;
use App\Entity\WpUsermeta;

class WpPostsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $service_manager;

    public function __construct(ServiceManager $service_manager, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->service_manager = $service_manager;
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return WpPosts::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Articles')
            ->setPageTitle('detail', 'Détails Article')
            ->setPageTitle('edit', 'Modifier Article')
            ->setEntityLabelInSingular('Article')
            ->setEntityLabelInPlural('Articles')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['postTitle','postStatus'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'Id'),
            TextField::new('postTitle', 'Titre'),
            TextField::new('postStatus', 'Statut')->setTemplatePath('admin/Annonces/Annonces/Fields/status.html.twig'),
            DateField::new('postDate', 'Date'),
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
        $response->andWhere("entity.postType = 'post'");
        return $response;
    }

    public function new(AdminContext $context)
    {
        return $this->render('admin/Articles/Articles/new.html.twig', [
            'categorie' => $this->service_manager->postCategorie('category', 0),
            'lang' => $context->getRequest()->getLocale(),
            'd' => new \DateTime(),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $id = $context->getRequest()->query->get('entityId');
        $p = $this->service_manager->readPosts($id);
        $img = $this->em
            ->getRepository(WpPosts::class)
            ->findOneBy(['postParent' => $id, 'postType' => 'attachment']);
        $meta_desc = $this->service_manager->readPostMeta($id, 'meta_desc');
        $contenu_en = $this->em
            ->getRepository(ExtTranslationWpPosts::class)
            ->findBy(['foreign_key' => $id, 'locale' => 'en_US']);

        return $this->render('admin/Articles/Articles/edit.html.twig', [
            'categorie' => $this->service_manager->postCategorie('category', 0),
            'article' => $p,
            'contenu_en' => $contenu_en,
            'meta_desc' => $meta_desc,
            'relation' => $this->service_manager->readTermRelation($p->getId()),
            'img' => $img,
            'lang' => $context->getRequest()->getLocale(),
            'd' => new \DateTime(),
        ]);
    }

    public function detail(AdminContext $context)
    {
        $id = $context->getRequest()->query->get('entityId');
        $annonce = $this->em->getRepository(WpPosts::class)->findOneBy(['id' => $id]);
        $postmeta = $this->em->getRepository(WpPostmeta::class)->findBy(['postId' => $id]);
        $contenu_en = $this->em->getRepository(ExtTranslationWpPosts::class)->findBy(['foreign_key' => $id, 'locale' => 'en_US']);
        //dd($contenu_en);
        $img = $this->em->getRepository(WpPosts::class)->findOneBy([
            'postParent' => $annonce->getId(),
            'postType' => 'attachment',
        ]);

        $user = $this->em->getRepository(User::class)->findOneBy(['id' => $annonce->getPostAuthor()]);
        $usermeta = null;
        if ($user) {
            $usermeta = $this->em->getRepository(WpUsermeta::class)->findBy(['userId' => $user->getId()]);
        }

        return $this->render('admin/Articles/Articles/detail.html.twig', [
            'annonce' => $annonce,
            'img' => $img,
            'user' => $user,
            'postmeta' => $postmeta,
            'usermeta' => $usermeta,
            'contenu_en' => $contenu_en,
        ]);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $r = $this->service_manager->deletePosts($entityInstance->getId());
        //$entityManager->remove($entityInstance);
        //$entityManager->flush();
    }
}
