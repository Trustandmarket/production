<?php

namespace App\Controller\Admin;

use App\Service\Admin\UserMainActivityResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class UserAdminFieldController extends AbstractController
{
    public function __construct(
        private readonly UserMainActivityResolver $userMainActivityResolver
    ) {
    }

    public function mainActivityLabel(int $id): Response
    {
        return $this->render('admin/user/Fields/main_activity.html.twig', [
            'label' => $this->userMainActivityResolver->resolveLabel($id),
        ]);
    }
}
