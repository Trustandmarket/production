<?php

namespace App\Controller;

use App\Service\Recaptcha\Recaptcha;
use App\Service\ServiceManager;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\WpOptions;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\WpPosts;
use App\Entity\WpTermRelationships;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpTerms;

/**
 * @Route("", requirements={"_locale": "fr"}, name="experience_")
 */
class ExperienceController extends AbstractController
{
    private $entityManager;
    private $service_manager;

    public function __construct(ServiceManager $service_manager, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->service_manager = $service_manager;
    }

    /**
     * @Route("/{_locale}/a-propos/nous-contacter", name="index")
     */
    public function index()
    {
        $sousMenu = $this->service_manager->naveMenuItem(40);
        $contenu = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => 'nous-contacter',
                'postType' => 'page',
            ]);
        //dd($contenu);
        return $this->render('experience/index.html.twig', [
            'sous_menu' => $sousMenu,
            'contenu' => $contenu,
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }

    /**
     * @Route("/{_locale}/aide/envoyez-nous-vos-commentaires", name="envoyez_commentaires")
     */
    public function envoyez_commentaires()
    {
        $sousMenu = $this->service_manager->naveMenuItem(141);
        $contenu = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy([
                'postName' => 'envoyez-nous-vos-commentaires',
                'postType' => 'page',
            ]);
        //dd($contenu);
        return $this->render('experience/envoyez_commentaires.html.twig', [
            'sous_menu' => $sousMenu,
            'contenu' => $contenu,
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }

    /**
     * @Route("/{_locale}/envoyez-nous-vos-commentaires/email", name="sendComment")
     * @param Request $request
     * @param Recaptcha $recaptcha
     * @return Response
     */
    public function sendCommentsEmails(Request $request, Recaptcha $recaptcha)
    {
        $recaptcha = $recaptcha->create_assessment('6Ld5EEUpAAAAAOcwEkhPcx1fjKi0BUggL3jqUpPJ',$request->get('g-recaptcha-response'),'sym-trust-adresse','TRUST_CONTACT_US');
        if($recaptcha['response']){
            $date = new DateTime();
            $response = "";
            //Email Admin
                        // Prepare the data
                        $data = [
                            'to' => [
                                [
                                    'email' => 'contact@kbr-global.com',
                                    'name' => "Trust & Market"
                                ]
                            ],
                            'templateId' => 10,
                            'params' => [
                                "nom" => $request->get('nom'),
                                "email" => $request->get('email'),
                                "message" => $request->get('message')
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

            //Email User
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => $request->get('email'),
                        'name' => $request->get('nom')
                    ]
                ],
                'templateId' => 9,
                'params' => [
                    "nom" => $request->get('nom'),
                    "email" => $request->get('email'),
                    "message" => $request->get('message')
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


            return $this->render('admin/resultat.html.twig', [
                'result' => 1,
            ]);
        }else{
            return $this->render('admin/resultat.html.twig', [
                'result' => 0,
            ]);
        }

    }

    /**
     * @Route("/{_locale}/feedbacks/email", name="sendFeedbacks")
     * @param Request $request
     * @param Recaptcha $recaptcha
     * @return Response
     */
    public function feedbacks(Request $request, Recaptcha $recaptcha)
    {
        $recaptcha = $recaptcha->create_assessment('6Ld5EEUpAAAAAOcwEkhPcx1fjKi0BUggL3jqUpPJ',$request->get('g-recaptcha-response'),'sym-trust-adresse','TRUST_FEEDBACKS');
        if($recaptcha['response']){
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => 'contact@kbr-global.com',
                        'name' => "Trust & Market"
                    ]
                ],
                'templateId' => 11,
                'params' => [
                    "email" => $this->getUser()->getEmailCanonical(),
                    "theme_feedback" => $request->get('sujet'),
                    "message" => $request->get('votre-message')
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

            return $this->render('admin/resultat.html.twig', ['result' => 1]);
        }else{
            return $this->render('admin/resultat.html.twig', ['result' => 0]);
        }

    }
}
