<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\Recaptcha\Recaptcha;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * @Route("/reset-password")
 */
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private $resetPasswordHelper;
    private $entityManager;

    public function __construct(ResetPasswordHelperInterface $resetPasswordHelper, EntityManagerInterface $entityManager)
    {
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->entityManager = $entityManager;
    }

    /**
     * Display & process form to request a password reset.
     *
     * @Route("", name="app_forgot_password_request")
     * @param Request $request
     * @param MailerInterface $mailer
     * @param TranslatorInterface $translator
     * @param Recaptcha $recaptcha
     * @return Response
     * @throws Exception
     */
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator, Recaptcha $recaptcha): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);
        $locale = (string) ($request->attributes->get('_locale') ?? $request->getLocale() ?? 'fr');
        $recaptchaEnabled = $recaptcha->shouldEnforce((string) $this->getParameter('environnement'));
        if ($locale === '') {
            $locale = 'fr';
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $captchaResult = [
                'state' => Recaptcha::STATE_ALLOW,
                'message' => 'OK',
            ];

            if ($recaptchaEnabled) {
                $captchaResult = $recaptcha->assess(
                    Recaptcha::ACTION_RESET_PASSWORD,
                    (string) $request->request->get('recaptcha_token', $request->get('g-recaptcha-response', ''))
                );
            }

            if ($captchaResult['state'] === Recaptcha::STATE_ALLOW) {
                return $this->processSendingPasswordResetEmail(
                    $form->get('email_canonical')->getData(),
                    $mailer,
                    $translator,
                    $locale
                );
            }

            throw new CustomUserMessageAccountStatusException($captchaResult['message']);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
            'recaptcha_site_key' => $recaptcha->getSiteKey(),
            'recaptcha_enabled' => $recaptchaEnabled,
            'recaptcha_action' => Recaptcha::ACTION_RESET_PASSWORD,
        ]);
    }

    private function processSendingPasswordResetEmail(
        string $emailFormData,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        string $locale = 'fr'
    ): RedirectResponse {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email_canonical' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email', ['_locale' => $locale]);
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // If you want to tell the user why a reset email was not sent, uncomment
            // the lines below and change the redirect to 'app_forgot_password_request'.
            // Caution: This may reveal if a user is registered or not.
            //
            // $this->addFlash('reset_password_error', sprintf(
            //     '%s - %s',
            //     $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
            //     $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            // ));

            return $this->redirectToRoute('app_check_email', ['_locale' => $locale]);
        }

        $resetUrl = $this->generateUrl(
            'app_reset_password',
            ['_locale' => $locale, 'token' => $resetToken->getToken()],
            UrlGenerator::ABSOLUTE_URL
        );

        $data = [
            'to' => [[
                'email' => $user->getEmailCanonical(),
                'name' => $user->getDisplayName(),
            ]],
            'templateId' => 4,
            'params' => [
                'url_resetpwd' => $resetUrl,
            ],
        ];

        $apiKey = (string) ($_SERVER['SENDBLUE_API_KEY'] ?? $_ENV['SENDBLUE_API_KEY'] ?? getenv('SENDBLUE_API_KEY') ?? '');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $brevoSent = $apiKey !== '' && $response !== false && $httpCode >= 200 && $httpCode < 300;

        if (!$brevoSent) {
            error_log(sprintf(
                '[reset-password] Brevo send failed for user %s (apiKeyEmpty=%s, httpCode=%d, curlError=%s, response=%s)',
                (string) $user->getEmailCanonical(),
                $apiKey === '' ? 'yes' : 'no',
                $httpCode,
                $curlError,
                (string) $response
            ));

            try {
                $fallbackEmail = (new TemplatedEmail())
                    ->from(new Address('commerce@trustandmarket.com', 'Trust & Market'))
                    ->to(new Address((string) $user->getEmailCanonical(), (string) $user->getDisplayName()))
                    ->subject('Reinitialisation de votre mot de passe')
                    ->htmlTemplate('reset_password/email.html.twig')
                    ->context([
                        'resetToken' => $resetToken,
                    ]);

                $mailer->send($fallbackEmail);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[reset-password] Symfony mailer fallback failed for user %s (%s)',
                    (string) $user->getEmailCanonical(),
                    $e->getMessage()
                ));
            }
        }

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email', ['_locale' => $locale]);
    }

    /**
     * Confirmation page after a user has requested a password reset.
     *
     * @Route("/{_locale}/check-email", name="app_check_email")
     */
    public function checkEmail(): Response
    {
        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether or not a user was found with the given email address or not.
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     *
     * @Route("/{_locale}/reset/{token}", name="app_reset_password")
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param TranslatorInterface $translator
     * @param string|null $token
     * @return Response
     */
    public function reset(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        TranslatorInterface $translator,
        string $token = null
    ): Response {
        if ($token) {
            // Store token in session and remove it from URL to avoid leaking it to 3rd party JS.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password', [
                '_locale' => (string) ($request->attributes->get('_locale') ?? $request->getLocale() ?? 'fr'),
            ]);
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once.
            $this->resetPasswordHelper->removeResetRequest($token);

            $encodedPassword = $userPasswordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            $this->cleanSessionAfterReset();
            $this->addFlash('info', $translator->trans('changement-mot-de-passe.message-succes', [], 'security'));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}
