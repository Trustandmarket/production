<?php

// src/App/Controller/AdminController.php

namespace App\Controller\Admin\ParcoursUtilisateur;

use DateTime;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use App\Entity\wp_users;
use App\Entity\WpPosts;
use App\Entity\WpPostmeta;
use App\Entity\WpOptions;
use App\Entity\WpUsermeta;
use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Service\ServiceManager;
use App\Service\Payment;
use Cocur\Slugify\Slugify;
use App\Controller\Admin\ParcoursUtilisateur\Experiences\WpPostsCrudController as ExperiencesCrudController;
use App\Controller\Admin\ParcoursUtilisateur\UniversTrust\WpPostsCrudController as UniversTrustCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class ParcoursUtilisateurController extends AbstractController
{
    private $entityManager;
    private $sm;
    private $adminUrlGenerator;
    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $sm,
        AdminUrlGenerator $adminUrlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->sm = $sm;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    /**
     *
     * @Route("/{_locale}/admin/parcours_utilisateur/univers_trust/create", name="parcours_utilisateur_univers_create", methods="POST")
     * @param Request $request
     * @param RequestStack $requestStack
     * @return Response
     */
    public function parcours_utilisateur_univers_create(Request $request, RequestStack $requestStack)
    {
        $uid = $this->getUser()->getId();
        $date = new DateTime();
        $slugify = new Slugify();
        $id = $this->sm->createPosts1(
            $this->getUser()->getId(),
            $date,
            $date,
            $request->get('contenu'),
            $request->get('titre'),
            $request->get('liens'),
            'save',
            'open',
            'closed',
            '',
            $slugify->slugify($request->get('titre')),
            0,
            0,
            $date,
            $date,
            '',
            0,
            $requestStack->getSession()->get('file'),
            0,
            'experiences_post',
            '',
            0,
            0
        );
        $requestStack->getSession()->set('file', '');

        //Stocker les metas categories a afficher
        $this->sm->createPostMeta(
            $id,
            'experiences_youtube',
            $request->get('youtube'),
            $request->getLocale()
        );
        //Stocker les metas descriptions
        $this->sm->createPostMeta(
            $id,
            'experiences_meta_description',
            $request->get('meta_description'),
            $request->getLocale()
        );
        $this->sm->createPostMeta(
            $id,
            'experiences_meta_titre',
            $request->get('meta_title'),
            $request->getLocale()
        );
        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/{_locale}/admin/parcours_utilisateur/univers_trust/details/{id}", name="parcours_utilisateur_univers_details", requirements={"_locale": "fr"})
     */
    public function parcours_utilisateur_univers_details(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $experience = $this->sm->getOneUserExperiencesProcess($request->get('id'));
        return new Response(json_encode($experience));
    }

    /**
     * @Route("/{_locale}/admin/parcours_utilisateur/univers_trust/update", name="parcours_utilisateur_univers_update", methods="POST")
     * @param Request $request
     * @param RequestStack $requestStack
     * @return Response
     */
    public function parcours_utilisateur_univers_update(Request $request, RequestStack $requestStack)
    {
        $date = new DateTime();
        $id = $request->get('id');
        $slugify = new Slugify();
        //Mettre a jour le post
        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if ($post) {
            $post->setPostTitle($request->get('titre'));
            $post->setPostName($slugify->slugify($request->get('titre')));
            $post->setPostContent($request->get('contenu'));
            $post->setPostExcerpt($request->get('liens'));
            $post->setPostModified($date);
            if (!empty($requestStack->getSession()->get('file'))) {
                $post->setGuid($requestStack->getSession()->get('file'));
            }
            //$this->entityManager->persist($post);
            $this->entityManager->flush();
        }

        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'experiences_youtube',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'experiences_youtube',
                $request->get('youtube'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'experiences_youtube',
                $request->get('youtube'),
                $request->getLocale()
            );
        }
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'experiences_meta_description',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'experiences_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'experiences_meta_description',
                $request->get('meta_description'),
                $request->getLocale()
            );
        }
        $meta = $this->entityManager->getRepository(WpPostmeta::class)->findOneBy([
            'postId' => $id,
            'metaKey' => 'experiences_meta_titre',
        ]);
        if ($meta) {
            $this->sm->updatePostMeta(
                $meta->getMetaId(),
                $id,
                'experiences_meta_titre',
                $request->get('meta_title'),
                $request->getLocale()
            );
        } else {
            $this->sm->createPostMeta(
                $id,
                'experiences_meta_titre',
                $request->get('meta_title'),
                $request->getLocale()
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $id,
        ]);
    }

    /**
     * @Route("/parcours_utilisateur/experience/update", name="parcours_utilisateur_experiences_update", methods="POST")
     */
    public function experience_update_actualite(Request $request)
    {
        $date = new DateTime();
        $exp = $this->entityManager->getRepository(WpPosts::class)->find($request->get('id'));
        $exp->setPostStatus($request->get('statut'));
        $this->entityManager->persist($exp);
        $this->entityManager->flush();
        //Generate list url
        $url = $this->adminUrlGenerator
        ->setController(ExperiencesCrudController::class)
        ->setAction('index')
        ->generateUrl();

        return $this->redirect($url);
    }

    /**
     * @Route("/{_locale}/admin/parcours_utilisateur/experiences/details/{id}", name="parcours_utilisateur_experience_details", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function parcours_utilisateur_experience_details(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $experience = $this->sm->getOneUserExperiencesProcess($request->get('id'));
        return new Response(json_encode($experience));
    }
}
