<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WpPosts;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsDailyKpiService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getDashboardMetrics(): array
    {
        return [
            'users' => $this->countUsersByRoles([
                'ROLE_ABONNE',
                'ROLE_SOCIETE',
                'ROLE_AUTO_ENTREPRENEUR',
            ]),
            'subscribers' => $this->countUsersByRole('ROLE_ABONNE'),
            'verified_subscribers' => $this->countUsersByRole('ROLE_ABONNE', true),
            'professionals' => $this->countUsersByRoles([
                'ROLE_SOCIETE',
                'ROLE_AUTO_ENTREPRENEUR',
            ]),
            'verified_professionals' => $this->countUsersByRoles([
                'ROLE_SOCIETE',
                'ROLE_AUTO_ENTREPRENEUR',
            ], true),
            'announcement_total' => (int) $this->entityManager->getRepository(WpPosts::class)->count([
                'postType' => 'product',
            ]),
            'announcement_rejected' => $this->countPostsByStatus('product', 'trash'),
            'announcement_draft' => $this->countPostsByStatus('product', 'draft'),
            'announcement_moderation' => $this->countPostsByStatus('product', 'moderation'),
            'announcement_published' => $this->countPostsByStatus('product', 'publish'),
        ];
    }

    public function getDailyBusinessKpis(): array
    {
        $dashboardMetrics = $this->getDashboardMetrics();

        return [
            'utilisateurs_total' => $dashboardMetrics['users'],
            'professionnels_total' => $dashboardMetrics['professionals'],
            'professionnels_verifies_total' => $dashboardMetrics['verified_professionals'],
            'annonces_total' => $dashboardMetrics['announcement_total'],
            'annonces_rejetees_total' => $dashboardMetrics['announcement_rejected'],
            'annonces_brouillon_total' => $dashboardMetrics['announcement_draft'],
            'annonces_moderation_total' => $dashboardMetrics['announcement_moderation'],
            'annonces_publiees_total' => $dashboardMetrics['announcement_published'],
        ];
    }

    public function countUsersByRole(string $role, ?bool $isVerified = null): int
    {
        return $this->countUsersByRoles([$role], $isVerified);
    }

    public function countUsersByRoles(array $roles, ?bool $isVerified = null): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u');

        $roleConditions = $qb->expr()->orX();
        foreach ($roles as $index => $role) {
            $param = 'role_' . $index;
            $roleConditions->add($qb->expr()->like('u.roles', ':' . $param));
            $qb->setParameter($param, '%"' . $role . '"%');
        }

        $qb->andWhere($roleConditions);

        if ($isVerified !== null) {
            $qb->andWhere('u.isVerified = :isVerified')
                ->setParameter('isVerified', $isVerified);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countPostsByStatus(string $postType, string $postStatus): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(WpPosts::class, 'p')
            ->andWhere('p.postType = :postType')
            ->andWhere('p.postStatus = :postStatus')
            ->setParameter('postType', $postType)
            ->setParameter('postStatus', $postStatus)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
