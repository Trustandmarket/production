<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WpUsermeta;
use App\Form\RegistrationFormType;
use App\Security\AppAuthenticator;
use App\Security\EmailVerifier;
use App\Service\Payment;
use App\Service\Recaptcha\Recaptcha;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;


class RegistrationController extends AbstractController
{
    private $emailVerifier;
    private $service_manager;
    private $payment;

    public function __construct(EmailVerifier $emailVerifier, ServiceManager $service_manager, Payment $payment)
    {
        $this->emailVerifier = $emailVerifier;
        $this->service_manager = $service_manager;
        $this->payment = $payment;
    }

    /**
     * @Route("/{_locale}/creer-un-compte", name="app_register")
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param Recaptcha $recaptcha
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Recaptcha $recaptcha, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $recaptcha = $recaptcha->create_assessment('6Ld5EEUpAAAAAOcwEkhPcx1fjKi0BUggL3jqUpPJ', $request->get('g-recaptcha-response'), 'sym-trust-adresse', 'TRUST_REGISTER');
            if ($recaptcha['response']) {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );
                $rolesArray = [$request->get('role_user')];
                $user->setUserEmail($user->getEmailCanonical());
                $user->setUsernameCanonical($user->getEmailCanonical());
                $user->setUserActivationKey($request->get('registration_form')['_token']);
                $user->setUserNicename($request->get('registration_form')['first_name']);
                $user->setDisplayName($request->get('registration_form')['first_name'] . ' ' . $request->get('registration_form')['last_name']);
                $user->setUserActivationKey($request->get('registration_form')['_token']);
                $user->setDateNaissance($request->get('registration_form')['dateNaissance']);
                $user->setRoles($rolesArray);

                $entityManager->persist($user);
                $entityManager->flush();
                // Updates meta data

                //Pour le first name
                $meta = new WpUsermeta();
                $meta->setUserId($user->getId());
                $meta->setMetaKey('first_name');
                $meta->setMetaValue($request->get('registration_form')['first_name']);
                $entityManager->persist($meta);
                $entityManager->flush();

                $this->service_manager->updateUserMeta($user->getId(), 'nom_commercial', $request->get('registration_form')['first_name']);

                //Pour le Last_name
                $meta = new WpUsermeta();
                $meta->setUserId($user->getId());
                $meta->setMetaKey('last_name');
                $meta->setMetaValue($request->get('registration_form')['last_name']);
                $entityManager->persist($meta);
                $entityManager->flush();

                //Nationalite
                $meta = new WpUsermeta();
                $meta->setUserId($user->getId());
                $meta->setMetaKey('nationalityCountry');
                $meta->setMetaValue($request->get('nationalite'));
                $entityManager->persist($meta);
                $entityManager->flush();

                //Residence
                $meta = new WpUsermeta();
                $meta->setUserId($user->getId());
                $meta->setMetaKey('residenceCountry');
                $meta->setMetaValue($request->get('residence'));
                $entityManager->persist($meta);
                $entityManager->flush();

                //Date de naissance
                $meta = new WpUsermeta();
                $meta->setUserId($user->getId());
                $meta->setMetaKey('bdaytime');
                $meta->setMetaValue($user->getDateNaissance());
                $entityManager->persist($meta);
                $entityManager->flush();

                //Email de facturation
                $meta = new WpUsermeta();
                $meta->setUserId($user->getId());
                $meta->setMetaKey('billing_email');
                $meta->setMetaValue($user->getEmailCanonical());
                $entityManager->persist($meta);
                $entityManager->flush();

                // Donnees de newsletter
                if ($request->get('_email_newsletter')) {
                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_newsletter');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_reservation_service_client');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_reservation_service_annonceur');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_annulation_reservation');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_annonce_en_brouillon');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_annonce_en_moderation');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_annonce_rejete');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();

                    $meta = new WpUsermeta();
                    $meta->setUserId($user->getId());
                    $meta->setMetaKey('_email_annonce_publie');
                    $meta->setMetaValue(1);
                    $entityManager->persist($meta);
                    $entityManager->flush();
                }
                // End updates meta data
                $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user);
                // authenticate the user and use onAuthenticationSuccess on the authenticator
                //$userAuthenticator->authenticateUser($user, $authenticator, $request);
                //Send email to admin
                // Prepare the data
                $data = [
                    'to' => [
                        [
                            'Email' => "serviceclients@kbr-global.com",
                            'Name' => "Admin"
                        ]
                    ],
                    'templateId' => 3,
                    'params' => [
                        'email_user' => $user->getEmailCanonical()
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

                return $this->redirectToRoute('app_registration_confirmation_email');
            } else {
                $this->addFlash('register_recaptcha_error', '');
            }

        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    //Redirection for old register urls

    /**
     * @Route("/{_locale}/register", name="old_register_redirection")
     * @param Request $request
     * @return RedirectResponse
     */
    public function old_register_redirection(Request $request)
    {
        return $this->redirectToRoute('app_register');
    }

