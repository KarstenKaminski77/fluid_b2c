<?php

namespace App\Services;

use App\Entity\Clinics;
use App\Entity\Distributors;
use App\Entity\Notifications;
use App\Entity\Orders;
use App\Entity\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class OrderStatusManager
{
    private $em;
    private $params;
    private $mailer;
    private $encryptor;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params, MailerInterface $mailer, Encryptor $encryptor) {
        $this->em = $em;
        $this->params = $params;
        $this->mailer = $mailer;
        $this->encryptor = $encryptor;
    }

    public function updateOrderStatus() {

        $conn = $this->em->getConnection();

        $sql = "
        SELECT
            id,
            orders_id,
            distributor_id,
            modified
        FROM
            order_status
        WHERE
            status_id = 5;
        ";

        $stmt = $conn->executeQuery($sql);
        $result = $stmt->fetchAllAssociative();

        foreach($result as $res){

            $date_time = $res['modified'];
            $date = date('Y-m-d', strtotime($date_time . '+1 day'));
            $id = $res['id'];

            if($date == date('Y-m-d')){

                $hour = date('H', strtotime($date_time . '+1 hour'));
                $hour_now = date('H');

                if($hour == $hour_now){

                    $sql = "
                    UPDATE
                        order_status
                    SET
                        status_id = 6
                    WHERE
                        id = $id
                    ";

                    $conn->executeQuery($sql);

                    $order_id = $res['orders_id'];

                    $sql = "
                    UPDATE
                        order_items
                    SET
                        is_quantity_correct = 1
                    WHERE
                        orders_id = $order_id
                    ";

                    $conn->executeQuery($sql);

                    // Clinic in app notification
                    $order = $this->em->getRepository(Orders::class)->find($res['orders_id']);
                    $distributor = $this->em->getRepository(Distributors::class)->find($res['distributor_id']);
                    $clinic = $this->em->getRepository(Clinics::class)->find($order->getClinic()->getId());

                    $notification = new Notifications();

                    $notification->setClinic($order->getClinic());
                    $notification->setIsActive(1);
                    $notification->setIsRead(0);
                    $notification->setIsReadDistributor(0);
                    $notification->setOrders($order);
                    $notification->setDistributor($distributor);
                    $notification->setIsOrder(1);
                    $notification->setIsTracking(0);
                    $notification->setIsMessage(0);

                    $this->em->persist($notification);

                    $this->em->flush();

                    $message = '
                    <table class="w-100">
                        <tr>
                            <td>
                                <a 
                                    href="#"
                                    data-order-id="'. $order->getId() .'"
                                    data-distributor-id="'. $distributor->getId() .'"
                                    data-clinic-id="'. $clinic->getId() .'"
                                    class="order_notification_alert"
                                >
                                    <span class="badge bg-success me-3">Order Shipped</span>
                                </a>
                            </td>
                            <td>
                                <a 
                                    href="#"
                                    data-order-id="'. $order->getId() .'"
                                    data-distributor-id="'. $distributor->getId() .'"
                                    data-clinic-id="'. $clinic->getId() .'"
                                    class="order_notification_alert"
                                >
                                    PO No. '. $distributor->getPoNumberPrefix() .'-'. $order->getId() .'
                                </a>
                            </td>
                            <td>
                                <a 
                                    href="#" class="delete-notification" 
                                    data-notification-id="'. $notification->getId() .'"
                                    data-order-id="'. $order->getId() .'"
                                    data-distributor-id="'. $distributor->getId() .'"
                                >
                                    <i class="fa-solid fa-xmark text-black-25 ms-3 float-end"></i>
                                </a>
                            </td>
                        </tr>
                    </table>';

                    $notification->setNotification($message);

                    $this->em->persist($notification);
                    $this->em->flush();

                    // Email Notifications
                    $to = $this->encryptor->decrypt($clinic->getEmail());
                    $order_url = $this->params->get('app.base_url') . '/clinics/order/'. $order->getId() .'/'. $distributor->getId();

                    $html = '<p>Please <a href="'. $order_url .'">click here</a> in order to view the progress of your order.</p>';

                    $html = $this->emailFooter($html);

                    $email = (new Email())
                        ->from($this->params->get('app.email_from'))
                        ->addTo($to)
                        ->subject('Fluid Order - PO  '. $order->getOrderItems()[0]->getPoNumber())
                        ->html($html);

                    $this->mailer->send($email);
                }
            }
        }
    }

    public function emailFooter($html):string
    {
        $response = '
        <table style="border: none; width: 100%; padding: 10px; font-family: Arial, Helvetica, sans-serif" cellpadding="10">
            <tr>
                <td colspan="2">
                    '. $html .'
                    <br>
                    <p>Kind Regards,</p>
                    <p style="margin-bottom: 0">The Fluid Team</p>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 0">&nbsp;</td>
            </tr>
            <tr>
                <td style="width: 170px">
                    <img src="'. $this->params->get('app.base_url') .'/images/logo.png" style="width: 150px; height: auto">
                </td>
                <td>
                    <table>
                        <tr>
                            <td style="width: 30px; text-align: center">
                                <img src="'. $this->params->get('app.base_url') .'/images/icons/telephone.png" style="width: 22px">
                            </td>
                            <td>
                                +971 6 5395443
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 30px; text-align: center">
                                <img src="'. $this->params->get('app.base_url') .'/images/icons/email.png" style="width: 20px">
                            </td>
                            <td>
                                info@fluid.vet
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 30px; text-align: center">
                                <img src="'. $this->params->get('app.base_url') .'/images/icons/web.png" style="width: 18px">
                            </td>
                            <td>
                                <a href="https://wwwfluid.vet">www.fluid.vet</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="border-top: solid 1px #ccc">
                    <p style="padding-top: 10px">
                        <a href="" style="margin-right: 5px; text-decoration: none;">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/facebook.png" style="width: 30px;">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/linkedin.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/tiktok.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/twitter.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/instagram.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/youtube.png" style="width: 30px">
                        </a>
                    </p>
                </td>
            </tr>
        </table>';

        return $response;
    }
}