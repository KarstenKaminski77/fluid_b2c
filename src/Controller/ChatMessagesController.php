<?php

namespace App\Controller;

use App\Entity\ChatMessages;
use App\Entity\ChatParticipants;
use App\Entity\Clinics;
use App\Entity\Distributors;
use App\Entity\Notifications;
use App\Entity\Orders;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ChatMessagesController extends AbstractController
{
    private $em;
    private $encryptor;
    private $mailer;

    public function __construct(EntityManagerInterface $em, Encryptor $encryptor, MailerInterface $mailer)
    {
        $this->em = $em;
        $this->encryptor = $encryptor;
        $this->mailer = $mailer;
    }

    #[Route('/distributors/send-message', name: 'distributor_send_message')]
    #[Route('/clinics/send-message', name: 'clinic_send_message')]
    public function sendMessageAction(Request $request): Response
    {
        $data = $request->request;
        $message = (string) $data->get('message');
        $orderId = (int) $data->get('order_id');
        $isClinic = $data->get('clinic');
        $isDistributor = $data->get('distributor');
        $distributorId = $data->get('distributor_id');
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $distributorRepo = $this->em->getRepository(Distributors::class)->find($distributorId);
        $clinicRepo = $this->em->getRepository(Clinics::class)->find($order->getClinic()->getId());
        $dateSent = '';

        $chatMessage = new ChatMessages();

        $chatMessage->setOrders($order);
        $chatMessage->setDistributor($distributorRepo);
        $chatMessage->setMessage($message);
        $chatMessage->setIsDistributor($isDistributor);
        $chatMessage->setIsClinic($isClinic);

        $this->em->persist($chatMessage);
        $this->em->flush();

        $chatMessages = $this->em->getRepository(ChatMessages::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);

        $distributor = false;
        $clinic = false;

        if($isDistributor == 1){

            $distributor = true;
        }

        if($isClinic == 1){

            $clinic = true;
        }

        $messages = $this->getMessages($chatMessages, $dateSent,$distributor,$clinic)->getContent();

        // In app alert
        $notification = new Notifications();

        $notification->setClinic($clinicRepo);
        $notification->setDistributor($distributorRepo);
        $notification->setIsMessage(1);
        $notification->setIsTracking(0);
        $notification->setIsOrder(0);
        $notification->setIsRead(0);
        $notification->setIsReadDistributor(0);
        $notification->setIsActive(1);
        $notification->setOrders($order);

        $this->em->persist($notification);
        $this->em->flush();

        $message = '
        <table class="w-100">
            <tr>
                <td>
                    <a 
                        href="#"
                        data-order-id="'. $orderId .'"
                        data-distributor-id="'. $distributorId .'"
                        data-clinic-id="'. $clinicRepo->getId() .'"
                        data-notification-id="'. $notification->getId() .'"
                        class="order_notification_alert"
                    >
                        <span class="badge bg-success me-3">New Message</span>
                    </a>
                </td>
                <td>
                    <a 
                        href="#"
                        data-order-id="'. $orderId .'"
                        data-distributor-id="'. $distributorId .'"
                        data-clinic-id="'. $clinicRepo->getId() .'"
                        data-notification-id="'. $notification->getId() .'"
                        class="order_notification_alert"
                    >
                        PO No. '. $distributorRepo->getPoNumberPrefix() .'-'. $orderId .'
                    </a>
                </td>
                <td>
                    <a 
                        href="#" class="delete-notification" 
                        data-notification-id="'. $notification->getId() .'"
                        data-order-id="'. $orderId .'"
                        data-distributor-id="'. $distributorId .'"
                    >
                        <i class="fa-solid fa-xmark text-black-25 ms-3 float-end"></i>
                    </a>
                </td>
            </tr>
        </table>';

        $notification->setNotification($message);

        $this->em->persist($notification);
        $this->em->flush();

        return new JsonResponse($messages);
    }

    #[Route('/instant-message/notification', name: 'im_notification')]
    public function imNotificationAction(Request $request): Response
    {
        $data = $request->request;
        $message = (string) $data->get('message');
        $orderId = (int) $data->get('order_id');
        $isClinic = $data->get('clinic');
        $isDistributor = $data->get('distributor');
        $distributorId = $data->get('distributor_id');
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $distributorRepo = $this->em->getRepository(Distributors::class)->find($distributorId);
        $clinicRepo = $this->em->getRepository(Clinics::class)->find($order->getClinic()->getId());

        $distributor = false;
        $clinic = false;

        if($isDistributor == 1){

            $distributor = true;
        }

        if($isClinic == 1){

            $clinic = true;
        }

        // Send Email Notification
        $url = '';

        if($clinic)
        {
            $url = $this->getParameter('app.base_url') .'/clinics/order/'. $orderId .'/'. $distributorRepo->getId();
            $email = $this->encryptor->decrypt($distributorRepo->getEmail());
        }
        elseif($distributor)
        {
            $url = $this->getParameter('app.base_url') .'/distributors/order/'. $orderId;
            $email = $this->encryptor->decrypt($clinicRepo->getEmail());
        }
        else
        {
            return new JsonResponse(null);
        }

        $body = '<p>'. $message .'</p>';
        $body .= '<p><a href="'. $url .'">Click here</a> to view the conversation.</p>';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $body,
        ]);

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($email)
            ->subject('Fluid - New IM')
            ->html($html->getContent());

        $this->mailer->send($email);

        return new JsonResponse(null);
    }

    public function getMessages($chatMessages, $dateSent,$distributor,$clinic){

        $messages = '<div style="height: 300px;overflow-x: hidden; overflow-y: auto; padding-bottom: 15px" id="distributor_chat_inner">';

        foreach($chatMessages as $chat){

            $type = false;

            if($distributor){

                $type = $chat->getIsDistributor();

            } elseif($clinic){

                $type = $chat->getIsClinic();
            }

            if($type){

                $class = 'speech-bubble-right p-3 mt-2 mb-2 me-1 float-end';

            } else {

                $class = 'speech-bubble-left p-3 mt-2 mb-2 ms-1 float-start';
            }

            if($dateSent != $chat->getCreated()->format('D dS M')){

                $messages .= '
                <div class="row">
                    <div class="col-12 text-center mb-2 mt-2">
                        <span class="badge badge-light p-1">
                            '. $chat->getCreated()->format('D dS M') .'
                        </span>
                    </div>
                </div>';
            }

            $dateSent = $chat->getCreated()->format('D dS M');

            $messages .= '
            <div class="row ps-3" style="width: calc(100% - 5px)">
                <div class="col-12">
                    <span class="'. $class .'">
                        '. $chat->getMessage() .'
                        <div class="text-end pt-1">'. $chat->getCreated()->format('H:i') .'</div>
                    </span>
                </div>
            </div>';
        }

        $messages .= '
            <div 
                class="ms-3 snippet position-absolute" 
                data-title=".dot-pulse" 
                id="chat_pulse"
                style="left: 5px;bottom: 5px"
            >
                <div class="stage">
                    <div class="dot-pulse"></div>
                </div>
            </div>
        </div>';

        return new Response($messages);
    }

    #[Route('/message/is_typing', name: 'is_typing')]
    public function sendIsTypingMessageAction(Request $request): Response
    {
        $data = $request->request;
        $orderId = $data->get('order_id');
        $distributorId = $data->get('distributor_id');
        $isClinic = $data->get('is_clinic');
        $isDistributor = $data->get('is_distributor');
        $isTyping = $data->get('is_typing');

        $chatParticipants = $this->em->getRepository(ChatParticipants::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);

        if ($isDistributor == 1 && $distributorId > 0) {

            $chatParticipants->setDistributorIsTyping($isTyping);

        } elseif ($isClinic == 1 && $distributorId > 0) {

            $chatParticipants->setClinicIsTyping($isTyping);

        } else {

            return new JsonResponse(false);
        }

        $this->em->persist($chatParticipants);
        $this->em->flush();

        $response = [
            'is_typing' => $isTyping,
            'clinic_is_typing' => $chatParticipants->getClinicIsTyping(),
            'distributor_is_typing' => $chatParticipants->getDistributorIsTyping()
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/order/get-messages', name: 'get_messages')]
    public function distributorGetMessageAction(Request $request): Response
    {
        $clinic = $request->get('clinic');
        $distributor = $request->get('distributor');
        $distributorId = $request->get('distributor_id');
        $orderId = $request->get('order_id');
        $totalMessages = $request->get('total_messages');
        $isTyping = 0;
        $dateSent = '';
        $chatMessages = $this->em->getRepository(ChatMessages::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);

        $chatParticipants = $this->em->getRepository(ChatParticipants::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);

        if($clinic){

            $isTyping = $chatParticipants->getDistributorIsTyping();

        } elseif($distributor){

            $isTyping = $chatParticipants->getClinicIsTyping();

        }

        // Only refresh chat if new messages
        $messages = '';

        if($totalMessages < count($chatMessages)){

            $isClinic = false;
            $isDistributor = false;

            if($clinic == 1){

                $isClinic = true;
            }

            if($distributor == 1){

                $isDistributor = true;
            }

            $messages = $this->getMessages($chatMessages, $dateSent,$isDistributor,$isClinic)->getContent();
        }

        $response = [
            'is_typing' => $isTyping,
            'messages' => $messages,
            'totals' => $totalMessages .' - '. count($chatMessages)
        ];

        return new JsonResponse($response);
    }
}
