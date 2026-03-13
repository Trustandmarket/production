<?php

namespace App\Controller\Admin;

use App\Entity\ReminderLog;
use App\Entity\User;
use App\Repository\ReminderLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ReminderLogAdminController extends AbstractController
{
    public function __construct(
        private readonly ReminderLogRepository $reminderLogRepository,
        private readonly UserRepository $userRepository
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
}
