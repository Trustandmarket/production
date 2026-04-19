<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class VerifiedProfileAccessSubscriber implements EventSubscriberInterface
{
    private const RESTRICTED_ROUTES = [
        'profile_annonces',
        'profile_creerAnnonces',
        'profile_EditDraftAnnounce',
        'profile_deleteDraftAnnounce',
        'profile_annoncesTag',
        'profile_listeAnnoncesPagine',
        'profile_ajouter_annonce',
        'profile_editer_annonce',
        'profile_fournisseurs',
        'profile_fournisseursAbonne',
        'profile_app_switch',
        'profile_updateBillingProfileData',
        'profile_parameters',
        'profile_parameters_abonne',
        'profile_userNotificationData',
        'profile_updateUserPassword',
        'profile_calendrier_ajouter_date',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;
        if (!$user instanceof User || $user->isVerified()) {
            return;
        }

        $routeName = (string) $event->getRequest()->attributes->get('_route', '');
        if (!in_array($routeName, self::RESTRICTED_ROUTES, true)) {
            return;
        }

        if ($event->getRequest()->hasSession()) {
            $event->getRequest()->getSession()->getFlashBag()->add('warning', 'Email de connexion non vérifié.');
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('profile_dashboard', [
                '_locale' => $event->getRequest()->getLocale() ?: 'fr',
            ])
        ));
    }
}
