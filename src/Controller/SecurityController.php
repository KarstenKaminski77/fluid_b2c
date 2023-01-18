<?php

namespace App\Controller;

use App\Entity\Clinics;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Security\AuthorizationChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/retail/login", name="retail_login")
     */
    public function retailLogin(AuthenticationUtils $authenticationUtils, Request $request, AuthorizationChecker $checker): Response
    {
        if (true === $checker->isGranted('ROLE_RETAIL')) {

            header('Location: '. $this->getParameter('app.base_url') . '/retail/search');

            die();
        }

        $uri = explode('/', $request->getPathInfo());

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'csrf_token_intention' => 'authenticate',
            'user_type' => $uri[1],

        ]);
    }

    /**
     * @Route("/retail/logout", name="retail_logout")
     */
    public function retailLogout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}