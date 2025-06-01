<?php

namespace App\Service\Export;

use App\Entity\Abonnement;
use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class AbonnementsCsvExporter
{
    private $entityManager;
    /**
     * ServiceManager constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }
    public function createResponseFromQueryBuilder(QueryBuilder $queryBuilder, FieldCollection $fields, string $filename): Response
    {
        $result = $queryBuilder->select(
            'DISTINCT entity.id as Identifiant',
            'CONCAT(u.displayName, \' - \', u.email_canonical) as Utilisateur',
            'o.titre as Offre',
            'entity.tarif as Tarif',
            'entity.abonnementActif as Abonnement_actif',
            'entity.createdAt as Date_debut',
            'entity.dateFin as Date_fin',
            'entity.updatedAt as Maj_le',
            'entity.tag as Tag',
            'DATE_DIFF(DATE_ADD(entity.dateFin, 1, \'DAY\'), CURRENT_DATE()) as Jour_restant'
        )->leftJoin('entity.offre', 'o')
            ->leftJoin('entity.user', 'u')
            ->getQuery()
            ->getArrayResult();

        // Convert DateTime objects into strings
        $data = [];
        foreach ($result as $index => $row) {
            foreach ($row as $columnKey => $columnValue) {
                $data[$index][$columnKey] = $columnValue instanceof DateTimeInterface
                    ? $columnValue->format('d-m-Y H:i:s')
                    : $columnValue;
                if ($columnKey == 'Role') {
                    $data[$index][$columnKey] = implode(';', $columnValue);
                }
                if ($columnKey == 'Abonnement_actif') {
                    if ($data[$index][$columnKey] == 0) {
                        $data[$index][$columnKey] = 'Non';
                    }else{
                        $data[$index][$columnKey] = 'Oui';
                    }
                }
                if ($columnKey == 'Offre') {
                    if ($data[$index][$columnKey] == null) {
                        $data[$index][$columnKey] = 'Gratuit';
                    }
                }
                if ($columnKey == 'Jour_restant') {
                    if ($data[$index][$columnKey] < 0) {
                        $data[$index][$columnKey] = 0;
                    }
                }
            }
        }
        // Humanize headers based on column labels in EA
        if (isset($data[0])) {
            $headers = [];
            $properties = array_keys($data[0]);
            foreach ($properties as $property) {
                $headers[$property] = ucfirst($property);
                foreach ($fields as $field) {
                    // Override property name if a custom label was set
                    if ($property === $field->getProperty() && $field->getLabel()) {
                        $headers[$property] = $field->getLabel();
                        // And stop iterating
                        break;
                    }
                }
            }
            // Add headers to the final data array
            array_unshift($data, $headers);
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
