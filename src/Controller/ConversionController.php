<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use App\Repository\UserRepository;

class ConversionController extends AbstractController
{
    /**
     * Route("/{_locale}/operations", name="tools_conversion", requirements={"_locale": "fr"})
     */
    public function index(ManagerRegistry $doctrine, UserRepository $userRepository): Response
    {
        /*        $entityManager = $doctrine->getManager();
                $users = $entityManager->getRepository(User::class)->findAll();

                foreach ($users as $key => $user) {
                    if ($user->getRoles() != '' && $user->getRoles() != '[]' && $user->getRoles() != null) {
                        $roles = unserialize($user->getRoles());
                        $roles = json_encode($roles);
                        $user->setRoles($roles);
                        $entityManager->flush();
                    }
                }*/
        return $this->render('conversion/index.html.twig', [
            'controller_name' => 'ConversionController',
        ]);
    }
}
