<?php

namespace App\Security;

use App\Entity\{Abonnement, OffreInterne, User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EmailVerifier
{
    private $verifyEmailHelper;
    private $mailer;
    private $entityManager;
    private $params;

    public function __construct(ParameterBagInterface $params, VerifyEmailHelperInterface $helper,
     MailerInterface $mailer, EntityManagerInterface $manager)
    {
        $this->verifyEmailHelper = $helper;
        $this->mailer = $mailer;
        $this->entityManager = $manager;
        $this->params = $params;
    }

    public function sendEmailConfirmation(string $verifyEmailRouteName, User $user): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            $user->getId(),
            $user->getEmailCanonical(),
            ['id' => $user->getId()]
        );

        $context['signedUrl'] = $signatureComponents->getSignedUrl();
        $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

        // Prepare the data
        $data = [
            'to' => [
                [
                    'email' => $user->getEmailCanonical(),
                    'name' => $user->getDisplayName()
                ]
            ],
            'templateId' => 1,
            'params' => [
                'url_activate' => $context['signedUrl']
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
    }

    /**
     * @param Request $request
     * @param User $user
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        // Validate the email confirmation token from the URI
        $this->verifyEmailHelper->validateEmailConfirmation($request->getUri(), $user->getId(), $user->getEmailCanonical());

        // Mark the user as verified and enable their account
        $user->setIsVerified(true);
        $user->setEnabled(1);

        // Retrieve the "gratuit" offer from the OffreInterne repository
        $forfait = $this->entityManager->getRepository(OffreInterne::class)->findOneBySlug('gratuit');

        // Create a new subscription and associate it with the retrieved offer
        $abonnement = new Abonnement();
        $abonnement->setOffre($forfait);
        $abonnement->setTarif(0); // Set the subscription tariff to 0
        $abonnement->setAbonnementActif(true); // Mark the subscription as active
        $abonnement->setUser($user); // Associate the subscription with the user

        // Persist the user entity in the database
        $this->entityManager->persist($user);

        // Persist the subscription only if the user has specific roles
        if (in_array('ROLE_SOCIETE', $user->getRoles()) || in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles())) {
            $this->entityManager->persist($abonnement);
        }

        // Save all changes to the database
        $this->entityManager->flush();
            $displayNameParts = explode(' ', $user->getDisplayName(), 2); // Split into at most 2 parts
            $firstName = $displayNameParts[0]; // The first part
            $lastName = isset($displayNameParts[1]) ? $displayNameParts[1] : ''; // The second part, or empty if not present
            $role = 'ROLE_ABONNE';
            if (in_array('ROLE_SOCIETE', $user->getRoles())) {
                $role = 'ROLE_SOCIETE';
            } elseif (in_array('ROLE_AUTO_ENTREPRENEUR', $user->getRoles())) {
                $role = 'ROLE_AUTO_ENTREPRENEUR';
            }
            
        if($this->params->get('environnement') == 'prod'){
            // Add the user to the Brevo directory using a cURL request
            $ch = curl_init();

            // Prepare the data payload for the Brevo API
            $data = [
                "email" => $user->getEmailCanonical(),
                "attributes" => [
                    "PRENOM" => $firstName, // Assuming user has a getFirstName() method
                    "NOM" => $lastName, // Assuming user has a getLastName() method
                    "PROFIL_TRUST" => $role,
                ],
                "listIds" => [7], // Replace with the actual list ID
                "emailBlacklisted" => false,
                "smsBlacklisted" => true,
                "updateEnabled" => true,
            ];

            // Configure the cURL options
            curl_setopt($ch, CURLOPT_URL, "https://api.brevo.com/v3/contacts");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            // Execute the request
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);
        }
    }
}
