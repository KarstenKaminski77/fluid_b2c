<?php

namespace App\Controller;

use App\Entity\Distributors;
use App\Services\PaginationManager;
use Doctrine\Persistence\ManagerRegistry;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class DistributorsController extends AbstractController
{
    private $em;
    private $emRemote;
    const ITEMS_PER_PAGE = 10;
    private $pageManager;
    private $requestStack;
    private $encryptor;
    private $mailer;

    public function __construct(ManagerRegistry $em, PaginationManager $pagination, RequestStack $requestStack, Encryptor $encryptor, MailerInterface $mailer) {
        $this->em = $em->getManager('default');
        $this->emRemote = $em->getManager('remote');
        $this->pageManager = $pagination;
        $this->requestStack = $requestStack;
        $this->encryptor = $encryptor;
        $this->mailer = $mailer;
    }

    #[Route('/sellers', name: 'sellers_page')]
    public function sellersAction(Request $request): Response
    {
        $sellers = $this->emRemote->getRepository(Distributors::class)->findAll();

        return $this->render('frontend/sellers.html.twig', [
            'sellers' => $sellers,
        ]);
    }
}
