<?php

namespace App\Security;

use App\Entity\ClinicUsers;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class ClinicsAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'clinics_login';

    private UrlGeneratorInterface $urlGenerator;
    private $encryptor;
    private $em;

    public function __construct(UrlGeneratorInterface $urlGenerator, Encryptor $encryptor, EntityManagerInterface $em)
    {
        $this->urlGenerator = $urlGenerator;
        $this->encryptor = $encryptor;
        $this->em = $em;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $hashedEmail = md5($email);
        $clinicUser = $this->em->getRepository(ClinicUsers::class)->findOneBy([
            'hashedEmail' => $hashedEmail,
        ]);
        $username = '';

        if($clinicUser != null) {

            $username = $clinicUser->getEmail();
        }

        $request->getSession()->set(Security::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // For example:
        //return new RedirectResponse($this->urlGenerator->generate('some_route'));
        return new RedirectResponse($this->urlGenerator->generate('clinic_orders_list', [
            'clinic_id' => $token->getUser()->getClinic()->getId()
        ]));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
