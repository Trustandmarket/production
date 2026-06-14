<?php

namespace App\Command;

use App\Service\AnnouncementModeration\AnnouncementModerationAiService;
use App\Service\AnnouncementModeration\AnnouncementModerationContextBuilder;
use App\Service\AnnouncementModeration\AnnouncementModerationDecisionService;
use App\Service\AnnouncementModeration\AnnouncementModerationJobManager;
use App\Service\AnnouncementModeration\AnnouncementModerationNotificationService;
use App\Service\AnnouncementModeration\AnnouncementModerationPrecheckService;
use App\Service\AnnouncementModeration\AnnouncementModerationStatusApplier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:announcement-ai:worker',
    description: 'Traite les jobs de moderation IA d annonces.'
)]
class AnnouncementAiModerationWorkerCommand extends Command
{
    public function __construct(
        private readonly AnnouncementModerationJobManager $jobManager,
        private readonly AnnouncementModerationContextBuilder $contextBuilder,
        private readonly AnnouncementModerationPrecheckService $precheckService,
        private readonly AnnouncementModerationAiService $aiService,
        private readonly AnnouncementModerationDecisionService $decisionService,
        private readonly AnnouncementModerationStatusApplier $statusApplier,
        private readonly AnnouncementModerationNotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Mode IA (mock|live)', 'mock')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max de jobs a traiter sur ce run', '10')
            ->addOption('job-id', null, InputOption::VALUE_REQUIRED, 'Traiter un job specifique (ID)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mode = strtolower(trim((string) $input->getOption('mode')));
        $limit = max(1, (int) $input->getOption('limit'));
        $jobIdOption = $input->getOption('job-id');
        $targetJobId = $jobIdOption !== null ? max(0, (int) $jobIdOption) : null;

        if (!in_array($mode, ['mock', 'live'], true)) {
            $io->error('Mode invalide. Valeurs supportees: mock, live.');
            return Command::INVALID;
        }

        if ($mode === 'live') {
            try {
                $this->aiService->validateLiveConfiguration();
            } catch (\Throwable $exception) {
                $io->error($exception->getMessage());
                return Command::INVALID;
            }
        }

        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $notificationErrors = 0;
        $manualReviewDigestItems = [];

        while ($processed < $limit) {
            $candidate = $this->jobManager->findNextPendingJob($targetJobId);
            if ($candidate === null) {
                break;
            }

            $jobId = (int) $candidate['id'];
            $announcementId = (int) $candidate['announcement_id'];

            if (!$this->jobManager->claimJob($jobId)) {
                $skipped++;

                if ($targetJobId !== null) {
                    break;
                }

                continue;
            }

            $processed++;
            $io->text(sprintf('Traitement job #%d (announcement_id=%d)...', $jobId, $announcementId));

            try {
                $context = $this->contextBuilder->buildForAnnouncement($announcementId);
                $prechecks = $this->precheckService->evaluate($context);
                $checks = $prechecks['checks'];
                $aiEvaluation = null;

                if ($prechecks['passed']) {
                    $aiEvaluation = $this->aiService->evaluate($context, $mode);
                    $checks = array_merge($checks, $aiEvaluation['checks']);
                }

                $decision = $this->decisionService->decide($prechecks['passed'], $aiEvaluation);
                $this->jobManager->completeJob($jobId, $decision, $checks, $context->toArray());
                $statusResult = $this->statusApplier->apply($context, $decision);

                if ($decision->getOutcome() === 'publish') {
                    $notificationResult = $this->notificationService->sendAutoPublishNotification($context);
                    if (!$notificationResult['ok']) {
                        $notificationErrors++;
                        $io->warning(sprintf(
                            'Notification auto publish non envoyee pour job #%d: %s',
                            $jobId,
                            $notificationResult['error']
                        ));
                    }
                }

                if ($decision->getOutcome() === 'manual_review') {
                    $manualReviewDigestItems[] = $this->notificationService->buildManualReviewDigestItem(
                        $jobId,
                        $context,
                        $decision
                    );
                }

                $succeeded++;
                $io->text(sprintf(
                    'Job #%d -> %s (%s) annonce=%s changed=%s',
                    $jobId,
                    $decision->getJobStatus(),
                    $decision->getDecisionCode(),
                    $statusResult['target_status'],
                    $statusResult['changed'] ? 'yes' : 'no'
                ));
            } catch (\Throwable $exception) {
                $failed++;
                $errorMessage = sprintf(
                    '%s in %s:%d',
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                );
                $this->jobManager->markJobAsFailed($jobId, $errorMessage);
                $io->warning(sprintf('Job #%d en echec: %s', $jobId, $errorMessage));
            }

            if ($targetJobId !== null) {
                break;
            }
        }

        if ($manualReviewDigestItems !== []) {
            $digestResult = $this->notificationService->sendManualReviewDigest($manualReviewDigestItems);
            if (!$digestResult['ok']) {
                $notificationErrors++;
                $io->warning('Echec envoi digest admin Trust: ' . $digestResult['error']);
            } else {
                $io->text(sprintf(
                    'Digest admin Trust envoye pour %d annonce(s) en revue manuelle.',
                    count($manualReviewDigestItems)
                ));
            }
        }

        $io->success(sprintf(
            'Worker termine. mode=%s processed=%d success=%d failed=%d skipped=%d notification_errors=%d limit=%d%s',
            $mode,
            $processed,
            $succeeded,
            $failed,
            $skipped,
            $notificationErrors,
            $limit,
            $targetJobId !== null ? sprintf(' target_job_id=%d', $targetJobId) : ''
        ));

        return ($failed > 0 || $notificationErrors > 0) ? Command::FAILURE : Command::SUCCESS;
    }
}
