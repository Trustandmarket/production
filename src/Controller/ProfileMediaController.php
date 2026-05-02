<?php

namespace App\Controller;

use App\Entity\WpPosts;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfileMediaController extends AbstractController
{
    private $service_manager;
    private $em;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $em
    ) {
        $this->service_manager = $service_manager;
        $this->em = $em;
    }

    /**
     * Update portfolio datas
     * @Route("/{_locale}/profil-utilisateur/portfolio", name="portfolio")
     * @param Request $request
     * @return Response
     */
    public function portfolio(Request $request)
    {
        $file = $request->files->get('file');
        $u = $this->getUser();
        $id = $this->service_manager->portfolio($file, $this->getParameter('portfolio_directory'), $u->getId());
        $port = $this->service_manager->readUserMeta($u->getId(), 'portfolio');

        if ($port && $port->getMetaValue() != '') {
            $id = $id . ',' . $port->getMetaValue();
        }
        if ($id) {
            $this->service_manager->updateUserMeta($u->getId(), 'portfolio', $id);
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/delete_portfolio", name="delete_portfolio")
     * @param Request $request
     * @return Response
     */
    public function deletePortfolio(Request $request)
    {
        $u = $this->getUser();
        $port = $this->service_manager->readUserMeta($u->getId(), 'portfolio');
        $id = '';
        if ($port && $port->getMetaValue() != '') {
            $ids = explode(',', $port->getMetaValue());
            for ($i = 0; $i < sizeof($ids); $i++) {
                if ($ids[$i] != $request->get('id')) {
                    if ($id) {
                        $id = $id . ',' . $ids[$i];
                    } else {
                        $id = $ids[$i];
                    }
                }
            }
        }

        if ($id) {
            $this->service_manager->updateUserMeta(
                $u->getId(),
                'portfolio',
                $id
            );
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/deleteImagesSecondaires/{id}/{postId}", name="delete_images_secondaires")
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function deleteImagesSecondaires(Request $request, $id)
    {
        $port = $this->service_manager->readPostMeta($request->get('postId'), 'images_annonces');
        $idnew = [];
        if ($port) {
            $ids = explode(',', $port->getMetaValue());
            for ($i = 0; $i < sizeof($ids); $i++) {
                if ($ids[$i] == $request->get('id')) {
                    $img = $this->em->getRepository(WpPosts::class)->findOneById($request->get('id'));
                    $this->em->remove($img);
                    $this->em->flush();
                } else {
                    $idnew[] = $ids[$i];
                }
            }
            $this->service_manager->deletePostMeta($port->getMetaId());
        }
        $this->service_manager->createPostMeta(
            $request->get('postId'),
            'images_annonces',
            implode(',', array_unique($idnew)),
            $request->getLocale()
        );

        return $this->render('admin/resultat.html.twig', ['result' => 1]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/delete_video", name="delete_video")
     * @param Request $request
     * @return Response
     */
    public function deleteVideo(Request $request)
    {
        $u = $this->getUser();
        $port = $this->service_manager->readUserMeta($u->getId(), 'video');
        $vid = [];
        $requestVideo = trim((string) $request->get('id', ''));
        $requestVideoId = $this->service_manager->getYouTubeId($requestVideo);
        if (!is_string($requestVideoId) || trim($requestVideoId) === '') {
            $requestVideoId = $requestVideo;
        }
        $requestVideoId = trim((string) $requestVideoId);

        if ($port && $port->getMetaValue() != '') {
            $ids = $this->normalizeStoredVideos($port->getMetaValue());

            foreach ($ids as $item) {
                $videoRaw = trim((string) $item);
                if ($videoRaw === '') {
                    continue;
                }

                $videoId = $this->service_manager->getYouTubeId($videoRaw);
                if (!is_string($videoId) || trim($videoId) === '') {
                    $videoId = $videoRaw;
                }
                $videoId = trim((string) $videoId);

                if ($videoId !== $requestVideoId) {
                    $vid[] = $videoRaw;
                }
            }
        }

        $this->service_manager->updateUserMeta($u->getId(), 'video', @serialize(array_filter($vid)));
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    private function normalizeStoredVideos($value)
    {
        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = @unserialize($trimmed);
        if (is_array($decoded)) {
            $result = [];
            foreach ($decoded as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $result[] = $item;
                }
            }
            return array_values($result);
        }

        $json = json_decode($trimmed, true);
        if (is_array($json)) {
            $result = [];
            foreach ($json as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $result[] = $item;
                }
            }
            return array_values($result);
        }

        if (preg_match('/[\r\n,]/', $trimmed) === 1) {
            $parts = preg_split('/[\r\n,]+/', $trimmed) ?: [];
            $result = [];
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $result[] = $part;
                }
            }
            return array_values($result);
        }

        return [$trimmed];
    }
}