    /**
     * @Route("/verify/email", name="app_verify_email")
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @param MailerInterface $mailer
     * @return Response
     */
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = $entityManager->getRepository(User::class)->find($request->query->get('id'));
        if (!$user) {
            throw $this->createNotFoundException();
        }
        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_register');
        }

        $this->storePaymentData($entityManager, $mailer, $user);

        if ($this->getParameter('environnement') == 'prod') {
            $role = 'Abonne';
            $kyc = $this->service_manager->getUserStringDataValue($user->getId(), 'kyc_doc_status');
            if ($kyc != '') {
                $kyc = 'Oui';
            } else {
                $kyc = 'Non';
            }
            if (in_array('ROLE_SOCIETE', $user->getRoles())) {
                $role = 'Societe';
            } elseif (in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles())) {
                $role = 'Autoent';
            }
        }
        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('info', $translator->trans('register.verifier-email-succes', [], 'security'));
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/registration/confirmation_email", name="app_registration_confirmation_email")
     */
    public function appRegistrationConfirmationEmail(): Response
    {
        return $this->render('registration/check_email.html.twig');
    }

    /**
     * Données supplémentaires, Compte mangopay et email admin
     * @param EntityManagerInterface $entityManager
     * @param MailerInterface $mailer
     * @param User $user
     * @return void
     */
    public function storePaymentData(EntityManagerInterface $entityManager, MailerInterface $mailer, User $user): void
    {
        //Compte mangopay
        $userType = null;
        $description = '';
        $legalPersonType = null;
        if (in_array('ROLE_ABONNE', $user->getRoles())) {
            $userType = 'ROLE_ABONNE';
        }
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles())) {
            $userType = 'ROLE_AUTO_ENTREPRENEUR';
        }
        if (in_array('ROLE_SOCIETE', $user->getRoles())) {
            $userType = 'ROLE_SOCIETE';
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

        $stripeAccount = '';
        $stripePerson = null;
        $accountToken = '';
        $stripePersonToken = null;
        $data = $this->getDataToUpdateMangopayUser($user->getId(), $user->getEmailCanonical());

    }

    public function getDataToUpdateMangopayUser($id, $email)
    {
        // Define all the keys you need to fetch
        $keys = [
            'first_name', 'last_name', 'telephone', 'sexe', 'billing_email', 'residenceCountry',
            'nationalityCountry', 'mp_user_id_sandbox', 'bdaytime', 'numeroNomRue_domicile',
            'pays_domicile', 'codePostal_domicile',
            'ville_domicile', 'region_domicile', 'numeroNomRue_livraison', 'pays_livraison',
            'codePostal_livraison', 'ville_livraison', 'region_livraison',
            //Compagny Datas
            'siret', 'tva'
        ];

        // Fetch all metadata for the user in a single query
        $userMetadata = $this->service_manager->getUserMetadata($id, $keys);

        // Map the metadata to the desired output structure
        $data = [
            "firstname" => $userMetadata['first_name'] ?? '',
            "lastname" => $userMetadata['last_name'] ?? '',
            "phone" => $userMetadata['telephone'] ?? '',
            "sexe" => isset($userMetadata['sexe']) ? ($userMetadata['sexe'] == 'femme' ? 'female' : 'male') : '',
            "user_email" => $email,
            "countryOfResidence" => $userMetadata['residenceCountry'] ?? '',
            "nationality" => $userMetadata['nationalityCountry'] ?? '',
            "mpAccount" => $userMetadata['mp_user_id_sandbox'] ?? '',
            "birthday" => $userMetadata['bdaytime'] ?? '',
            "user_address_1" => $userMetadata['numeroNomRue_domicile'] ?? '',
            "user_country" => $userMetadata['pays_domicile'] ?? '',
            "user_postcode" => $userMetadata['codePostal_domicile'] ?? '',
            "user_city" => $userMetadata['ville_domicile'] ?? '',
            "region_domicile" => $userMetadata['region_domicile'] ?? '',
            "user_address_1_livraison" => $userMetadata['numeroNomRue_livraison'] ?? '',
            "user_country_livraison" => $userMetadata['pays_livraison'] ?? '',
            "user_postcode_livraison" => $userMetadata['codePostal_livraison'] ?? '',
            "user_city_livraison" => $userMetadata['ville_livraison'] ?? '',
            "region_livraison" => $userMetadata['region_livraison'] ?? '',
            //Company Datas
            "siret" => $userMetadata['siret'] ?? '',
            "tva" => $userMetadata['tva'] ?? ''
        ];

        // Fallback for billing email
        if (empty($data["billing_email"])) {
            $data["billing_email"] = $data["user_email"];
        }

        return $data;
    }

    /**
     * Handles Stripe account and person creation.
     * @param $userType
     * @param $data
     * @param $userId
     * @return mixed|null
     */
    private function handleStripeAccountCreation($userType, $data, $userId)
    {
        $accountToken = $this->payment->createStripeAccountToken($userType, $data);
        if (empty($accountToken['id'])) return null;

        // 🔹 Create Stripe Account from Token
        $stripeAccount = $this->payment->createStripeUserFromToken($accountToken['id']);
        if (empty($stripeAccount['id'])) return null;

        $this->service_manager->updateUserMeta($userId, 'mp_user_id_sandbox', $stripeAccount['id']);
        $this->payment->updateStripeUser($stripeAccount['id'], $userType, $data);

        // 🔹 Create Stripe Person if required
        if($userType != 'ROLE_ABONNE'){
            $stripePersonToken = $this->payment->createStripePersonToken($data);
            if (!empty($stripePersonToken['id'])) {
                $stripePerson = $this->payment->createStripePersonFromToken($stripeAccount['id'], $stripePersonToken['id']);
                if (!empty($stripePerson['id'])) {
                    $this->service_manager->updateUserMeta($userId, 'stripe_person_user', $stripePerson['id']);
                    $this->payment->updateStripePerson($stripeAccount['id'], $stripePerson['id'], $data);
                }
            }
        }
        return $stripeAccount;
    }

}
