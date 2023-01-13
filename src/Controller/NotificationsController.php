<?php

namespace App\Controller;

use App\Entity\Notifications;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationsController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('clinics/get-notification', name: 'get_notifications')]
    public function getNotifications(): Response
    {
        $response = '';

        if($this->getUser() != null) {

            $notifications = $this->em->getRepository(Notifications::class)->findByClinic($this->getUser()->getClinic());

            if(count($notifications) > 0){

                $i = 0;

                $response .= '
                <li>
                    <span class="notification-panel">';

                foreach($notifications as $notification){

                    $i++;
                    $mb = 'mb-3';

                    if($i == count($notifications)){

                        $mb = '';
                    }

                    $response .= '<div class="'. $mb .'">'. $notification->getNotification() .'</div>';
                }

                $response .= '
                    </span>
                </li>';

            } else {

                $response .= '<li><span class="notification-panel">You have no notifications</span></li>';
            }
        }

        return new JsonResponse($response);
    }

    #[Route('clinics/delete-notification', name: 'delete_notifications')]
    public function deleteNotifications(Request $request): Response
    {
        $notification = $this->em->getRepository(Notifications::class)->find($request->request->get('notification-id'));
        $type = $request->request->get('type');

        if ($type == 'distributor')
        {
            $notification->setIsReadDistributor(1);
            $this->em->persist($notification);
        }
        elseif ($type == 'clinic')
        {
            $notification->setIsRead(1);
            $this->em->persist($notification);
        }

        $this->em->flush();

        if ($notification != null && $notification->getIsRead() == 1 && $notification->getIsReadDistributor() == 1){

            $this->em->remove($notification);
            $this->em->flush();

            $flash = '<b><i class="fas fa-check-circle"></i> Notification successfully deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        }
        else
        {
            $flash = '<b><i class="fa-solid fa-circle-xmark"></i> An error occurred, please try again later.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $response = [
            'flash' => $flash,
            'notifications' => $this->getNotifications(),
        ];

        return new JsonResponse($response);
    }

    #[Route('/get-order-notifications', name: 'get_order_notification')]
    public function getOrderNotificationsAction(Request $request): Response
    {
        $businessId = (int) $request->get('id');
        $type = $request->get('type');
        $alerts = '';
        $i = 0;

        if($type == 'clinic')
        {
            $notifications = $this->em->getRepository(Notifications::class)->findBy([
                'clinic' => $businessId,
                'isActive' => 1,
                'isRead' => 0,
            ]);
        }

        else

        {
            $notifications = $this->em->getRepository(Notifications::class)->findBy([
                'distributor' => $businessId,
                'isActive' => 1,
                'isReadDistributor' => 0,
            ]);
        }

        if (is_array($notifications) && count($notifications) > 0)
        {
            foreach($notifications as $notification){

                $alerts .= '
            <li>
                <span 
                    class="notification-panel"
                    data-order-id="'. $notification->getOrders()->getId() .'"
                    data-notification-id="'. $notification->getId() .'"
                >';

                $i = 0;

                foreach($notifications as $notification){

                    $i++;
                    $mb = 'mb-3';

                    if($i == count($notifications)){

                        $mb = '';
                    }

                    $alerts .= '<div class="'. $mb .'">'. $notification->getNotification() .'</div>';
                }

                $alerts .= '
                </span>
            </li>';
            }
        }

        $response = [
            'alert' => $alerts,
            'count' => $i
        ];

        return new JsonResponse($response);
    }
}
