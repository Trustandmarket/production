<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\WpPosts;
use App\Entity\User;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class LikesController extends AbstractController
{
    private $service_manager;
    private $entityManager;

    public function __construct(ServiceManager $service_manager, EntityManagerInterface $entityManager)
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/likes/{id}", name="likes_like")
     */
    public function like($id)
    {
        $post = $this->entityManager->getRepository(User::class)->find($id);
        /**@var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse([], Response::HTTP_UNAUTHORIZED);
        }
        $post->like($currentUser);
        $this->entityManager->flush();

        return new JsonResponse(['count'=> $post->getLikedBy()->count() ]);
    }

    /**
     * @Route("/unlike/{id}", name="likes_unlike")
     */
    public function unlike($id)
    {
        $post = $this->entityManager->getRepository(User::class)->find($id);
        /**@var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse([], Response::HTTP_UNAUTHORIZED);
        }
        $post->getlikedBy()->removeElement($currentUser);
        $this->entityManager->flush();

        return new JsonResponse(['count'=> $post->getLikedBy()->count() ]);
    }
}
