<?php

namespace App\Controller;

use App\Entity\Addresses;
use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\Orders;
use App\Entity\RetailUsers;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AddressesController extends AbstractController
{
    private $em;
    private $pageManager;
    private $encryptor;
    const ITEMS_PER_PAGE = 10;

    public function __construct(EntityManagerInterface $em, PaginationManager $pageManager, Encryptor $encryptor)
    {
        $this->em = $em;
        $this->pageManager = $pageManager;
        $this->encryptor = $encryptor;
    }

    private function getAddresses($addresses, $module = 'clinic')
    {
        $request = Request::createFromGlobals();
        $path = $request->server->get('REQUEST_URI');
        $class = 'hidden';

        if(strstr($path, 'clinics'))
        {
            $class = '';
        }

        $response = '
        <div class="row pt-3">
            <div class="col-12 text-center mt-1 pt-3 pb-3">
                <h4 class="text-primary text-truncate">Manage Shipping Addresses</h4>
                <span class="d-none d-sm-inline mb-5 mt-2 text-center text-primary text-sm-start">
                    Add or remove shipping addresses from the list below.
                    <strong>A valid address is required for purchasing Fluid Commerce items and redeeming Fluid rewards.</strong>
                </span>
            </div>
            <div class="col-12 '. $class .'">
                <a 
                    href="#" 
                    class="align-middle text-primary nav-icon text-truncate float-end mb-2" 
                    data-bs-toggle="modal" 
                    data-bs-target="#modal_address"
                    id="address_new"
                >
                    <i class="fa-regular fa-square-plus fa-fw"></i>
                    <span class="ms-1">Create New</span>
                </a>
            </div>
        </div>';

        if(count($addresses) > 0)
        {
            $response .= '
            <div class="row d-none bg-light d-xl-flex border-right border-left border-top">
                <div class="col-9">
                    <div class="row">
                        <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                            Type
                        </div>
                        <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                            Telephone
                        </div>
                        <div class="col-md-8 pt-3 pb-3 text-primary fw-bold">
                            Address
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                </div>
            </div>
    
            <div id="address_list">';

            $i = 0;

            foreach ($addresses as $address)
            {
                $class = 'address-icon';
                $classBilling = 'address-icon';
                $topBorder = '';
                $i++;

                // Default Shipping Address
                if ($address->getIsDefault() == 1)
                {
                    $class = 'is-default-address-icon';
                }

                // Default Billing Address
                if($address->getIsDefaultBilling() == 1)
                {
                    $classBilling = 'is-default-address-icon';
                }

                if($address->getType() == 1)
                {
                    $type = 'Billing';
                }
                else
                {
                    $type = 'Shipping';
                }

                if($i == 1)
                {
                    $topBorder = 'border-top';
                }

                $response .= '
                <div class="row t-row border-0">
                    <div class="col-12 col-xl-9 col-cell border-left border-bottom border-bottom-sm-users border-right-sm-users '. $topBorder .'">
                        <div class="row">
                            <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary border-list pt-3 pb-3">Name</div>
                            <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                                ' . $type . '
                            </div>
                            <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary border-list pt-3 pb-3">Telephone</div>
                            <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                                ' . $this->encryptor->decrypt($address->getTelephone()) . '
                            </div>
                            <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary border-list pt-3 pb-3">Address</div>
                            <div class="col-8 col-md-10 col-xl-8 t-cell text-truncate border-list pt-3 pb-3">
                                ' . $this->encryptor->decrypt($address->getAddress()) . '
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-3 text-center text-sm-start border-right col-cell border-right border-bottom">
                        <div class="row">
                            <div class="col-12 col-xl-12 t-cell pt-3 pb-3 border-left-sm-users">
                                <a 
                                    href="" 
                                    class="float-end address_update" 
                                    data-address-id="' . $address->getId() . '" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modal_address"
                                    data-action="click->retail#getAddressEditModal"
                                >
                                    <i class="fa-solid fa-pen-to-square edit-icon"></i>
                                </a>
                                <a 
                                    href="" 
                                    class="delete-icon float-none float-sm-end open-delete-address-modal" 
                                    data-bs-toggle="modal" data-address-id="' . $address->getId() . '" 
                                    data-bs-target="#modal_address_delete"
                                    data-action="click->retail#getAddressDeleteModal"
                                >
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>';

                    if($type == 'Billing')
                    {
                        $response .= '
                        <a 
                            href="#" 
                            class="address_default_billing float-start float-sm-none" 
                            data-billing-address-id="' . $address->getId() . '"
                            data-action="click->retail#defaultBillingAddress"
                        >
                            <i class="fa-solid fa-star float-end ' . $classBilling . '"></i>
                        </a>';

                    }

                    if($type == 'Shipping')
                    {
                        $response .= '
                        <a 
                            href="#" class="address_default float-start float-sm-none" 
                            data-address-id="' . $address->getId() . '"
                            data-action="click->retail#defaultShippingAddress"
                        >
                            <i class="fa-solid fa-star float-end ' . $class . '"></i>
                        </a>';
                    }

                    $response .= '
                            </div>
                        </div>
                    </div>
                </div>';
            }

            $response .= '
                </div>
    
                <!-- Modal Manage Address -->
                <div class="modal fade" id="modal_address" tabindex="-1" aria-labelledby="address_delete_label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <form name="form_addresses" id="form_addresses" method="post" data-action="submit->retail#onAddressSubmit">
                                ' . $this->getAddressModal($module)->getContent() . '
                            </form>
                        </div>
                    </div>
                </div>
    
                <!-- Modal Delete Address -->
                <div class="modal fade" id="modal_address_delete" tabindex="-1" aria-labelledby="address_delete_label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <input type="hidden" value="" name="addresses_form[address_id]" id="address_id">
                            <div class="modal-header">
                                <h5 class="modal-title" id="address_delete_label">Delete Address</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12 mb-0">
                                        Are you sure you would like to delete this address? This action cannot be undone.
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">CANCEL</button>
                                <button 
                                    type="button" 
                                    class="btn btn-danger btn-sm" id="delete_address"
                                    data-action="click->retail#deleteAddress"
                                >DELETE</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Addresses -->';
        }
        else
        {
            $response .= '
            <div class="row border-left border-right border-top border-bottom bg-light">
                <div class="col-12 text-center mt-3 mb-3 pt-3 pb-3 text-center">
                    You don\'t have any addresses saved. 
                </div>
            </div>
            <!-- Modal Manage Address -->
            <div class="modal fade" id="modal_address" tabindex="-1" aria-labelledby="address_delete_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <form name="form_addresses" id="form_addresses" method="post">
                            ' . $this->getAddressModal($module)->getContent() . '
                        </form>
                    </div>
                </div>
            </div>';
        }

        return $response;
    }

    #[Route('/clinics/get-address-modal/{type}', name: 'get_address_modal')]
    public function getCheckoutAddressModal(Request $request): Response
    {
        $type = $request->get('type');

        if($request->request->get('retail'))
        {
            $retailUser = $this->getUser();
            $addresses = $this->em->getRepository(Addresses::class)->findBy([
                'retail' => $retailUser->getId(),
                'isActive' => 1,
                'type' => $type,
            ]);

            $response['modal'] = '
            <input type="hidden" name="addresses_form[is-retail]" value="true">
            <input type="hidden" name="addresses_form[is-clinic]" value="false">';
        }
        else
        {
            $clinic = $this->getUser()->getClinic();
            $addresses = $this->em->getRepository(Addresses::class)->findBy([
                'clinic' => $clinic->getId(),
                'isActive' => 1,
                'type' => $type
            ]);

            $response['modal'] = '
            <input type="hidden" name="addresses_form[is-retail]" value="false">
            <input type="hidden" name="addresses_form[is-clinic]" value="true">';
        }

        $deliveryType = 'Shipping';

        if($type == 1){

            $deliveryType = 'Billing';
        }

        $i = 0;
        $response['existing_shipping_addresses'] = '';

        foreach($addresses as $address)
        {
            $i++;
            $marginTop = '';

            if($i == 1){

                $marginTop = 'mt-3';
            }

            $response['existing_shipping_addresses'] .= '
            <div class="row '. $marginTop .'">
                <div class="col-12">
                    <input 
                        type="radio" 
                        name="address" 
                        class="btn-check existing-address" 
                        value="'. $address->getId() .'" 
                        id="address_'. $i .'" 
                        autocomplete="off"
                    >
                    <label class="btn btn-outline-primary alert alert-secondary w-100" for="address_'. $i .'">'.
                        $this->encryptor->decrypt($address->getAddress()) .'
                    </label>
                </div>
            </div>';
        }

        $response['modal'] .= '
        <input type="hidden" value="" name="addresses_form[address-id]" id="address_id">
        <div class="modal-header" id="modal_header_address">
            <h5 class="modal-title" id="address_modal_label">Create an Address</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body modal-body-address-new pb-0 mb-0">
            <div class="row mb-3">
            
                <!-- Address Type -->
                <div class="col-12 col-sm-4 mb-3">
                    <label class="info">Address Type</label>
                    <input type="text" class="form-control" value="'. $deliveryType .'" readonly>
                    <input 
                        type="hidden" 
                        name="addresses-form[type]"
                        id="address_type"
                        class="form-control"
                        value="'. $type .'">
                    <div class="hidden_msg" id="error_address_type">
                        Required Field
                    </div>
                </div>

                <!-- Clinic Name -->
                <div class="col-12 col-sm-4 mb-3">
                    <label class="info">Clinic Name</label>
                    <input
                        type="text"
                        name="addresses-form[clinic-name]"
                        id="address_clinic_name"
                        class="form-control"
                        value=""
                    >
                    <div class="hidden_msg" id="error_address_clinic_name">
                        Required Field
                    </div>
                </div>

                <!-- Telephone Number -->
                <div class="col-12 col-sm-4 mb-3">
                    <label class="info">Telephone</label>
                    <input 
                        type="text" 
                        name="addresses-mobile" 
                        id="address_mobile" 
                        class="form-control" 
                        value=""
                        data-action="keyup->retail-checkout#onKeyUpMobileNo"
                    >
                    <input
                        type="hidden"
                        name="addresses-form[telephone]"
                        id="address_telephone"
                        value=""
                    >
                    <input
                        type="hidden"
                        name="addresses-form[iso-code]"
                        id="address_iso_code"
                        value=""
                    >
                    <input
                        type="hidden"
                        name="addresses-form[intl-code]"
                        id="address_intl_code"
                        value=""
                    >
                    <div class="hidden_msg" id="error_address_telephone">
                        Required Field
                    </div>
                </div>

                <!-- Address Line 1 -->
                <div class="col-12 mb-3">
                    <label class="info">
                        Address
                    </label>
                    <span role="button" class="text-primary float-end d-sm-block" id="btn_map_checkout_'. strtolower($deliveryType) .'">
                        <img src="/images/google-maps.png" class="google-map-icon">
                        Find on Mapxxxxx
                    </span>
                    <textarea
                        name="addresses-form[address]"
                        id="address_line_1"
                        class="form-control"
                        rows="5"
                    ></textarea>
                    <div class="hidden_msg" id="error_address_line_1">
                        Required Field
                    </div>
                </div>
                
                <!-- Google Map -->
                <div class="col-12 hidden position-relative" id="address_map">
                    '. $this->render('frontend/clinics/map.html.twig')->getContent() .'
                </div>
            </div>
        </div>
        <div class="modal-body modal-body-address-existing hidden pb-0 mb-0 pt-0">
            '. $response['existing_shipping_addresses'] .'
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary w-sm-100 mb-3 mb-sm-0 w-sm-100" data-bs-dismiss="modal">CANCEL</button>
            <button type="submit" class="btn btn-primary w-sm-100 mb-sm-0 w-sm-100" id="btn_save_address">SAVE</button>
        </div>';

        return new JsonResponse($response);
    }

    public function getAddressModal($module): Response
    {

        $response = '
        <input type="hidden" value="" name="addresses-form[address-id]" id="address_id">
        <div class="modal-header" id="modal_header_address">
            <h5 class="modal-title" id="address_modal_label">Create an Address</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body modal-body-address-new pb-0 mb-0">
            <div class="row mb-3">
            
                <!-- Address Type -->
                <div class="col-12 col-sm-4 mb-3">
                    <label class="info">Address Type</label>
                    <select
                        name="addresses-form[type]"
                        id="address_type"
                        class="form-control"
                    >
                        <option value=""></option>
                        <option value="1" id="option_billing">Billing</option>
                        <option value="2" id="option_shipping">Shipping</option>
                    </select>
                    <div class="hidden_msg" id="error_address_type">
                        Required Field
                    </div>
                </div>

                <!-- '. $module .' Name -->
                <div class="col-12 col-sm-4 mb-3">
                    <label class="info">Clinic Name</label>
                    <input
                        type="text"
                        name="addresses-form['. $module .'-name]"
                        id="address_'. $module .'_name"
                        class="form-control"
                        value=""
                    >
                    <div class="hidden_msg" id="error_address_'. $module .'_name">
                        Required Field
                    </div>
                </div>

                <!-- Telephone Number -->
                <div class="col-12 col-sm-4 mb-3">
                    <label class="info">Telephone</label>
                    <input 
                        type="text" 
                        name="addresses-mobile" 
                        id="address_mobile" 
                        class="form-control" 
                        value=""
                    >
                    <input
                        type="hidden"
                        name="addresses-form[telephone]"
                        id="address_telephone"
                        value=""
                    >
                    <input
                        type="hidden"
                        name="addresses-form[iso-code]"
                        id="address_iso_code"
                        value=""
                    >
                    <input
                        type="hidden"
                        name="addresses-form[intl-code]"
                        id="address_intl_code"
                        value=""
                    >
                    <div class="hidden_msg" id="error_address_telephone">
                        Required Field
                    </div>
                </div>

                <!-- Address Line 1 -->
                <div class="col-12 mb-3">
                    <label class="info">
                        Address
                    </label>
                    <span 
                        role="button" 
                        class="text-primary float-end d-sm-block" 
                        id="btn_map"
                        data-action="click->retail#btnMap"
                    >
                        <img src="/images/google-maps.png" class="google-map-icon">
                        Find on Map
                    </span>
                    <textarea
                        name="addresses-form[address]"
                        id="address_line_1"
                        class="form-control"
                        rows="5"
                    ></textarea>
                    <div class="hidden_msg" id="error_address_line_1">
                        Required Field
                    </div>
                </div>
                
                <!-- Google Map -->
                <div class="col-12 hidden position-relative" id="address_map">
                    '. $this->render('frontend/clinics/map.html.twig')->getContent() .'
                </div>
            </div>
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary w-sm-100 mb-3 mb-sm-0 w-sm-100" data-bs-dismiss="modal">CANCEL</button>
            <button type="submit" class="btn btn-primary w-sm-100 mb-sm-0 w-sm-100" id="btn_save_address">SAVE</button>
        </div>';

        return new Response($response);
    }

    #[Route('/clinics/get-clinic-addresses', name: 'get_clinic_addresses')]
    public function getClinicAddressesAction(Request $request): Response
    {
        $permissions = json_decode($request->request->get('permissions'), true);

        if(!in_array(12, $permissions))
        {
            $html = '
            <div class="row mt-3 mt-md-5">
                <div class="col-12 text-center">
                    <i class="fa-solid fa-ban pe-2" style="font-size: 30vh; margin-bottom: 30px; color: #CCC;text-align: center"></i>
                </div>
            </div>
            <div class="row">
                <div class="col-12 text-center">
                    <h1>Access Denied</h1>

                        <p class="mt-4">
                            Your user account does not have permission to view the requested page.
                        </p>
                </div>
            </div>';

            $response = [
                'html' => $html,
                'pagination' => ''
            ];

            return new JsonResponse($response);
        }

        $clinic = $this->getUser()->getClinic();
        $addresses = $this->em->getRepository(Addresses::class)->getAddresses($clinic->getId());
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);
        $html = $this->getAddresses($results, 'clinic');

        $response = [
            'html' => $html,
            'pagination' => $pagination
        ];
        
        return new JsonResponse($response);
    }

    #[Route('/get-address', name: 'get_address')]
    public function getAddressAction(Request $request): Response
    {
        $response = '';

        if((int) $request->request->get('id') > 0) {

            $address = $this->em->getRepository(Addresses::class)->find($request->request->get('id'));

            $response = [

                'id' => $address->getId(),
                'clinic_name' => $this->encryptor->decrypt($address->getClinicName()),
                'telephone' => $this->encryptor->decrypt($address->getTelephone()),
                'address' => $this->encryptor->decrypt($address->getAddress()),
                'type' => $address->getType(),
                'iso_code' => $this->encryptor->decrypt($address->getIsoCode()),
                'intl_code' => $this->encryptor->decrypt($address->getIntlCode()),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/update-address', name: 'update_address')]
    public function updateAddressAction(Request $request): Response
    {
        // Billing Address = 1
        // Shipping Address = 2

        $data = $request->request->get('addresses-form');
        $isRetail = $data['is-retail'] ?? 0;
        $clinic = null;
        $retailUser = null;

        if($isRetail)
        {
            $retailUserId = $this->getUser()->getId();
            $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);

            $defaultBillingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
                'isActive' => 1,
                'type' => 1,
                'isDefaultBilling' => 1,
                'retail' => $retailUserId,
            ]);

            $defaultShippingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
                'isActive' => 1,
                'type' => 2,
                'isDefault' => 1,
                'retail' => $retailUserId,
            ]);
        }
        else
        {
            $clinicId = $this->getUser()->getClinic()->getId();
            $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);

            $defaultBillingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
                'isActive' => 1,
                'type' => 1,
                'isDefaultBilling' => 1,
                'clinic' => $clinicId,
            ]);

            $defaultShippingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
                'isActive' => 1,
                'type' => 2,
                'isDefault' => 1,
                'clinic' => $clinicId,
            ]);
        }

        $addressId = $data['address-id'] ?? 0;

        if($addressId == 0 || empty($addressId)){

            $clinicAddress = new Addresses();
            $flash = '<b><i class="fas fa-check-circle"></i> Address details successfully created.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $clinicAddress = $this->em->getRepository(Addresses::class)->find($addressId);
            $flash = '<b><i class="fas fa-check-circle"></i> Address successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $clinicAddress->setClinic($clinic);
        $clinicAddress->setRetail($retailUser);
        $clinicAddress->setType($data['type']);
        $clinicAddress->setClinicName($this->encryptor->encrypt($data['clinic-name']));
        $clinicAddress->setTelephone($this->encryptor->encrypt($data['telephone']));
        $clinicAddress->setAddress($this->encryptor->encrypt($data['address']));
        $clinicAddress->setIsDefault(0);
        $clinicAddress->setIsActive(1);
        $clinicAddress->setIsoCode($this->encryptor->encrypt($data['iso-code']));
        $clinicAddress->setIntlCode($this->encryptor->encrypt($data['intl-code']));

        if($defaultShippingAddress == null){

            $clinicAddress->setIsDefault(1);
        }

        if($defaultBillingAddress == null){

            $clinicAddress->setIsDefaultBilling(1);
        }

        $this->em->persist($clinicAddress);
        $this->em->flush();

        // Checkout Create New Address
        $checkoutAddress = '';
        $checkoutAddressId = '';
        if($request->request->get('checkout') != null){

            $orderId = $request->request->get('checkout');
            $order = $this->em->getRepository(Orders::class)->find($orderId);

            $order->setAddress($clinicAddress);

            $this->em->persist($order);
            $this->em->flush();

            $checkoutAddress = $this->encryptor->decrypt($clinicAddress->getAddress());
            $checkoutAddressId = $clinicAddress->getId();
        }

        if($isRetail)
        {
            $addresses = $this->em->getRepository(Addresses::class)->getRetailAddresses($retailUserId);
            $module = 'retail';
        }
        else
        {
            $addresses = $this->em->getRepository(Addresses::class)->getAddresses($clinicId);
            $module = 'clinic';
        }
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);

        $addresses = $this->getAddresses($results, $module);

        $response = [
            'flash' => $flash,
            'addresses' => $addresses,
            'checkout_address' => $checkoutAddress,
            'checkout_address_id' => $checkoutAddressId,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/retail/update-retail-address', name: 'update_retail_address')]
    public function updateRetailAddressAction(Request $request): Response
    {
        // Billing Address = 1
        // Shipping Address = 2

        $data = $request->request->get('addresses-form');
        $retailUserId = $this->getUser()->getId();
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);

        $defaultBillingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
            'isActive' => 1,
            'type' => 1,
            'isDefaultBilling' => 1,
            'retail' => $retailUserId,
        ]);

        $defaultShippingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
            'isActive' => 1,
            'type' => 2,
            'isDefault' => 1,
            'retail' => $retailUserId,
        ]);

        $addressId = $data['address-id'];

        if($data['address-id'] == 0 || empty($data['address-id'])){

            $retailAddress = new Addresses();
            $flash = '<b><i class="fas fa-check-circle"></i> Address successfully created.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $retailAddress = $this->em->getRepository(Addresses::class)->find($addressId);
            $flash = '<b><i class="fas fa-check-circle"></i> Address successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $retailAddress->setClinic(null);
        $retailAddress->setRetail($retailUser);
        $retailAddress->setType($data['type']);
        $retailAddress->setClinicName($this->encryptor->encrypt($data['retail-name']));
        $retailAddress->setTelephone($this->encryptor->encrypt($data['telephone']));
        $retailAddress->setAddress($this->encryptor->encrypt($data['address']));
        $retailAddress->setIsDefault(0);
        $retailAddress->setIsActive(1);
        $retailAddress->setIsoCode($this->encryptor->encrypt($data['iso-code']));
        $retailAddress->setIntlCode($this->encryptor->encrypt($data['intl-code']));

        if($defaultShippingAddress == null){

            $retailAddress->setIsDefault(1);
        }

        if($defaultBillingAddress == null){

            $retailAddress->setIsDefaultBilling(1);
        }

        $this->em->persist($retailAddress);
        $this->em->flush();

        $addresses = $this->em->getRepository(Addresses::class)->getRetailAddresses($retailUserId);
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);

        $addresses = $this->getAddresses($results, 'retail');

        $response = [
            'flash' => $flash,
            'addresses' => $addresses,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/address/default', name: 'clinic_address_default')]
    public function clinicDefaultAddress(Request $request): Response
    {
        $addressId = $request->request->get('id');
        $clinicId = $this->get('security.token_storage')->getToken()->getUser()->getClinic()->getId();
        $this->em->getRepository(Clinics::class)->getClinicDefaultAddresses($clinicId, $addressId);
        $addresses = $this->em->getRepository(Addresses::class)->getAddresses($clinicId);
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);
        $addresses = $this->getAddresses($results);

        $flash = '<b><i class="fas fa-check-circle"></i> Default address successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'addresses' => $addresses,
            'flash' => $flash,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/address/default-billing', name: 'clinic_billing_address_default')]
    public function clinicDefaultBillingAddress(Request $request): Response
    {
        $addressId = $request->request->get('id');
        $defaultAddress = $this->em->getRepository(Addresses::class)->find($addressId);
        $clinicId = $this->get('security.token_storage')->getToken()->getUser()->getClinic()->getId();

        $addresses = $this->em->getRepository(Addresses::class)->findBy([
            'clinic' => $clinicId
        ]);

        // Clear default
        foreach($addresses as $address){

            $address->setIsDefaultBilling(0);
            $this->em->persist($address);
        }

        $this->em->flush();

        $defaultAddress->setIsDefaultBilling(1);

        $this->em->persist($defaultAddress);
        $this->em->flush();

        $addresses = $this->em->getRepository(Addresses::class)->getAddresses($clinicId);
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);

        $addresses = $this->getAddresses($results);

        $flash = '<b><i class="fas fa-check-circle"></i> Default address successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'addresses' => $addresses,
            'flash' => $flash,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/address/delete', name: 'address_delete')]
    public function clinicDeleteAddress(Request $request): Response
    {
        $addressId = $request->request->get('id');
        $address = $this->em->getRepository(Addresses::class)->find($addressId);

        $address->setIsActive(0);

        $this->em->persist($address);
        $this->em->flush();

        $addresses = $this->em->getRepository(Addresses::class)->getAddresses($this->getUser()->getClinic()->getId());
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);

        $html = $this->getAddresses($results, 'retail');

        $flash = '<b><i class="fas fa-check-circle"></i> Address successfully deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'addresses' => $html,
            'flash' => $flash,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/address', name: 'find_address')]
    public function clinicFindAddress(Request $request): Response
    {
        return $this->render('frontend/clinics/map.html.twig');
    }

    #[Route('/retail/get-retail-addresses', name: 'get_retail_addresses')]
    public function getRetailAddressesAction(Request $request): Response
    {
        $pageId = $request->request->get('page_id') ?? 1;
        $retailUserId = $this->getUser()->getId();
        $addresses = $this->em->getRepository(Addresses::class)->getRetailAddresses($retailUserId);
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($pageId, $results);

        $html = $this->getAddresses($results, 'retail');

        $response = [
            'html' => $html,
            'pagination' => $pagination
        ];

        return new JsonResponse($response);
    }

    #[Route('/retail/address/default', name: 'retail_address_default')]
    public function retailDefaultAddress(Request $request): Response
    {
        $addressId = $request->request->get('id');
        $pageId = $request->request->get('page-id') ?? 1;
        $retailId = $this->getUser()->getId();
        $this->em->getRepository(Addresses::class)->getRetailDefaultAddresses($retailId, $addressId);
        $addresses = $this->em->getRepository(Addresses::class)->getRetailAddresses($retailId);
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($pageId, $results);
        $addresses = $this->forward('App\Controller\AddressesController::getRetailAddressesAction', [
            'page_id'  => $pageId
        ])->getContent();
        $addresses = json_decode($addresses, true);
        $flash = '<b><i class="fas fa-check-circle"></i> Default address successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'addresses' => $addresses['html'],
            'flash' => $flash,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/retail/address/default-billing', name: 'retail_billing_address_default')]
    public function retailDefaultBillingAddress(Request $request): Response
    {
        $addressId = $request->request->get('id');
        $defaultAddress = $this->em->getRepository(Addresses::class)->find($addressId);
        $retailId = $this->getUser()->getId();

        $addresses = $this->em->getRepository(Addresses::class)->findBy([
            'retail' => $retailId,
            'isActive' => 1
        ]);

        // Clear default
        foreach($addresses as $address){

            $address->setIsDefaultBilling(0);
            $this->em->persist($address);
        }

        $this->em->flush();

        $defaultAddress->setIsDefaultBilling(1);

        $this->em->persist($defaultAddress);
        $this->em->flush();

        $addresses = $this->em->getRepository(Addresses::class)->getRetailAddresses($retailId);
        $results = $this->pageManager->paginate($addresses[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);

        $addresses = $this->getAddresses($results, 'retail');

        $flash = '<b><i class="fas fa-check-circle"></i> Default address successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'addresses' => $addresses,
            'flash' => $flash,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    public function getPagination($pageId, $results)
    {
        $currentPage = $pageId;
        $lastPage = $this->pageManager->lastPage($results);
        $pagination = '';

        if(count($results) > 0) {

            $pagination .= '
            <!-- Pagination -->
            <div class="row">
                <div class="col-12">';

            if ($lastPage > 1) {

                $previousPage_no = $currentPage - 1;
                $url = '/clinics/addresses';
                $previousPage = $url;

                $pagination .= '
                <nav class="custom-pagination">
                    <ul class="pagination justify-content-center">
                ';

                $disabled = 'disabled';
                $dataDisabled = 'true';

                // Previous Link
                if ($currentPage > 1) {

                    $disabled = '';
                    $dataDisabled = 'false';
                }

                $pagination .= '
                <li class="page-item ' . $disabled . '">
                    <a class="address-pagination" aria-disabled="' . $dataDisabled . '" data-page-id="' . $currentPage - 1 . '" href="' . $previousPage . '">
                        <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline">Previous</span>
                    </a>
                </li>';

                $isActive = false;

                for ($i = 1; $i <= $lastPage; $i++) {

                    $active = '';

                    if ($i == (int)$currentPage) {

                        $active = 'active';
                        $isActive = true;
                    }

                    // Go to previous page if all records for a page have been deleted
                    if(!$isActive && $i == count($results)){

                        $active = 'active';
                    }

                    $pagination .= '
                    <li class="page-item ' . $active . '">
                        <a class="address-pagination" data-page-id="' . $i . '" href="' . $url . '">' . $i . '</a>
                    </li>';
                }

                $disabled = 'disabled';
                $dataDisabled = 'true';

                if ($currentPage < $lastPage) {

                    $disabled = '';
                    $dataDisabled = 'false';
                }

                $pagination .= '
                <li class="page-item ' . $disabled . '">
                    <a class="address-pagination" aria-disabled="' . $dataDisabled . '" data-page-id="' . $currentPage + 1 . '" href="' . $url . '">
                        <span class="d-none d-sm-inline">Next</span> <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>';

                if(count($results) < $currentPage){

                    $currentPage = count($results);
                }

                $pagination .= '
                        </ul>
                    </nav>
                    <input type="hidden" id="page_no" value="' . $currentPage . '">
                </div>';
            }
        }

        return $pagination;
    }
}
