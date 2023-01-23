<?php

namespace App\Controller;

use App\Entity\Addresses;
use App\Entity\ApiDetails;
use App\Entity\Baskets;
use App\Entity\Clinics;
use App\Entity\Distributors;
use App\Entity\OrderItems;
use App\Entity\Orders;
use App\Entity\OrderStatus;
use App\Entity\ProductImages;
use App\Entity\Products;
use App\Entity\Status;
use App\Services\PaginationManager;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Snappy\Pdf;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class OrdersController extends AbstractController
{
    private $em;
    private $emRemote;
    private $mailer;
    private $pageManager;
    private $requestStack;
    private $encryptor;
    const ITEMS_PER_PAGE = 6;

    public function __construct(
        ManagerRegistry $em, MailerInterface $mailer, Encryptor $encryptor ,
        PaginationManager $pagination, RequestStack $requestStack)
    {
        $this->em = $em->getManager('default');
        $this->emRemote = $em->getManager('remote');
        $this->mailer = $mailer;
        $this->pageManager = $pagination;
        $this->requestStack = $requestStack;
        $this->encryptor = $encryptor;
    }

    #[Route('/retail/checkout/options', name: 'checkout_options_retail')]
    public function getCheckoutOptionsAction(Request $request): Response
    {
        $requiresAuth = $request->request->get('require-auth');
        $clinic = $this->emRemote->getRepository(Clinics::class)->find($this->getUser()->getClinicId());

        if($requiresAuth == null)
        {
            $permissions = json_decode($request->request->get('permissions'), true);
            $resp = $this->accessDeniedAction($permissions, 3);

            if($resp != false)
            {
                return new JsonResponse($resp);
            };
        }

        $basketId = $request->request->get('basket_id') ?? $request->request->get('basket-id');
        $order = $this->em->getRepository(Orders::class)->findOneBy([
            'basket' => $basketId,
        ]);
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);

        $user = $this->getUser();
        $retail = $this->getUser();

        $shippingAddresses = $this->em->getRepository(Addresses::class)->findBy([
            'retail' => $user->getId(),
            'isActive' => 1,
            'type' => 2
        ]);

        $defaultAddress = $this->em->getRepository(Addresses::class)->findOneBy([
                'retail' => $user->getId(),
                'isDefault' => 1,
                'type' => 2,
                'isActive' => 1,
        ]);

        $defaultBillingAddress = $this->em->getRepository(Addresses::class)->findOneBy([
            'retail' => $user->getId(),
            'isDefaultBilling' => 1,
            'type' => 1,
            'isActive' => 1,
        ]);

        if($defaultAddress != null)
        {
            $response['default_address_id'] = $defaultAddress->getId();
        }
        else
        {
            $response['default_address_id'] = '';
        }

        if($defaultBillingAddress != null)
        {
            $response['default_billing_address_id'] = $defaultBillingAddress->getId();
        }
        else
        {
            $response['default_billing_address_id'] = '';
        }

        // Create / update orders
        if($order == null)
        {
            $order = new Orders();
        }

        $deliveryFee = 0;
        $subTotal = $basket->getTotal();
        $email = $this->encryptor->decrypt($user->getEmail());
        $tax = 0;

        $order->setClinicId($clinic->getId());
        $order->setBasket($basket);
        $order->setRetail($retail);
        $order->setStatus('checkout');
        $order->setDeliveryFee($deliveryFee);
        $order->setSubTotal($subTotal);
        $order->setTax($tax);
        $order->setTotal($deliveryFee + $subTotal + $tax);
        $order->setEmail($this->encryptor->encrypt($email));

        $this->em->persist($order);
        $this->em->flush();

        $response['order_id'] = $order->getId();
        $orderItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $order->getId(),
        ]);
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findBy([
            'ordersId' => $order->getId(),

        ]);

        // Remove any previous items
        if(count($orderItems) > 0)
        {
            foreach($orderItems as $orderItem)
            {
                $this->em->remove($orderItem);
            }
        }

        // Remove previous status
        if(count($orderStatus) > 0)
        {
            foreach($orderStatus as $status)
            {
                $this->em->remove($status);
            }
        }

        // Create new order items
        if(count($basket->getBasketItems()) > 0)
        {
            foreach($basket->getBasketItems() as $basketItem)
            {
                // Generate PO prefix if one isn't yet set
                $prefix = $clinic->getPoNumberPrefix();
                $name = $clinic->getClinicName();

                if($prefix == null)
                {
                    $words = preg_split("/\s+/", $this->encryptor->decrypt($name));
                    $prefix = '';

                    foreach($words as $word)
                    {

                        $prefix .= substr(ucwords($word), 0, 1);
                    }

                    $basket->getClinic()->setPoNumberPrefix($prefix);
                    $this->em->persist($basket);
                }

                $orderItems = new OrderItems();
                $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
                $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());

                $orderItems->setOrders($order);
                $orderItems->setDistributorId($basketItem->getDistributorId());
                $orderItems->setProductId($basketItem->getProductId());
                $orderItems->setUnitPrice($basketItem->getUnitPrice());
                $orderItems->setQuantity($basketItem->getQty());
                $orderItems->setQuantityDelivered($basketItem->getQty());
                $orderItems->setTotal($basketItem->getTotal());
                $orderItems->setName($basketItem->getName());
                $orderItems->setPoNumber($prefix .'-'. $order->getId());
                $orderItems->setOrderPlacedBy($this->encryptor->encrypt($firstName .' '. $lastName));
                $orderItems->setIsAccepted(0);
                $orderItems->setIsRenegotiate(0);
                $orderItems->setIsCancelled(0);
                $orderItems->setIsConfirmedDistributor(0);
                $orderItems->setIsQuantityCorrect(0);
                $orderItems->setIsQuantityInCorrect(0);
                $orderItems->setIsQuantityAdjust(0);
                $orderItems->setIsAcceptedOnDelivery(1);
                $orderItems->setIsRejectedOnDelivery(0);
                $orderItems->setStatus('Pending');
                $orderItems->setItemId($basketItem->getItemId());

                $this->em->persist($orderItems);

                // Order Status
                $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
                    'ordersId' => $order->getId(),
                ]);
                if($orderStatus == null)
                {
                    $status = $this->em->getRepository(Status::class)->find(2);

                    $orderStatus = new OrderStatus();

                    $orderStatus->setOrdersId($order->getId());
                    $orderStatus->setDistributorId($basketItem->getDistributorId());
                    $orderStatus->setStatus($status);

                    $this->em->persist($orderStatus);
                }
            }

            $this->em->flush();
        }

        $purchaseOrders = $this->em->getRepository(Orders::class)->find($order->getId());
        $plural = '';

        if(count($purchaseOrders->getOrderItems()) > 1){

            $plural = 's';
        }

        $response['header'] = '
        <h4 class="text-primary">Fluid Checkout</h4>
        <span class="text-primary">
            Select shipping and payment options
        </span>';

        $response['body'] = '
        <form 
            id="form_checkout_options" 
            name="form_checkout_options" 
            method="post"
            data-action="submit->retail-checkout#onSubmitCheckoutOptions"
        >
            <input type="hidden" name="order-id" value="'. $order->getId() .'">
            <input type="hidden" name="type" value="" id="address_type">
            <div class="row mt-4">
                <div class="col-12">
                    <h5 class="text-truncate">Fluid Account</h5>
                    <div class="alert alert-secondary" role="alert">
                        <div class="row border-bottom-dashed border-dark mb-3 pb-3">
                            <div class="col-6 text-truncate">
                                Account ID
                            </div>
                            <div class="col-6 text-end text-truncate">
                                '. $user->getId() .'
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 text-truncate">
                                Name
                            </div>
                            <div class="col-6 text-end text-truncate">
                                '. $this->encryptor->decrypt($this->getUser()->getFirstName())
                                .' '.
                                $this->encryptor->decrypt($this->getUser()->getLastName()) .'
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- PO Number -->
            <div class="row mt-3">
                <div class="col-12 text-truncate">
                    <h5 class="text-truncate">PO Number'. $plural .'</h5>
                    <div class="alert alert-secondary" role="alert">
                        <div class="row border-dark">
                            <div class="col-6 text-truncate">
                                ' . $this->encryptor->decrypt($clinic->getClinicName()) . '
                            </div>
                            <div class="col-6 text-end text-truncate">
                                ' . $clinic->getPoNumberPrefix() . '-' . $order->getId() . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Email Address -->
            <div class="row mt-3">
                <div class="col-12 text-truncate">
                    <h5 class="text-truncate">Confirmation Email*</h5>
                    <input 
                        type="email" 
                        name="confirmation_email"
                        id="confirmation_email"
                        class="form-control alert alert-secondary" 
                        value="'. $email .'"
                    >
                </div>
                <div class="hidden_msg" id="error_confirmation_email">
                    Required Field
                </div>
            </div>
            <!-- Shipping Address -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="row">
                        <div class="col-6 text-truncate">
                            <h5 class="text-truncate">Shipping Address*</h5>
                        </div>
                        <div class="col-6 text-end text-truncate">
                            <a 
                                href="" class="text-truncate" 
                                data-bs-toggle="modal"
                                data-bs-target="#modal_shipping_address"
                                id="link_shipping_address_modal"
                                data-action="click->retail-checkout#onClickModalShippingAddress"
                                data-order-id="'. $order->getId() .'"
                                data-type="2"
                            >
                                Change Address
                            </a>
                        </div>
                    </div>
                    <div class="form-control alert alert-secondary" id="checkout_shipping_address">';

                        if($defaultAddress != null)
                        {
                            $response['body'] .=
                            $this->encryptor->decrypt($defaultAddress->getAddress());
                        }

                    $response['body'] .= '
                    </div>
                    <input type="hidden" name="shipping_address_id" id="shipping_address_id" value="">
                    <div class="hidden_msg" id="error_shipping_address">
                        Required Field
                    </div>
                </div>
            </div>
        
            <!-- Billing Address -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="row">
                        <div class="col-6 text-truncate">
                            <h5 class="text-truncate">Billing Address*</h5>
                        </div>
                        <div class="col-6 text-end text-truncate">
                            <a 
                                href="" class="text-truncate"
                                data-bs-toggle="modal" data-bs-target="#modal_billing_address"
                                id="link_billing_address_modal"
                                data-action="click->retail-checkout#onClickModalBillingAddress"
                                data-order-id="'. $order->getId() .'"
                                data-type="1"
                            >
                                Change Address
                            </a>
                        </div>
                    </div>
                    <div class="form-control alert alert-secondary" rows="4" name="address_billing" id="checkout_billing_address">';

                        if($defaultBillingAddress != null)
                        {
                            $response['body'] .=
                                $this->encryptor->decrypt($defaultBillingAddress->getAddress());
                        }

                    $response['body'] .= '
                    </div>
                    <input type="hidden" id="billing_address_id" name="billing_address_id" value="">
                    <input type="hidden" name="type" value="1">
                    <div class="hidden_msg" id="error_billing_address">
                        Required Field
                    </div>
                </div>
            </div>
            <!-- Additional Notes -->
            <div class="row mt-3">
                <div class="col-12 text-truncate">
                    <h5 class="text-truncate">Additional Notes</h5>
                    <div class="info mb-2 text-truncate">Add any special instructions with this order</div>
                    <textarea class="form-control alert alert-secondary" name="notes">'. $order->getNotes() .'</textarea>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-12 text-truncate">
                    <button 
                        type="submit"
                        class="btn btn-primary float-end text-truncate w-sm-100" 
                        id="btn_order_review" 
                        data-order-id="5">
                            <div class="text-truncate">
                                REVIEW ORDER 
                                <i class="fa-solid fa-circle-right ps-2"></i>
                            </div>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Modal Manage Shipping Address -->
        <div class="modal fade" id="modal_shipping_address" tabindex="-1" aria-labelledby="address_delete_label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form 
                        name="form_addresses_shipping_checkout" 
                        id="form_addresses_shipping_checkout" 
                        method="post"
                        data-action="submit->retail-checkout#onSubmitShippingAddress"
                    >
                        <input type="hidden" value="'. $order->getId() .'" name="checkout">
                        <div id="shipping_address_modal"></div>
                    </form>
                </div>
            </div>
        </div>
       
        <!-- Modal Manage Billing Address -->
        <div class="modal fade" id="modal_billing_address" tabindex="-1" aria-labelledby="address_delete_label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form 
                        name="form_addresses_billing_checkout" 
                        id="form_addresses_billing_checkout" 
                        method="post"
                        data-action="submit->retail-checkout#onSubmitBillingAddress"
                    >
                        <input type="hidden" value="'. $order->getId() .'" name="checkout">
                        <div id="billing_address_modal"></div>
                    </form>
                </div>
            </div>
        </div>';

        $response['existing_shipping_addresses'] = '';
        $i = 0;

        foreach($shippingAddresses as $address)
        {
            $i++;
            $marginTop = '';

            if($i == 1)
            {
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

        return new JsonResponse($response);
    }

    #[Route('/retail/checkout/save/options', name: 'checkout_save_options_retail')]
    public function saveCheckoutOptionsRetailAction(Request $request): Response
    {
        $data = $request->request;
        $retail = $this->getUser();
        $orderId = $data->get('order-id');
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $clinic = $this->emRemote->getRepository(Clinics::class)->find($order->getClinicId());
        $currency = $retail->getCountry()->getCurrency();
        $firstName = $this->encryptor->decrypt($retail->getFirstName());
        $lastName = $this->encryptor->decrypt($retail->getLastName());
        $response = '';

        if($order != null)
        {
            $shippingAddress = $this->em->getRepository(Addresses::class)->find($data->get('shipping_address_id'));
            $billingAddress = $this->em->getRepository(Addresses::class)->find($data->get('billing_address_id'));
            $basket = $order->getBasket();

            // Update order
            $order->setEmail($this->encryptor->encrypt($data->get('confirmation_email')));
            $order->setAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);

            if($data->get('notes') != null)
            {
                $order->setNotes($data->get('notes'));
            }

            $this->em->persist($order);
            $this->em->flush();

            // Order Review
            $response .= '
            <div class="row">
                <div class="col-12 text-center pt-3 pb-3 form-control-bg-grey text-truncate" id="basket_header">
                    <h4 class="text-primary">Fluid Checkout</h4>
                    <span class="text-primary">
                        Order conirmation
                    </span>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-sm-6 mt-2 text-truncate">
                    <div class="alert alert-light text-truncate">
                        <b class="text-primary">Account ID:</b> <span class="float-end">'. $retail->getId() .'</span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 mt-2 text-truncate">
                    <div class="alert alert-light text-truncate">
                        <b class="text-primary">Name:</b> <span class="float-end">'. $firstName .' '. $lastName .'</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-sm-6 mt-2 text-truncate">
                    <div class="alert alert-light">
                        <b class="text-primary">Telephone:</b> 
                        <span class="float-end">
                            '. $this->encryptor->decrypt($retail->getTelephone()) .'
                        </span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 mt-2 text-truncate">
                    <div class="alert alert-light text-truncate">
                        <b class="text-primary">Confirmation Email:</b> 
                        <span class="float-start float-sm-end">
                            '. $this->encryptor->decrypt($retail->getEmail()) .'
                        </span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-col-12 col-sm-6 mt-2">
                    <div class="alert alert-light">
                        <div class="text-primary mb-3 fw-bold">Shipping Address</div>
                        '. $this->encryptor->decrypt($basket->getOrders()->getAddress()->getClinicName()) .'<br>
                        '. $this->encryptor->decrypt($basket->getOrders()->getAddress()->getAddress()) .'<br><br>
                        <span class="fw-bold text-primary">Telephone :</span> 
                        '. $this->encryptor->decrypt($basket->getOrders()->getAddress()->getTelephone()) .'
                    </div>
                </div>
                <div class="col-12 col-sm-6 mt-2">
                    <div class="alert alert-light">
                        <div class="text-primary mb-3 fw-bold">Billing Address</div>
                        '. $this->encryptor->decrypt($basket->getOrders()->getBillingAddress()->getClinicName()) .'<br>
                        '. $this->encryptor->decrypt($basket->getOrders()->getBillingAddress()->getAddress()) .'<br><br>
                        <span class="fw-bold text-primary">Telephone :</span> 
                        '. $this->encryptor->decrypt($basket->getOrders()->getBillingAddress()->getTelephone()) .'
                    </div>
                </div>
            </div>';

            // Additional notes
            if(!empty($data->get('notes')))
            {
                $response .= '
                <div class="row">
                    <div class="col-12 mt-2">
                        <div class="alert alert-light">
                            <div class="row">
                                <div class="col-12 text-truncate">
                                    <div class="text-primary mb-3 fw-bold">Additional Notes</div>
                                    '. $data->get('notes') .'
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
            }

            // Purchase orders
            $response .= '
            <div class="row">
                <div class="col-12 mt-2 text-truncate">
                    <div class="alert alert-light">
                        <div class="text-primary mb-3 fw-bold border-bottom-dashed border-dark mb-3 pb-3">
                            PO Number
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 text-truncate">
                                '. $this->encryptor->decrypt($clinic->getClinicName()) .'
                            </div>
                            <div class="col-12 col-sm-6 text-end text-truncate">
                                '. $clinic->getPoNumberPrefix() .'-'. $order->getId() .'
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-2">
                    <div class="alert alert-light">';

                    foreach($basket->getBasketItems() as $item)
                    {
                        // Product Image
                        $image = $this->emRemote->getRepository(ProductImages::class)->findOneBy([
                            'product' => $item->getProductId(),
                            'isDefault' => 1
                        ]);

                        if($image == null){

                            $firstImage = 'image-not-found.jpg';

                        } else {

                            $firstImage = $image->getImage();
                        }

                        $product = $this->emRemote->getRepository(Products::class)->find($item->getProductId());

                        $response .= '
                        <div class="row">
                            <!-- Thumbnail -->
                            <div class="col-12 col-sm-2 text-center pt-3">
                                <img class="img-fluid basket-img" src="/images/products/' . $firstImage . '" style="max-height: 45px">
                            </div>
                            <div class="col-12 col-sm-10 pt-3">
                                <!-- Product Name and Qty -->
                                <div class="row">
                                    <!-- Product Name -->
                                    <div class="col-12 col-sm-6 text-center text-sm-start">
                                        <span class="info">'. $this->encryptor->decrypt($clinic->getClinicName()) .'</span>
                                        <h6 class="fw-bold text-center text-sm-start text-primary mb-0">
                                            ' . $product->getName() . ': ' . $product->getDosage() . ' ' . $product->getUnit() . '
                                        </h6>
                                    </div>
                                    <!-- Product Quantity -->
                                    <div class="col-12 col-sm-6 d-table">
                                        <div class="row d-table-row">
                                            <div class="col-5 text-center text-sm-start d-table-cell align-bottom text-truncate">
                                                ' . $currency .' '. number_format($item->getUnitPrice(),2) . '
                                            </div>
                                            <div class="col-3 text-center d-table-cell align-bottom">
                                                ' . $item->getQty() . '
                                            </div>
                                            <div class="col-5 text-center text-sm-end fw-bold d-table-cell align-bottom text-truncate">' . $currency .' '. number_format($item->getTotal(),2) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
                    }

            $response .= '
                    </div>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-12 text-end">
                    <button 
                        type="submit" 
                        class="btn btn-primary float-end w-sm-100" 
                        id="btn_place_order" 
                        data-order-id="'. $order->getId() .'"
                    >
                        PLACE ORDER 
                        <i class="fa-solid fa-circle-right ps-2"></i>
                    </button>
                </div>
            </div>';
        }

        return new JsonResponse($response);
    }

    public function zohoRefreshToken($refreshToken, $distributorId): string
    {
        $apiDetails = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $distributorId,
        ]);
        $curl = curl_init();
        $endpoint = 'https://accounts.zoho.com/oauth/v2/token?refresh_token=' . $refreshToken . '&';
        $endpoint .= 'client_id=' . $this->encryptor->decrypt($apiDetails->getClientId()) . '&';
        $endpoint .= 'client_secret=' . $this->encryptor->decrypt($apiDetails->getClientSecret()) . '&';
        $endpoint .= 'redirect_uri=https://fluid.vet/clinics/zoho/access-token&grant_type=refresh_token';

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST'
        ]);

        $json = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($json, true);

        return $response['access_token'];
    }

    private function zohoCreateSalesOrder($order)
    {
        $curl = curl_init();
        $session = $this->requestStack->getSession();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/salesorders?organization_id='. $this->getParameter('    app.zoho_client_secret'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "customer_id": '. $order['customerId'] .',
                "date": "'. $order['date'] .'",
                "reference_number": "FL-'. $order['orderNo'] .'",
                "line_items": [
                    {
                        "item_id": 1625918000009703354,
                        "quantity": '. $order['qty'] .',
                        "unit": "qty"
                    }
                ]
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Zoho-oauthtoken '. $session->get('refreshToken'),
            ),
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;
    }

    private function sendOrderEmail($orderId, $distributorId, $clinicId, $type)
    {
        $i = 0;
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $orderItems = $this->em->getRepository(OrderItems::class)->findByNotCancelled($orderId,$distributorId);
        $distributorName = $distributor->getDistributorName();
        $emailAddress = $clinic->getEmail();
        $poNumber = $distributor->getPoNumberPrefix() .'-'. $orderId;
        $subject = 'Fluid Order - PO '. $poNumber;
        $url = $this->getParameter('app.base_url').'/'. $type .'/order/'. $orderId;

        $rows = '
            <table style="border: none; border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px; width: 100%">
                <tr>
                    <th style="border: solid 1px #ccc; background: #ccc">#</th>
                    <th style="border: solid 1px #ccc; background: #ccc">Name</th>
                    <th style="border: solid 1px #ccc; background: #ccc">Price</th>
                    <th style="border: solid 1px #ccc; background: #ccc">Qty</th>
                    <th style="border: solid 1px #ccc; background: #ccc">Total</th>
                </tr>';

        foreach($orderItems as $item){

            $i++;

            $rows .= '
                <tr>
                    <td style="border: solid 1px #ccc; border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px">'. $i .'</td>
                    <td style="border: solid 1px #ccc; border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px">'. $item->getName() .'</td>
                    <td style="border: solid 1px #ccc; border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px">'. $item->getUnitPrice() .'</td>
                    <td style="border: solid 1px #ccc; border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px">'. $item->getQuantity() .'</td>
                    <td style="border: solid 1px #ccc; border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px">'. $item->getTotal() .'</td>
                </tr>';
        }

        $rows .= '</table>';

        $body = '
        <table style="border-collapse: collapse; padding: 8px; font-family: Arial; font-size: 14px; width: 700px;">
            <tr>
                <td colspan="2">
                    Please <a href="'. $url .'">click here</a> in order to login in to your Fluid account to manage this order
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td>
                    '. $distributorName .'
                </td>
                <td align="right" rowspan="2">
                    <span style="font-size: 24px">
                        PO Number: '. $poNumber .'
                    </span>
                </td>
            </tr>
            <tr>
                <td align="right">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    '. $rows .'
                </td>
            </tr>
        </table>
        <br>';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $body,
        ]);

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($emailAddress)
            ->subject($subject)
            ->html($html->getContent());

        $this->mailer->send($email);
    }

    private function btnConfirmOrder($orders, $orderId, $distributorId)
    {
        $totalItems = 0;
        $accepted = 0;
        $cancelled = 0;

        if($orders != null && count($orders) > 0){

            $totalItems = count($orders);

            foreach($orders as $order){

                $accepted += $order->getIsAccepted();
                $cancelled += $order->getIsCancelled();
            }
        }

        if(($accepted + $cancelled) == $totalItems && $totalItems != 0){

            if($cancelled == $totalItems){

                $btnConfirm = '
                <a 
                    href="#" 
                    id="btn_cancel_order" 
                    data-order-id="' . $orderId . '"
                    data-distributor-id="' . $distributorId . '"
                    data-clinic-id="' . $orders[0]->getOrders()->getClinic()->getId() . '"
                >
                    <i class="fa-regular fa-credit-card me-5 me-md-2"></i>
                    <span class=" d-none d-md-inline-block pe-4">Cancel & Close Order</span>
                </a>';

            } else {

                $btnConfirm = '
                <a 
                    href="#" 
                    id="btn_confirm_order" 
                    data-order-id="' . $orderId . '"
                    data-clinic-id="' . $orders[0]->getOrders()->getClinic()->getId() . '"
                    data-distributor-id="'. $distributorId .'"
                >
                    <i class="fa-regular fa-credit-card me-5 me-md-2"></i>
                    <span class=" d-none d-md-inline-block pe-4">Confirm Order</span>
                </a>';
            }

        } else {

            $btnConfirm = '
            <span 
                class="disabled"
                id="btn_confirm_order"
            >
                <i class="fa-regular fa-credit-card me-5 me-md-2"></i>
                <span class=" d-none d-md-inline-block pe-4">Confirm Order</span>
            </span>';
        }

        return $btnConfirm;
    }

    public function generatePdfAction($orderId, $distributorId, $status)
    {
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $currency = $distributor->getAddressCountry()->getCurrency();
        $orderItems = $this->em->getRepository(OrderItems::class)->findByDistributorOrder(
            (int) $orderId,
            (int) $distributorId,
            $status
        );

        if(count($orderItems) == 0){

            return '';
        }

        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $billingAddress = $this->em->getRepository(Addresses::class)->find($order->getBillingAddress());
        $shippingAddress = $this->em->getRepository(Addresses::class)->find($order->getAddress());
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $sumTotal = $this->em->getRepository(OrderItems::class)->findSumTotalOrderItems($orderId, $distributorId);

        $order->setSubTotal($sumTotal[0]['totals']);
        $order->setTotal($sumTotal[0]['totals'] +  + $order->getDeliveryFee() + $order->getTax());

        $this->em->persist($order);
        $this->em->flush();

        $additionalNotes = '';

        if($order->getNotes() != null){

            $additionalNotes = '
            <div style="padding-top: 20px; padding-right: 30px; line-height: 30px">
                <b>Additional Notes</b><br>
                '. $order->getNotes() .'
            </div>';
        }

        $address = '';

        if($distributor->getAddressStreet() != null){

            $address .= $this->encryptor->decrypt($distributor->getAddressStreet()) .'<br>';
        }

        if($distributor->getAddressCity() != null){

            $address .= $this->encryptor->decrypt($distributor->getAddressCity()) .'<br>';
        }

        if($distributor->getAddressPostalCode() != null){

            $address .= $this->encryptor->decrypt($distributor->getAddressPostalCode()) .'<br>';
        }

        if($distributor->getAddressState() != null){

            $address .= $this->encryptor->decrypt($distributor->getAddressState()) .'<br>';
        }

        if($distributor->getAddressCountry() != null){

            $address .= $this->encryptor->decrypt($distributor->getAddressStreet()) .'<br>';
        }
 
        $snappy = new Pdf(__DIR__ .'/../../vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64');
        $html = '
        <table style="width: 100%; border: none; border-collapse: collapse; font-size: 12px; font-family: Arial, Helvetica, sans-serif">
            <tr>
                <td style=" line-height: 25px">';

                    if($distributor->getLogo() != null) {

                        $html .= '
                            <img 
                                src="' . __DIR__ . '/../../public/images/logos/' . $distributor->getLogo() . '"
                                style="width:100%; max-width: 200px"
                            >
                            <br>';
                    }

                    $html .= '
                    '. $this->encryptor->decrypt($distributor->getDistributorName()) .'<br>
                    '. $address .'
                    '. $this->encryptor->decrypt($distributor->getTelephone()) .'<br>
                    '. $this->encryptor->decrypt($distributor->getEmail()) .'
                </td>
                <td style="text-align: right">
                    <h1>PURCHASE ORDER</h1>
                    <table style="width: auto;margin-right: 0px;margin-left: auto; text-align: right;font-size: 12px">
                        <tr>
                            <td>
                                DATE:
                            </td>
                            <td style="padding-left: 20px; line-height: 25px">
                                '. date('Y-m-d') .'
                            </td>
                        </tr>
                        <tr>
                            <td>
                                PO#:
                            </td>
                            <td style="line-height: 25px">
                                '. $orderItems[0]->getPoNumber() .'
                            </td>
                        </tr>
                        <tr>
                            <td>
                                Status:
                            </td>
                            <td style="line-height: 25px">
                                '. $status .'
                            </td>
                        </tr>  
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td style="width: 50%; vertical-align: top">
                    <table style="width: 80%; border-collapse: collapse;font-size: 12px">
                        <tr style="background: #54565a; color: #fff; border: solid 1px #54565a;">
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                Vendor
                            </th>
                        </tr>
                        <tr>
                            <td style="padding-top: 10px; line-height: 25px">
                                '. $this->encryptor->decrypt($billingAddress->getClinicName()) .'<br>
                                '. $this->encryptor->decrypt($billingAddress->getAddress()) .'<br>
                                '. $this->encryptor->decrypt($billingAddress->getPostalCode()) .'<br>
                                '. $this->encryptor->decrypt($billingAddress->getCity()) .'<br>
                                '. $this->encryptor->decrypt($billingAddress->getState()) .'<br>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top">
                    <table style="width: 80%; border-collapse: collapse; margin-left: auto;margin-right: 0; font-size: 12px">
                        <tr style="background: #54565a; color: #fff">
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                Deliver To
                            </th>
                        </tr>
                        <tr>
                            <td style="padding-top: 10px; line-height: 25px">
                                '. $this->encryptor->decrypt($shippingAddress->getClinicName()) .'<br>
                                '. $this->encryptor->decrypt($shippingAddress->getAddress()) .'<br>
                                '. $this->encryptor->decrypt($shippingAddress->getPostalCode()) .'<br>
                                '. $this->encryptor->decrypt($shippingAddress->getCity()) .'<br>
                                '. $this->encryptor->decrypt($shippingAddress->getState()) .'<br>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px">
                        <tr style="background: #54565a; color: #fff">
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                #SKU
                            </th>
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                Description
                            </th>
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                Qty
                            </th>
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                Unit Priice
                            </th>
                            <th style="padding: 8px; border: solid 1px #54565a;">
                                Total
                            </th>
                        </tr>';

                    foreach($orderItems as $item) {

                        $quantity = $item->getQuantity();

                        // Use quantity delivered once delivered
                        if($orderStatus->getId() >= 7){

                            $quantity = $item->getQuantityDelivered();
                        }

                        if ($item->getIsAccepted() == 1) {

                            $name = $item->getName() . ': ';
                            $dosage = $item->getProduct()->getDosage() . $item->getProduct()->getUnit() . ', ' . $item->getProduct()->getSize() . ' Count';

                            if ($item->getProduct()->getForm() == 'Each') {

                                $dosage = $item->getProduct()->getSize() . $item->getProduct()->getUnit();
                            }

                            $html .= '
                            <tr>
                                <td style="padding: 8px; border: solid 1px #54565a;text-align: center">
                                    ' . $item->getProduct()->getDistributorProducts()[0]->getSku() . '
                                </td>
                                <td style="padding: 8px; border: solid 1px #54565a;">
                                    ' . $name . $dosage . '
                                </td>
                                <td style="padding: 8px; border: solid 1px #54565a;text-align: center">
                                    ' . $quantity . '
                                </td>
                                <td style="padding: 8px; border: solid 1px #54565a;text-align: right; padding-right: 8px; width: 10%">
                                    ' . $currency .' '. number_format($item->getUnitPrice(), 2) . '
                                </td>
                                <td style="padding: 8px; border: solid 1px #54565a;text-align: right; padding-right: 8px; width: 10%">
                                    '. $currency .' '. number_format($item->getTotal(), 2) . '
                                </td>
                            </tr>';
                        }
                    }

                    $html .= '
                        <tr>
                            <td colspan="3" rowspan="4" style="padding: 8px; padding-top: 16px; border: none;">
                                '. $additionalNotes .'
                            </td>
                            <td style="padding: 8px; padding-top: 16px; border: none;text-align: right">
                                Subtotal
                            </td>
                            <td style="padding: 8px; padding-top: 16px;text-align: right; border: none">
                                '. $currency .' '. number_format($order->getSubTotal(),2) .'
                            </td>
                        </tr>';

                        if($order->getDeliveryFee() > 0) {

                            $html .= '
                            <tr>
                                <td style="padding: 8px; border: none;text-align: right">
                                    Delivery
                                </td>
                                <td style="padding: 8px;text-align: right; border: none">
                                    ' . $currency .' '. number_format($order->getDeliveryFee(), 2) . '
                                </td>
                            </tr>';
                        }

                        if($order->getTax() > 0) {

                            $html .= '
                            <tr>
                                <td style="padding: 8px; border: none;text-align: right">
                                    Tax
                                </td>
                                <td style="padding: 8px; border:none; text-align: right">
                                    ' . $currency .' '. number_format($order->getTax(), 2) . '
                                </td>
                            </tr>';
                        }

                        $html .= '
                        <tr>
                            <td style="padding: 8px; border: none;text-align: right">
                                <b>Total</b>
                            </td>
                            <td style="padding: 8px;text-align: right; border: none">
                                <b>'. $currency .' '. number_format($order->getTotal(),2) .'</b>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $file = uniqid() .'.pdf';
        $snappy->generateFromHtml($html,'pdf/'. $file,['page-size' => 'A4']);

        $orderStatus->setPoFile($file);

        $this->em->persist($orderStatus);
        $this->em->flush();

        return $orderStatus->getPoFile();
    }

    private function accessDeniedAction($permissions, $permissionId){

        if(!in_array($permissionId, $permissions)){

            $response = '
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

            return $response;

        } else {

            return false;
        }
    }

    public function getPagination($pageId, $results, $url, $companyId, $type): string
    {
        $currentPage = $pageId;
        $lastPage = $this->pageManager->lastPage($results);
        $margin = '';

        if($type == 'distributor'){

            $margin = 'mt-3';
        }

        $pagination = '
        <!-- Pagination -->
        <div class="row '. $margin .'">
            <div class="col-12">';

        if($lastPage > 1) {

            $previousPageNo = $currentPage - 1;
            $url = $url . $companyId;
            $previousPage = $url . $previousPageNo;

            $pagination .= '
            <nav class="custom-pagination">
                <ul class="pagination justify-content-center">
            ';

            $disabled = 'disabled';
            $dataDisabled = 'true';

            // Previous Link
            if($currentPage > 1){

                $disabled = '';
                $dataDisabled = 'false';
            }

            $pagination .= '
            <li class="page-item '. $disabled .'">
                <a 
                    class="order-link" 
                    aria-disabled="'. $dataDisabled .'" 
                    data-page-id="'. $currentPage - 1 .'" 
                    data-'. $type .'-id="'. $companyId .'"
                    href="'. $previousPage .'"
                >
                    <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline-block">Previous</span>
                </a>
            </li>';

            $i = $currentPage;
            $pageCount = $currentPage + 9;

            // First 10 pages
            // If page count is less than 10
            if($currentPage < 10 && $lastPage <= 10){

                $i = 1;
                $pageCount = $lastPage;
            }

            // If page count is greater than 10
            if($currentPage < 10 && $lastPage > 10){

                $i = 1;
                $pageCount = 10;
            }

            // Last 10 pages
            if($currentPage >= 10 && $currentPage > $lastPage - 10){

                $i = $currentPage - 9;
                $pageCount = $currentPage;
            }

            for($i; $i <= $pageCount; $i++) {

                $active = '';

                if($i == (int) $currentPage){

                    $active = 'active';
                }

                $pagination .= '
                <li class="page-item '. $active .'">
                    <a 
                        class="order-link" 
                        data-page-id="'. $i .'" 
                        href="'. $url .'"
                        data-'. $type .'-id="'. $companyId .'"    
                    >'. $i .'</a>
                </li>';

                if($i == $lastPage){
                    break;
                }
            }

            $disabled = 'disabled';
            $dataDisabled = 'true';

            if($currentPage < $lastPage) {

                $disabled = '';
                $dataDisabled = 'false';
            }

            $pagination .= '
            <li class="page-item '. $disabled .'">
                <a 
                    class="order-link" 
                    aria-disabled="'. $dataDisabled .'" 
                    data-page-id="'. $currentPage + 1 .'" 
                    href="'. $url . $currentPage + 1 .'"
                    data-'. $type .'-id="'. $companyId .'"
                >
                    <span class="d-none d-sm-inline-block">Next</span> <span aria-hidden="true">&raquo;</span>
                </a>
            </li>';

            $pagination .= '
                    </ul>
                </nav>
            </div>';
        }

        return $pagination;
    }
}
