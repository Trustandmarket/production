<?php

namespace App\Controller;

use App\Entity\WpOptions;
use App\Service\ServiceManager;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpTerms;

/**
 * @Route(name="blog_")
 */
class BlogController extends AbstractController
{
    private $service_manager;
    private $entityManager;
    private $requestStack;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack
    )
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/{_locale}/blog", name="index", requirements={"_locale": "fr"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Request $request)
    {
        return $this->render('blog/index.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'data' => $this->service_manager->getAllBlog(),
            'dataMusique' => $this->service_manager->getAllBlogBy('musique'),
            'dataPhotographie' => $this->service_manager->getAllBlogBy('photographie'),
            'dataVideo' => $this->service_manager->getAllBlogBy('video'),
            'dataExperience' => $this->service_manager->getAllBlogBy('experiences'),
            'categorie' =>$request->get('categorie'),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }

    /**
     * @Route("/{_locale}/blog/{slug}", name="details_postBlog", requirements={"_locale": "fr"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function details(Request $request)
    {
        $post = $this->service_manager->getDetailsOfBlogPostBySlug(
            $request->get('slug')
        );
        if ($post) {
            $meta_desc = $this->service_manager->readPostMeta(
                $post->getId(),
                'meta_desc'
            );
        } else {
            $meta_desc = null;
        }
        $relation = $this->service_manager->readTermRelation($post->getId());
        $categorie = null;
        if ($relation) {
            $categorie = $this->entityManager
            ->getRepository(WpTerms::class)
            ->findOneByTermId($relation->getTermTaxonomyId());
        }

        return $this->render('blog/details.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'post' => $post,
            'meta_desc' => $meta_desc,
            'relatedPost' => array_slice($this->service_manager->getRelatedBlogPost($post->getId()), 5),
            'lastArticles' => $this->service_manager->getLastBlogPost(18),
            'nbrComments' => $this->service_manager->countPostCommentReceived($post->getId()),
            'categories' => $this->service_manager->postCategorie('category', 0),
            'relation' => $relation,
            'categorie' => $categorie,
            'listeCommentaires' => $this->service_manager->listAndOrderPostComment($post->getId()),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube')
        ]);
    }

    /**
     * @Route("/blog/details/ajouter-commentaires/{_locale}/{id}", name="addComment", requirements={"_locale": "fr"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addComment(Request $request)
    {
        $userId = 0;
        $commentAuthor = $request->get('nom');
        $emailAuthor = $request->get('emailAuthor');
        if ($this->getUser()) {
            $userId = $this->getUser()->getId();
            $commentAuthor =
                $this->service_manager->getUserStringDataValue(
                    $userId,
                    'first_name'
                ) .
                ' ' .
                $this->service_manager->getUserStringDataValue(
                    $userId,
                    'last_name'
                );

            $emailAuthor = $this->getUser()->getEmailCanonical();
        }
        $date = new DateTime();

        $commentaire = $this->service_manager->createComments(
            $request->get('id'),
            $commentAuthor,
            $emailAuthor,
            $request->get('site'),
            $request->getClientIp(),
            $date,
            $date,
            $request->get('commentaire'),
            0,
            'trash',
            '',
            '',
            0,
            $userId
        );
        return $this->render('admin/resultat.html.twig', [
            'result' => 1
        ]);
    }

    /**
     * @Route("/blog/details/ajout-rapide-commentaires/{_locale}", name="rapid_add_Comment", requirements={"_locale": "fr"})
     * @param Request $request
     * @return RedirectResponse
     */
    public function rapidAddComment(Request $request)
    {
        $userId = $this->getUser()->getId();
        $date = new DateTime();
        $commentAuthor =
            $this->service_manager->getUserStringDataValue(
                $request->get('parent_id'),
                'first_name'
            ) .
            ' ' .
            $this->service_manager->getUserStringDataValue(
                $userId,
                'last_name'
            );

        $commentaire = $this->service_manager->createComments(
            $request->get('comment_id'),
            $commentAuthor,
            $this->getUser()->getEmailCanonical(),
            '',
            $request->getClientIp(),
            $date,
            $date,
            $request->get('commentaire'),
            0,
            'trash',
            '',
            '',
            $request->get('comment_id'),
            $userId
        );

        // sessions message here
        $this->requestStack->getSession()
            ->getFlashBag()
            ->set('success', " 'systeme.requetes.requete-en-cours'|trans ");

        $referer = $request->headers->get('referer');
        return new RedirectResponse($referer);
    }

    /**
     * Réponse a un commentaire
     * @param $commentId
     * @param $postId
     * @param $message
     * @return null|array
     */
    public function responseToPostComment($commentId, $postId, $message)
    {
        $commentaire = $this->service_manager->createComments(
            $userId,
            $commentAuthor,
            $this->getUser()->getEmailCanonical(),
            $request->get('site'),
            $request->getClientIp(),
            $date,
            $date,
            $request->get('commentaire'),
            0,
            1,
            '',
            '',
            0,
            $userId
        );

        return $commentaire;
    }
}
