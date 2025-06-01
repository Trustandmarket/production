<?php

namespace App\Controller\Admin\Articles;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use App\Entity\WpPosts;
use App\Entity\wp_users;
use App\Entity\WpUsermeta;
use App\Entity\WpPostmeta;
use App\Entity\Translation\WpPostsTranslation;
use App\Service\ServiceManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Config\Loader\FileLoader;

class ArticleController extends AbstractController
{
    private $sm;
    public function __construct(ServiceManager $sm)
    {
        $this->sm = $sm;
    }

    public function editAction()
    {
        $id = $this->request->query->get('id');
        $p = $this->sm->readPosts($id);
        $img = $this->em
            ->getRepository(WpPosts::class)
            ->findOneBy(['postParent' => $id, 'postType' => 'attachment']);
        $meta_desc = $this->sm->readPostMeta($id, 'meta_desc');
        $contenu_en = $this->em
            ->getRepository(WpPostsTranslation::class)
            ->findBy(['foreignKey' => $id, 'locale' => 'en_US']);

        return $this->render('admin/edit_post.html.twig', [
            'categorie' => $this->sm->postCategorie('category', 0),
            'article' => $p,
            'contenu_en' => $contenu_en,
            'meta_desc' => $meta_desc,
            'relation' => $this->sm->readTermRelation($p->getId()),
            'img' => $img,
            'lang' => $this->request->getLocale(),
            'd' => new DateTime(),
        ]);
    }

    public function deleteAction()
    {
        $id = $this->request->query->get('id');
        $r = $this->sm->deletePost($id);
        return $this->redirect(
            '/fr/admin/?entity=Article&action=list&menuIndex=4&submenuIndex=0'
        );
    }

    /**
     * @Route("/{_locale}/admin/sous_categorie", name="admin_sous_categorie")
     */
    public function sousCategorie(Request $request)
    {
        return $this->render('admin/sous_categorie.html.twig', [
            'categorie' => $this->sm->postCategorie(
                'product_cat',
                $request->get('id')
            ),
        ]);
    }
    // inserer une annonce en bd
    /**
     * @Route("/{_locale}/admin/upload", name="upload")
     */
    public function uploads(Request $request, SessionInterface $session)
    {
        $file = $request->files->get('file');
        $u = $this->getUser();
        $id = $this->sm->upload1(
            $file,
            $this->getParameter('product_directory'),
            $u->getId()
        );

        $session->set('file', $id);
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/admin/updateUpload", name="update_upload")
     */
    public function updateUpload(Request $request, SessionInterface $session)
    {
        $file = $request->files->get('file');
        $u = $this->getUser();
        $id = $this->sm->updateUpload(
            $file,
            $this->getParameter('product_directory'),
            $request->get('idImage'),
            $request->get('postId'),
            $u->getId()
        );

        $session->set('file', $id);

        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    // inserer une image
    /**
     * @Route("/{_locale}/admin/admin_create_post", name="admin_create_post")
     */
    public function submitAnnonce(Request $request, SessionInterface $session)
    {
        $u = $this->getUser();
        $uid = $u->getId();
        $date = new DateTime();
        $id = 0;
        if ($request->get('titre') != '') {
            $ids = explode(', ', $request->get('titre'));
            $name = $this->sm->slugify($request->get('titre'));
            for ($i = 1; $i < sizeof($ids); $i++) {
                $name = $name . '-' . $ids[$i];
            }

            $idc = $request->get('categorie');

            $id = $this->sm->createPosts(
                $uid,
                $date,
                $date,
                $request->get('description'),
                $request->get('titre'),
                $request->get('titre'),
                $request->get('status'),
                'open',
                'open',
                $name,
                $name,
                $name,
                $name,
                $date,
                $date,
                $request->get('description'),
                0,
                $request->get('titre'),
                0,
                'post',
                'annonce',
                0,
                $idc
            );
            if ($request->get('meta_desc')) {
                $this->sm->createPostMeta(
                    $id,
                    'meta_desc',
                    $request->get('meta_desc'),
                    $request->getLocale()
                );
            }
            // meta post

            if (!empty($session->get('file'))) {
                $id1 = $this->sm->createPosts(
                    $uid,
                    $date,
                    $date,
                    $request->get('description'),
                    $request->get('titre'),
                    $request->get('titre'),
                    'publish',
                    'open',
                    'open',
                    $name,
                    $name,
                    $name,
                    $name,
                    $date,
                    $date,
                    $request->get('description'),
                    $id,
                    $session->get('file'),
                    0,
                    'attachment',
                    'annonce',
                    0,
                    0
                );
            }
            $session->set('file', ' ');
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/{_locale}/admin/admin_edit_post", name="admin_edit_post")
     * @param Request $request
     * @param SessionInterface $session
     * @return Response
     */
    public function updateAnnonce(Request $request, SessionInterface $session)
    {
        $u = $this->getUser();
        $uid = $u->getId();
        $id = 0;

        if ($request->get('titre') != '' && $uid > 0) {
            $id = $this->sm->updateArticle(
                $request->get('titre'),
                $request->get('description'),
                $request->get('id'),
                $request->get('status'),
                $request->get('categorie'),
                $request->getLocale()
            );

            if ($request->get('meta_desc')) {
                $meta_desc = $this->sm->readPostMeta($id, 'meta_desc');
                if ($meta_desc) {
                    $this->sm->updatePostMeta(
                        $meta_desc->getMetaId(),
                        $id,
                        'meta_desc',
                        $request->get('meta_desc'),
                        $request->getLocale()
                    );
                } else {
                    $this->sm->createPostMeta(
                        $id,
                        'meta_desc',
                        $request->get('meta_desc'),
                        $request->getLocale()
                    );
                }
            }

            if (!empty($session->get('file')) && $id > 0) {
                $img = $this->sm->postParentImage($id);
                if ($img && $session->get('file') != '') {
                    $id1 = $this->sm->updateImage(
                        $img->getId(),
                        $session->get('file')
                    );
                }
            }
            $session->set('file', '');
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }
}
