<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WpUsermeta;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Mime\Address;
use App\Service\Payment;
use App\Service\Recaptcha\Recaptcha;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
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

        if ($form->isSubmitted() && $form->isValid()) {
            $recaptcha = $recaptcha->create_assessment('6Ld5EEUpAAAAAOcwEkhPcx1fjKi0BUggL3jqUpPJ', $request->get('g-recaptcha-response'), 'sym-trust-adresse',
                'TRUST_RESET_PASSWORD');

            if ($recaptcha['response']) {
                return $this->processSendingPasswordResetEmail(
                    $form->get('email_canonical')->getData(),
                    $mailer,
                    $translator
                );
            } else {
                throw new CustomUserMessageAccountStatusException($recaptcha['message']);
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email_canonical' => $emailFormData,
        ]);
        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            //dd($e);
            // If you want to tell the user why a reset email was not sent, uncomment
            // the lines below and change the redirect to 'app_forgot_password_request'.
            // Caution: This may reveal if a user is registered or not.
            //
            // $this->addFlash('reset_password_error', sprintf(
            //     '%s - %s',
            //     $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
            //     $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            // ));

            return $this->redirectToRoute('app_check_email');
        }
        $reset_url = $this->generateUrl('app_reset_password', ['token' => $resetToken->getToken()], UrlGenerator::ABSOLUTE_URL);
        
        //Send request email
        $data = [
            'to' => [
                [
                    'Email' => $user->getEmailCanonical(),
                    'Name' => $user->getDisplayName()
                ]
            ],
            'templateId' => 4,
            'params' => [
                'url_resetpwd' => $reset_url
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


        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }

    /**
     * Confirmation page after a user has requested a password reset.
     *
     * @Route("/{_locale}/check-email", name="app_check_email")
     */
    public function checkEmail(): Response
    {
        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether or not a user was found with the given email address or not
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
     * @param EntityManagerInterface $entityManager
     * @param MailerInterface $mailer
     * @param string|null $token
     * @return Response
     */
    public function reset(Request $request, UserPasswordHasherInterface $userPasswordHasher, TranslatorInterface $translator, EntityManagerInterface $entityManager, MailerInterface $mailer, string $token = null): Response
    {
        if ($token) {
            // We store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
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
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            // Encode(hash) the plain password, and set it.
            $encodedPassword = $userPasswordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            //Check mangopay
            $this->storePaymentData($entityManager, $user);

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();
            $this->addFlash('info', $translator->trans('changement-mot-de-passe.message-succes', [], 'security'));
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    /**
     * Données supplémentaires, Compte mangopay et email admin
     * @param EntityManagerInterface $entityManager
     * @param User $user
     * @return void
     */
    public function storePaymentData(EntityManagerInterface $entityManager, User $user): void
    {
        //Compte mangopay
        $mpAccount = $entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $user->getId(), 'metaKey' => 'mp_user_id_sandbox']);
        if ($mpAccount) {
            $mpAccount = $mpAccount->getMetaValue();
        } else {
            $payment = new Payment();
            if (in_array('ROLE_ABONNE', $user->getRoles())) {
                $userType = 'UserNatural';
                $description = 'Client';
                $legalPersonType = "";
            }
            if (in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles())) {
                $userType = 'UserLegal';
                $description = 'Auto Entrepreneur';
                $legalPersonType = 'Soletrader';
            }
            if (in_array('ROLE_SOCIETE', $user->getRoles())) {
                $userType = 'UserLegal';
                $description = 'Societe';
                $legalPersonType = 'Business';
            }
            $first_name = $entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $user->getId(), 'metaKey' => 'first_name']);
            if ($first_name) {
                $first_name = $first_name->getMetaValue();
            } else {
                $first_name = null;
            }

            $last_name = $entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $user->getId(), 'metaKey' => 'last_name']);
            if ($last_name) {
                $last_name = $last_name->getMetaValue();
            } else {
                $last_name = null;
            }

            $nationalite = $entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $user->getId(), 'metaKey' => 'nationalityCountry']);
            if ($nationalite) {
                $nationalite = $nationalite->getMetaValue();
            } else {
                $nationalite = null;
            }

            $residence = $entityManager->getRepository(WpUsermeta::class)->findOneBy(['userId' => $user->getId(), 'metaKey' => 'residenceCountry']);
            if ($residence) {
                $residence = $residence->getMetaValue();
            } else {
                $residence = null;
            }

            $mangopayUser = $payment->createMangoUser(
                $userType,
                $legalPersonType,
                trim($first_name),
                trim($last_name),
                strtotime($user->getDateNaissance()),
                $nationalite,
                $residence,
                $user->getEmailCanonical()
            );

            $mpAccount = new WpUsermeta();
            $mpAccount->setUserId($this->getUser()->getId());
            $mpAccount->setMetaKey('mp_user_id_sandbox');
            $mpAccount->setMetaValue($mangopayUser->Id);
            $entityManager->persist($mpAccount);
            $entityManager->flush();

            $userWallets = $payment->createWallet(
                $mangopayUser->Id,
                $description,
                'EUR'
            );
        }
    }
}
