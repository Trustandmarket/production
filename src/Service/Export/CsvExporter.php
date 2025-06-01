<?php

namespace App\Service\Export;

use App\Entity\Departement;
use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\WpUsermeta;

class CsvExporter
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
            'entity.email_canonical as Email',
            'entity.displayName as Noms',
            'entity.isVerified as Email_verifie',
            'entity.isVerified as Mangopay',
            'entity.roles as Role',
            'entity.userRegistered as Date_de_creation',
            'p.departement as departement',
            'u.nomCommercial as nom_commercial',
            'o.titre as Offre_Pro'
        )->leftJoin('entity.userUniqueData', 'u')
            ->leftJoin('u.departement', 'p')
            ->leftJoin('entity.abonnements', 'a')
            ->leftJoin('a.offre', 'o')
            ->getQuery()
            ->getArrayResult();

        // Convert DateTime objects into strings
        $data = [];
        foreach ($result as $index => $row) {
            $mpAccount = $this->entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $row['Identifiant'], 'metaKey' => 'mp_user_id_sandbox'],
            ['umetaId' => 'desc']);
            foreach ($row as $columnKey => $columnValue) {
                $data[$index][$columnKey] = $columnValue instanceof DateTimeInterface
                    ? $columnValue->format('d-m-Y H:i:s')
                    : $columnValue;
                if ($columnKey == 'Role') {
                    $data[$index][$columnKey] = implode(';', $columnValue);
                }
                if ($columnKey == 'Email_verifie') {
                    $data[$index][$columnKey] = 'Oui';
                    if ($data[$index][$columnKey] === false) {
                        $data[$index][$columnKey] = 'Non';
                    }
                }
                if ($columnKey == 'Mangopay') {
                    $data[$index][$columnKey] = 'Non';
                    if ($mpAccount) {
                        $data[$index][$columnKey] = 'Oui';
                    }
                }
                if ($columnKey == 'Offre_Pro') {
                    if ($data[$index][$columnKey] == null) {
                        $data[$index][$columnKey] = 'Gratuit';
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
