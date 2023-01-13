<?php

namespace App\Services;

use App\Entity\Clinics;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\Notifications;
use App\Entity\Orders;
use App\Entity\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class UpdateInventory
{
    private $em;
    private $params;
    private $mailer;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params, MailerInterface $mailer) {
        $this->em = $em;
        $this->params = $params;
        $this->mailer = $mailer;
    }

    public function updateInventory($distributorId)
    {
        $filePath = __DIR__ .'/../../public/csv/distributors/';
        $fileName = 'inventory-'. $distributorId .'.csv';
        $i = 0;

        if(($fp = fopen($filePath . $fileName, "r")) !== FALSE)
        {
            while (($row = fgetcsv($fp)) !== FALSE)
            {
                if($i > 0)
                {
                    $distributorProductId = (int)$row[0];
                    $stockLevel = (int) $row[3];
                    $unitPrice = (float) $row[4];
                    $distributorProduct = $this->em->getRepository(DistributorProducts::class)->find($distributorProductId);

                    if ($distributorProduct != null)
                    {
                        $distributorProduct->setStockCount($stockLevel);
                        $distributorProduct->setUnitPrice($unitPrice);

                        $this->em->persist($distributorProduct);
                    }
                }

                $i++;
            }

            $this->em->flush();

            fclose($fp);
        }
    }
}