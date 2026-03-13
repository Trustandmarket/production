<?php

namespace App\Controller\Admin;

use App\Entity\ReminderLog;
use App\Entity\User;
use App\Repository\ReminderLogRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReminderLogAdminController extends AbstractController
{
    public function __construct(
        private readonly ReminderLogRepository $reminderLogRepository,
        private readonly UserRepository $userRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public function latestReminderLabel(int $id): Response
    {
        $user = $this->userRepository->find($id);
        $label = 'Jamais';

        if ($user !== null) {
            $latestReminder = $this->reminderLogRepository->findLatestSentForUser($user);
            if ($latestReminder instanceof ReminderLog && $latestReminder->getSentAt() !== null) {
                $label = $latestReminder->getSentAt()->format('d/m/Y H:i');
            }
        }

        return $this->render('admin/user/Fields/latest_reminder.html.twig', [
            'label' => $label,
        ]);
    }

    #[Route('/{_locale}/admin/reminder-log/user/{id}', name: 'admin_reminder_log_user_history', requirements: ['_locale' => 'fr'])]
    public function userHistory(string $_locale, int $id): RedirectResponse
    {
        $url = $this->adminUrlGenerator
            ->setRoute('admin', ['_locale' => $_locale])
            ->setController(ReminderLogCrudController::class)
            ->setAction(Action::INDEX)
            ->set('userId', $id)
            ->generateUrl();

        return $this->redirect($url);
    }
}
