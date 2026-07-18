<?php

namespace App\Controller;

use App\Entity\MusicUniverse;
use App\Entity\WpPosts;
use App\Service\ServiceManager;
use App\Service\ToolsMeta;
use Cocur\Slugify\Slugify;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;


/**
 * @Route(name="experiences_")
 */
class ExperiencesController extends AbstractController
{
    private $service_manager;
    private $tools;
    private $entityManager;
    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $service_manager,
        ToolsMeta $tools
    ) {
        $this->entityManager = $entityManager;
        $this->service_manager = $service_manager;
        $this->tools = $tools;
    }

    /**
     * @Route("/{_locale}/creer-une-experience/demarrer", name="index", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->render('experiences/index.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    /**
     * @Route("/{_locale}/creer-une-experience/lancez-vous", name="creer_experience", requirements={"_locale": "fr"})
     */
    public function creerExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $musicUniverses = $this->entityManager->getRepository(MusicUniverse::class)->findBy(
            ['isActive' => true],
            ['position' => 'ASC', 'label' => 'ASC']
        );
        return $this->render('experiences/creer_experience.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'pixel_facebook' => false,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'music_universes' => $musicUniverses,
            'google_maps_api_key' => $this->getGoogleMapsApiKey(),
        ]);
    }

    /**
     * @Route("/{_locale}/creer-une-experience/create", name="create_experience", methods={"POST"}, requirements={"_locale": "fr"})
     */
    public function submitExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $date = new DateTime();
        $default_exp = 'exp_experiences';
        if ($this->service_manager->slugify($request->get('type_experience')) == 'divertissement-et-evenementiels') {
            $default_exp = 'exp_evenementiel';
        }

        $id = $this->service_manager->createPosts1(
            $this->getUser()->getId(),
            $date,
            $date,
            $default_exp,
            $default_exp,
            $default_exp,
            strtolower($request->get('status')),
            'open',
            'closed',
            '',
            $this->service_manager->slugify($default_exp),
            0,
            0,
            $date,
            $date,
            '',
            0,
            '',
            0,
            $default_exp,
            '',
            0,
            0,
            $request->getLocale()
        );

        if (in_array($this->service_manager->slugify($request->get('type_experience')), ['musique', 'production-musicale'], true)) {
            if ($request->get('ville')) {
                $ville = $this->service_manager->createPostMeta($id, 'exp_ville', $request->get('ville'), $request->getLocale());
            }
            if ($request->get('univers')) {
                $univers = $this->service_manager->createPostMeta($id, 'exp_univers', $request->get('univers'), $request->getLocale());
            }
        } else {
            if ($request->get('lieu_evt')) {
                $univers = $this->service_manager->createPostMeta($id, 'exp_lieu_evt', $request->get('lieu_evt'), $request->getLocale());
            }
            if ($request->get('participants_evt')) {
                $univers = $this->service_manager->createPostMeta($id, 'exp_participants_evt', $request->get('participants_evt'), $request->getLocale());
            }
        }
        if ($request->get('nom_evt')) {
            $meta = $this->service_manager->createPostMeta($id, 'exp_nom_evt', $request->get('nom_evt'), $request->getLocale());
        }
        if ($request->get('type_experience')) {
            $univers = $this->service_manager->createPostMeta($id, 'exp_type_experience', $request->get('type_experience'), $request->getLocale());
        }
        if ($request->get('besoins')) {
            foreach ($request->get('besoins') as $key => $value) {
                $besoins = $this->service_manager->createPostMeta($id, 'exp_besoins', $value, $request->getLocale());
            }
        }
        if ($request->get('options')) {
            foreach ($request->get('options') as $key => $value) {
                $options = $this->service_manager->createPostMeta($id, 'exp_options', $value, $request->getLocale());
            }
        }
        if ($request->get('precisions')) {
            $precisions = $this->service_manager->createPostMeta($id, 'exp_precisions', $request->get('precisions'), $request->getLocale());
        }

        $experience = $this->service_manager->getOneUserExperiencesProcess($id);
        $exp_besoins_options = '';
        foreach ($experience['exp_besoins'] as $key => $value) {
            $exp_besoins_options = $exp_besoins_options . ' ' . $value['metaValue'];
        }
        foreach ($experience['exp_options'] as $key => $value) {
            $exp_besoins_options = $exp_besoins_options . ' * ' . $value['metaValue'];
        }
        $mailParams = [
            "intitule_experience" => $experience['exp_type_experience'] . ' ' . $experience['exp_ville'] . ' ' . $experience['exp_univers'],
            "statut_experience" => $request->get('status'),
            "besoins_experience" => $exp_besoins_options,
            "precisions_experience" => $experience['exp_precisions'],
            "email_createur" => $this->getUser()->getEmailCanonical(),
        ];

        $this->sendBrevoTemplateEmail(
            [
                [
                    'email' => $this->getUser()->getEmailCanonical(),
                    'name' => $this->getUser()->getEmailCanonical()
                ]
            ],
            15,
            $mailParams
        );

        if (strtolower((string) $request->get('status')) === 'publish') {
            foreach ($this->getExperienceProfessionalRecipients($this->getUser()->getId()) as $recipient) {
                $this->sendBrevoTemplateEmail(
                    [[
                        'email' => $recipient['email'],
                        'name' => $recipient['name'],
                    ]],
                    61,
                    $mailParams
                );
            }

            $this->sendBrevoTemplateEmail(
                [[
                    'email' => 'commerce@trustandmarket.com',
                    'name' => 'Trust & Market',
                ]],
                61,
                $mailParams
            );
        }

        return new JsonResponse(json_encode(['response' => 'success']));
    }

    private function getExperienceProfessionalRecipients(?int $excludeUserId = null): array
    {
        $activityIds = $this->getExperienceTargetActivityIds();
        if (empty($activityIds)) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        $sql = <<<SQL
SELECT DISTINCT u.id, u.email_canonical, u.display_name
FROM wp_users u
INNER JOIN wp_usermeta um ON um.user_id = u.id
WHERE um.meta_key = :metaKey
  AND um.meta_value IN (:activityIds)
  AND (
    u.roles LIKE :roleAuto
    OR u.roles LIKE :roleSociete
  )
SQL;

        $params = [
            'metaKey' => 'activite_principale',
            'activityIds' => $activityIds,
            'roleAuto' => '%ROLE_AUTO_ENTREPRENEUR%',
            'roleSociete' => '%ROLE_SOCIETE%',
        ];
        $types = [
            'activityIds' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
        ];

        if ($excludeUserId !== null) {
            $sql .= ' AND u.id <> :excludeUserId';
            $params['excludeUserId'] = $excludeUserId;
        }

        $rows = $connection->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $recipients = [];
        foreach ($rows as $row) {
            if (empty($row['email_canonical'])) {
                continue;
            }

            $recipients[$row['email_canonical']] = [
                'email' => $row['email_canonical'],
                'name' => $row['display_name'] ?: $row['email_canonical'],
            ];
        }

        return array_values($recipients);
    }

    private function getExperienceTargetActivityIds(): array
    {
        $targetActivities = [
            "Studio d'enregistrement",
            'Mixage audio',
            'Mastering audio',
        ];

        $activityRows = $this->service_manager->postCategorie1('product_activity');
        $activityIds = [];

        foreach ($activityRows as $activityRow) {
            if (!in_array($activityRow['name'] ?? '', $targetActivities, true)) {
                continue;
            }

            if (!empty($activityRow['termTaxonomyId'])) {
                $activityIds[] = (string) $activityRow['termTaxonomyId'];
            }

            if (!empty($activityRow['termId'])) {
                $activityIds[] = (string) $activityRow['termId'];
            }
        }

        return array_values(array_unique(array_filter($activityIds)));
    }

    private function isExperienceProfessionalRecipient(int $userId): bool
    {
        $activityIds = $this->getExperienceTargetActivityIds();
        if (empty($activityIds)) {
            return false;
        }

        $connection = $this->entityManager->getConnection();
        $sql = <<<SQL
SELECT COUNT(1)
FROM wp_users u
INNER JOIN wp_usermeta um ON um.user_id = u.id
WHERE u.id = :user
  AND um.meta_key = :metaKey
  AND um.meta_value IN (:activityIds)
  AND (
    u.roles LIKE :roleAuto
    OR u.roles LIKE :roleSociete
  )
SQL;

        $count = (int) $connection->executeQuery(
            $sql,
            [
                'user' => $userId,
                'metaKey' => 'activite_principale',
                'activityIds' => $activityIds,
                'roleAuto' => '%ROLE_AUTO_ENTREPRENEUR%',
                'roleSociete' => '%ROLE_SOCIETE%',
            ],
            [
                'activityIds' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
            ]
        )->fetchOne();

        return $count > 0;
    }

    private function getReceivedExperiencesForProfessional(int $userId, int $page = 1, int $perPage = 5): array
    {
        $emptyPagination = [
            'items' => [],
            'page' => max(1, $page),
            'total' => 0,
            'total_pages' => 1,
        ];

        if (!$this->isExperienceProfessionalRecipient($userId)) {
            return $emptyPagination;
        }

        $connection = $this->entityManager->getConnection();
        $whereSql = <<<SQL
FROM wp_posts wp
WHERE (wp.post_type = :type OR wp.post_type = :type2)
  AND wp.post_status IN (:statuses)
  AND wp.post_author <> :user
SQL;

        $params = [
            'type' => 'exp_experiences',
            'type2' => 'exp_evenementiel',
            'statuses' => ['publish', 'assigned'],
            'user' => $userId,
        ];

        $types = [
            'statuses' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
        ];

        $total = (int) $connection->executeQuery('SELECT COUNT(1) ' . $whereSql, $params, $types)->fetchOne();
        if ($total === 0) {
            return $emptyPagination;
        }

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = <<<SQL
SELECT wp.ID AS id
{$whereSql}
ORDER BY wp.ID DESC
LIMIT :limit OFFSET :offset
SQL;

        $rows = $connection->executeQuery(
            $sql,
            array_merge($params, [
                'limit' => $perPage,
                'offset' => $offset,
            ]),
            array_merge($types, [
                'limit' => \PDO::PARAM_INT,
                'offset' => \PDO::PARAM_INT,
            ])
        )->fetchAllAssociative();

        $experiences = [];
        foreach ($rows as $row) {
            $experience = $this->service_manager->getOneUserExperiencesProcess($row['id']);
            if (!empty($experience)) {
                $experiences[] = $experience;
            }
        }

        return [
            'items' => $experiences,
            'page' => $page,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    private function sendBrevoTemplateEmail(array $to, int $templateId, array $params, array $bcc = []): void
    {
        if (empty($to)) {
            return;
        }

        $data = [
            'to' => $to,
            'templateId' => $templateId,
            'params' => $params,
        ];

        if (!empty($bcc)) {
            $data['bcc'] = $bcc;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $_SERVER['SENDBLUE_API_KEY'],
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * @Route("/{_locale}/creer-une-experience/update", name="update_experience", methods={"POST"}, requirements={"_locale": "fr"})
     * @param Request $request
     * @return JsonResponse
     */
    public function updateExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $date = new DateTime();
        $id = $request->get('postId');
        $default_exp = '';
        $exp = $this->entityManager->getRepository(WpPosts::class)->find($id);
        $previousStatus = strtolower((string) $exp->getPostStatus());
        $newStatus = strtolower((string) $request->get('status'));
        $exp->setPostStatus($newStatus);
        if ($this->service_manager->slugify($request->get('type_experience')) == 'divertissement-et-evenementiels') {
            $default_exp = 'exp_evenementiel';
        } else {
            $default_exp = 'exp_experiences';
        }
        $exp->setPostType($default_exp);
        $this->entityManager->persist($exp);
        $this->entityManager->flush();

        $meta = $this->service_manager->readPostMeta($id, 'exp_ville');
        if ($meta && $request->get('ville')) {
            $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_ville', $request->get('ville'), $request->getLocale());
        } else {
            $ville = $this->service_manager->createPostMeta($id, 'exp_ville', $request->get('ville'), $request->getLocale());
        }

        $meta = $this->service_manager->readPostMeta($id, 'exp_univers');
        if ($meta && $request->get('univers')) {
            $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_univers', $request->get('univers'), $request->getLocale());
        } else {
            $univers = $this->service_manager->createPostMeta($id, 'exp_univers', $request->get('univers'), $request->getLocale());
        }

        //Divertissements et evenementiels
        $meta = $this->service_manager->readPostMeta($id, 'exp_nom_evt');
        if ($meta && $request->get('nom_evt')) {
            $univers = $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_nom_evt', $request->get('nom_evt'), $request->getLocale());
        } else {
            $univers = $this->service_manager->createPostMeta($id, 'exp_nom_evt', $request->get('nom_evt'), $request->getLocale());
        }

        $meta = $this->service_manager->readPostMeta($id, 'exp_lieu_evt');
        if ($meta && $request->get('lieu_evt')) {
            $univers = $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_lieu_evt', $request->get('lieu_evt'), $request->getLocale());
        } else {
            $univers = $this->service_manager->createPostMeta($id, 'exp_lieu_evt', $request->get('lieu_evt'), $request->getLocale());
        }

        $meta = $this->service_manager->readPostMeta($id, 'exp_participants_evt');
        if ($meta && $request->get('participants_evt')) {
            $univers = $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_participants_evt', $request->get('participants_evt'), $request->getLocale());
        } else {
            $univers = $this->service_manager->createPostMeta($id, 'exp_participants_evt', $request->get('participants_evt'), $request->getLocale());
        }
        //Fin Divertissements et evenementiels

        $meta = $this->service_manager->readPostMeta($id, 'exp_type_experience');
        if ($meta && $request->get('type_experience')) {
            $univers = $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_type_experience', $request->get('type_experience'), $request->getLocale());
        } else {
            $univers = $this->service_manager->createPostMeta($id, 'exp_type_experience', $request->get('type_experience'), $request->getLocale());
        }

        $meta = $this->service_manager->readPostMeta($id, 'exp_precisions');
        if ($meta && $request->get('precisions')) {
            $this->service_manager->updatePostMeta($meta->getMetaId(), $id, 'exp_precisions', $request->get('precisions'), $request->getLocale());
        } else {
            $precisions = $this->service_manager->createPostMeta($id, 'exp_precisions', $request->get('precisions'), $request->getLocale());
        }

        if ($request->get('besoins')) {
            //delete old besoins
            $del = $this->service_manager->deleteAllPostmetaByPostIdKey($id, 'exp_besoins');
            //store new one
            foreach ($request->get('besoins') as $key => $value) {
                $besoins = $this->service_manager->createPostMeta($id, 'exp_besoins', $value, $request->getLocale());
            }
        }
        if ($request->get('options')) {
            //delete old besoins
            $del = $this->service_manager->deleteAllPostmetaByPostIdKey($id, 'exp_options');
            //store new one
            foreach ($request->get('options') as $key => $value) {
                $options = $this->service_manager->createPostMeta($id, 'exp_options', $value, $request->getLocale());
            }
        }
        $experience = $this->service_manager->getOneUserExperiencesProcess($id);
        $exp_besoins_options = '';
        foreach ($experience['exp_besoins'] as $key => $value) {
            $exp_besoins_options = $exp_besoins_options . ' ' . $value['metaValue'];
        }
        foreach ($experience['exp_options'] as $key => $value) {
            $exp_besoins_options = $exp_besoins_options . ' * ' . $value['metaValue'];
        }

        $mailParams = [
            "intitule_experience" => $experience['exp_type_experience'] . ' ' . $experience['exp_ville'] . ' ' . $experience['exp_univers'],
            "statut_experience" => $request->get('status'),
            "besoins_experience" => $exp_besoins_options,
            "precisions_experience" => $experience['exp_precisions'],
            "email_createur" => $this->getUser()->getEmailCanonical(),
        ];

        if ($previousStatus !== 'publish' && $newStatus === 'publish') {
            $this->sendBrevoTemplateEmail(
                [
                    [
                        'email' => $this->getUser()->getEmailCanonical(),
                        'name' => $this->getUser()->getEmailCanonical()
                    ]
                ],
                15,
                $mailParams
            );

            foreach ($this->getExperienceProfessionalRecipients($this->getUser()->getId()) as $recipient) {
                $this->sendBrevoTemplateEmail(
                    [[
                        'email' => $recipient['email'],
                        'name' => $recipient['name'],
                    ]],
                    61,
                    $mailParams
                );
            }

            $this->sendBrevoTemplateEmail(
                [[
                    'email' => 'commerce@trustandmarket.com',
                    'name' => 'Trust & Market',
                ]],
                61,
                $mailParams
            );
        }

        //Envoie du mail a l'admin
/*         $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $_SERVER['MJ_EMAIL_FROM'],
                        'Name' => "Trust & Market"
                    ],
                    'To' => [
                        [
                            'Email' => 'serviceclients@kbr-global.com',
                            'Name' => "Trust & Market"
                        ]
                    ],
                    'TemplateID' => 4790421,
                    'TemplateLanguage' => true,
                    'Subject' => "Nouvelle expérience en brouillon sur Trust & Market",
                    'Variables' => array("intitulé_expérience" => $experience['exp_type_experience'] . ' ' . $experience['exp_ville'] . ' ' . $experience['exp_univers'],
                        "statut_expérience" => $request->get('status'),
                        "besoins_expérience" => $exp_besoins_options, "précisions_expérience" => $experience['exp_precisions'])
                ]
            ]
        ];

        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => "admin@kbr-global.com",
                        'Name' => "Trust & Market"
                    ],
                    'To' => [
                        [
                            'Email' => $this->getUser()->getEmailCanonical(),
                            'Name' => $this->getUser()->getEmailCanonical()
                        ]
                    ],
                    'TemplateID' => 4790421,
                    'TemplateLanguage' => true,
                    'Subject' => "Nouvelle expérience en brouillon sur Trust & Market",
                    'Variables' => array("intitulé_expérience" => $experience['exp_type_experience'] . ' ' . $experience['exp_ville'] . ' ' . $experience['exp_univers'],
                        "statut_expérience" => $request->get('status'),
                        "besoins_expérience" => $exp_besoins_options, "précisions_expérience" => $experience['exp_precisions'])
                ]
            ]
        ]; */
        
        return new JsonResponse(json_encode(['response' => 'success']));
    }


    /**
     * @Route("/{_locale}/creer-une-experience/editer/{id}", name="edit_experience", requirements={"_locale": "fr"})
     */
    public function editExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $experience = $this->service_manager->getOneUserExperiencesProcess($request->get('id'));
        $musicUniverses = $this->entityManager->getRepository(MusicUniverse::class)->findBy(
            ['isActive' => true],
            ['position' => 'ASC', 'label' => 'ASC']
        );
        /* dd($experience); */
        return $this->render('experiences/edit_experience.html.twig', [
            'experience' => $experience,
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'pixel_facebook' => false,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'music_universes' => $musicUniverses,
            'google_maps_api_key' => $this->getGoogleMapsApiKey(),
        ]);
    }

    private function getGoogleMapsApiKey(): string
    {
        $fromEnv = $_ENV['GOOGLE_MAPS_API_KEY'] ?? null;
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        $fromServer = $_SERVER['GOOGLE_MAPS_API_KEY'] ?? null;
        if (is_string($fromServer) && trim($fromServer) !== '') {
            return trim($fromServer);
        }

        $fromGetEnv = getenv('GOOGLE_MAPS_API_KEY');
        if (is_string($fromGetEnv) && trim($fromGetEnv) !== '') {
            return trim($fromGetEnv);
        }

        return '';
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/experiences", name="liste_experience", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function listeExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $experiences_publish = $this->service_manager->getAllUserExperiencesProcess($this->getUser()->getId(), 'publish');
        $experiences_draft = $this->service_manager->getAllUserExperiencesProcess($this->getUser()->getId(), 'draft');
        $experiences_assigned = $this->service_manager->getAllUserExperiencesProcess($this->getUser()->getId(), 'assigned');
        $isExperienceProfessional = in_array('ROLE_AUTO_ENTREPRENEUR', $this->getUser()->getRoles(), true)
            || in_array('ROLE_SOCIETE', $this->getUser()->getRoles(), true);
        $receivedPagination = $isExperienceProfessional
            ? $this->getReceivedExperiencesForProfessional($this->getUser()->getId(), $request->query->getInt('receivedPage', 1), 5)
            : [
                'items' => [],
                'page' => 1,
                'total' => 0,
                'total_pages' => 1,
            ];
        //dd($experiences_publish);
        return $this->render('experiences/liste_experience.html.twig', [
            'experiences_publish' => $experiences_publish,
            'experiences_draft' => $experiences_draft,
            'experiences_assigned' => $experiences_assigned,
            'experiences_received' => $receivedPagination['items'],
            'received_current_page' => $receivedPagination['page'],
            'received_total_pages' => $receivedPagination['total_pages'],
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'page_name' => 'Mes expériences'
        ]);
    }

    /**
     * @Route("/{_locale}/experience/experience-front/details/{id}", name="details_experience", requirements={"_locale": "fr"})
     */
    public function detailsExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $experience = $this->service_manager->getOneUserExperiencesProcess($request->get('id'));
        return new Response(json_encode($experience));
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/experiences/delete", name="post_delete_get", methods="GET")
     */
    public function deletePostDef(Request $request)
    {
        $r = 0;
        if ($request->get('id') > 0) {
            $this->service_manager->deletePosts($request->get('id'));
            $r = 1;
        }
        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/experiences/assign", name="assign_get", methods="GET", requirements={"_locale": "fr"})
     */
    public function assignExperience(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $r = 0;
        $id = (int) $request->get('id');

        if ($id > 0) {
            $experience = $this->entityManager->getRepository(WpPosts::class)->find($id);
            if (
                $experience
                && (int) $experience->getPostAuthor() === (int) $this->getUser()->getId()
                && $experience->getPostStatus() === 'publish'
                && in_array($experience->getPostType(), ['exp_experiences', 'exp_evenementiel'], true)
            ) {
                $experience->setPostStatus('assigned');
                $this->entityManager->persist($experience);
                $this->entityManager->flush();
                $r = 1;
            }
        }

        return $this->render('admin/resultat.html.twig', [
            'result' => $r,
        ]);
    }
}
