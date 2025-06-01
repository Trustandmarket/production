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

class CsvAnnoncesExporter
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
            'entity.id as TypePrestation',
            'entity.postTitle as Titre',
            'entity.postStatus as Statut',
            'entity.id as Prix'
        )->getQuery()->getArrayResult();

        // Convert DateTime objects into strings
        $data = [];
        foreach ($result as $index => $row) {
            foreach ($row as $columnKey => $columnValue) {
                $data[$index][$columnKey] = $columnValue instanceof \DateTimeInterface
                    ? $columnValue->format('d-m-Y H:i:s')
                    : $columnValue;

                if ($columnKey == 'TypePrestation') {
                    $cat = $this->sm->getCategoryFromAnnounce($data[$index]['Identifiant']);
                    if (array_key_exists('name', $cat)) {
                        $data[$index][$columnKey] = $cat[0]['name'];
                    } else {
                        $data[$index][$columnKey] = 'Pas de catégorie';
                    }
                }

                if ($columnKey == 'Prix') {
                    $data[$index][$columnKey] = $this->sm->getPostStringDataValue($data[$index]['Identifiant'], '_price');
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
