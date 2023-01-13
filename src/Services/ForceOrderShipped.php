<?php

namespace App\Services;

use App\Entity\Clinics;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\Notifications;
use App\Entity\Orders;
use App\Entity\OrderStatus;
use App\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ForceOrderShipped
{
    private $em;
    private $params;
    private $mailer;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params) {
        $this->em = $em;
        $this->params = $params;
    }

    public function forceOrderShipped($orderId)
    {
        $status = $this->em->getRepository(Status::class)->find(6);
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'orders' => $orderId,
        ]);

        $orderStatus->setStatus($status);

        $this->em->persist($orderStatus);
        $this->em->flush();
    }
}