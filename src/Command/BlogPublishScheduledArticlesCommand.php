<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:blog:publish-scheduled',
    description: 'Publie les articles de blog planifiés arrivés à échéance.'
)]
class BlogPublishScheduledArticlesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $publishedCount = $this->entityManager->getConnection()->executeStatement(
            <<<'SQL'
UPDATE wp_posts
SET post_status = :publishedStatus
WHERE post_type = :postType
  AND post_status = :scheduledStatus
  AND post_date_gmt <= UTC_TIMESTAMP()
SQL,
            [
                'publishedStatus' => 'publish',
                'scheduledStatus' => 'future',
                'postType' => 'post',
            ]
        );

        $io->success(sprintf('%d article(s) planifié(s) publié(s).', $publishedCount));

        return Command::SUCCESS;
    }
}
