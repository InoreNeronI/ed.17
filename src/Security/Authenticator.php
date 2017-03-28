<?php

namespace Security\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

/**
 * Class Authenticator
 *
 * @see http://activelamp.com/blog/development/shibboleth-authentication-in-symfony/
 */
class Authenticator extends AbstractGuardAuthenticator implements LogoutSuccessHandlerInterface
{
    /**
     * @var
     */
    private $idpUrl;

    /**
     * @var null
     */
    private $remoteUserVar;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator, $idpUrl, $remoteUserVar = null)
    {
        $this->idpUrl = $idpUrl;
        $this->remoteUserVar = $remoteUserVar ?: 'HTTP_EPPN';
        $this->urlGenerator = $urlGenerator;
    }

    protected function getRedirectUrl()
    {
        return $this->urlGenerator->generateUrl('shib_login');
    }

    /**
     * @param Request                 $request       The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     *
     * @return Response
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $redirectTo = $this->getRedirectUrl();
        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'You are not authenticated.',
                'redirect' => $redirectTo,
            ], Response::HTTP_FORBIDDEN);
        }

        return new RedirectResponse($redirectTo);
    }

    /**
     * @param Request $request
     *
     * @return mixed|null
     */
    public function getCredentials(Request $request)
    {
        if (!$request->server->has($this->remoteUserVar)) {
            return;
        }

        $id = $request->server->get($this->remoteUserVar);

        if ($id) {
            return ['eppn' => $id];
        }

        return null;
    }

    /**
     * @param mixed                 $credentials
     * @param UserProviderInterface $userProvider
     *
     * @throws AuthenticationException
     *
     * @return UserInterface|null
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials['eppn']);
    }

    /**
     * @param mixed         $credentials
     * @param UserInterface $user
     *
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    /**
     * @param Request                 $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $redirectTo = $this->getRedirectUrl();
        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Authentication failed.',
                'redirect' => $redirectTo,
            ], Response::HTTP_FORBIDDEN);
        }

        return new RedirectResponse($redirectTo);
    }

    /**
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $providerKey The provider (i.e. firewall) key
     *
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    /**
     * @return bool
     */
    public function supportsRememberMe()
    {
        return false;
    }

    /**
     * @param Request $request
     *
     * @return Response never null
     */
    public function onLogoutSuccess(Request $request)
    {
        $redirectTo = $this->urlGenerator->generate('shib_logout', [
            'return' => $this->idpUrl.'/profile/Logout',
        ]);

        return new RedirectResponse($redirectTo);
    }
}
