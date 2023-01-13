<?php

namespace App\Controller;

use App\Entity\Banners;
use App\Entity\Products;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class HomePageController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/', name: 'home_page')]
    public function index(): Response
    {
        $banners = $this->em->getRepository(Banners::class)->findHomePage();
        $products = $this->em->getRepository(Products::class)->findByRand();

        foreach($products as $product){

            $per = strtolower($product->getForm());
            $from = '';

            if($product->getSize() > 1){

                $price = number_format($product->getUnitPrice() ?? 0.00 / $product->getSize(), 2);

                $product->setPriceFrom($price);
            }
        }

        return $this->render('frontend/index.html.twig', [
            'banners' => $banners,
            'products' => $products,
        ]);
    }

    #[Route('/contact-form', name: 'contact_form')]
    public function contactForm(Request $request, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $name = $data->get('name');
        $telephone = $data->get('telephone');
        $email = $data->get('email');
        $message = $data->get('message');

        $html = '
        <table border="0" cellpadding="8" cellspacing="0">
            <tr>
                <td colspan="2">
                    <h3>Fluid Enquiry</h3>
                </td>
            </tr>
            <tr>
                <td colspan="2">&nbsp;</td>
            </tr>
            <tr>
                <td>
                    <b>Date: </b>
                </td>
                <td>
                    '. date('Y-m-d H:i') .'
                </td>
            </tr>
            <tr>
                <td>
                    <b>Name: </b>
                </td>
                <td>
                    '. $name .'
                </td>
            </tr>
            <tr>
                <td>
                    <b>Telephone: </b>
                </td>
                <td>
                    '. $telephone .'
                </td>
            </tr>
            <tr>
                <td>
                    <b>Email: </b>
                </td>
                <td>
                    '. $email .'
                </td>
            </tr>
            <tr>
                <td valign="top">
                    <b>Message:</b>
                </td>
                <td>
                    '. $message .'
                </td>
            </tr>
        </table>
        ';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $html,
        ]);

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($this->getParameter('app.email_from'))
            ->addCc($email)
            ->subject('Fluid General Enquiry')
            ->html($html->getContent());

        $mailer->send($email);

        $flash = '<b><i class="fas fa-check-circle"></i> Enquiry successfully sent.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($flash);
    }

    #[Route('/error', name: 'frontend_error_500')]
    public function frontend500ErrorAction(Request $request): Response
    {
        return $this->render('bundles/TwigBundle/Exception/error500.html.twig', [
            'type' => 'frontend',
            'id' => 0,
        ]);
    }
}
