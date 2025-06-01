<?php

namespace App\Controller;

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
        return $this->render('experiences/creer_experience.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'pixel_facebook' => false,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
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

        if ($this->service_manager->slugify($request->get('type_experience')) == 'musique') {
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
        // Prepare the data
        $data = [
            'to' => [
                [
                    'email' => $this->getUser()->getEmailCanonical(),
                    'name' => $this->getUser()->getEmailCanonical()
                ]
            ],
            'bcc' => [
                [
                    'email' => 'serviceclients@kbr-global.com',
                    'name' => "Trust & Market"
                ]
            ],
            'templateId' => 15,
            'params' => [
                "intitule_experience" => $experience['exp_type_experience'] . ' ' . $experience['exp_ville'] . ' ' . $experience['exp_univers'],
                "statut_experience" => $request->get('status'),
                "besoins_experience" => $exp_besoins_options,
                "precisions_experience" => $experience['exp_precisions']
            ]
        ];

        // Initialize cURL
        $ch = curl_init();

        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute the request
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        return new JsonResponse(json_encode(['response' => 'success']));
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
        $exp->setPostStatus(strtolower($request->get('status')));
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
        /* dd($experience); */
        return $this->render('experiences/edit_experience.html.twig', [
            'experience' => $experience,
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'pixel_facebook' => false,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
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
        //dd($experiences_publish);
        return $this->render('experiences/liste_experience.html.twig', [
            'experiences_publish' => $experiences_publish,
            'experiences_draft' => $experiences_draft,
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
}
