<?php

namespace App\Service\Export;

use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvCommandesExporter
{
    private $entityManager;
    private $sm;
    /**
     * ServiceManager constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager, ServiceManager $sm)
    {
        $this->entityManager = $entityManager;
        $this->sm = $sm;
    }
    public function createResponseFromQueryBuilder(QueryBuilder $queryBuilder, FieldCollection $fields, string $filename): Response
    {
        $result = $queryBuilder->select(
            'entity.id as Identifiant',
            'entity.guid as Client',
            'entity.postTitle as Titre',
            'entity.postMimeType as Vendeur',
            'entity.postParent as Prix_TTC',
            'entity.postStatus as Statut',
            'entity.postDate as Date'
        )->getQuery()->getArrayResult();

        // Convert DateTime objects into strings
        $data = [];
        foreach ($result as $index => $row) {
            foreach ($row as $columnKey => $columnValue) {
                $data[$index][$columnKey] = $columnValue instanceof \DateTimeInterface
                    ? $columnValue->format('d-m-Y H:i:s')
                    : $columnValue;

                if ($columnKey == 'Statut') {
                    if ($data[$index][$columnKey] == 'wc-pending') {
                        $data[$index][$columnKey] = 'En attente de payement';
                    } elseif ($data[$index][$columnKey] == 'wc-completed') {
                        $data[$index][$columnKey] = 'Terminé';
                    } elseif ($data[$index][$columnKey] == 'wc-in-progress') {
                        $data[$index][$columnKey] = 'En cours';
                    } elseif ($data[$index][$columnKey] == 'wc-cancelled') {
                        $data[$index][$columnKey] = 'Annulé';
                    } elseif ($data[$index][$columnKey] == 'wc-failed') {
                        $data[$index][$columnKey] = 'Echoué';
                    } elseif ($data[$index][$columnKey] == 'wc-pending-trash') {
                        $data[$index][$columnKey] = 'En attente de payement';
                    } elseif ($data[$index][$columnKey] == 'wc-completed-trash') {
                        $data[$index][$columnKey] = 'Terminé';
                    } elseif ($data[$index][$columnKey] == 'wc-in-progress-trash') {
                        $data[$index][$columnKey] = 'En cours';
                    } elseif ($data[$index][$columnKey] == 'wc-cancelled-trash') {
                        $data[$index][$columnKey] = 'Annulé';
                    } elseif ($data[$index][$columnKey] == 'wc-failed-trash') {
                        $data[$index][$columnKey] = 'Annulé';
                    }
                }

                if ($columnKey == 'Client') {
                    $data[$index][$columnKey] = strip_tags($data[$index][$columnKey]);
                }

                if ($columnKey == 'Statut') {
                    $data[$index][$columnKey] = strip_tags($data[$index][$columnKey]);
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
