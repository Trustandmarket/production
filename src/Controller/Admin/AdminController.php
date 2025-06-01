<?php

// src/App/Controller/AdminController.php

namespace App\Controller\Admin;

use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Admin\CategoriesProduits\WpTermTaxonomyCrudController;
use App\Controller\Admin\Commandes\Commandes\WpPostsCrudController as CommandesWpPostsCrudController;
use App\Entity\{User, Abonnement, WpOptions, WpPosts, WpTerms, WpUsermeta};
use App\Service\{Payment, ServiceManager};
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminController extends AbstractController
{
    private $entityManager;
    private $sm;
    private $payment;
    private $appKernel;
    private $adminUrlGenerator;
    private $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $sm,
        Payment $payment,
        AdminUrlGenerator $adminUrlGenerator,
        KernelInterface $appKernel,
        RequestStack $requestStack
    )
    {
        $this->entityManager = $entityManager;
        $this->sm = $sm;
        $this->requestStack = $requestStack;
        $this->payment = $payment;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->appKernel = $appKernel;
    }

    public function dashboard()
    {
        $avatars = $this->sm->readUserMeta(
            $this->getUser()->getId(),
            'basic_user_avatar'
        );
        $avatar = '';
        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $avatar = $img[sizeof($img) - 1];
            $this->requestStack->getSession()->set('avatar', $avatar);
        }
        return $this->render('admin/dashboard.html.twig', [
            'users' => $this->sm->totalUser(),
            'post' => $this->sm->totalPost('product'),
            'order' => $this->sm->totalPost('shop_order'),
            'article' => $this->sm->totalPost('post'),
        ]);
    }

    // Cette fonction a pour but de lire le contenu du fichier de traduction

    /**
     * @Route("/admin/translation", name="admin_translation")
     */
    public function readFileTranslation(Request $request)
    {
        $file = $this->appKernel->getProjectDir() .
            '/translations/messages.' .
            $request->getLocale() .
            '.yaml';

        return $this->render('admin/translation/translation.html.twig', [
            'trans' => $this->sm->getFile($file),
        ]);
    }

    // Cette fonction a pour but de lire le contenu du fichier de traduction

    /**
     * @Route("/admin/writteTranslation")
     */
    public function writteFileTranslation(Request $request)
    {
        $file = $this->appKernel->getProjectDir() .
            '/translations/messages.' .
            $request->getLocale() .
            '.yaml';
        $result = $this->sm->writteFile(
            $file,
            $request->get('key'),
            $request->get('val')
        );

        return $this->render('admin/resultat.html.twig', [
            'result' => $result,
        ]);
    }

    //fonction qui permet a l'admin d'ajouter un utilisateur

    /**
     * @Route("/{_locale}/admin/user/create_account", name="app_admin_create_account")
     */
    public function submitUser(Request $request): Response
    {
        $user = new User();
        $rolesArray = [$request->get('role')];
        $password = password_hash($request->get('password'), PASSWORD_BCRYPT);
        $user->setUserEmail($request->get('email'));
        $user->setEmailCanonical($request->get('email'));
        $user->setUsernameCanonical($request->get('email'));
        $user->setUserNicename($request->get('first_name') . ' ' . $request->get('last_name'));
        $user->setDisplayName($request->get('first_name') . ' ' . $request->get('last_name'));
        $user->setDateNaissance($request->get('bdaytime'));
        $user->setRoles($rolesArray);
        $user->setUserActivationKey($password);
        $user->setPassword($password);
        $user->setEnabled(1);
        $user->setIsVerified(1);
        $user->setUserStatus(1);

        $r = 0;
        if (!empty($user->getEmailCanonical())) {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            if ($user->getId() > 0) {
                $r = 1;
                $userId = $user->getId();
                //First Name
                $first_name = $this->sm->updateUserMeta(
                    $userId,
                    'first_name',
                    trim($request->get('first_name'))
                );

                //Last name
                $last_name = $this->sm->updateUserMeta(
                    $userId,
                    'last_name',
                    trim($request->get('last_name'))
                );
                //Birthday
                $bdaytime = $this->sm->updateUserMeta(
                    $userId,
                    'bdaytime',
                    $request->get('bdaytime')
                );
                //Sexe
                $sexe = $this->sm->updateUserMeta(
                    $userId,
                    'sexe',
                    trim($request->get('sexe'))
                );
                //Birth Place
                $birth_place = $this->sm->updateUserMeta(
                    $userId,
                    'birth_place',
                    trim($request->get('birth_place'))
                );
                //Telephone
                $telephone = $this->sm->updateUserMeta(
                    $userId,
                    'telephone',
                    trim($request->get('telephone'))
                );
                //Raison Sociale
                $raison_sociale = $this->sm->updateUserMeta(
                    $userId,
                    'raison_sociale',
                    trim($request->get('raison_sociale'))
                );
                //SIRET
                $siret = $this->sm->updateUserMeta(
                    $userId,
                    'siret',
                    trim($request->get('siret'))
                );
                //TVA
                $tva = $this->sm->updateUserMeta(
                    $userId,
                    'tva',
                    trim($request->get('tva'))
                );
                // Avatar
                $avatarUrl = $this->sm->uploadAvatar(
                    $userId,
                    $request->files->get('avatar'),
                    $this->getParameter('avatar_directory')
                );
                if ($avatarUrl) {
                    $avatar = $this->sm->updateUserMeta(
                        $userId,
                        'basic_user_avatar',
                        $avatarUrl
                    );
                    $this->requestStack->getSession()->set('avatar', $avatarUrl);
                }
                //TITRE
                $titre = $this->sm->updateUserMeta(
                    $userId,
                    'titre',
                    trim($request->get('titre'))
                );

                //DESCRIPTION
                $description = $this->sm->updateUserMeta(
                    $userId,
                    'description',
                    trim($request->get('description'))
                );

                if ($request->get('competence1')) {
                    $competences = $this->sm->updateUserMeta(
                        $userId,
                        'competence',
                        implode(
                            ',',
                            array_column(
                                json_decode($request->get('competence1')),
                                'value'
                            )
                        )
                    );
                } else {
                    $competences = $this->sm->updateUserMeta(
                        $userId,
                        'competence',
                        ''
                    );
                }
                //REGION
                $region = $this->sm->updateUserMeta(
                    $userId,
                    'region',
                    trim($request->get('region'))
                );
                //REFERENCE
                $reference = $this->sm->updateUserMeta(
                    $userId,
                    'reference',
                    trim($request->get('reference'))
                );
                //VIDEO

                $video = $this->sm->readUserMeta($userId, 'video');

                if ($video && $request->get('new_vid')[0] != '') {
                    if (sizeof(@unserialize($video->getMetaValue())) > 0) {
                        $tabeauVideos = @unserialize($video->getMetaValue());
                        $tabeauVideos = $this->trierTableau($tabeauVideos);
                        $vid = $this->sm->updateUserMeta(
                            $userId,
                            'video',
                            @serialize(
                                array_merge(
                                    $tabeauVideos,
                                    $this->trierTableau(
                                        $request->get('new_vid')
                                    )
                                )
                            )
                        );
                    } else {
                        $vid = $this->sm->updateUserMeta(
                            $userId,
                            'video',
                            @serialize(
                                $this->trierTableau(
                                    $this->trierTableau(
                                        $request->get('new_vid')
                                    )
                                )
                            )
                        );
                    }
                } elseif ($request->get('new_vid')[0] != '') {
                    $vid = $this->sm->updateUserMeta(
                        $userId,
                        'video',
                        @serialize(
                            $this->trierTableau($request->get('new_vid'))
                        )
                    );
                }
                //portfolio
                $file = $request->files->get('file');
                $id = $this->sm->portfolio(
                    $file,
                    $this->getParameter('portfolio_directory'),
                    $userId
                );
                $port = $this->sm->readUserMeta($userId, 'portfolio');

                if ($port && $port->getMetaValue() != '') {
                    $id = $id . ',' . $port->getMetaValue();
                }
                if ($id) {
                    $portfolio = $this->sm->updateUserMeta(
                        $userId,
                        'portfolio',
                        $id
                    );
                }

                if ($request->get('bank')) {
                    //First Name
                    $typeCompte = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_type',
                        $request->get('bank')
                    );
                }
                if ($request->get('accountHolder')) {
                    //Last name
                    $nomCompte = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_name',
                        $request->get('accountHolder')
                    );
                }
                if ($request->get('addressHolder')) {
                    //Birthday
                    $adresseDetenteur = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_address1',
                        $request->get('addressHolder')
                    );
                }
                if ($request->get('cityHolder')) {
                    //Sexe
                    $villeCompte = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_city',
                        $request->get('cityHolder')
                    );
                }
                if ($request->get('codePostalHolder')) {
                    //Birth Place
                    $codePostaleCompte = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_postcode',
                        $request->get('codePostalHolder')
                    );
                }
                if ($request->get('countryHolder')) {
                    //Telephone
                    $paysCompte = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_country',
                        $request->get('countryHolder')
                    );
                }

                if ($request->get('regionHolder')) {
                    //Raison Sociale
                    $regionCompte = $this->sm->updateUserMeta(
                        $userId,
                        'vendor_account_region',
                        $request->get('regionHolder')
                    );
                    //SIRET
                }

                //Billing

                if ($request->get('billing_last_name')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_last_name',
                        trim($request->get('billing_last_name'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_first_name')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_first_name',
                        trim($request->get('billing_first_name'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_company')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_company',
                        trim($request->get('billing_company'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_city')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_city',
                        trim($request->get('billing_city'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_state')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_state',
                        trim($request->get('billing_state'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_address_1')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_address_1',
                        trim($request->get('billing_address_1'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_postcode')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_postcode',
                        trim($request->get('billing_postcode'))
                    );
                    //VIDEO
                }
                if ($request->get('billing_country')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_country',
                        trim($request->get('billing_country'))
                    );
                    //VIDEO
                }

                if ($request->get('billing_email')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_email',
                        trim($request->get('billing_email'))
                    );
                    //VIDEO
                }
                if ($request->get('billing_phone')) {
                    //REFERENCE
                    $reference = $this->sm->updateUserMeta(
                        $userId,
                        'billing_phone',
                        trim($request->get('billing_phone'))
                    );
                    //VIDEO
                }
                $r = 1;
            }
        } else {
            $r = 2;
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    public function trierTableau($tabeauVideos)
    {
        $tab = array_unique($tabeauVideos);
        $tab = array_filter($tabeauVideos);
        return $tab;
    }
    // Recuperation des principaux menu

    /**
     * @Route("/admin/menu", name="admin_menu")
     * @param Request $request
     * @return Response
     */
    public function menu(Request $request)
    {
        //$menu=$this->sm->postCategorie("nav_menu",0);
        return $this->render('admin/MenusPages/menu.html.twig', [
            'header' => $this->sm->naveMenuItem(10),
            'footer' => $this->sm->naveMenuItem(18),
            'pages_libres' => $this->sm->naveMenuItem(18),
        ]);
    }

    /**
     * @Route("/admin/sous-menu-brouillon", name="admin_sous_menu_brouillon")
     * @param Request $request
     * @return Response
     */
    public function sousMenuBrouillon(Request $request)
    {
        $sousMenu = $this->sm->naveMenuItemBrouillon();
        return $this->render('admin/MenusPages/sous_menu_brouillon.html.twig', [
            'sous_menu' => $sousMenu,
            'statut' => 'draft',
        ]);
    }

    /**
     * @Route("/admin/sous-menu-trash",name="admin_sous_menu_trash")
     */
    public function sousMenuTrash(Request $request)
    {
        $sousMenu = $this->sm->naveMenuTrash();
        return $this->render('admin/MenusPages/sous_menu_brouillon.html.twig', [
            'sous_menu' => $sousMenu,
            'statut' => 'trash',
        ]);
    }

    /**
     * @Route("/admin/update_page", name="admin_update_page")
     * @param Request $request
     * @return Response
     */
    public function updatePage(Request $request)
    {
        $r = 0;
        if ($request->get('id') > 0 && $request->get('description') != '') {
            $r = $this->sm->updatePage(
                $request->get('id'),
                $request->get('id_menu'),
                $request->get('description'),
                $request->get('statut'),
                $request->getLocale(),
                $request->get('title'),
                $this->requestStack->getSession()->get('file')
            );
            if ($request->get('meta_desc')) {
                $meta_desc = $this->sm->readPostMeta(
                    $request->get('id'),
                    'meta_desc'
                );
                if ($meta_desc) {
                    $this->sm->updatePostMeta(
                        $meta_desc->getMetaId(),
                        $request->get('id'),
                        'meta_desc',
                        $request->get('meta_desc'),
                        $request->getLocale()
                    );
                } else {
                    $this->sm->createPostMeta(
                        $request->get('id'),
                        'meta_desc',
                        $request->get('meta_desc'),
                        $request->getLocale()
                    );
                }
            }
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/update_menu", name="admin_update_menu")
     * @param Request $request
     * @return Response
     */
    public function updateMenu(Request $request)
    {
        $r = 0;
        if (
            $request->get('id') > 0 &&
            trim($request->get('description')) != ''
        ) {
            $r = $this->sm->updateMenu(
                $request->get('id'),
                trim($request->get('description')),
                $request->getLocale()
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/update_category")
     * @param Request $request
     * @return Response
     */
    public function updateCategorie(Request $request)
    {
        $r = 0;
        if ($request->get('id') > 0 && $request->get('cat') != '') {
            $r = $this->sm->updateCat(
                $request->get('id'),
                $request->get('cat'),
                $request->getLocale()
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/delete_post", name="admin_delete_post")
     * @param Request $request
     * @return Response
     */
    public function deletePost(Request $request)
    {
        $r = 0;
        if ($request->get('id') >= 0) {
            $r = $this->sm->deletePosts1(
                $request->get('id'),
                $request->get('statut')
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/delete_post_definitely", name="post_delete_get", methods="GET")
     * @param Request $request
     * @return Response
     */
    public function deletePostDef(Request $request)
    {
        $r = 0;
        if ($request->get('id') > 0) {
            $this->sm->deletePosts($request->get('id'));
            $r = 1;
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/delete_categorie", name="admin_delete_categorie")
     * @param Request $request
     * @return Response
     */
    public function deleteCategorie(Request $request)
    {
        $r = 0;
        $cat = $this->sm->postCategorieWithLocaleContent($request->get('id'));
        $annonces = $this->sm->readAllAnnoncesParentforAjaxSearch($request->get('id'), '');

        if (sizeof($annonces) > 0) {
            $r = 2;
        } elseif (strtolower($request->get('id')) == 'produits') {
            $r = 3;
        } else {
            $r = $this->sm->deleteCategorie($request->get('id'));
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/delete_sub_categorie", name="admin_delete_sub_categorie")
     * @param Request $request
     * @return Response
     */
    public function deleteSubCategorie(Request $request)
    {
        $r = 0;
        $cat = $this->sm->sousCategorieById($request->get('id'));
        $annonces = $this->sm->readAllAnnoncesforAjaxSearch($cat['name'], '');
        if (sizeof($annonces) > 0) {
            $r = 2;
        } else {
            $r = $this->sm->deleteCategorie($request->get('id'));
            $r = 1;
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/new_categorie", name="admin_new_categorie")
     * @param Request $request
     * @return Response
     */
    public function newCategorie(Request $request)
    {
        $r = 0;
        if ($request->get('name') != '') {
            $r = $this->sm->createCategorie(
                $request->get('name'),
                $request->get('parent'),
                $request->get('type')
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/new_prestation_type", name="admin_new_prestation_type")
     * @param Request $request
     * @return Response
     */
    public function newPrestationType(Request $request)
    {
        $r = 0;
        $parent = 0;
        $frais = 0;

        if ($request->get('parent') > 0) {
            $parent = $request->get('parent');
        }
        if ($request->get('frais_livraison')) {
            $frais = $request->get('frais_livraison');
        }
        $r = $this->sm->createCategorie(
            $request->get('name'),
            $parent,
            $request->get('type'),
            0,
            $frais,
            $request->getLocale()
        );
        return $this->render('admin/resultat.html.twig', ['result' => $r]);
    }

    /**
     * @Route("/categorie/editer/{id}", name="category_edit_get", methods="GET")
     * @param int $id
     * @return Response
     */
    public function category_edit_get(int $id)
    {
        $category = $this->entityManager
            ->getRepository(WpTerms::class)
            ->findOneByTermId($id);

        return $this->render('admin/categories/edit_category.html.twig', [
            'category' => $category,
        ]);
    }

    /**
     * @Route("/categorie/editer", name="category_edit_post", methods="POST")
     * @param Request $request
     * @return RedirectResponse
     */
    public function category_edit_post(Request $request)
    {
        $category = $this->sm->updateCatWithDelivery(
            $request->get('id'),
            $request->get('titre'),
            $request->get('frais_livraison'),
            $request->getLocale()
        );

        $url = $this->adminUrlGenerator
            ->setController(WpTermTaxonomyCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
        return $this->redirect($url, 302);
    }

    /**
     * @Route("/admin/new_menu", name="admin_new_menu")
     * @param Request $request
     * @return Response
     */
    public function newMenu(Request $request)
    {
        $r = 0;
        $u = $this->getUser();
        $date = new DateTime();
        if ($request->get('name') != '') {
            $c = $this->sm->createCategorie(
                $request->get('name'),
                0,
                'nav_menu'
            );
            $name = $request->get('name');
            //Ajout d'un nouveau menu
            $id = $this->sm->createPosts(
                $u->getId(),
                $date,
                $date,
                $name,
                $name,
                $name,
                'inherit',
                'open',
                'closed',
                $name,
                $name,
                $name,
                $name,
                $date,
                $date,
                $name,
                0,
                'null',
                0,
                'nav_menu_item',
                'menu',
                0,
                $request->get('type')
            );
            $r = $this->sm->createPostMeta(
                $id,
                '_menu_item_menu_item_parent',
                $c,
                'fr'
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    //creation d'une nouvelle page

    /**
     * @Route("/new_page")
     * @param Request $request
     * @return Response
     */
    public function newPage(Request $request)
    {
        if ($request->get('id') > 0) {
            $menuMeta = $this->sm->readPostMeta(
                $request->get('id'),
                '_menu_item_menu_item_parent'
            );
            if ($menuMeta && $menuMeta->getMetaValue() > 0) {
                $sousMenu = $this->sm->naveMenuItem($menuMeta->getMetaValue());
            } else {
                $sousMenu = $this->sm->naveMenuItem($request->get('id'));
            }
            $this->smenup = [];
            for ($i = 0; $i < sizeof($sousMenu); $i++) {
                $p = $this->sm->readPostMeta(
                    $sousMenu[$i]->getId(),
                    '_menu_item_menu_item_parent'
                );
                if ($p) {
                    $idp = $p->getMetaValue();
                } else {
                    $idp = 0;
                }

                if ($idp == 0) {
                    $this->smenup[$i] = $sousMenu[$i];
                } else {
                    $sousMenu[$i]->setPostParent($idp);
                }
            }
        }
        return $this->render('admin/new_page.html.twig', [
            'id_nav_menu' => $menuMeta->getMetaValue(),
            'sous_menu_parent' => $this->smenup,
            'idmenu' => $request->get('id'),
            'menu_order' => sizeof($sousMenu) + 1,
            'lang' => $request->getLocale(),
        ]);
    }

    /**
     * @Route("/admin/submit_page", name="admin_submit_page")
     * @param Request $request
     * @return Response
     */
    public function submitPage(Request $request)
    {
        $r = 0;
        $u = $this->getUser();
        $date = new DateTime();
        if ($request->get('titre') != '' && $request->get('id_nav_menu') > 0) {
            $name = $this->sm->slugify($request->get('titre'));
            //Ajout d'une page

            $idpost = $this->sm->createPosts(
                $u->getId(),
                $date,
                $date,
                $request->get('description'),
                $request->get('titre'),
                $request->get('titre'),
                $request->get('statut'),
                'open',
                'closed',
                $name,
                '',
                $name,
                $name,
                $date,
                $date,
                $name,
                0,
                'null',
                0,
                'page',
                'menu',
                0,
                0
            );
            $idmenu = $this->sm->createPosts(
                $u->getId(),
                $date,
                $date,
                $name,
                $request->get('titre'),
                $request->get('titre'),
                $request->get('statut'),
                'open',
                'closed',
                $name,
                $name,
                $name,
                $name,
                $date,
                $date,
                $name,
                0,
                'null',
                $request->get('menu_order'),
                'nav_menu_item',
                'menu',
                0,
                $request->get('id_nav_menu')
            );
            $r = $this->sm->createPostMeta(
                $idmenu,
                '_menu_item_object_id',
                $idpost,
                $request->getLocale()
            );
            $r1 = $this->sm->createPostMeta(
                $idmenu,
                '_menu_item_menu_item_parent',
                $request->get('parent'),
                $request->getLocale()
            );
            if ($request->get('menu_order') == 1) {
                $r2 = $this->sm->updatePostParent(
                    $this->requestStack->getSession()->get('file'),
                    $idpost
                );
                if ($r2 > 0) {
                    $this->requestStack->getSession()->set('file', '');
                }
            }
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/login_register", name="admin_login_register")
     * @param Request $request
     * @return Response
     */
    public function login_register(Request $request)
    {
        return $this->render('admin/MenusPages/login_register.html.twig', [
            'lang' => $request->getLocale(),
            'bloc1' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('login_image'),
            'bloc2' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('register_image'),
            'bloc3' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName(
                    'verifier_email_reinitialiser_compte_image'
                ),
            'bloc4' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('reinitialiser_mot_de_passe_image'),
            'bloc5' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName(
                    'requete_reinitialiser_mot_de_passe_image'
                ),
            'bloc6' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('verifier_email_creation_compte_image'),
        ]);
    }

    /**
     * @Route("/admin/recaptcha", name="admin_get_recaptcha")
     * @param Request $request
     * @return Response
     */
    public function get_recaptcha(Request $request)
    {
        $recaptcha = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('recaptcha');
        return $this->render('admin/MenusPages/recaptcha.html.twig', [
            'lang' => $request->getLocale(),
            'recaptcha' => $recaptcha,
        ]);
    }

    /**
     * @Route("/admin/recaptcha/post", name="post_recaptcha")
     * @param Request $request
     * @return Response
     */
    public function post_recaptcha(Request $request)
    {
        $recaptcha = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('recaptcha');
        if ($recaptcha) {
            $this->entityManager->remove($recaptcha);
            $this->entityManager->flush();
        }

        $recaptcha = new WpOptions();
        $recaptcha->setOptionName('recaptcha');
        $recaptcha->setOptionValue($request->get('recaptcha'));
        $recaptcha->setAutoLoad('no');
        $this->entityManager->persist($recaptcha);
        $this->entityManager->flush();

        return $this->render('admin/resultat.html.twig', [
            'result' => $recaptcha->getOptionId(),
        ]);
    }

    /**
     * @Route("/admin/youtube", name="admin_get_youtube")
     * @param Request $request
     * @return Response
     */
    public function get_youtube(Request $request)
    {
        $youtube = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('home-youtube');
        return $this->render('admin/MenusPages/youtube.html.twig', [
            'lang' => $request->getLocale(),
            'youtube' => $youtube,
        ]);
    }

    /**
     * @Route("/admin/youtube/post", name="post_youtube")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function post_youtube(Request $request)
    {
        $youtube = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('home-youtube');
        if ($youtube) {
            $this->entityManager->remove($youtube);
            $this->entityManager->flush();
            $conn = $this->entityManager->getConnection();
            //delete translatable data
            $sql1 = 'DELETE FROM ext_translations_wp_options WHERE foreign_key =:id';
            $stmt = $conn->prepare($sql1);
            $stmt->execute(['id' => $youtube->getOptionId()]);
            $this->entityManager->flush();
        }

        $youtube = new WpOptions();
        $youtube->setOptionName('home-youtube');
        $youtube->setOptionValue($request->get('youtube'));
        if ($request->get('statut')) {
            $youtube->setAutoLoad('yes');
        } else {
            $youtube->setAutoLoad('no');
        }
        $this->entityManager->persist($youtube);
        $this->entityManager->flush();

        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/admin/recherche-vide/message", name="admin_get_empty_request_message")
     * @param Request $request
     * @return Response
     */
    public function get_empty_request_message(Request $request)
    {
        $message = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneByPostType('p_empty_search_text');

        return $this->render('admin/MenusPages/product_empty_request_message.html.twig', [
            'lang' => $request->getLocale(),
            'message' => $message,
        ]);
    }

    /**
     * @Route("/admin/recherche-vide-nom-commercial/message", name="admin_get_empty_request_nom_commercial_message")
     * @param Request $request
     * @return Response
     */
    public function admin_get_empty_request_nom_commercial_message(Request $request)
    {
        $message = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy(array('postType'=>'empty_nom_commercial'), array('id' => 'DESC'));

        return $this->render('admin/MenusPages/product_empty_nom_commercial_request_message.html.twig', [
            'lang' => $request->getLocale(),
            'message' => $message,
        ]);
    }

    /**
     * @Route("/admin/recherche-vide/message/post", name="post_get_empty_request_message")
     * @param Request $request
     * @return Response
     */
    public function post_get_empty_request_message(Request $request)
    {
        $date = new DateTime();
        $u = $this->getUser();
        $message = $this->entityManager
            ->getRepository(WpPosts::class)
                ->findOneByPostType($request->get('post_type'));
        if ($message) {
            $this->entityManager->remove($message);
            $this->entityManager->flush();
        }

        $id = $this->sm->createPosts1(
            $u->getId(),
            $date,
            $date,
            $request->get('message'),
            'message',
            $request->get('message'),
            'publish',
            'open',
            '',
            '',
            'message',
            '',
            '',
            $date,
            $date,
            $request->get('message'),
            0,
            '',
            0,
            $request->get('post_type'),
            $request->get('post_type'),
            0,
            0
        );

        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/infos-bulle", name="get_infos_bulle")
     * @param Request $request
     * @return Response
     */
    public function get_infos_bulle(Request $request)
    {
        $infos = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('infos_bulle_' . $request->getLocale());
        return $this->render('admin/Commandes/InfosBulle/infos_bulle.html.twig', [
            'lang' => $request->getLocale(),
            'infos' => $infos,
        ]);
    }

    /**
     * @Route("/calendar-infos-bulle", name="get_calendar_infos_bulle")
     * @param Request $request
     * @return Response
     */
    public function get_calendar_infos_bulle(Request $request)
    {
        $calendar_infos = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('calendar_infos_bulle_' . $request->getLocale());
        return $this->render('admin/Commandes/InfosBulle/calendar_infos_bulle.html.twig', [
            'lang' => $request->getLocale(),
            'calendar_infos' => $calendar_infos,
        ]);
    }

    /**
     * @Route("/infos-bulle/post", name="post_infos_bulle")
     * @param Request $request
     * @return RedirectResponse
     */
    public function post_infos_bulle(Request $request)
    {
        $infos = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('infos_bulle_' . $request->getLocale());
        if ($infos) {
            $this->entityManager->remove($infos);
            $this->entityManager->flush();
        }

        $infos = new WpOptions();
        $infos->setOptionName('infos_bulle_' . $request->getLocale());
        $infos->setOptionValue($request->get('infos_bulle'));
        $infos->setAutoLoad('no');
        $this->entityManager->persist($infos);
        $this->entityManager->flush();

        return $this->redirectToRoute('admin', ['menuIndex' => '7', 'routeName' => 'get_infos_bulle', 'submenuIndex' => '1']);
    }

    /**
     * @Route("/calendar-infos-bulle/post", name="post_calendar_infos_bulle")
     * @param Request $request
     * @return RedirectResponse
     */
    public function post_calendar_infos_bulle(Request $request)
    {
        $infos = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('calendar_infos_bulle_' . $request->getLocale());
        if ($infos) {
            $this->entityManager->remove($infos);
            $this->entityManager->flush();
        }

        $infos = new WpOptions();
        $infos->setOptionName('calendar_infos_bulle_' . $request->getLocale());
        $infos->setOptionValue($request->get('infos_bulle'));
        $infos->setAutoLoad('no');
        $this->entityManager->persist($infos);
        $this->entityManager->flush();

        return $this->redirectToRoute('admin', ['menuIndex' => '7', 'routeName' => 'get_calendar_infos_bulle', 'submenuIndex' => '2']);
    }

    /**
     * @Route("/admin/slider",name="admin_slider")
     * @param Request $request
     * @return Response
     */
    public function slider(Request $request)
    {
        return $this->render('admin/MenusPages/slider.html.twig', [
            'categories' => $this->sm->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'lang' => $request->getLocale(),
            'bloc1' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_1'),
            'bloc2' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_2'),
            'bloc3' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_3'),
            'bloc4' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_4'),
            'bloc5' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_5'),
            'bloc6' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_6'),
            'bloc7' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_7'),
            'bloc8' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_8'),
            'bloc9' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_9'),
            'bloc10' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_10'),
            'bloc11' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_11'),
            'bloc12' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('accueil_bloc_12'),
            'slogan_homepage' => $this->entityManager
                ->getRepository(WpOptions::class)
                ->findOneByOptionName('slogan_homepage'),
            'encart_homepage_1' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('encart_homepage_1'),
            'encart_homepage_2' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('encart_homepage_2'),
        ]);
    }

    /**
     * @Route("/all_prestation_type")
     * @param Request $request
     * @return Response
     */
    public function all_prestation_type(Request $request)
    {
        $prestations = $this->sm->postCategorieWithMultilang(
            'prestation_type',
            0
        );
        //dd($prestations);
        return $this->render('admin/categories/all_prestation_type.html.twig', [
            'categories' => $this->sm->postCategorieWithMultilang(
                'product_cat',
                0
            ),
            'prestations' => $prestations,
            'lang' => $request->getLocale(),
        ]);
    }

    /**
     * @Route("/admin/meta/delete", name="admin_meta_delete")
     * @param Request $request
     * @return Response
     */
    public function deleteMeta(Request $request)
    {
        $r = $this->entityManager
            ->getRepository(WpUsermeta::class)
            ->find($request->get('id'));
        if ($r) {
            $this->entityManager->remove($r);
            $this->entityManager->flush();
        }
        return $this->render('admin/resultat.html.twig', ['result' => 1]);
    }

    public function getPagePostTitleFr($code)
    {
        $title = $this->entityManager->getRepository(WpPosts::class)->find($code);
        if ($title) {
            return new Response($title->getPostTitle());
        } else {
            return new Response('');
        }
    }

    /**
     * @Route("/handle_category_product", name="handle_category_product")
     * @param Request $request
     * @return Response
     */
    public function handle_category_product(Request $request)
    {
        $cat_produits = $this->sm->postCategorieProductWithMultilang(
            'prestation_type',
            'product_cat',
            0
        );

        return $this->render(
            'admin/categories/all_category_product.html.twig',
            [
                'categories' => $this->sm->postCategorieWithMultilang(
                    'prestation_type',
                    0
                ),
                'cat_produits' => $cat_produits,
                'lang' => $request->getLocale(),
            ]
        );
    }

    /**
     * @Route("/add_image_home", name="add_image_home")
     * @param Request $request
     * @return Response
     */
    public function submitImageHome(Request $request)
    {
        $id = 0;
        $config = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName($request->get('option_name'));
        if ($config) {
            $this->entityManager->remove($config);
            $this->entityManager->flush();
        }
        $config = new WpOptions();
        $config->setOptionName($request->get('option_name'));
        if ($request->get('categories')) {
            $config->setOptionValue(
                $request->get('categories') .
                '|||' .
                $this->requestStack->getSession()->get('file')
            );
        } else {
            $config->setOptionValue($this->requestStack->getSession()->get('file'));
        }
        $config->setAutoLoad('no');
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        $id = 1;
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/admin/slogan_homepage", name="slogan_homepage")
     * @param Request $request
     * @return Response
     */
    public function slogan_homepage(Request $request)
    {
        $id = 0;
        $config = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName('slogan_homepage');
        if ($config) {
            $config->setOptionValue($request->get('slogan_homepage_text'));
            $this->entityManager->flush();
        } else {
            $config = new WpOptions();
            $config->setOptionName('slogan_homepage');
            $config->setOptionValue($request->get('slogan_homepage_text'));
            $config->setAutoLoad('no');
            $this->entityManager->persist($config);
            $this->entityManager->flush();
        }
        $id = 1;
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/admin/encart_homepage", name="encart_homepage")
     * @param Request $request
     * @return Response
     */
    public function encartHomepage(Request $request)
    {
        $id = 0;
        $config = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findOneByOptionName($request->get('encart_option_name'));
        if ($config) {
            $config->setOptionValue($request->get('encart_title') . '|||' . $request->get('encart_description') . '|||' . $request->get('encart_url'));
            $this->entityManager->flush();
        } else {
            $config = new WpOptions();
            $config->setOptionName($request->get('encart_option_name'));
            $config->setOptionValue($request->get('encart_title') . '|||' . $request->get('encart_description') . '|||' . $request->get('encart_url'));
            $config->setAutoLoad('no');
            $this->entityManager->persist($config);
            $this->entityManager->flush();
        }
        $id = 1;
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/admin/date_annonce", name="admin_date_annonce")
     * @param Request $request
     * @return Response
     */
    public function dateAnnonce(Request $request)
    {
        $date = [];
        if ($request->get('id') > 0) {
            $p = $this->sm->postById($request->get('id'));

            $d1 = $this->sm->readUserMeta($p->getPostAuthor(), 'disponibilite');
            if ($d1) {
                $date = explode(',', $d1->getMetaValue());
            }
        }

        return $this->render('admin/Admin/date.html.twig', [
            'date' => $date,
            'date1' => null,
        ]);
    }

    /**
     * @Route("/admin/date_annonce1", name="admin_date_annonce1")
     * @param Request $request
     * @return Response
     */
    public function dateAnnonce1(Request $request)
    {
        $date = [];
        if ($request->get('id') > 0) {
            $p = $this->sm->postById($request->get('id'));
            $c = $this->sm->postById($request->get('idc'));
            $produit = @unserialize($c->getPostContent());
            $date1 = [];
            for ($i = 0; $i < sizeof($produit); $i++) {
                if ($produit && $produit[$i]['id'] == $request->get('id')) {
                    $date1 = explode('|', $produit[$i]['dates']);
                }
            }
            $d1 = $this->sm->readUserMeta($p->getPostAuthor(), 'disponibilite');
            if ($d1) {
                $date = explode(',', $d1->getMetaValue());
            }
        }

        return $this->render('admin/Admin/date.html.twig', [
            'date' => $date,
            'date1' => $date1,
        ]);
    }

    /**
     * @Route("/admin/admin_delete_service", name="admin_delete_service")
     * @param Request $request
     * @return RedirectResponse
     */
    public function adminDeleteService(Request $request)
    {
        $r = 0;
        if ($request->get('id') > 0) {
            $p = $this->sm->postById($request->get('id'));
            $u = $this->sm->userById($p->getPostAuthor());
            $c = $this->sm->postById($request->get('idcommande'));
            $produit = @unserialize($c->getPostContent());
            $produit1 = [];
            for ($i = 0; $i < sizeof($produit); $i++) {
                if ($produit[$i]['id'] != $request->get('id')) {
                    $produit1[sizeof($produit1)] = $produit[$i];
                } elseif ($produit[$i]['id'] == $request->get('id')) {
                    $c->setPostParent(
                        $c->getPostParent() - $produit[$i]['prix']
                    );
                }
            }
            $r = $this->sm->updateCommande(
                $c->getId(),
                @serialize($produit1),
                $c->getPostParent()
            );
        }

        $url = $this->adminUrlGenerator
            ->setController(CommandesWpPostsCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($request->get('id'))
            ->generateUrl();
        return $this->redirect($url);
    }

    /**
     * @Route("/admin/admin_add_service", name="admin_add_service")
     * @param Request $request
     * @return Response
     */
    public function adminAddService(Request $request)
    {
        $r = 0;
        if ($request->get('id') > 0 || $request->get('idpost') > 0) {
            $p = $this->sm->postById($request->get('id'));
            $u = $this->sm->userById($p->getPostAuthor());
            $c = $this->sm->postById($request->get('idcommande'));
            $produit = @unserialize($c->getPostContent());
            $prix = $this->sm->getPostStringDataValue($p->getId(), '_price');
            $devise = $this->sm->getPostStringDataValue(
                $p->getId(),
                '_product_devise'
            );
            $adresse = $this->sm->getPostStringDataValue(
                $p->getId(),
                '_product_adress'
            );
            $gallerie = $this->sm->getAllImageForAnnouces($p->getId());
            $img = '';
            if ($gallerie && $gallerie[0]) {
                $img = $gallerie[0]->getGuid();
            }
            $date = implode('|', $request->get('date'));
            $prixTotal = 0;
            if ($prix) {
                $prixTotal = $prix * sizeof($request->get('date'));
            }

            if ($request->get('idpost')) {
                for ($i = 0; $i < sizeof($produit); $i++) {
                    if ($produit[$i]['id'] == $request->get('idpost')) {
                        $c->setPostParent(
                            $c->getPostParent() - $produit[$i]['prix']
                        );
                        $produit[$i]['dates'] = $date;
                        $produit[$i]['prix'] = $prixTotal;
                        $produit[$i]['qte'] = sizeof($request->get('date'));
                    }
                }
            } else {
                $produit[] = [
                    'id' => $p->getId(),
                    'userId' => $p->getPostAuthor(),
                    'libelle' => $p->getPostTitle(),
                    'qte' => sizeof($request->get('date')),
                    'prixu' => $prix,
                    'prix' => $prixTotal,
                    'devise' => $devise,
                    'dates' => $date,
                    'image' => $img,
                    'adresse' => $adresse,
                    'email' => $u->getEmailCanonical(),
                ];
            }

            $r = $this->sm->updateCommande(
                $c->getId(),
                @serialize($produit),
                $c->getPostParent() + $prixTotal
            );
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/admin/admin_edit_commande",name="admin_edit_commande")
     * @param Request $request
     * @return Response
     */
    public function adminupdatePost(Request $request)
    {
        $r = 0;
        if ($request->get('id_post') > 0) {
            $r = $this->sm->updateCommande1(
                $request->get('id_post'),
                $request->get('date'),
                $request->get('status')
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("admin/admin_update_user", name="admin_update_user")
     * @param Request $request
     * @return Response
     */
    public function updateUserData(Request $request)
    {
        $userId = $request->get('idu');

        //First Name
        if ($request->get('first_name')) {
            $first_name = $this->sm->updateUserMeta(
                $userId,
                'first_name',
                trim($request->get('first_name'))
            );
            // code...
        }
        //Last name
        if ($request->get('last_name')) {
            $last_name = $this->sm->updateUserMeta(
                $userId,
                'last_name',
                trim($request->get('last_name'))
            );
            // code...
        }
        if ($request->get('bdaytime')) {
            //Birthday
            $bdaytime = $this->sm->updateUserMeta(
                $userId,
                'bdaytime',
                $request->get('bdaytime')
            );
            // code...
        }
        if ($request->get('sexe')) {
            //Sexe
            $sexe = $this->sm->updateUserMeta(
                $userId,
                'sexe',
                trim($request->get('sexe'))
            );
            // code...
        }
        //Birth Place
        if ($request->get('birth_place')) {
            $birth_place = $this->sm->updateUserMeta(
                $userId,
                'birth_place',
                trim($request->get('birth_place'))
            );
        }
        if ($request->get('telephone')) {
            //Telephone
            $telephone = $this->sm->updateUserMeta(
                $userId,
                'telephone',
                trim($request->get('telephone'))
            );
            // code...
        }
        if ($request->get('raison_sociale')) {
            //Raison Sociale
            $raison_sociale = $this->sm->updateUserMeta(
                $userId,
                'raison_sociale',
                trim($request->get('raison_sociale'))
            );
        }
        //SIRET
        if ($request->get('siret')) {
            $siret = $this->sm->updateUserMeta(
                $userId,
                'siret',
                trim($request->get('siret'))
            );
        }
        if ($request->get('tva')) {
            //TVA
            $tva = $this->sm->updateUserMeta(
                $userId,
                'tva',
                trim($request->get('tva'))
            );
            // code...
        }
        if ($request->files->get('avatar')) {
            // Avatar
            $avatarUrl = $this->sm->uploadAvatar(
                $userId,
                $request->files->get('avatar'),
                $this->getParameter('avatar_directory')
            );
            if ($avatarUrl) {
                $avatar = $this->sm->updateUserMeta(
                    $userId,
                    'basic_user_avatar',
                    $avatarUrl
                );
                $this->requestStack->getSession()->set('avatar', $avatarUrl);
            }
        }
        if ($request->get('titre')) {
            //TITRE
            $titre = $this->sm->updateUserMeta(
                $userId,
                'titre',
                trim($request->get('titre'))
            );
        }
        //DESCRIPTION
        if ($request->get('description')) {
            $description = $this->sm->updateUserMeta(
                $userId,
                'description',
                trim($request->get('description'))
            );
            // code...
        }
        if ($request->get('competence1')) {
            $competences = $this->sm->updateUserMeta(
                $userId,
                'competence',
                implode(
                    ',',
                    array_column(
                        json_decode($request->get('competence1')),
                        'value'
                    )
                )
            );
        } else {
            $competences = $this->sm->updateUserMeta($userId, 'competence', '');
        }
        if ($request->get('region')) {
            //REGION
            $region = $this->sm->updateUserMeta(
                $userId,
                'region',
                trim($request->get('region'))
            );
        }
        if ($request->get('reference')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'reference',
                trim($request->get('reference'))
            );
            //VIDEO
        }
        if ($request->get('billing_last_name')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_last_name',
                trim($request->get('billing_last_name'))
            );
            //VIDEO
        }

        if ($request->get('billing_first_name')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_first_name',
                trim($request->get('billing_first_name'))
            );
            //VIDEO
        }

        if ($request->get('billing_company')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_company',
                trim($request->get('billing_company'))
            );
            //VIDEO
        }

        if ($request->get('billing_city')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_city',
                trim($request->get('billing_city'))
            );
            //VIDEO
        }

        if ($request->get('billing_address_1')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_address_1',
                trim($request->get('billing_address_1'))
            );
            //VIDEO
        }

        if ($request->get('billing_postcode')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_postcode',
                trim($request->get('billing_postcode'))
            );
            //VIDEO
        }
        if ($request->get('billing_country')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_country',
                trim($request->get('billing_country'))
            );
            //VIDEO
        }

        if ($request->get('billing_email')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_email',
                trim($request->get('billing_email'))
            );
            //VIDEO
        }
        if ($request->get('billing_phone')) {
            //REFERENCE
            $reference = $this->sm->updateUserMeta(
                $userId,
                'billing_phone',
                trim($request->get('billing_phone'))
            );
            //VIDEO
        }

        $video = $this->sm->readUserMeta($userId, 'video');

        if ($video && $request->get('new_vid')[0] != '') {
            if (sizeof(@unserialize($video->getMetaValue())) > 0) {
                $tabeauVideos = @unserialize($video->getMetaValue());
                $tabeauVideos = $this->trierTableau($tabeauVideos);
                $vid = $this->sm->updateUserMeta(
                    $userId,
                    'video',
                    @serialize(
                        array_merge(
                            $tabeauVideos,
                            $this->trierTableau($request->get('new_vid'))
                        )
                    )
                );
            } else {
                $vid = $this->sm->updateUserMeta(
                    $userId,
                    'video',
                    @serialize(
                        $this->trierTableau(
                            $this->trierTableau($request->get('new_vid'))
                        )
                    )
                );
            }
        } elseif ($request->get('new_vid') && $request->get('new_vid')[0] != '') {
            $vid = $this->sm->updateUserMeta(
                $userId,
                'video',
                @serialize($this->trierTableau($request->get('new_vid')))
            );
        }
        if ($request->files->get('file')) {
            //portfolio
            $file = $request->files->get('file');
            $id = $this->sm->portfolio(
                $file,
                $this->getParameter('portfolio_directory'),
                $userId
            );
            $port = $this->sm->readUserMeta($userId, 'portfolio');

            if ($port && $port->getMetaValue() != '') {
                $id = $id . ',' . $port->getMetaValue();
            }
            if ($id) {
                $portfolio = $this->sm->updateUserMeta(
                    $userId,
                    'portfolio',
                    $id
                );
            }
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/admin/recherche_element" , name="recherche_element_dashboard")
     * @param Request $request
     * @return JsonResponse
     */
    public function recherche_element_dashboard(Request $request)
    {
        $data = $this->sm->search_admin_content(
            $request->get('term'),
            $request->get('type')
        );
        return new JsonResponse($data);
    }

    public function checkMangopayAccount(int $id)
    {
        $mpAccount = $this->entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $id, 'metaKey' => 'mp_user_id_sandbox']);
        if ($mpAccount) {
            return new Response($mpAccount->getMetaValue());
        } else {
            return new Response('Non');
        }
    }

    public function checkAbonnementDaysLeft(int $id)
    {
        // Set current date to midnight
        $now = new \DateTime();
        $now->setTime(0, 0, 0);  // Remove hours, minutes, and seconds

        $abonnement = $this->entityManager->getRepository(Abonnement::class)->find($id);

        if ($abonnement && $abonnement->isAbonnementActif() && strtolower($abonnement->getOffre()->getTitre()) != 'gratuit') {
            // Add one day to the dateFin and set its time to midnight
            $dateFinPlusOneDay = clone $abonnement->getDateFin();
            $dateFinPlusOneDay->modify('+1 day');
            $dateFinPlusOneDay->setTime(0, 0, 0);  // Remove hours, minutes, and seconds

            // Calculate the difference in days
            $interval = $now->diff($dateFinPlusOneDay);

            // If the difference is less than or equal to 0, return 0, otherwise return the days left
            $daysLeft = ($interval->invert == 1 || $interval->days == 0) ? 0 : $interval->days;

            return new Response($daysLeft);
        } else {
            return new Response(0);
        }
    }

    public function isUserKycDocumentValidated(int $id)
    {
        $response = $this->sm->getUserStringDataValue($id, 'kyc_doc_status');
        if ($response) {
            return new Response('Oui');
        } else {
            return new Response('Non');
        }
    }
}
