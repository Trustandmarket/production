<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\ProfileCompletionCalculator;
use App\Service\ServiceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:profile:recompute-completion', description: 'Recalcule le taux de remplissage des profils pro (ROLE_AUTO_ENTREPRENEUR, ROLE_SOCIETE).')]
class ProfileRecomputeCompletionCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileCompletionCalculator $profileCompletionCalculator,
        private readonly ServiceManager $serviceManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Calcule sans ecrire en base.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $users = $this->userRepository->findUsersForProfileCompletionRecompute();

        $processed = 0;
        $updated = 0;

        foreach ($users as $user) {
            $processed++;
            $rate = $this->profileCompletionCalculator->calculateForUser($user);

            if (!$dryRun) {
                $this->serviceManager->updateUserMeta((int) $user->getId(), 'profile_completion_rate', (string) $rate);
                $updated++;
            }
        }

        $io->success(sprintf(
            'Recompute termine. processed=%d, updated=%d, dryRun=%s',
            $processed,
            $updated,
            $dryRun ? 'yes' : 'no'
        ));

        return Command::SUCCESS;
    }
}
