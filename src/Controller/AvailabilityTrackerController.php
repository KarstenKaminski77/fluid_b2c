<?php

namespace App\Controller;

use App\Entity\AvailabilityTracker;
use App\Entity\ClinicCommunicationMethods;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\Products;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AvailabilityTrackerController extends AbstractController
{
    private $em;
    private $encryptor;

    public function __construct(EntityManagerInterface $em, Encryptor $encryptor) {
        $this->em = $em;
        $this->encryptor = $encryptor;
    }

    #[Route('/clinics/get-availability-tracker', name: 'get_availability_tracker')]
    public function getAvailabilityTrackerAction(Request $request): Response
    {
        $data = $request->request;
        $clinicId = $this->getUser()->getClinic()->getId();
        $productId = $data->get('product_id');

        $products = $this->em->getRepository(DistributorProducts::class)->findBy([
            'product' => $productId,
            'stockCount' => 0
        ]);
        $communicationMethods = $this->em->getRepository(ClinicCommunicationMethods::class)->findBy([
            'clinic' => $clinicId,
            'isActive' => 1
        ]);

        $savedTrackers = $this->em->getRepository(AvailabilityTracker::class)->getSavedTrackers($productId,$clinicId);
        $distributors = '';

        if(count($savedTrackers) > 0){

            foreach($savedTrackers as $tracker){

                $distributors .= $tracker->getDistributor()->getId() .',';
            }

            $distributors = trim($distributors,',');
        }

        $html = '
        <form id="form_availability_tracker" name="form_availability_tracker" method="post">
            <input type="hidden" name="product_id" value="'. $productId .'">
            <input type="hidden" name="availability_tracker_id" value="0">
            <h5 class="pb-3 pt-3">Availability Tracker</h5>';
        $html .= '
        <div class="row">
            <div class="col-12">
                <p>
                Create custom alerts when a backordered item comes back in stock. Set a notification
                for how you would like to be notified and which suppliers you would like to track.
                Once an item comes back in stock and you are notified, the tracker will automatically
                turn off. You can also view a list of all tracked items in your shopping list.
                Note: Fluid cannot track the availability of items that are drop shipped directly
                from the vendor.
                </p>
            </div>
        </div>';

        if(count($products) > 0) {

            $html .= '
            <div class="row">
                <div class="col-12">
                    <h6 class="text-primary pt-3 pb-3">How Would You Like To Be Notified?</h6>';

            if(count($communicationMethods) > 0){

                $i = 0;

                $html .= '<div class="row">';

                foreach($communicationMethods as $method){

                    $i++;

                    if($method->getCommunicationMethod()->getId() == 1){

                        $notification = $method->getCommunicationMethod()->getMethod();

                    } else {

                        $notification = $method->getSendTo();
                    }

                    $html .= '
                    <div class="col-2">
                        <input 
                            type="checkbox" 
                            value="'. $method->getId() .'"
                            class="btn-check" 
                            name="method[]" 
                            id="method_'. $i .'" 
                            autocomplete="off"
                        >
                        <label class="btn btn-sm btn-outline-primary w-100 text-truncate" for="method_'. $i .'">
                            '. $this->encryptor->decrypt($notification) .'
                        </label>
                    </div>';

                    if($i % 6 == 0 && $i != count($communicationMethods)){

                        $html .= '
                        </div>
                        <div class="row mb-4">';
                    }
                }

                $html .= '
                </div>
                <div class="row">
                    <div class="col-12 hidden_msg" id="error_at_methods">
                        Please select at least one communication method.
                    </div>
                </div>';


            } else {

                $html .= '
                <button type="button" class="btn btn-primary">
                    <i class="fa-solid fa-circle-plus me-2"></i>
                    CREATE NEW COMMUNICATION METHOD
                </button>
                ';
            }

            $html .= '<h6 class="text-primary pt-3 mt-4 pb-3">Which Suppliers Would You Like To Track?</h6>';
            $html .= '<div class="row">';
            $i = 0;

            foreach($products as $product) {

                $i++;

                $html .= '
                <div class="col-2">
                    <input type="checkbox" class="btn-check" name="distributor[]" value="'. $product->getDistributor()->getId() .'" id="btn_distributor_'. $i .'" autocomplete="off">
                    <label class="btn btn-sm btn-outline-primary w-100 text-truncate" for="btn_distributor_'. $i .'">
                        '. $this->encryptor->decrypt($product->getDistributor()->getDistributorName()) .'
                    </label>
                </div>';

                if($i % 6 == 0){

                    $html .= '
                        </div>
                        <div class="row mb-4">';
                }
            }

            $html .= '
            </div>
            <div class="row">
                    <div class="col-12 hidden_msg" id="error_at_distributors">
                        Please select at least one distributor.
                    </div>
                </div>';

            $html .= '
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <input type="submit" class="btn btn-primary float-end" value="ENABLE AVAILABILITY TRACKING">
                </div>
            </div>
            <div class="row mt-3 hidden" id="availability_tracker_row">
                <div class="col-12" id="availability_tracker_col">
                
                </div>
            </div>';

        } else {

            $html .= '
            <div class="row">
                <div class="col-12 mt-4 text-center">
                    There are no items to track. Tracking is only available for items that are currently out of stock.
                </div>
            </div>';
        }

        $html .= '</form>';

        $response = [
            'html' => $html,
            'list' => $this->getAvailabilityTrackers($productId),
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/create-availability-tracker', name: 'create_availability_tracker')]
    public function createAvailabilityTrackerAction(Request $request): Response
    {
        $data = $request->request;
        $methods = $data->get('method');
        $distributors = $data->get('distributor');
        $productId = $data->get('product_id');
        $clinic = $this->getUser()->getClinic();
        $product = $this->em->getRepository(Products::class)->find($productId);

        if($data->get('availability_tracker_id') == 0){

            foreach($distributors as $distributor){

                $distributorObj = $this->em->getRepository(Distributors::class)->find($distributor);

                foreach($methods as $method){

                    $availabilityTracker = new AvailabilityTracker();

                    $communicationMethod = $this->em->getRepository(ClinicCommunicationMethods::class)->find($method);

                    $availabilityTracker->setClinic($clinic);
                    $availabilityTracker->setProduct($product);
                    $availabilityTracker->setDistributor($distributorObj);
                    $availabilityTracker->setCommunication($communicationMethod);
                    $availabilityTracker->setIsSent(0);

                    $this->em->persist($availabilityTracker);
                    $this->em->flush();
                }
            }

            $flash = '<b><i class="fa-solid fa-circle-check"></i></i></b> Availability tracker create.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            //$availabilityTracker = $this->em->getRepository(AvailabilityTracker::class);

        }

        $response = [
            'flash' => $flash,
            'list' => $this->getAvailabilityTrackers($productId)
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/delete-availability-tracker', name: 'delete_availability_tracker')]
    public function deleteAvailabilityTrackerAction(Request $request): Response
    {
        $trackerId = $request->request->get('tracker_id');
        $tracker = $this->em->getRepository(AvailabilityTracker::class)->find($trackerId);
        $productId = $tracker->getProduct()->getId();
        $flash = '';

        if($tracker != null){

            $this->em->remove($tracker);
            $this->em->flush();

            $flash = '<b><i class="fa-solid fa-circle-check"></i></i></b> Availability tracker deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $html = $this->getAvailabilityTrackers($productId);

        $response = [
            'flash' => $flash,
            'html' => $html,
        ];

        return new JsonResponse($response);
    }

    private function getAvailabilityTrackers($productId)
    {
        $clinic = $this->getUser()->getClinic();
        $savedTrackers = $this->em->getRepository(AvailabilityTracker::class)->findBy([
            'product' => $productId,
            'clinic' => $clinic->getId(),
            'isSent' => 0,
        ]);

        $response = '';

        if(count($savedTrackers) > 0) {

            $response .= '
            <div id="availability_trackers">
                <div class="row d-none d-xl-flex ms-1 me-1 ms-md-0 me-md-0 mt-5">
                    <div class="col-4 t-header">
                        Communication Method
                    </div>
                    <div class="col-3 t-header">
                        Distributor
                    </div>
                    <div class="col-3 t-header">
                        Send To
                    </div>
                    <div class="col-2 t-header">
        
                    </div>
                </div>';

            $i = 0;

            foreach ($savedTrackers as $tracker) {

                $borderTop = '';
                $i++;

                if ($i == 1) {

                    $borderTop = 'style="border-top: 1px solid #d3d3d4"';
                }

                $col = 3;
                $sendTo = $tracker->getCommunication()->getCommunicationMethod()->getClinicCommunicationMethods()[0]->getSendTo();
                $communicationMethod = $tracker->getCommunication()->getId();

                if (empty($sendTo)) {

                    $col = 6;
                }

                if ($communicationMethod == 1) {

                    $sendTo = '';
                }

                $response .= '
                <div class="row t-row ms-1 me-1 ms-md-0 me-md-0"  ' . $borderTop . '>
                    <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary border-list text-truncate">Method</div>
                    <div class="col-8 col-sm-10 col-xl-4 t-cell text-truncate border-list">
                        ' . $tracker->getCommunication()->getCommunicationMethod()->getMethod() . '
                    </div>
                    <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary border-list text-truncate">Distributor</div>
                    <div class="col-8 col-sm-10 col-xl-' . $col . ' t-cell text-truncate border-list">
                        ' . $tracker->getDistributor()->getDistributorName() . '
                    </div>';

                if (!empty($sendTo)) {

                    $response .= '
                    <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary border-list text-truncate">Send To</div>
                    <div class="col-8 col-sm-10 col-xl-3 t-cell text-truncate border-list">
                        ' . $sendTo . '
                    </div>';
                }

                $response .= '
                    <div class="col-12 col-xl-2 t-cell text-truncate">
                        <a 
                            href="" 
                            class="delete-icon float-start float-sm-end availability-tracker-delete-icon" 
                            data-bs-toggle="modal" data-availability-tracker-id="' . $tracker->getId() . '" 
                            data-bs-target="#modal_availability_tracker_delete"
                        >
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>
                </div>';
            }

            $response .= '
                        </div>
                    </div>
            
                    <!-- Modal Delete Availability Tracker -->
                    <div 
                        class="modal fade" 
                        id="modal_availability_tracker_delete" 
                        tabindex="-1" 
                        aria-labelledby="availability_tracker_delete_label" 
                        aria-hidden="true"
                    >
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="availability_tracker_delete_label">Delete Availability Tracker</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-12 mb-0">
                                            Are you sure you would like to delete this availability tracker? This action cannot be undone.
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">CANCEL</button>
                                    <button 
                                        type="button" 
                                        class="btn btn-danger btn-sm communication-method-delete" 
                                        id="delete_tracker">DELETE</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

        return $response;
    }
}
