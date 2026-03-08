<?php

namespace App\Service\Export;

use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserCsvExporter
{
    public function createResponseFromQueryBuilder(QueryBuilder $queryBuilder, string $filename): Response
    {
        $result = $queryBuilder
            ->select(
                'DISTINCT entity.id AS id',
                'entity.displayName AS display_name',
                'entity.email_canonical AS email_canonical',
                'entity.roles AS roles',
                'entity.enabled AS enabled',
                'entity.isVerified AS is_verified',
                "(CASE WHEN umCompletion.metaValue IS NULL OR umCompletion.metaValue = '' THEN 0 ELSE umCompletion.metaValue END) AS completion_rate",
                'entity.userRegistered AS userRegistered',
                'entity.updatedAt AS updatedAt'
            )
            ->getQuery()
            ->getArrayResult();

        $data = [[
            'id' => 'Id',
            'display_name' => 'Noms',
            'email_canonical' => 'Email',
            'roles' => 'Roles',
            'enabled' => 'Compte Actif?',
            'is_verified' => 'Email verifie?',
            'completion_rate' => 'Completion Rate',
            'userRegistered' => 'Date de creation',
            'updatedAt' => 'Date de MAJ',
        ]];

        foreach ($result as $row) {
            $data[] = [
                'id' => $row['id'] ?? null,
                'display_name' => $row['display_name'] ?? null,
                'email_canonical' => $row['email_canonical'] ?? null,
                'roles' => isset($row['roles']) && is_array($row['roles']) ? implode(';', $row['roles']) : ($row['roles'] ?? null),
                'enabled' => !empty($row['enabled']) ? 'Oui' : 'Non',
                'is_verified' => !empty($row['is_verified']) ? 'Oui' : 'Non',
                'completion_rate' => sprintf('%s%%', (string) ($row['completion_rate'] ?? 0)),
                'userRegistered' => ($row['userRegistered'] ?? null) instanceof DateTimeInterface
                    ? $row['userRegistered']->format('d-m-Y H:i:s')
                    : ($row['userRegistered'] ?? null),
                'updatedAt' => ($row['updatedAt'] ?? null) instanceof DateTimeInterface
                    ? $row['updatedAt']->format('d-m-Y H:i:s')
                    : ($row['updatedAt'] ?? null),
            ];
        }

        $response = new StreamedResponse(function () use ($data) {
            $config = new ExporterConfig();
            $exporter = new Exporter($config);
            $exporter->export('php://output', $data);
        });

        $dispositionHeader = $response->headers->makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');

        return $response;
    }
}
