<?php

namespace App\Controller\Admin;

use App\Service\Admin\UserMainActivityResolver;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class UserAdminFieldController extends AbstractController
{
    public function __construct(
        private readonly UserMainActivityResolver $userMainActivityResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function mainActivityLabel(int $id): Response
    {
        try {
            return $this->render('admin/user/Fields/main_activity.html.twig', [
                'label' => $this->userMainActivityResolver->resolveLabel($id),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('UserAdminFieldController.mainActivityLabel failed', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->render('admin/user/Fields/main_activity.html.twig', [
                'label' => '[ERREUR] ' . $e->getMessage(),
            ]);
        }
    }
}
