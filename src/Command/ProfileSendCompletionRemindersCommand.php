<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BrevoMailer;
use App\Service\ServiceManager;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:profile:send-completion-reminders',
    description: 'Relance les pros avec profile_completion_rate < seuil, envoi Brevo (mode AbonnementController) + tracabilite anti-spam.'
)]
class ProfileSendCompletionRemindersCommand extends Command
{
    private const META_RATE_KEY = 'profile_completion_rate';
    private const META_LAST_SENT_AT_KEY = 'profile_completion_reminder_last_sent_at';
    private const META_SENT_COUNT_KEY = 'profile_completion_reminder_sent_count';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ServiceManager $serviceManager,
        private readonly BrevoMailer $brevoMailer,
        private readonly ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Calcule sans envoyer ni ecrire en base.')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Seuil minimum requis', '80')
            ->addOption('cooldown-days', null, InputOption::VALUE_REQUIRED, 'Cooldown entre 2 relances (jours)', '7')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d envois (0 = illimite)', '0')
            ->addOption('template-id', null, InputOption::VALUE_REQUIRED, 'Template Brevo ID (obligatoire pour envoi reel)', '0')
            ->addOption('admin-bcc', null, InputOption::VALUE_REQUIRED, 'Email BCC admin/commercial', 'commerce@trustandmarket.com')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'URL de base pour le lien profil', 'https://rec.trustandmarket.com')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale du lien profil', 'fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');
        $threshold = max(0, min(100, (int) $input->getOption('threshold')));
        $cooldownDays = max(0, (int) $input->getOption('cooldown-days'));
        $limit = max(0, (int) $input->getOption('limit'));
        $templateId = max(0, (int) $input->getOption('template-id'));
        $adminBcc = trim((string) $input->getOption('admin-bcc'));
        $baseUrl = rtrim((string) $input->getOption('base-url'), '/');
        $locale = (string) $input->getOption('locale');

        $environnement = (string) $this->parameterBag->get('environnement');
        $isProd = $environnement === 'prod';

        if (!$dryRun && $templateId <= 0) {
            $io->error('template-id invalide. Fournis un template Brevo ID > 0 pour un envoi reel.');
            return Command::INVALID;
        }

        if (!$isProd && !$dryRun) {
            $io->warning(sprintf('environnement=%s: envoi Brevo desactive (mode AbonnementController). Lance en --dry-run ou en prod.', $environnement));
            return Command::SUCCESS;
        }

        $users = $this->userRepository->findUsersForProfileCompletionRecompute();
        $now = new DateTimeImmutable('now');

        $processed = 0;
        $eligible = 0;
        $sent = 0;
        $skippedRate = 0;
        $skippedCooldown = 0;
        $skippedNoEmail = 0;
        $errors = 0;

        foreach ($users as $user) {
            $processed++;

            $email = (string) $user->getEmailCanonical();
            if ($email === '') {
                $skippedNoEmail++;
                continue;
            }

            $rate = (int) $this->serviceManager->getUserStringDataValue((int) $user->getId(), self::META_RATE_KEY);
            $rate = max(0, min(100, $rate));

            if ($rate >= $threshold) {
                $skippedRate++;
                continue;
            }

            $lastSentRaw = (string) $this->serviceManager->getUserStringDataValue((int) $user->getId(), self::META_LAST_SENT_AT_KEY);
            $lastSentAt = $this->parseDate($lastSentRaw);

            if ($lastSentAt !== null && $cooldownDays > 0) {
                $nextAllowed = $lastSentAt->modify(sprintf('+%d days', $cooldownDays));
                if ($nextAllowed > $now) {
                    $skippedCooldown++;
                    continue;
                }
            }

            $eligible++;

            if ($limit > 0 && $sent >= $limit) {
                break;
            }

            $profileUrl = sprintf('%s/%s/profil-utilisateur/profil', $baseUrl, $locale);
            $displayName = (string) $user->getDisplayName();

            if ($dryRun) {
                $sent++;
                continue;
            }

            $payload = [
                'to' => [[
                    'email' => $email,
                    'name' => $displayName !== '' ? $displayName : $email,
                ]],
                'templateId' => $templateId,
                'params' => [
                    'display_name' => $displayName,
                    'completion_rate' => $rate,
                    'required_rate' => $threshold,
                    'profile_url' => $profileUrl,
                ],
            ];

            if ($adminBcc !== '') {
                $payload['bcc'] = [[
                    'email' => $adminBcc,
                    'name' => 'Trust & Market',
                ]];
            }

            $result = $this->brevoMailer->sendTemplate($payload);
            if (!$result['ok']) {
                $errors++;
                $io->warning(sprintf('Echec envoi user_id=%d email=%s error=%s', (int) $user->getId(), $email, $result['error']));
                continue;
            }

            $this->serviceManager->updateUserMeta((int) $user->getId(), self::META_LAST_SENT_AT_KEY, $now->format(DateTimeInterface::ATOM));
            $currentCount = (int) $this->serviceManager->getUserStringDataValue((int) $user->getId(), self::META_SENT_COUNT_KEY);
            $this->serviceManager->updateUserMeta((int) $user->getId(), self::META_SENT_COUNT_KEY, (string) ($currentCount + 1));
            $sent++;
        }

        $io->success(sprintf(
            'Batch termine. processed=%d eligible=%d sent=%d skipped_rate=%d skipped_cooldown=%d skipped_no_email=%d errors=%d dryRun=%s threshold=%d cooldownDays=%d limit=%d',
            $processed,
            $eligible,
            $sent,
            $skippedRate,
            $skippedCooldown,
            $skippedNoEmail,
            $errors,
            $dryRun ? 'yes' : 'no',
            $threshold,
            $cooldownDays,
            $limit
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function parseDate(string $raw): ?DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}