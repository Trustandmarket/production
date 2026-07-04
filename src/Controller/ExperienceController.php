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
    public function index(Request $request, Recaptcha $recaptcha)
    {
        $session = $request->getSession();
        $recaptchaEnabled = $recaptcha->shouldEnforce((string) $this->getParameter('environnement'));
        $recaptchaMode = 'v3';
        if (
            $recaptchaEnabled
            && $recaptcha->isV2FallbackEnabled()
            && $session
            && $session->get('recaptcha_contact_mode') === 'v2'
        ) {
            $recaptchaMode = 'v2';
            $session->remove('recaptcha_contact_mode');
        }

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
            'recaptcha_site_key' => $recaptcha->getSiteKey(),
            'recaptcha_v2_site_key' => $recaptcha->getV2SiteKey(),
            'recaptcha_enabled' => $recaptchaEnabled,
            'recaptcha_v2_fallback_available' => $recaptchaEnabled && $recaptcha->isV2FallbackAvailableForAction(Recaptcha::ACTION_CONTACT_US),
            'recaptcha_action' => Recaptcha::ACTION_CONTACT_US,
            'recaptcha_mode' => $recaptchaMode,
            'disable_legacy_recaptcha' => true,
        ]);
    }

    /**
     * @Route("/{_locale}/aide/envoyez-nous-vos-commentaires", name="envoyez_commentaires")
     */
    public function envoyez_commentaires(Request $request, Recaptcha $recaptcha)
    {
        $session = $request->getSession();
        $recaptchaEnabled = $recaptcha->shouldEnforce((string) $this->getParameter('environnement'));
        $recaptchaMode = 'v3';
        if (
            $recaptchaEnabled
            && $recaptcha->isV2FallbackEnabled()
            && $session
            && $session->get('recaptcha_feedback_mode') === 'v2'
        ) {
            $recaptchaMode = 'v2';
            $session->remove('recaptcha_feedback_mode');
        }

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
            'recaptcha_site_key' => $recaptcha->getSiteKey(),
            'recaptcha_v2_site_key' => $recaptcha->getV2SiteKey(),
            'recaptcha_enabled' => $recaptchaEnabled,
            'recaptcha_v2_fallback_available' => $recaptchaEnabled && $recaptcha->isV2FallbackAvailableForAction(Recaptcha::ACTION_FEEDBACKS),
            'recaptcha_action' => Recaptcha::ACTION_FEEDBACKS,
            'recaptcha_mode' => $recaptchaMode,
            'disable_legacy_recaptcha' => true,
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
        $session = $request->getSession();
        $recaptchaEnabled = $recaptcha->shouldEnforce((string) $this->getParameter('environnement'));
        $recaptchaMode = $request->request->get('recaptcha_mode') === 'v2' && $recaptcha->isV2FallbackEnabled() ? 'v2' : 'v3';
        $captchaResult = [
            'state' => Recaptcha::STATE_ALLOW,
            'message' => 'OK',
        ];
        $forceV2Fallback = $request->request->get('recaptcha_force_v2') === '1';

        if ($recaptchaEnabled && $forceV2Fallback && $recaptcha->isV2FallbackAvailableForAction(Recaptcha::ACTION_CONTACT_US)) {
            if ($session) {
                $session->set('recaptcha_contact_mode', 'v2');
            }

            return $this->json([
                'success' => false,
                'state' => Recaptcha::STATE_FALLBACK_V2_REQUIRED,
                'message' => 'Verification renforcee requise. Merci de confirmer le controle de securite.',
                'reload_url' => $this->generateUrl('experience_index', ['_locale' => $request->getLocale()]),
            ]);
        }

        if ($recaptchaEnabled) {
            $captchaResult = $recaptchaMode === 'v2'
                ? $recaptcha->assessFallbackV2(
                    Recaptcha::ACTION_CONTACT_US,
                    (string) $request->request->get('g-recaptcha-response', $request->get('g-recaptcha-response', ''))
                )
                : $recaptcha->assessPrimary(
                    Recaptcha::ACTION_CONTACT_US,
                    (string) $request->request->get('recaptcha_token', $request->get('g-recaptcha-response', ''))
                );
        }

        if($captchaResult['state'] === Recaptcha::STATE_ALLOW){
            $date = new DateTime();
            $response = "";
            //Email Admin
                        // Prepare the data
                        $data = [
                            'to' => [
                                [
                                    'email' => 'commerce@trustandmarket.com',
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

            return $this->json([
                'success' => true,
                'state' => Recaptcha::STATE_ALLOW,
                'message' => 'OK',
            ]);
        }

        if ($captchaResult['state'] === Recaptcha::STATE_FALLBACK_V2_REQUIRED) {
            if ($session) {
                $session->set('recaptcha_contact_mode', 'v2');
            }

            return $this->json([
                'success' => false,
                'state' => Recaptcha::STATE_FALLBACK_V2_REQUIRED,
                'message' => 'Verification renforcee requise. Merci de confirmer le controle de securite.',
                'reload_url' => $this->generateUrl('experience_index', ['_locale' => $request->getLocale()]),
            ]);
        }

        return $this->json([
            'success' => false,
            'state' => $captchaResult['state'],
            'message' => $captchaResult['message'],
        ]);

    }

    /**
     * @Route("/{_locale}/feedbacks/email", name="sendFeedbacks")
     * @param Request $request
     * @param Recaptcha $recaptcha
     * @return Response
     */
    public function feedbacks(Request $request, Recaptcha $recaptcha)
    {
        $session = $request->getSession();
        $recaptchaEnabled = $recaptcha->shouldEnforce((string) $this->getParameter('environnement'));
        $recaptchaMode = $request->request->get('recaptcha_mode') === 'v2' && $recaptcha->isV2FallbackEnabled() ? 'v2' : 'v3';
        $captchaResult = [
            'state' => Recaptcha::STATE_ALLOW,
            'message' => 'OK',
        ];
        $forceV2Fallback = $request->request->get('recaptcha_force_v2') === '1';

        if ($recaptchaEnabled && $forceV2Fallback && $recaptcha->isV2FallbackAvailableForAction(Recaptcha::ACTION_FEEDBACKS)) {
            if ($session) {
                $session->set('recaptcha_feedback_mode', 'v2');
            }

            return $this->json([
                'success' => false,
                'state' => Recaptcha::STATE_FALLBACK_V2_REQUIRED,
                'message' => 'Verification renforcee requise. Merci de confirmer le controle de securite.',
                'reload_url' => $this->generateUrl('experience_envoyez_commentaires', ['_locale' => $request->getLocale()]),
            ]);
        }

        if ($recaptchaEnabled) {
            $captchaResult = $recaptchaMode === 'v2'
                ? $recaptcha->assessFallbackV2(
                    Recaptcha::ACTION_FEEDBACKS,
                    (string) $request->request->get('g-recaptcha-response', $request->get('g-recaptcha-response', ''))
                )
                : $recaptcha->assessPrimary(
                    Recaptcha::ACTION_FEEDBACKS,
                    (string) $request->request->get('recaptcha_token', $request->get('g-recaptcha-response', ''))
                );
        }

        if($captchaResult['state'] === Recaptcha::STATE_ALLOW){
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => 'commerce@trustandmarket.com',
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

            return $this->json([
                'success' => true,
                'state' => Recaptcha::STATE_ALLOW,
                'message' => 'OK',
            ]);
        }

        if ($captchaResult['state'] === Recaptcha::STATE_FALLBACK_V2_REQUIRED) {
            if ($session) {
                $session->set('recaptcha_feedback_mode', 'v2');
            }

            return $this->json([
                'success' => false,
                'state' => Recaptcha::STATE_FALLBACK_V2_REQUIRED,
                'message' => 'Verification renforcee requise. Merci de confirmer le controle de securite.',
                'reload_url' => $this->generateUrl('experience_envoyez_commentaires', ['_locale' => $request->getLocale()]),
            ]);
        }

        return $this->json([
            'success' => false,
            'state' => $captchaResult['state'],
            'message' => $captchaResult['message'],
        ]);

    }
}
