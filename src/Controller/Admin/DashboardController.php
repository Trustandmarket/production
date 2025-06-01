<?php

namespace App\Controller\Admin;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Admin\Annonces\Annonces\WpPostsCrudController as AnnoncesCrudController;
use App\Controller\Admin\Annonces\Devis\WpPostsCrudController as DevisCrudController;
use App\Controller\Admin\Articles\Articles\WpPostsCrudController as ArticlesCrudController;
use App\Controller\Admin\Articles\Categories\WpTermsCrudController as CategoriesArticlesCrudController;
use App\Controller\Admin\Categories\WpTermTaxonomyCrudController as CategoriesCrudController;
use App\Controller\Admin\CategoriesProduits\WpTermTaxonomyCrudController as CategoriesProduitsCrudController;
use App\Controller\Admin\Commandes\Commandes\WpPostsCrudController as CommandesCrudController;
use App\Controller\Admin\Emailing\Active\UserCrudController as EmailingActiveCrudController;
use App\Controller\Admin\Emailing\Trash\UserCrudController as EmailingTrashCrudController;
use App\Controller\Admin\Livraisons\GererCriteres\WpPostsCrudController as GererCriteresCrudController;
use App\Controller\Admin\Livraisons\OpeFraisGratuits\WpPostsCrudController as OpeFraisGratuitsCrudController;
use App\Controller\Admin\Livraisons\TypesLivraisons\WpPostsCrudController as TypesLivraisonsCrudController;
use App\Controller\Admin\Marketing\Actualites\WpPostsCrudController as ActualitesCrudController;
use App\Controller\Admin\Marketing\LeadsGen\WpPostsCrudController as LeadsGenCrudController;
use App\Controller\Admin\Marketing\OpsSpeciales\WpPostsCrudController as OpsSpecialesCrudController;
use App\Controller\Admin\Marketing\PromoComs\WpPostsCrudController as PromoComsCrudController;
use App\Controller\Admin\PagesStatiques\WpPostsCrudController as PagesStatiquesCrudController;
use App\Controller\Admin\ParcoursUtilisateur\Experiences\WpPostsCrudController as ExperiencesCrudController;
use App\Controller\Admin\ParcoursUtilisateur\UniversTrust\WpPostsCrudController as UniversTrustCrudController;
use App\Controller\Admin\ToutesCategories\WpTermTaxonomyCrudController as ToutesCategoriesCrudController;
use App\Controller\Admin\Activities\WpTermTaxonomyCrudController as ActivitiesCrudController;
use App\Controller\Admin\Configurations\{DepartementCrudController, OffreInterneCrudController};
use App\Controller\Admin\Paiements\{AbonnementCrudController};
use App\Entity\{OffreInterne, User, WpComments, Departement, WpTerms, WpTermTaxonomy, Abonnement};
use App\Service\Payment;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardController extends AbstractDashboardController
{
    private $entityManager;
    private $sm;
    private $payment;
    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $sm,
        Payment $payment
    ) {
        $this->entityManager = $entityManager;
        $this->sm = $sm;
        $this->payment = $payment;
    }

    /**
     * @Route("/{_locale}/admin", name="admin")
     * @param RequestStack $requestStack
     * @return Response
     */
    public function dashboard(RequestStack $requestStack): Response
    {
        $avatars = $this->sm->readUserMeta(
            $this->getUser()->getId(),
            'basic_user_avatar'
        );


        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $requestStack->getSession()->set('avatar', $img[sizeof($img) - 1]);
        }
        return $this->render('admin/dashboard.html.twig', [
            'users' => $this->sm->totalUser(),
            'post' => $this->sm->totalPost('product'),
            'order' => $this->sm->totalPost('shop_order'),
            'article' => $this->sm->totalPost('post'),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Trust&Market')->disableUrlSignatures();
    }

    public function configureMenuItems(): iterable
    {
        /* Acces au dashboard */
        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CONTRIBUTEUR') || $this->isGranted('ROLE_COMMERCE')) {
            yield MenuItem::linkToRoute('admin.menu.dashboard', 'fa fa-dashboard', 'admin');
        }

        /* Gestion des utilisateurs */
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::subMenu('admin.menu.manage-user', 'fa fa-address-book')->setSubItems([
                MenuItem::linkToCrud('admin.menu.all-user', 'fa fa-address-book', User::class),
                MenuItem::linkToCrud('admin.menu.offre', 'fa fa-list', OffreInterne::class)->setController(OffreInterneCrudController::class),
                MenuItem::linkToCrud('admin.menu.abonnement', 'fa fa-money-bill', Abonnement::class)->setController(AbonnementCrudController::class)
            ]);
        }

        /* Gestion des catégories et produits */
        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CONTRIBUTEUR')) {
            yield MenuItem::subMenu('admin.menu.manage-category', 'fa fa-object-group')->setSubItems([
                MenuItem::linkToCrud('admin.menu.type-prestation', 'fa fa-list', WpTermTaxonomy::class)->setController(CategoriesCrudController::class),
                MenuItem::linkToCrud('admin.menu.categorie-produit', 'fa fa-list', WpTermTaxonomy::class)->setController(CategoriesProduitsCrudController::class),
                MenuItem::linkToCrud('admin.menu.all-categories', 'fa fa-paw', WpTermTaxonomy::class)->setController(ToutesCategoriesCrudController::class),
                MenuItem::linkToCrud('admin.menu.all-activities', 'fa fa-paw', WpTermTaxonomy::class)->setController(ActivitiesCrudController::class),
                MenuItem::linkToCrud('Departements', 'fa fa-map', Departement::class)->setController(DepartementCrudController::class),
            ]);

            yield MenuItem::subMenu('admin.menu.gerer-livraisons', 'fa fa-object-group')->setSubItems([
                MenuItem::linkToCrud('admin.menu.gerer-criteres', 'fa fa-list-ol', WpPosts::class)->setController(GererCriteresCrudController::class),
                MenuItem::linkToCrud('admin.menu.type-de-livraison', 'fa fa-truck', WpPosts::class)->setController(TypesLivraisonsCrudController::class),
                MenuItem::linkToCrud('admin.menu.operations-frais-gratuit', 'fa fa-list-ul', WpPosts::class)->setController(OpeFraisGratuitsCrudController::class)
            ]);


            yield MenuItem::subMenu('admin.menu.gerer-marketing', 'fa fa-envelope-open-text')->setSubItems([
                MenuItem::linkToCrud('admin.menu.actualite', 'fa fa-list', WpPosts::class)->setController(ActualitesCrudController::class),
                MenuItem::linkToRoute('Brouillons Actualites', 'fa fa-list', 'marketing_actualite_list', ['etat' => 'draft']),
                MenuItem::linkToCrud('admin.menu.ops-speciales', 'fa fa-tasks', WpPosts::class)->setController(OpsSpecialesCrudController::class),
                MenuItem::linkToCrud('admin.menu.promo-com', 'fa fa-ad', WpPosts::class)->setController(PromoComsCrudController::class),
                MenuItem::linkToCrud('admin.menu.leads-gen', 'fa fa-pen-square', WpPosts::class)->setController(LeadsGenCrudController::class),
            ]);


            yield MenuItem::subMenu('Parcours utilisateur', 'fa fa-solid fa-map')->setSubItems([
                MenuItem::linkToCrud('Expériences', 'fa fa-ad', WpPosts::class)->setController(ExperiencesCrudController::class),
                MenuItem::linkToCrud('Univers trust', 'fa fa-pen-square', WpPosts::class)->setController(UniversTrustCrudController::class),
            ]);
        }


        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_COMMERCE')) {
            yield MenuItem::subMenu('admin.menu.manage-post', 'fa fa-bullhorn')->setSubItems([
                MenuItem::linkToCrud('admin.menu.all-post', 'fa fa-list', WpPosts::class)->setController(AnnoncesCrudController::class),
                MenuItem::linkToCrud('admin.menu.all-devis', 'fa fa-list', WpPosts::class)->setController(DevisCrudController::class),
            ]);

            yield MenuItem::subMenu('admin.menu.manage-oders', 'fa fa-shopping-basket')->setSubItems([
                MenuItem::linkToCrud('admin.menu.manage-oders', 'fa fa-shopping-basket', WpPosts::class)->setController(CommandesCrudController::class),
                MenuItem::linkToRoute('Infos-bulle', 'fa fa-comment', 'get_infos_bulle'),
                MenuItem::linkToRoute('Infos-bulle Calendrier', 'fa fa-comment', 'get_calendar_infos_bulle'),
            ]);
        }

        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CONTRIBUTEUR')) {
            yield MenuItem::subMenu('admin.menu.manage-article', 'fa fa-pencil')->setSubItems([
                MenuItem::linkToCrud('admin.menu.all-article', 'fa fa-book', WpPosts::class)->setController(ArticlesCrudController::class),
                MenuItem::linkToCrud('admin.menu.all-categories', 'fa fa-list', WpTerms::class)->setController(CategoriesArticlesCrudController::class),
            ]);
            yield MenuItem::linkToCrud('Pages statiques', 'fa fa-envelope-open', User::class)->setController(PagesStatiquesCrudController::class);
            yield MenuItem::subMenu('admin.menu.modifier-menu', 'fa fa-cog')->setSubItems([
                MenuItem::linkToRoute('Contenus', 'fa fa-list', 'admin_menu'),
                MenuItem::linkToRoute('Brouillon', 'fa fa-list', 'admin_sous_menu_brouillon'),
                MenuItem::linkToRoute('Corbeille', 'fa fa-trash', 'admin_sous_menu_trash'),
                MenuItem::linkToRoute("Gestion de l'accueil", 'fa fa-home', 'admin_slider'),
                MenuItem::linkToRoute('Connexion / Inscription', 'fa fa-sign-in', 'admin_login_register'),
                MenuItem::linkToRoute('Recaptcha', 'fa fa-ban', 'admin_get_recaptcha'),
                MenuItem::linkToRoute('Accueil Youtube', 'fa fa-list', 'admin_get_youtube'),
                MenuItem::linkToRoute('Message recherche', 'fa fa-list', 'admin_get_empty_request_message'),
                MenuItem::linkToRoute('Message recherche Nom Commercial', 'fa fa-file-text', 'admin_get_empty_request_nom_commercial_message'),
            ]);
        }

        /* Gestion des commentaires */
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::linkToCrud('admin.menu.message', 'fa fa-envelope-open', WpComments::class)->setController(WpCommentsCrudController::class);
        }

        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CONTRIBUTEUR')) {
            yield MenuItem::subMenu('admin.menu.emailing', 'fa fa-envelope-open')->setSubItems([
                MenuItem::linkToCrud('Abonnés', 'fa fa-envelope-open', User::class)->setController(EmailingActiveCrudController::class),
                MenuItem::linkToCrud('Corbeille', 'fa fa-trash', User::class)->setController(EmailingTrashCrudController::class)
            ]);
        }

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::subMenu('admin.menu.language', 'fa fa-language')->setSubItems([
                MenuItem::linkToUrl('Fr', 'fa fa-flag', '/fr/admin'),
                MenuItem::linkToUrl('En', 'fa fa-flag', '/fr/admin'),
                MenuItem::linkToRoute('admin.menu.traduction', 'fa fa-pencil', 'admin_translation')
            ]);
        }

        yield MenuItem::linkToRoute('Accueil', 'fa fa-home', 'home');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out-alt');
        //yield MenuItem::linkToCrud('The Label', 'fas fa-list', User::class);
    }
}
