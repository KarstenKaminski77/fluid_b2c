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
     * @Route("/distributors/login", name="distributor_login")
     */
    public function distributorLogin(AuthenticationUtils $authenticationUtils, Request $request, AuthorizationChecker $checker): Response
    {
        if (true === $checker->isGranted('ROLE_DISTRIBUTOR')) {

            $distributor_id = $this->getUser()->getDistributor()->getId();

            header('Location: '. $this->getParameter('app.base_url') . '/distributors/orders/' . $distributor_id);
//            $this->redirectToRoute('clinic_orders_list',[
//                'clinic_id' => $clinic_id
//            ]);

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
     * @Route("/clinics/login", name="clinics_login")
     */
    public function clinicLogin(AuthenticationUtils $authenticationUtils, Request $request, AuthorizationChecker $checker): Response
    {
        if (true === $checker->isGranted('ROLE_CLINIC'))
        {
            $clinicId = $this->getUser()->getClinic()->getId();

            return $this->redirectToRoute('clinic_orders_list',[
                'clinic_id' => $clinicId
            ]);
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
     * @Route("/manufacturers/login", name="manufacturer_login")
     */
    public function manufacturerLogin(AuthenticationUtils $authenticationUtils, Request $request, AuthorizationChecker $checker): Response
    {
        if (true === $checker->isGranted('ROLE_MANUFACTURER')) {

            header('Location: '. $this->getParameter('app.base_url') . '/manufacturers/analytics');

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
     * @Route("/admin/login", name="admin_login")
     */
    public function adminLogin(AuthenticationUtils $authenticationUtils, Request $request, AuthorizationChecker $checker): Response
    {
        if (true === $checker->isGranted('ROLE_ADMIN')) {

            header('Location: '. $this->getParameter('app.base_url') . '/admin/products/1');

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
            'user_type' => '',

        ]);
    }

    /**
     * @Route("/admin/logout", name="admin_logout")
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/clinics/logout", name="clinics_logout")
     */
    public function clinicLogout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/distributors/logout", name="distributors_logout")
     */
    public function distributorLogout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/manufacturers/logout", name="manufacturers_logout")
     */
    public function manufacturerLogout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/retail/logout", name="retail_logout")
     */
    public function retailLogout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}