<?php

namespace App\Security;

use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Entity\User;
use App\Service\Recaptcha\Recaptcha;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private $urlGenerator;
    private $requestStack;
    private $service_manager;
    private $entityManager;
    private $recaptcha;
    private $params;

    public function __construct(UrlGeneratorInterface $urlGenerator, ServiceManager $service_manager, RequestStack $requestStack,
                                ParameterBagInterface $params,EntityManagerInterface $entityManager,Recaptcha $recaptcha)
    {
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->recaptcha = $recaptcha;
        $this->params = $params;
    }

    public function authenticate(Request $request): Passport
    {
        $emailCanonical = $request->request->get('email_canonical', '');
        if($this->params->get('environnement') == 'rec' || $this->params->get('environnement') == 'prod'){
            $recaptcha = $this->recaptcha->create_assessment('6Ld5EEUpAAAAAOcwEkhPcx1fjKi0BUggL3jqUpPJ', $request->get('g-recaptcha-response'), 'sym-trust-adresse', 'TRUST_LOGIN');
            $this->requestStack->getSession()->set(Security::LAST_USERNAME, $emailCanonical);
            if($recaptcha['response']){
                return new Passport(
                    new UserBadge($emailCanonical),
                    new PasswordCredentials($request->request->get('password', '')),
                    [
                        new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                        new RememberMeBadge(),
                    ]
                );
            }else{
                throw new CustomUserMessageAccountStatusException($recaptcha['message']);
            }
        }else{
            return new Passport(
                new UserBadge($emailCanonical),
                new PasswordCredentials($request->request->get('password', '')),
                [
                    new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                    new RememberMeBadge(),
                ]
            );
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email_canonical' => $request->request->get('email_canonical', '')]);
        $avatar = '';
        $avatars = null;
        if($user){
            $avatars = $this->service_manager->readUserMeta($user, 'basic_user_avatar');
            if ($avatars && $avatars->getMetaValue()) {
                $img = @unserialize($avatars->getMetaValue());
                $avatar = end($img);
                $this->requestStack->getSession()->set('avatar', $avatar);
            }
        }
        if($request->get('_target_path')){
            return new RedirectResponse($request->get('_target_path'));
        }
        elseif ($targetPath = $this->getTargetPath($this->requestStack->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }
        return new RedirectResponse($this->urlGenerator->generate('home'));
        throw new Exception('TODO: provide a valid redirect inside '.__FILE__);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
