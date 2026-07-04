<?php

namespace App\Controller;

use LogicException;
use App\Service\Recaptcha\Recaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/{_locale}/connexion", name="app_login")
     * @param AuthenticationUtils $authenticationUtils
     * @return Response
     */
    public function login(AuthenticationUtils $authenticationUtils, Recaptcha $recaptcha): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error,
            'environnement' => $this->getParameter('environnement'),
            'recaptcha_site_key' => $recaptcha->getSiteKey(),
            'recaptcha_enabled' => $recaptcha->shouldEnforce((string) $this->getParameter('environnement')),
            'recaptcha_action' => Recaptcha::ACTION_LOGIN,
        ]);
    }

    //Redirection for old login urls

    /**
     * @Route("/{_locale}/login", name="old_login_redirection")
     * @param Request $request
     * @return RedirectResponse
     */
    public function old_login_redirection(Request $request)
    {
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }



}
