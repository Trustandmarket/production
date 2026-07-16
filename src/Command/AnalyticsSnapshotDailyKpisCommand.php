<?php

namespace App\Command;

use App\Service\AnalyticsDailyKpiService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analytics:snapshot-daily-kpis',
    description: 'Calcule les KPI metier et alimente la base analytique quotidienne.'
)]
class AnalyticsSnapshotDailyKpisCommand extends Command
{
    private const JOB_NAME = 'app:analytics:snapshot-daily-kpis';

    public function __construct(
        private readonly AnalyticsDailyKpiService $analyticsDailyKpiService,
        private readonly ManagerRegistry $doctrine
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Calcule les KPI sans ecrire dans la base analytics.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
        $snapshotDate = $now->format('Y-m-d');
        $snapshotAt = $now->format('Y-m-d H:i:s');

        $kpis = $this->analyticsDailyKpiService->getDailyBusinessKpis();

        if ($dryRun) {
            $io->success(sprintf(
                'Dry-run termine pour %s. KPI=%s',
                $snapshotDate,
                json_encode($kpis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ));

            return Command::SUCCESS;
        }

        /** @var Connection $analyticsConnection */
        $analyticsConnection = $this->doctrine->getConnection('analytics');
        $jobRunId = null;

        try {
            $jobRunId = $this->insertJobRun($analyticsConnection, $snapshotDate, $snapshotAt);
            $this->upsertDailyBusinessKpis($analyticsConnection, $snapshotDate, $snapshotAt, $kpis);
            $this->markJobRun($analyticsConnection, (int) $jobRunId, 'success', $snapshotAt, null);

            $io->success(sprintf(
                'Snapshot analytics enregistre pour %s. job_run_id=%d',
                $snapshotDate,
                $jobRunId
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($jobRunId !== null) {
                try {
                    $this->markJobRun(
                        $analyticsConnection,
                        (int) $jobRunId,
                        'failed',
                        (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s'),
                        $e->getMessage()
                    );
                } catch (\Throwable) {
                }
            }

            $io->error('Echec snapshot analytics: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function insertJobRun(Connection $analyticsConnection, string $snapshotDate, string $startedAt): int
    {
        $analyticsConnection->insert('analytics_job_runs', [
            'job_name' => self::JOB_NAME,
            'run_date' => $snapshotDate,
            'started_at' => $startedAt,
            'finished_at' => null,
            'status' => 'started',
            'message' => null,
            'created_at' => $startedAt,
        ]);

        return (int) $analyticsConnection->lastInsertId();
    }

    private function upsertDailyBusinessKpis(
        Connection $analyticsConnection,
        string $snapshotDate,
        string $snapshotAt,
        array $kpis
    ): void {
        $sql = <<<'SQL'
INSERT INTO daily_business_kpis (
    snapshot_date,
    snapshot_at,
    utilisateurs_total,
    professionnels_total,
    professionnels_verifies_total,
    annonces_total,
    annonces_rejetees_total,
    annonces_brouillon_total,
    annonces_moderation_total,
    annonces_publiees_total,
    created_at
) VALUES (
    :snapshot_date,
    :snapshot_at,
    :utilisateurs_total,
    :professionnels_total,
    :professionnels_verifies_total,
    :annonces_total,
    :annonces_rejetees_total,
    :annonces_brouillon_total,
    :annonces_moderation_total,
    :annonces_publiees_total,
    :created_at
)
ON DUPLICATE KEY UPDATE
    snapshot_at = VALUES(snapshot_at),
    utilisateurs_total = VALUES(utilisateurs_total),
    professionnels_total = VALUES(professionnels_total),
    professionnels_verifies_total = VALUES(professionnels_verifies_total),
    annonces_total = VALUES(annonces_total),
    annonces_rejetees_total = VALUES(annonces_rejetees_total),
    annonces_brouillon_total = VALUES(annonces_brouillon_total),
    annonces_moderation_total = VALUES(annonces_moderation_total),
    annonces_publiees_total = VALUES(annonces_publiees_total)
SQL;

        $analyticsConnection->executeStatement($sql, [
            'snapshot_date' => $snapshotDate,
            'snapshot_at' => $snapshotAt,
            'utilisateurs_total' => $kpis['utilisateurs_total'],
            'professionnels_total' => $kpis['professionnels_total'],
            'professionnels_verifies_total' => $kpis['professionnels_verifies_total'],
            'annonces_total' => $kpis['annonces_total'],
            'annonces_rejetees_total' => $kpis['annonces_rejetees_total'],
            'annonces_brouillon_total' => $kpis['annonces_brouillon_total'],
            'annonces_moderation_total' => $kpis['annonces_moderation_total'],
            'annonces_publiees_total' => $kpis['annonces_publiees_total'],
            'created_at' => $snapshotAt,
        ]);
    }

    private function markJobRun(
        Connection $analyticsConnection,
        int $jobRunId,
        string $status,
        string $finishedAt,
        ?string $message
    ): void {
        $analyticsConnection->update('analytics_job_runs', [
            'finished_at' => $finishedAt,
            'status' => $status,
            'message' => $message,
        ], [
            'id' => $jobRunId,
        ]);
    }
}
