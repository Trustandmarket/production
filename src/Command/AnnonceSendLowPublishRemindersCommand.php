<?php

namespace App\Command;

use App\Entity\ReminderLog;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WpPostsRepository;
use App\Service\BrevoMailer;
use App\Service\ServiceManager;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:annonce:send-low-publish-reminders',
    description: 'Relance les utilisateurs ayant publie moins de X annonces pour les inviter a en creer de nouvelles.'
)]
class AnnonceSendLowPublishRemindersCommand extends Command
{
    private const META_LAST_SENT_AT_KEY = 'annonce_low_publish_reminder_last_sent_at';
    private const META_SENT_COUNT_KEY = 'annonce_low_publish_reminder_sent_count';
    private const REMINDER_TYPE = 'low_publish_annonce_reminder';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly WpPostsRepository $wpPostsRepository,
        private readonly ServiceManager $serviceManager,
        private readonly BrevoMailer $brevoMailer,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Calcule sans envoyer ni ecrire en base.')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Nombre maximum d annonces publiees pour etre eligible', '10')
            ->addOption('cooldown-days', null, InputOption::VALUE_REQUIRED, 'Cooldown entre 2 relances (jours)', '14')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d envois (0 = illimite)', '0')
            ->addOption('template-id', null, InputOption::VALUE_REQUIRED, 'Template Brevo ID utilisateur', '60')
            ->addOption('admin-summary-to', null, InputOption::VALUE_REQUIRED, 'Email admin pour recap final', 'commerce@trustandmarket.com')
            ->addOption('admin-summary-template-id', null, InputOption::VALUE_REQUIRED, 'Template Brevo ID recap admin final (0 = desactive)', '59')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'URL de base pour le lien annonce', 'https://trustandmarket.com')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale du lien annonce', 'fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');
        $threshold = max(0, (int) $input->getOption('threshold'));
        $cooldownDays = max(0, (int) $input->getOption('cooldown-days'));
        $limit = max(0, (int) $input->getOption('limit'));
        $templateId = max(0, (int) $input->getOption('template-id'));
        $adminSummaryTo = trim((string) $input->getOption('admin-summary-to'));
        $adminSummaryTemplateId = max(0, (int) $input->getOption('admin-summary-template-id'));
        $baseUrl = rtrim((string) $input->getOption('base-url'), '/');
        $locale = (string) $input->getOption('locale');

        $environnement = (string) $this->parameterBag->get('environnement');
        $isProd = $environnement === 'prod';

        if (!$dryRun && $templateId <= 0) {
            $io->error('template-id invalide. Fournis un template Brevo ID > 0 pour un envoi reel.');
            return Command::INVALID;
        }

        if (!$isProd && !$dryRun) {
            $io->warning(sprintf('environnement=%s: envoi Brevo desactive. Lance en --dry-run ou en prod.', $environnement));
            return Command::SUCCESS;
        }

        $users = $this->userRepository->findUsersForProfileCompletionRecompute();
        $now = new DateTimeImmutable('now');

        $processed = 0;
        $eligible = 0;
        $sent = 0;
        $skippedThreshold = 0;
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

            $publishedCount = $this->wpPostsRepository->countPublishedProductsByUser((int) $user->getId());
            if ($publishedCount >= $threshold) {
                $skippedThreshold++;
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

            $annonceCreateUrl = sprintf('%s/%s/profil-utilisateur/annonces/publier-annonce', $baseUrl, $locale);
            $displayName = (string) $user->getDisplayName();
            $summary = sprintf('Relance annonces publiees %d/%d.', $publishedCount, $threshold);
            $context = [
                'published_count' => $publishedCount,
                'threshold' => $threshold,
                'cooldown_days' => $cooldownDays,
                'annonce_create_url' => $annonceCreateUrl,
                'dry_run' => $dryRun,
            ];

            if ($dryRun) {
                $this->entityManager->persist($this->buildReminderLog($user, $email, $displayName, 'dry_run', $templateId > 0 ? $templateId : null, $summary, $context, $now));
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
                    'published_count' => $publishedCount,
                    'target_count' => $threshold,
                    'annonce_create_url' => $annonceCreateUrl,
                ],
            ];

            $result = $this->brevoMailer->sendTemplate($payload);
            if (!$result['ok']) {
                $errors++;
                $this->entityManager->persist($this->buildReminderLog($user, $email, $displayName, 'failed', $templateId, $summary, $context + ['error' => $result['error']], $now));
                $io->warning(sprintf('Echec envoi user_id=%d email=%s error=%s', (int) $user->getId(), $email, $result['error']));
                continue;
            }

            $this->serviceManager->updateUserMeta((int) $user->getId(), self::META_LAST_SENT_AT_KEY, $now->format(DateTimeInterface::ATOM));
            $currentCount = (int) $this->serviceManager->getUserStringDataValue((int) $user->getId(), self::META_SENT_COUNT_KEY);
            $this->serviceManager->updateUserMeta((int) $user->getId(), self::META_SENT_COUNT_KEY, (string) ($currentCount + 1));

            $this->entityManager->persist($this->buildReminderLog($user, $email, $displayName, 'sent', $templateId, $summary, $context, $now));
            $sent++;
        }

        $this->entityManager->flush();

        if (!$dryRun && $isProd && $adminSummaryTo !== '' && $adminSummaryTemplateId > 0) {
            $adminPayload = [
                'to' => [[
                    'email' => $adminSummaryTo,
                    'name' => 'Trust & Market',
                ]],
                'templateId' => $adminSummaryTemplateId,
                'params' => [
                    'date_jour' => $now->format('d-m-Y H:i:s'),
                    'processed' => $processed,
                    'eligible' => $eligible,
                    'sent' => $sent,
                    'skipped_threshold' => $skippedThreshold,
                    'skipped_cooldown' => $skippedCooldown,
                    'skipped_no_email' => $skippedNoEmail,
                    'errors' => $errors,
                    'threshold' => $threshold,
                    'cooldown_days' => $cooldownDays,
                ],
            ];

            $adminResult = $this->brevoMailer->sendTemplate($adminPayload);
            if (!$adminResult['ok']) {
                $errors++;
                $io->warning('Echec envoi recap admin final: ' . $adminResult['error']);
            }
        }

        $io->success(sprintf(
            'Batch termine. processed=%d eligible=%d sent=%d skipped_threshold=%d skipped_cooldown=%d skipped_no_email=%d errors=%d dryRun=%s threshold=%d cooldownDays=%d limit=%d',
            $processed,
            $eligible,
            $sent,
            $skippedThreshold,
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

    private function buildReminderLog(User $user, string $email, string $displayName, string $status, ?int $templateId, string $payloadSummary, array $context, DateTimeImmutable $sentAt): ReminderLog
    {
        $log = new ReminderLog();
        $log->setUser($user);
        $log->setUserEmail($email);
        $log->setUserDisplayName($displayName !== '' ? $displayName : $email);
        $log->setType(self::REMINDER_TYPE);
        $log->setChannel('email');
        $log->setStatus($status);
        $log->setTemplateId($templateId);
        $log->setPayloadSummary($payloadSummary);
        $log->setContextJson(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $log->setSentAt($sentAt);

        return $log;
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
