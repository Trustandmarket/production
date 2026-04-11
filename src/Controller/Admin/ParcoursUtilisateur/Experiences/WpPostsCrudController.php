<?php

namespace App\Controller\Admin\ParcoursUtilisateur\Experiences;

use App\Entity\WpPosts;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class WpPostsCrudController extends AbstractCrudController
{
    private EntityManagerInterface $em;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(
        private readonly ServiceManager $service_manager,
        EntityManagerInterface $em,
        AdminUrlGenerator $adminUrlGenerator
    ) {
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return WpPosts::class;
    }

    public function index(AdminContext $context)
    {
        $request = $context->getRequest();

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(10, min(100, (int) $request->query->get('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $status = trim((string) $request->query->get('status', ''));
        $allQuery = $request->query->all();
        $typeFilters = $allQuery['type'] ?? [];
        if (!is_array($typeFilters)) {
            $typeFilters = [$typeFilters];
        }
        $typeFilters = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $typeFilters), static fn ($v) => $v !== ''));
        $email = trim((string) $request->query->get('email', ''));
        $search = trim((string) $request->query->get('q', ''));

        $sortBy = (string) $request->query->get('sort_by', 'id');
        $sortDir = strtolower((string) $request->query->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'id' => 'e.id',
            'type' => 'e.exp_type_experience',
            'ville' => 'e.exp_ville_display',
            'email' => 'e.user',
            'status' => 'e.status',
            'date' => 'e.created_at',
        ];
        $orderBy = $sortMap[$sortBy] ?? 'e.id';

        $conn = $this->em->getConnection();

        $baseSelect = <<<SQL
SELECT
    wp.ID AS id,
    wp.post_status AS status,
    wp.post_date AS created_at,
    wu.email_canonical AS user,
    (SELECT pm.meta_value FROM wp_postmeta pm WHERE pm.post_id = wp.ID AND pm.meta_key = 'exp_type_experience' ORDER BY pm.meta_id DESC LIMIT 1) AS exp_type_experience,
    (SELECT pm.meta_value FROM wp_postmeta pm WHERE pm.post_id = wp.ID AND pm.meta_key = 'exp_ville' ORDER BY pm.meta_id DESC LIMIT 1) AS exp_ville,
    (SELECT pm.meta_value FROM wp_postmeta pm WHERE pm.post_id = wp.ID AND pm.meta_key = 'exp_lieu_evt' ORDER BY pm.meta_id DESC LIMIT 1) AS exp_lieu_evt,
    (SELECT pm.meta_value FROM wp_postmeta pm WHERE pm.post_id = wp.ID AND pm.meta_key = 'exp_univers' ORDER BY pm.meta_id DESC LIMIT 1) AS exp_univers,
    COALESCE(
        (SELECT pm.meta_value FROM wp_postmeta pm WHERE pm.post_id = wp.ID AND pm.meta_key = 'exp_ville' ORDER BY pm.meta_id DESC LIMIT 1),
        (SELECT pm.meta_value FROM wp_postmeta pm WHERE pm.post_id = wp.ID AND pm.meta_key = 'exp_lieu_evt' ORDER BY pm.meta_id DESC LIMIT 1),
        ''
    ) AS exp_ville_display
FROM wp_posts wp
INNER JOIN wp_users wu ON wu.ID = wp.post_author
WHERE (wp.post_type = :type1 OR wp.post_type = :type2)
SQL;

        $params = [
            'type1' => 'exp_experiences',
            'type2' => 'exp_evenementiel',
        ];
        $filterSql = '';

        if ($status !== '') {
            $filterSql .= ' AND e.status = :status';
            $params['status'] = $status;
        }

        if (!empty($typeFilters)) {
            $typeParts = [];
            foreach ($typeFilters as $i => $typeFilter) {
                $paramName = 'typeFilter' . $i;
                $typeParts[] = 'e.exp_type_experience = :' . $paramName;
                $params[$paramName] = $typeFilter;
            }
            $filterSql .= ' AND (' . implode(' OR ', $typeParts) . ')';
        }

        if ($email !== '') {
            $filterSql .= ' AND e.user LIKE :emailFilter';
            $params['emailFilter'] = '%' . $email . '%';
        }

        if ($search !== '') {
            $filterSql .= ' AND (e.user LIKE :search OR e.exp_type_experience LIKE :search OR e.exp_ville_display LIKE :search OR e.exp_univers LIKE :search OR CAST(e.id AS CHAR) LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $countSql = "SELECT COUNT(*) AS total FROM ({$baseSelect}) e WHERE 1=1 {$filterSql}";
        $total = (int) $conn->executeQuery($countSql, $params)->fetchOne();

        $listSql = "SELECT * FROM ({$baseSelect}) e WHERE 1=1 {$filterSql} ORDER BY {$orderBy} {$sortDir} LIMIT :limit OFFSET :offset";
        $listParams = array_merge($params, [
            'limit' => $perPage,
            'offset' => $offset,
        ]);

        $experiences = $conn->executeQuery(
            $listSql,
            $listParams,
            [
                'limit' => \PDO::PARAM_INT,
                'offset' => \PDO::PARAM_INT,
            ]
        )->fetchAllAssociative();

        $typeOptionsSql = <<<SQL
SELECT DISTINCT
    (SELECT pm.meta_value
     FROM wp_postmeta pm
     WHERE pm.post_id = wp.ID
       AND pm.meta_key = 'exp_type_experience'
     ORDER BY pm.meta_id DESC
     LIMIT 1) AS exp_type_experience
FROM wp_posts wp
WHERE (wp.post_type = :type1 OR wp.post_type = :type2)
SQL;
        $typeRows = $conn->executeQuery($typeOptionsSql, [
            'type1' => 'exp_experiences',
            'type2' => 'exp_evenementiel',
        ])->fetchAllAssociative();
        $typeOptions = array_values(array_filter(array_unique(array_map(
            static fn ($row) => trim((string) ($row['exp_type_experience'] ?? '')),
            $typeRows
        )), static fn ($v) => $v !== ''));
        sort($typeOptions, SORT_NATURAL | SORT_FLAG_CASE);

        $emailOptionsSql = <<<SQL
SELECT DISTINCT wu.email_canonical AS email
FROM wp_posts wp
INNER JOIN wp_users wu ON wu.ID = wp.post_author
WHERE (wp.post_type = :type1 OR wp.post_type = :type2)
  AND wu.email_canonical IS NOT NULL
  AND wu.email_canonical <> ''
ORDER BY wu.email_canonical ASC
SQL;
        $emailRows = $conn->executeQuery($emailOptionsSql, [
            'type1' => 'exp_experiences',
            'type2' => 'exp_evenementiel',
        ])->fetchAllAssociative();
        $emailOptions = array_values(array_filter(array_unique(array_map(
            static fn ($row) => trim((string) ($row['email'] ?? '')),
            $emailRows
        )), static fn ($v) => $v !== ''));

        $totalPages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/ParcoursUtilisateur/Experiences/list.html.twig', [
            'experiences' => $experiences,
            'lang' => $request->getLocale(),
            'filters' => [
                'status' => $status,
                'type' => $typeFilters,
                'email' => $email,
                'q' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
            ],
            'type_options' => $typeOptions,
            'email_options' => $emailOptions,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function edit(AdminContext $context)
    {
        $exp = $this->service_manager->getOneUserExperiencesProcess($context->getRequest()->query->get('entityId'));

        return $this->render('admin/ParcoursUtilisateur/Experiences/edit.html.twig', [
            'exp' => $exp,
        ]);
    }
}
