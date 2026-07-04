<?php

namespace App\Controller;

use App\Entity\Newsletter;
use App\Entity\WpOptions;
use App\Service\Payment;
use App\Service\Recaptcha\Recaptcha;
use App\Service\ServiceManager;
use App\Service\ToolsMeta;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use DateTime;

class NewsletterController extends AbstractController
{
    private $service_manager;
    private $entityManager;
    private $tools;
    private $twig;
    private $payment;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $entityManager,
        Environment $twig,
        Payment $payment,
        ToolsMeta $tools
    )
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->payment = $payment;
        $this->twig = $twig;
        $this->tools = $tools;
    }

    #[Route('/{_locale}/newsletter', name: 'app_newsletter')]
    public function index(Recaptcha $recaptcha): Response
    {
        return $this->render('newsletter/subscribe.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'pageName' => 'Newsletter',
            'recaptcha_site_key' => $recaptcha->getSiteKey(),
            'recaptcha_enabled' => $recaptcha->shouldEnforce((string) $this->getParameter('environnement')),
            'recaptcha_action' => Recaptcha::ACTION_NEWSLETTER,
            'disable_legacy_recaptcha' => true,
        ]);
    }

    #[Route('/{_locale}/newsletter/inscription-reussie', name: 'app_newsletter_success')]
    public function app_newsletter_success(): Response
    {
        return $this->render('newsletter/result.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'pageName' => 'Newsletter',
        ]);
    }

    /**
     * @param Request $request
     * @param Recaptcha $recaptcha
     * @return Response
     */
    #[Route('/{_locale}/newsletter/ajouter',name: 'newsletterUser', requirements: ['_locale' => 'fr'])]
    public function newsletterUser(Request $request, Recaptcha $recaptcha)
    {
        $recaptchaEnabled = $recaptcha->shouldEnforce((string) $this->getParameter('environnement'));
        $captchaResult = [
            'state' => Recaptcha::STATE_ALLOW,
            'message' => 'OK',
        ];

        if ($recaptchaEnabled) {
            $captchaResult = $recaptcha->assess(
                Recaptcha::ACTION_NEWSLETTER,
                (string) $request->request->get('recaptcha_token', $request->get('g-recaptcha-response', ''))
            );
        }

        if ($captchaResult['state'] === Recaptcha::STATE_ALLOW) {

            $email_exist = $this->entityManager->getRepository(Newsletter::class)->findOneBy(['email' => $request->get('email')]);
            $result = 0;
            //verifier le champs captcha
            if ($email_exist) {
                $result = 2;
            } else {
                $saveDate = new DateTime('now');
                $user = new Newsletter();
                $user->setUserName($request->get('prenom'));
                $user->setEmail($request->get('email'));
                $user->setStatus('publish');
                $user->setCreatedAt($saveDate);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

            //Send Notification Email User
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => $request->get('email'),
                        'name' => $request->get('prenom')
                    ]
                ],
                'templateId' => 12,
                'params' => [
                    "prenom" => $request->get('prenom')
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


            //Send Notification Email Admin
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => "commerce@trustandmarket.com",
                        'name' => "Trust & Market"
                    ]
                ],
                'templateId' => 13,
                'params' => [
                    "prenom" => $request->get('prenom'),
                    "email" => $request->get('email')
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
                //End Send Notification Email
                //Add user to newsletter contact list on mailjet
                if ($this->getParameter('environnement') == 'prod') {
/*
                    $body = [
                        'Contacts' => [
                            [
                                'Email' => $request->get('email'),
                                'IsExcludedFromCampaigns' => "false",
                                'Name' => $request->get('prenom'),
                                'Properties' => "object"
                            ]
                        ],
                        'ContactsLists' => [
                            [
                                'Action' => "addforce",
                                'ListID' => "229294"
                            ]
                        ]
                    ];
                    $response = $mj->post(Resources::$ContactManagemanycontacts, ['body' => $body]); */
                }
                $result = 1;
            }

            return $this->render('admin/resultat.html.twig', [
                'result' => $result,
            ]);
        } else {
            return $this->render('admin/resultat.html.twig', [
                'result' => 0,
            ]);
        }

    }
}
