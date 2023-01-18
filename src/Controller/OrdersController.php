<?php

namespace App\Controller;

use App\Entity\Addresses;
use App\Entity\ApiDetails;
use App\Entity\Baskets;
use App\Entity\ChatMessages;
use App\Entity\Clinics;
use App\Entity\DistributorClinics;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\Notifications;
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
        $distributor = $this->emRemote->getRepository(Distributors::class)->find($order->getOrderItems()->first()->getDistributorId());
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
                $orderItems->setProduct($basketItem->getProduct());
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
                    $distributorId = $basketItem->getDistributorId();
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
                                ' . $this->encryptor->decrypt($distributor->getDistributorName()) . '
                            </div>
                            <div class="col-6 text-end text-truncate">
                                ' . $distributor->getPoNumberPrefix() . '-' . $order->getId() . '
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
                        $productImages = $item->getProduct()->getProductImages();
                        $image = 'image-not-found.jpg';

                        if(count($productImages) > 0)
                        {
                            $image = $this->em->getRepository(ProductImages::class)->findOneBy([
                                'product' => $item->getProduct()->getId(),
                                'isDefault' => 1
                            ])->getImage();
                        }

                        $response .= '
                        <div class="row">
                            <!-- Thumbnail -->
                            <div class="col-12 col-sm-2 text-center pt-3">
                                <img class="img-fluid basket-img" src="/images/products/' . $image . '" style="max-height: 45px">
                            </div>
                            <div class="col-12 col-sm-10 pt-3">
                                <!-- Product Name and Qty -->
                                <div class="row">
                                    <!-- Product Name -->
                                    <div class="col-12 col-sm-6 text-center text-sm-start">
                                        <span class="info">'. $this->encryptor->decrypt($clinic->getClinicName()) .'</span>
                                        <h6 class="fw-bold text-center text-sm-start text-primary mb-0">
                                            ' . $item->getProduct()->getName() . ': ' . $item->getProduct()->getDosage() . ' ' . $item->getProduct()->getUnit() . '
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

    #[Route('/distributors/update-order', name: 'distributor_update_order')]
    public function distributorUpdateOrderAction(Request $request, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $orderId = (int) $data->get('order_id');
        $expiryDates = $data->get('expiry_date');
        $prices = $data->get('price');
        $quantities = $data->get('qty');
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $distributor = $this->getUser()->getDistributor();
        $productId = $data->get('product_id');

        if($productId != null && count($productId) > 0){

            for($i = 0; $i < count($productId); $i++){

                $product = $this->em->getRepository(Products::class)->find($productId[$i]);
                $orderItem = $this->em->getRepository(OrderItems::class)->findOneBy([
                    'product' => $productId[$i],
                    'orders' => $orderId,
                    'distributor' => $distributor->getId()
                ]);

                if($expiryDates[$i] != 0) {

                    $orderItem->setExpiryDate(\DateTime::createFromFormat('Y-m-d', $expiryDates[$i]));
                }

                $orderItem->setUnitPrice($prices[$i]);
                $orderItem->setQuantity($quantities[$i]);
                $orderItem->setQuantityDelivered($quantities[$i]);
                $orderItem->setTotal($prices[$i] * $quantities[$i]);

                $this->em->persist($orderItem);
            }

            $this->em->flush();

            $sumTotal = $this->em->getRepository(OrderItems::class)->findSumTotalPdfOrderItems($orderId, $distributor->getId());

            $order->setSubTotal($sumTotal[0]['totals']);
            $order->setTotal($sumTotal[0]['totals'] + $order->getDeliveryFee() + $order->getTax());

            $this->em->persist($order);
            $this->em->flush();
        }

        $flash = '<b><i class="fa-solid fa-circle-check"></i></i></b> Order successfully saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'order_id' => $orderId,
            'distributor_id' => $distributor->getId(),
            'clinic_id' => $order->getClinic()->getId(),
            'flash' => $flash
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/send-order-notification', name: 'distributor_send_order_notification')]
    public function distributorSendOrderNotificationAction(Request $request, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $orderId = $data->get('order_id');
        $distributorId = $data->get('distributor_id');
        $clinicId = $data->get('clinic_id');
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);

        // Clinic in app notification
        $this->sendNotification($order, $distributor, $order->getClinic(), 'Order Update');

        // Send Email Notification
        $this->sendOrderEmail($orderId, $distributorId, $clinicId, 'clinics');

        $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Order successfully saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/distributors/order', name: 'distributor_get_order_details')]
    public function distributorOrderDetailAction(Request $request): Response
    {
        $orderId = $request->request->get('order_id');
        $distributor = $this->getUser()->getDistributor();
        $currency = $distributor->getAddressCountry()->getCurrency();
        $chatMessages = $this->em->getRepository(ChatMessages::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributor->getId()
        ]);
        $orders = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributor->getId()

        ]);
        $dateSent = '';
        $messages = $this->forward('App\Controller\ChatMessagesController::getMessages', [
            'chatMessages' => $chatMessages,
            'dateSent' => $dateSent,
            'distributor' => true,
            'clinic' => false,
        ])->getContent();
        $orderStatusId = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributor->getId()
        ]);
        $isAuthorised = true;
        $disabled = '';

        if(!in_array(5, json_decode($request->request->get('permissions')))){

            $isAuthorised = false;
            $disabled = 'disabled';
        }

        $response = '
        <form name="form_distributor_orders" id="form_distributor_orders" class="row" method="post">
            <input type="hidden" name="order_id" value="'. $orders[0]->getOrders()->getId() .'">
            <div class="col-12">
                <div class="row">
                    <div class="col-12 text-center mt-1 pt-3 pb-3" id="order_header">
                        <h4 class="text-primary">'. $orders[0]->getPoNumber() .'</h4>
                        <span class="text-primary">
                            '. $this->encryptor->decrypt($orders[0]->getOrders()->getClinic()->getClinicName()) .'
                        </span>
                    </div>
                </div>
                <!-- Actions Row -->
                <div class="row" id="order_action_row_1">
                    <div class="col-12 d-flex justify-content-center border-bottom pt-3 pb-3 bg-light border-left border-right border-top">
                        <a 
                            href="#" 
                            class="orders_link"
                            data-distributor-id="'. $distributor->getId() .'"
                        >
                            <i class="fa-solid fa-angles-left me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Back To Orders</span>
                        </a>
                        <a 
                            href="#" 
                            class="refresh-distributor-order" 
                            data-order-id="'. $orderId .'"
                            data-distributor-id="'. $distributor->getId() .'"
                            data-clinic-id="'. $orders[0]->getOrders()->getClinic()->getId() .'"
                        >
                            <i class="fa-solid fa-arrow-rotate-right me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Refresh Order</span>
                        </a>';

                        // Before the oder is shipped
                        if($orderStatusId->getStatus()->getId() > 4){

                            $response .= '
                            <span class="saved_baskets_link info p-0 opacity-50">
                                <i class="fa-solid fa-floppy-disk me-5  me-md-2"></i>
                                <span class=" d-none d-md-inline-block pe-4">Save Order</span>
                            </span>';

                        } else {

                            if($isAuthorised){

                                $response .= '
                                <button type="submit" class="saved_baskets_link btn btn-sm btn-light p-0 text-primary">
                                    <i class="fa-solid fa-floppy-disk me-5  me-md-2"></i>
                                    <span class=" d-none d-md-inline-block pe-4">Save Order</span>
                                </button>
                                
                                <a 
                                    href="#" 
                                    id="order_send_notification"
                                    data-order-id="'. $orderId .'"
                                    data-distributor-id="'. $orders[0]->getDistributor()->getId() .'"
                                    data-clinic-id="'. $orders[0]->getOrders()->getClinic()->getId() .'"
                                >
                                    <i class="fa-solid fa-paper-plane me-0 me-md-2"></i><span class=" d-none d-md-inline-block pe-4">Send Notification</span>
                                </a>';

                            } else {

                                $response .= '
                                <span class="btn btn-sm btn-light p-0 text-primary text-disabled cursor-disabled">
                                    <i class="fa-solid fa-floppy-disk me-5  me-md-2"></i>
                                    <span class=" d-none d-md-inline-block pe-4">Save Order</span>
                                </span>
                                
                                <span class="text-disabled cursor-disabled">
                                    <i class="fa-solid fa-paper-plane me-0 me-md-2"></i>
                                    <span class="d-none d-md-inline-block pe-4">
                                        Send Notification
                                    </span>
                                </span>';
                            }

                        }

                        $response .= '
                    </div>
                </div>
                <!-- Products -->
                <div class="row border-0 bg-light">
                    <div class="col-12 col-md-9 border-right col-cell border-left border-right border-bottom">
                        <input type="hidden" name="distributor_id" value="'. $distributor->getId() .'">';

                        $i = 0;

                        foreach($orders as $order) {

                            $statusId = $orderStatusId->getStatus()->getId();

                            if($order->getIsCancelled() == 1 && ($statusId == 6 || $statusId == 7 || $statusId == 8)){

                                continue;
                            }

                            $expiry = '';

                            if(!empty($order->getExpiryDate())){

                                $expiry = $order->getExpiryDate()->format('Y-m-d');
                            }

                            // Item status
                            $opacity = '';
                            $badgeCancelled = '';
                            $badgeConfirm = '';
                            $badgePending = '';
                            $clinicStatus = '';
                            $badgeShipped = '';
                            $badgeDeliveredPending = '';
                            $badgeDeliveredCorrect = '';
                            $badgeDeliveredIncorrect = '';
                            // Quantities
                            $quantity = $order->getQuantity();

                            if($statusId >= 7){

                                $quantity = $order->getQuantityDelivered();
                            }

                            if($order->getIsCancelled() == 1){

                                $disabled = 'disabled';
                                $opacity = 'opacity-50';

                                if($isAuthorised){

                                    $badgeCancelled = '
                                    <span
                                        class="badge float-end ms-2 text-light border border-danger text-light order_item_accept bg-danger badge-danger-filled-sm"
                                    >Cancelled</span>';

                                } else {

                                    $badgeCancelled = '
                                    <span
                                        class="badge float-end ms-2 text-light border border-danger text-light order_item_accept bg-danger badge-danger-filled-sm bg-disabled"
                                    >Cancelled</span>';
                                }

                            } else {

                                if ($order->getIsConfirmedDistributor() == 1) {

                                    // If order is preparing for shipping or later
                                    if($order->getOrders()->getOrderStatuses()[0]->getStatus()->getId() >= 5){

                                        // Shipped
                                        if($statusId == 6){

                                            if($isAuthorised){

                                                $badgeShipped = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-success bg-success badge-success-filled-sm"
                                                >Shipped</span>';

                                            } else {

                                                $badgeShipped = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-success bg-success badge-success-filled-sm bg-disabled"
                                                >Shipped</span>';
                                            }
                                        }

                                        // Delivered
                                        if($statusId == 7){

                                            // Quantities not confirmed by clinic
                                            if(
                                                $order->getIsAcceptedOnDelivery() == 0 &&
                                                $order->getIsRejectedOnDelivery() == 0 &&
                                                $order->getIsQuantityAdjust() == 0
                                            ){

                                                $badgeDeliveredPending = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-dark-grey bg-dark-grey"
                                                >Pending Clinic</span>';
                                            }

                                            // Quantity confirmed by clinic
                                            if($order->getIsAcceptedOnDelivery() == 1){

                                                if($isAuthorised){

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-light border border-success 
                                                        bg-success badge-success-filled-sm text-truncate"
                                                    >Complete</span>';

                                                } else {

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-truncate bg-disabled"
                                                    >Complete</span>';
                                                }
                                            }

                                            // Quantity rejected by clinic
                                            if($order->getIsRejectedOnDelivery() == 1){

                                                if($isAuthorised){

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-light border border-danger 
                                                        bg-danger badge-danger-filled-sm"
                                                    >Rejected</span>';

                                                } else {

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-truncate bg-disabled"
                                                    >Rejected</span>';
                                                }
                                            }

                                            // Quantity adjust
                                            if($order->getIsQuantityAdjust() == 1){

                                                if($isAuthorised){

                                                    $badgeDeliveredIncorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-light border border-warning 
                                                        bg-warning badge-warning-filled-sm text-truncate"
                                                    >Adjusting Quantity</span>';

                                                } else {

                                                    $badgeDeliveredIncorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-truncate bg-disabled"
                                                    >Adjusting Quantity</span>';
                                                }
                                            }
                                        }

                                        // Closed
                                        if($statusId == 8){

                                            // Quantity confirmed by clinic
                                            if($order->getIsAcceptedOnDelivery() == 1){

                                                if($isAuthorised){

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-light border border-success 
                                                        bg-success badge-success-filled-sm text-truncate"
                                                    >Accepted</span>';

                                                } else {

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-truncate bg-disabled"
                                                    >Accepted</span>';
                                                }
                                            }

                                            // Quantity rejected by clinic
                                            if($order->getIsRejectedOnDelivery() == 1){

                                                if($isAuthorised){

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-light border 
                                                        border-danger bg-danger text-truncate"
                                                    >Rejected</span>';

                                                } else {

                                                    $badgeDeliveredCorrect = '
                                                    <span 
                                                        class="badge float-end ms-2 text-truncate bg-disabled"
                                                    >Rejected</span>';
                                                }
                                            }
                                        }

                                    // Pre shipping  statuses
                                    } else {

                                        if ($order->getIsAccepted() == 1) {

                                            $disabled = 'disabled';

                                            if($isAuthorised){

                                                $clinicStatus = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-success bg-success badge-success-filled-sm"
                                                >Accepted</span>';

                                            } else {

                                                $clinicStatus = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-success bg-success badge-success-filled-sm bg-disabled"
                                                >Accepted</span>';
                                            }

                                        } elseif ($order->getIsRenegotiate() == 1) {

                                            $disabled = '';

                                            if($isAuthorised){

                                                $clinicStatus = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-warning bg-warning badge-warning-filled-sm"
                                                >Renegotiating</span>';

                                            } else {

                                                $clinicStatus = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-warning bg-warning badge-warning-filled-sm bg-disabled"
                                                >Renegotiating</span>';
                                            }

                                        // Distributor expiry date, qty & price confirmed
                                        } else {

                                            if($isAuthorised){

                                                $badgePending = '
                                                <a href="#" 
                                                    class="badge float-end ms-2 border-1 badge-pending-outline-only btn_pending badge-pending-sm"
                                                    data-order-id="' . $orderId . '"
                                                    data-item-id="' . $order->getId() . '"
                                                >Pending</a>';

                                                $badgeConfirm = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-success bg-success badge-success-filled-sm"
                                                >Confirmed</span>';

                                            } else {

                                                $badgePending = '
                                                <span
                                                    class="badge float-end ms-2 border-1 badge-pending-outline-only badge-pending-sm bg-disabled"
                                                >Pending</a>';

                                                $badgeConfirm = '
                                                <span 
                                                    class="badge float-end ms-2 text-light border border-success bg-success badge-success-filled-sm bg-disabled"
                                                >Confirmed</span>';
                                            }
                                        }
                                    }

                                // Pending distributor confirmation of expiry date and stock
                                } else {

                                    if($isAuthorised){

                                        $badgePending = '
                                        <span 
                                            class="badge float-end ms-2 text-light border-1 bg-dark-grey border-dark-grey badge-pending-filled-sm text-truncate"
                                        >Pending</span>';

                                        $badgeConfirm = '
                                        <a href="#" 
                                            class="badge float-end ms-2 text-success border-1 badge-success-outline-only badge-success-sm btn_confirm"
                                            data-order-id="' . $orderId . '"
                                            data-item-id="' . $order->getId() . '"
                                        >Confirm</a>';

                                    } else {

                                        $badgePending = '
                                        <span 
                                            class="badge float-end ms-2 text-light border-1 bg-dark-grey border-dark-grey badge-pending-filled-sm text-truncate bg-disabled"
                                        >Pending</span>';

                                        $badgeConfirm = '
                                        <span
                                            class="badge float-end ms-2 text-success border-1 bg-disabled"
                                        >Confirm</span>';
                                    }
                                }
                            }

                            $prdId = '<input type="hidden" name="product_id[]" value="'. $order->getProduct()->getId() .'" '. $disabled .'>';
                            $expiryDateRequired = $order->getProduct()->getExpiryDateRequired();

                            if($expiryDateRequired) {

                                $expiryDate = '
                                <input 
                                    placeholder="Expiry Date" 
                                    name="expiry_date[]"
                                    data-item-id="'. $order->getId() .'"
                                    class="form-control form-control-sm expiry-date ' . $opacity . '" 
                                    type="text" 
                                    onfocus="(this.type=\'date\')" 
                                    id="date"
                                    value="' . $expiry . '"
                                     ' . $disabled . '
                                >';
                            } else {

                                $expiryDate = '
                                <input 
                                    name="expiry_date[]"
                                    type="hidden" 
                                    value="0">';
                            }
                            $unitPrice = '
                            <input 
                                type="text" 
                                name="price[]" 
                                data-item-id="'. $order->getId() .'"
                                value="'. number_format($order->getUnitPrice(),2) .'"
                                class="form-control form-control-sm item-price '. $opacity .'"
                                 '. $disabled .'
                            >';
                            $qty = '
                            <input 
                                type="number" 
                                name="qty[]" 
                                data-item-id="'. $order->getId() .'"
                                class="form-control basket-qty form-control-sm text-center item-qty '. $opacity .'" 
                                value="'. $order->getQuantity() .'" 
                                 '. $disabled .'
                            />';

                            // Remove form fields once accepted
                            if($order->getIsAccepted() == 1 || $order->getIsCancelled()){

                                if($order->getIsCancelled() == 1){

                                    $opacity = 'opacity-50';
                                }

                                if($order->getExpiryDate() != null) {

                                    $expiryDate = '<span class="'. $opacity .'">'. $order->getExpiryDate()->format('Y-m-d') .'</span>';
                                }

                                $unitPrice = '<span class="'. $opacity .'">'. $currency .' '. number_format($order->getUnitPrice(),2). '</span>';
                                $qty = '<span class="'. $opacity .'">'. $quantity .'</span>';
                            }

                            $popover = '<b>Ordered By</b> '. $this->encryptor->decrypt($order->getOrderPlacedBy()) .'<br>';

                            if($order->getOrderReceivedBy() != null){

                                $popover .= '
                                <b>Recieved By</b> '. $this->encryptor->decrypt($order->getOrderReceivedBy());
                            }

                            if($order->getRejectReason() != null){

                                $popover .= '
                                <br><br>
                                <b>Reason For Rejection</b><br>
                                '. $order->getRejectReason();
                            }

                                $response .= '
                                <!-- Product Name and Qty -->
                                '. $prdId .'
                                <div class="row overflow-hidden">
                                    <!-- Product Name -->
                                    <div class="col-12 col-sm-5 pt-3 pb-3 text-center text-sm-start">
                                        <span class="info '. $opacity .'">
                                            '. $this->encryptor->decrypt($order->getDistributor()->getDistributorName()) .'
                                        </span>
                                        <h6 
                                            class="fw-bold text-primary lh-base mb-0 text-truncate '. $opacity .'"
                                            data-bs-html="true" 
                                            data-bs-trigger="hover" 
                                            data-bs-container="body" 
                                            data-bs-toggle="popover" 
                                            data-bs-placement="top" 
                                            data-bs-content="'. $order->getName() .'"
                                        >
                                            '. $order->getName() .'
                                        </h6>
                                    </div>
                                    <!-- Expiry Date -->
                                    <div class="col-12 col-sm-7 pt-3 pb-3 d-table">
                                        <div class="row d-table-row">
                                            <div class="col-12 col-sm-3 mb-3 mb-sm-0 text-start text-sm-center text-sm-end d-table-cell align-bottom">
                                                <div class="row">
                                                    <div class="col-5 d-sm-none fw-bold text-truncate">
                                                        Expiry Date: 
                                                    </div>
                                                    <div class="col-7 col-sm-12">
                                                        '. $expiryDate .'
                                                        <div class="hidden_msg" id="error_expiry_date_'. $order->getProduct()->getId() .'">
                                                            Required Field
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-3 mb-3 mb-sm-0 text-sm-start text-sm-center d-table-cell align-bottom">
                                                <div class="row">
                                                    <div class="col-5 d-sm-none fw-bold text-truncate">
                                                        Unit Price: 
                                                    </div>
                                                    <div class="col-7 col-sm-12">
                                                        '. $unitPrice .'
                                                        <div class="hidden_msg" id="error_price_'. $order->getProduct()->getId() .'">
                                                            Required Field
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-3 mb-3 mb-sm-0 d-table-cell align-bottom">
                                                <div class="row">
                                                    <div class="col-5 d-sm-none fw-bold text-truncate">
                                                        Qty: 
                                                    </div>
                                                    <div class="col-7 col-sm-12">
                                                        '. $qty .'
                                                        <div class="hidden_msg" id="error_qty_'. $order->getProduct()->getId() .'">
                                                            Required Field
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-4 mb-3 mb-sm-0 fw-bold d-sm-block d-block d-sm-table-cell align-bottom '. $opacity .'">
                                                <div class="row">
                                                    <div class="col-5 d-sm-none fw-bold text-truncate">
                                                        Total: 
                                                    </div>
                                                    <div class="col-7 col-sm-12 text-end">
                                                        '. $currency .' '. number_format($order->getUnitPrice() * $quantity,2) .'
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-2 text-end text-sm-start fw-bold d-table-cell align-bottom '. $opacity .'">
                                                <button
                                                    type="button"
                                                    class="bg-transparent border-0 text-secondary"
                                                    data-bs-html="true"
                                                    data-bs-trigger="hover"
                                                    data-bs-container="body" 
                                                    data-bs-toggle="popover" 
                                                    data-bs-placement="top" 
                                                    data-bs-content="'. $popover .'"-center
                                                >
                                                    <i class="fa-solid fa-circle-info"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Status -->
                                <div class="row">
                                    <div class="col-12">';

                                        if(
                                            ($order->getProduct()->getExpiryDateRequired() == 1 && $order->getExpiryDate() != null) ||
                                            ($order->getProduct()->getExpiryDateRequired() == 0)
                                        ) {

                                            $response .=  $badgeConfirm;
                                        }

                                        $response .= $badgePending . $badgeCancelled . $clinicStatus . $badgeShipped .
                                        $badgeDeliveredPending . $badgeDeliveredCorrect . $badgeDeliveredIncorrect;

                                        $response .= '
                                            </div>
                                        </div>';
                        }

                    $response .= '    
                    </div>
                    <!-- Chat -->
                    <div class="col-12 col-md-3 col-cell p-0 border-bottom border-right">
                        <table class="table table-borderless h-100 mb-0">
                            <tr>
                                <td class="link-secondary table-primary border-bottom" style="height: 30px; background: #f4f8fe">
                                    '. $this->encryptor->decrypt($order->getOrders()->getClinic()->getClinicName()) .'
                                </td>
                            </tr>
                            <tr>
                                <td 
                                    class="border-bottom position-relative p-0" 
                                    id="distributor_chat_container"
                                >
                                    '. $messages .'
                                </td>
                            </tr>
                            <tr>
                                <td style="height: 30px">
                                    <div class="input-group">
                                        <input 
                                            type="text" 
                                            id="chat_field" 
                                            class="form-control form-control-sm border-0"  
                                            autocomplete="off"
                                            data-distributor-id="'. $orders[0]->getDistributor()->getId() .'"
                                            data-order-id="'. $orderId .'"
                                            data-clinic-id="0"
                                        />
                                        <button 
                                            type="button" 
                                            class="btn btn-light btn-sm chat-send" 
                                            id="btn_chat_send"
                                            data-order-id="'. $orderId .'"
                                            data-distributor-id="'. $orders[0]->getDistributor()->getId() .'"
                                        >
                                            <i class="fa-solid fa-paper-plane me-0 me-md-2 text-primary"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </form>';

        return new JsonResponse($response);
    }

    #[Route('/distributors/orders', name: 'distributor_get_order_list')]
    public function distributorGetOrdersAction(Request $request): Response
    {
        $data = $request->request;
        $distributorId = $this->getUser()->getDistributor()->getId();
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $orders = $this->em->getRepository(Orders::class)->findByDistributor(
            $distributor,
            $data->get('clinic_id'),
            $data->get('status_id'),
            $data->get('date')
        );
        $results = $this->pageManager->paginate($orders[0], $request, self::ITEMS_PER_PAGE);
        $statuses = $this->em->getRepository(Status::class)->findAll();
        $clinics = $this->em->getRepository(OrderItems::class)->findClinicsByDistributorOrders($distributorId);

        $html = '
        <div class="col-12">
            <div class="row">
                <div class="col-12 text-center mt-1 pt-3 pb-3" id="order_header">
                    <h4 class="text-primary text-truncate">Manage Fluid Orders</h4>
                </div>
            </div>';

        $clinicsSelect = '
                 <select class="form-control me-2 clinic_select">';

        $clinicsSelect .= '
                 <option value = "">Clinic</option>
                    ';

        foreach ($clinics as $clinic){

            $clinicsSelect .= '
                    <option value = "'. $clinic->getOrders()->getClinic()->getId() .'">
                        '. $this->encryptor->decrypt($clinic->getOrders()->getClinic()->getClinicName()) .'
                     </option>';
        };

        $clinicsSelect .= '
                </select>';

        $statusSelect = '
                <select class="form-control me-2 ms-3 status_select">';

        $statusSelect .= '
                <option value = "">Status</option>
                    ';

        foreach ($statuses as $status){

            $statusSelect .= '
                    <option value = "'. $status->getId() .'">
                    '. $status->getStatus() .'
                    </option>
                        ';
        };

        $statusSelect .= '
                </select>';

        $html .= '
        <!-- Actions Row -->
        <div class="row bg-light border-left border-right border-top border-bottom border-top">
            <div class="col-12 col-sm-12 col-md-8 offset-sm-0 offset-md-2 d-flex justify-content-center pt-3 pb-3 d-none d-sm-flex">
                '. $clinicsSelect .'
                <input 
                    type="text" 
                    class="form-control ms-2 datepicker" 
                    name="datetimes" 
                    autocomplete="off"
                    id="datepicker"
                    placeholder="Date"
                    value="Date"
                >
        
                '. $statusSelect .'
        
                <button 
                    class="btn btn-primary ms-3 clinic_search"
                    data-distributor-id="'. $distributor->getId() .'"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                <button 
                    class="btn btn-secondary ms-3 clinic_refresh"
                    data-distributor-id="'. $distributor->getId() .'"
                >
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
            
            <div class="col-12 d-block d-sm-none">
                <div class="row border-bottom" id="filter_row_toggle">
                    <div role="button" class="col-12 text-danger pt-3 pb-3" id="filter_orders">
                        <i class="fa-solid fa-filter me-3"></i>Filter Orders
                    </div>
                </div>
                <div class="row hidden border-bottom" id="filter_row">
                    <div class="col-12 col-sm-5 d-flex pt-3 d-block d-sm-none">
                        '. $clinicsSelect .'
                    </div>
                    <div class="col-12 col-sm-5 d-flex pt-3 d-block d-sm-none">
                        <input 
                            type="text" 
                            class="form-control datepicker" 
                            name="datetimes" 
                            id="datepicker_mobile" 
                            autocomplete="off"
                            value="Date"
                            placeholder="Date"
                        >
                    </div>
                    <div class="col-12 col-sm-5 d-flex pt-3 d-block d-sm-none">
                        '. $statusSelect .'
                    </div>
                    <div class="col-12 pt-3 d-block d-sm-none">
                        <button 
                            class="btn btn-primary clinic_search w-sm-100 text-center"
                            data-distributor-id="'. $distributor->getId() .'"
                        >
                            <i class="fa-solid fa-magnifying-glass me-3"></i>
                            SEARCH
                        </button>
                    </div>
                    <div class="col-12 pt-3 pb-3 d-block d-sm-none">
                        <button 
                            class="btn btn-secondary clinic_refresh w-sm-100 text-center"
                            data-distributor-id="'. $distributor->getId() .'"
                        >
                            <i class="fa-solid fa-rotate me-3"></i>
                            CANCEL
                        </button>
                    </div>
                </div>
            </div>
        </div>';

        if(count($orders[1]) > 0) {

            $html .= '
            <!-- Orders -->
            <div class="row d-none d-xl-block">
                <div class="col-12 bg-light border-bottom border-right border-left">
                    <div class="row">
                        <div class="col-12 col-sm-1 pt-3 pb-3 text-primary fw-bold">
                            #Id
                        </div>
                        <div class="col-12 col-sm-4 pt-3 pb-3 text-primary fw-bold">
                            Clinic
                        </div>
                        <div class="col-12 col-sm-2 pt-3 pb-3 text-primary fw-bold">
                            Total
                        </div>
                        <div class="col-12 col-sm-2 pt-3 pb-3 text-primary fw-bold">
                            Date
                        </div>
                        <div class="col-12 col-sm-2 pt-3 pb-3 text-primary fw-bold">
                            Status
                        </div>
                    </div>    
                </div>
            </div>';
        }

        $html .= '
        <div class="row">
            <div class="col-12 border-right bg-light col-cell border-left border-right border-bottom">';

                if(count($orders[1]) > 0) {

                    foreach ($results as $order) {

                        $html .= '
                        <!-- Orders -->
                        <div class="row border-bottom">
                            <div class="col-4 col-sm-2 d-block d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">#Id: </div>
                            <div class="col-8 col-sm-10 col-xl-1 pt-3 pb-3 border-list text-truncate">
                                ' . $order->getId() . '
                            </div>
                            <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Clnic: </div>
                            <div class="col-8 col-sm-10 col-xl-4 pt-3 pb-3 text-truncate border-list">
                                ' . $this->encryptor->decrypt($order->getClinic()->getClinicName()) . '
                            </div>
                            <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Total: </div>
                            <div class="col-8 col-sm-10 col-xl-2 pt-3 pb-3 border-list">
                                AED' . number_format($order->getTotal(),2) . '
                            </div>
                            <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Date: </div>
                            <div class="col-8 col-sm-10 col-xl-2 pt-3 pb-3 border-list">
                                ' . $order->getCreated()->format('Y-m-d') . '
                            </div>
                            <div class="col-4 col-sm-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Status: </div>
                            <div class="col-8 col-sm-10 col-xl-2 pt-3 pb-3 border-list">
                                ' . ucfirst($order->getOrderStatuses()[0]->getStatus()->getStatus()) . '
                            </div>
                            <div class="col-12 col-sm-1 pt-3 pb-3 text-end">
                                <a 
                                    href="' . $this->getParameter('app.base_url') . '/distributors/order/' . $order->getId() . '" 
                                    class="pe-0 pe-sm-3 order_detail_link"
                                    data-order-id="' . $order->getId() . '"
                                    data-distributor-id="' . $distributor->getId() . '"
                                    data-clinic-id="' . $order->getClinic()->getId() . '"
                                >
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </div>
                        </div>';
                    }

                } else {

                    $html .= '
                    <div class="row">
                        <div class="col-12 text-center mt-5 mb-5 pt-3 pb-3 text-center">
                            You don\'t have any orders available. 
                        </div>
                    </div>';
                }

                $html .= '
                </div>
            </div>
        </div>';

        // Pagination
        $pagination = $this->getPagination(
            $request->request->get('page_id'), $results, '/distributors/orders/',
            $distributorId, 'distributor'
        );

        $response = [
            'html' => $html,
            'pagination' => $pagination
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/order/', name: 'clinic_get_order_details')]
    public function clinicOrderDetailAction(Request $request): Response
    {
        $data = $request->request;

        if(is_array($data->get('permissions'))){

            $permissions = $data->get('permissions');

        } else {

            $permissions = json_decode($data->get('permissions'), true);
        }

        $orderId = $data->get('order_id');
        $distributorId = $data->get('distributor_id');

        if($data->get('order_id') == null && $data->get('distributor_id') == null){

            $orderId = $request->get('order_id');
            $distributorId = $request->get('distributor_id');
        }

        $statuses = $this->em->getRepository(Status::class)->findByIds(['6','7','8']);
        $chatMessages = $this->em->getRepository(ChatMessages::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $orders = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributorId

        ]);
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $dateSent = '';
        $messages = $this->forward('App\Controller\ChatMessagesController::getMessages', [
            'chatMessages' => $chatMessages,
            'dateSent' => $dateSent,
            'distributor' => false,
            'clinic' => true,
        ])->getContent();

        $isAuthorised = true;

        if(is_array($permissions) && !in_array(5, $permissions)){

            $isAuthorised = false;
        }

        $response = '
        <form name="form_distributor_orders" class="row" id="form_distributor_orders" method="post">
            <input type="hidden" name="order_id" value="'. $orders[0]->getOrders()->getId() .'">
            <div class="col-12">
                <div class="row">
                    <div class="col-12 text-center pt-3 pb-3" id="order_header">
                        <h4 class="text-primary">'. $orders[0]->getPoNumber() .'</h4>
                        <span class="text-primary">
                            '. $this->encryptor->decrypt($orders[0]->getOrders()->getClinic()->getClinicName()) .'
                        </span>
                    </div>
                </div>
                <!-- Actions Row -->
                <div class="row">
                    <div 
                        class="bg-light border-left border-right col-12 d-flex justify-content-center border-bottom border-top pt-3 pb-3"
                         id="order_action_row"
                    >
                    <a 
                        href="#" 
                        class="orders_link" 
                        data-order-id="' . $orderId . '"
                        data-distributor-id="' . $distributorId . '"
                        data-clinic-id="' . $orders[0]->getOrders()->getClinic()->getId() . '"
                    >
                        <i class="fa-solid fa-angles-left me-5 me-md-2"></i>
                        <span class=" d-none d-md-inline-block pe-4">Back To Orders</span>
                    </a>';

                    // If order is preparing for shipping or later
                    $orderStatusId = $orderStatus->getStatus()->getId();
                    if($orderStatusId < 5 && $orderStatusId != 9) {

                        $response .= '
                        <a 
                            href="#" 
                            class="refresh-clinic-order" 
                            data-order-id="' . $orderId . '"
                            data-distributor-id="' . $distributorId . '"
                            data-clinic-id="' . $orders[0]->getOrders()->getClinic()->getId() . '"
                        >
                            <i class="fa-solid fa-arrow-rotate-right me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Refresh Order</span>
                        </a>
                        ' . $this->btnConfirmOrder($orders, $orderId, $distributorId);

                    } else {

                        // Status Delivered & Shipped
                        if($orderStatusId == 6 || $orderStatusId == 7) {

                            $statusString = '
                            <select 
                                data-distributor-id="'. $distributorId .'" 
                                data-order-id="'. $orderId .'" 
                                id="order_status" 
                                class="status-dropdown"
                            >';

                            foreach ($statuses as $status) {

                                $selected = '';
                                $disabled = '';
                                $optionId = '';
                                $dataOrderId = '';
                                $dataDistributorId = '';
                                $isAccepted = 0;
                                $isRejected = 0;
                                $isQuantityAdjust = true;

                                // Disable Close Option
                                $canClose = false;
                                $itemCount = count($orders);

                                foreach($orders as $order){

                                    if($order->getIsQuantityAdjust() == 1){

                                        $isQuantityAdjust = false;
                                    }

                                    if($order->getIsAcceptedOnDelivery() == 1){

                                        $isAccepted += 1;
                                    }

                                    if($order->getIsRejectedOnDelivery() == 1){

                                        $isRejected += 1;
                                    }
                                }

                                // If all items are either rejected or accepted
                                if(
                                    $isRejected + $isAccepted == $itemCount &&
                                    $isQuantityAdjust = true && $isAuthorised
                                ){

                                    $canClose = true;
                                }

                                // Check if user has permission to change status once shipped
                                if($status->getId() == 7 && !$isAuthorised){

                                    $disabled = 'disabled';
                                }

                                // Select option is closed & order is shipped
                                if($status->getId() == 8 && $orderStatusId == 6){

                                    $disabled = 'disabled';
                                }

                                // Select option is closed & order is delivered
                                if($status->getId() == 8 && $orderStatusId == 7){

                                    $optionId = 'id="close_order" ';
                                    $dataOrderId = 'data-order-id="'. $orderId .'" ';
                                    $dataDistributorId = 'data-distributor-id="'. $distributorId .'" ';
                                }

                                if($status->getId() == 8 && !$canClose){

                                    $disabled = 'disabled ';
                                }

                                if ($status->getId() == $orderStatusId) {

                                    $selected = 'selected ';
                                }

                                $statusString .= '
                                <option
                                   
                                    value="' . $status->getId() . '" 
                                    ' . $selected . $disabled . $optionId . $dataOrderId . $dataDistributorId .'
                                >
                                    ' . $status->getStatus() . '
                                </option>';
                            }

                            $statusString .= '</select>';

                        } else {

                            $statusString = $orderStatus->getStatus()->getStatus();
                        }

                        $response .= '
                        <span class="text-primary pe-4">
                            <b class="pe-2 d-none d-md-inline-block">Order Status:</b>
                            '. $statusString .'
                        </span>
                        <a 
                            href="'. $this->getParameter('app.base_url') .'/pdf_po.php?pdf='. $orderStatus->getPoFile() .'"
                            id="btn_download_po"
                            data-pdf="'. $orderStatus->getPoFile() .'"
                            target="_blank"
                        >
                            <i class="fa-solid fa-file-pdf me-5 me-md-2"></i>
                            <span class="d-none d-md-inline-block pe-4">Download</span>
                        </a>';
                    }

                    $response .= '
                    </div>
                </div>
                <!-- Products -->
                <div class="row border-0 bg-light">
                    <div class="col-12 col-md-9 border-right col-cell border-left border-right border-bottom">
                        <input type="hidden" name="distributor_id" value="'. $distributorId .'">';

                        foreach($orders as $order) {

                            $expiry = '';
                            $opacity = '';
                            $expiryDisplay = '';
                            $currency = $order->getOrders()->getClinic()->getCountry()->getCurrency();

                            // Don't show cancelled on delivery
                            if($order->getIsCancelled() == 1 && $orderStatusId == 7){

                                continue;
                            }

                            if(!empty($order->getExpiryDate())){

                                $expiry = $order->getExpiryDate()->format('Y-m-d');
                            }

                            // Status badges
                            if($order->getIsAccepted() == 1){

                                $badgeAccept = 'bg-success badge-success-filled-sm';

                            } else {

                                $badgeAccept = 'badge-success-outline-only badge-success-sm';
                            }

                            if($order->getIsRenegotiate() == 1){

                                $badgeRenegotiate = 'bg-warning badge-warning-filled-sm';

                            } else {

                                $badgeRenegotiate = 'badge-warning-outline-only badge-warning-sm';
                            }

                            if($order->getIsCancelled() == 1){

                                $badgeCancelled = 'bg-danger badge-danger-filled-sm';
                                $opacity = 'opacity-50';

                            } else {

                                $badgeCancelled = 'badge-danger-outline-only badge-danger-sm';
                            }

                            if($order->getExpiryDate() == null){

                                $expiryDisplay = ' style="display:none !important"';
                            }

                            // Display the qty delivered field if delivered
                            $colExpDate = 3;
                            $colQtyDelivered = '
                            <div class="col-12 col-sm-1 d-table-cell align-bottom alert-text-grey">
                                <div class="row pb-2">
                                    <div class="col-5 d-block d-sm-none fw-bold text-truncate">
                                        Qty Delivered:
                                    </div>
                                    <div class="col-7 col-sm-12">
                                        '. $order->getQuantityDelivered() .'
                                    </div>
                                </div>
                            </div>';

                            // Delivered
                            if($orderStatusId == 7){

                                $colExpDate = 4;

                                if($order->getIsQuantityAdjust() == 1){

                                    $colQtyDelivered = '
                                    <div class="col-12 col-sm-2 d-table-cell align-bottom text-start text-sm-end alert-text-grey">
                                        <div class="row">
                                            <div class="col-5 d-sm-none fw-bold text-truncate">
                                                Qty Delivered:
                                            </div>
                                            <div class="col-7 col-sm-12 d-block d-sm-table-cell align-bottom text-end alert-text-grey">
                                                <input 
                                                    type="number" 
                                                    class="form-control form-control-sm order-qty-delivered" 
                                                    value="'. $order->getQuantityDelivered() .'"
                                                    data-qty-delivered-id="'. $order->getId() .'"
                                                    
                                                >
                                            </div>
                                        </div>
                                    </div>';
                                }
                            }

                            $response .= '
                            <!-- Product Name and Qty -->
                            <div class="row">
                                <!-- Product Name -->
                                <div class="col-12 col-md-5 text-center text-sm-start pt-3 pb-3 '. $opacity .'">
                                    <span class="info">
                                        '. $this->encryptor->decrypt($order->getDistributor()->getDistributorName()) .'
                                    </span>
                                    <h6 class="fw-bold text-center text-sm-start text-primary lh-base">
                                        '. $order->getName() .'
                                    </h6>
                                </div>
                                <!-- Expiry Date -->
                                <div class="col-12 col-md-7 pt-md-3 pb-md-3 d-table '. $opacity .'">
                                    <div class="row d-table-row">
                                        <div class="col-12 col-sm-3 col-md-'. $colExpDate .' text-truncate text-start text-md-end d-table-cell align-bottom alert-text-grey" '. $expiryDisplay .'>
                                            <div class="row pb-2">
                                                <div class="col-5 d-block d-sm-none fw-bold text-truncate">
                                                    Expiry Date:
                                                </div>
                                                <div class="col-7 col-sm-12 ps-sm-0">
                                                    '. $expiry .'
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-3 text-truncate d-table-cell align-bottom alert-text-grey">
                                            <div class="row pb-2">
                                                <div class="col-5 d-block d-sm-none fw-bold text-truncate">
                                                    Unit Price:
                                                </div>
                                                <div class="col-7 col-sm-12">
                                                    ' . $currency .' '. number_format($order->getUnitPrice(),2) .'
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-1 text-truncate d-table-cell align-bottom alert-text-grey">
                                            <div class="row pb-2">
                                                <div class="col-5 d-block d-sm-none fw-bold text-truncate">
                                                    Qty Ordered:
                                                </div>
                                                <div class="col-7 col-sm-12">
                                                    '. $order->getQuantity() .'
                                                </div>
                                            </div>
                                        </div>';

                                        if($orderStatusId > 5)
                                        {
                                            $response .= $colQtyDelivered;
                                        }

                                        $popover = '<b>Ordered By</b> '. $this->encryptor->decrypt($order->getOrderPlacedBy()) .'<br>';

                                        if($order->getOrderReceivedBy() != null){

                                            $popover .= '
                                            <b>Recieved By</b> '. $this->encryptor->decrypt($order->getOrderReceivedBy());
                                        }

                                        if($order->getRejectReason() != null){

                                            $popover .= '
                                            <br><br>
                                            <b>Reason For Rejection</b><br>
                                            '. $order->getRejectReason();
                                        }

                                        $response .= '
                                        <div class="col-12 col-sm-4 text-truncate text-sm-end fw-bold d-table-cell align-bottom alert-text-grey">
                                            <div class="row pb-2">
                                                <div class="col-5 d-block d-sm-none fw-bold text-truncate">
                                                    Total:
                                                </div>
                                                <div class="col-7 col-sm-12">
                                                    '. $currency .' '. number_format($order->getUnitPrice() * $order->getQuantityDelivered(),2) .'
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-2 d-table-cell align-bottom text-end pb-2">
                                            <button
                                                type="button"
                                                class="bg-transparent border-0 text-secondary"
                                                data-bs-html="true"
                                                data-bs-trigger="hover"
                                                data-bs-container="body" 
                                                data-bs-toggle="popover" 
                                                data-bs-placement="top" 
                                                data-bs-content="'. $popover .'"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Actions -->
                                <div class="col-12 pb-2">';

                                    if($order->getIsConfirmedDistributor() == 1) {

                                        // If order is preparing for shipping or later
                                        if($order->getOrders()->getOrderStatuses()[0]->getStatus()->getId() >= 5){

                                            // Delivered status, check quantity delivered == quantity ordered
                                            if($orderStatusId == 7) {

                                                // Accept CTA
                                                if ($order->getIsAcceptedOnDelivery() == 1) {

                                                    if($isAuthorised){

                                                        $btnAccept = '
                                                        <a href="#" 
                                                            class="badge float-end ms-2 text-light border-success 
                                                            bg-success btn-item-accept badge-success-filled-sm text-truncate"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                        >Accept</a>';

                                                    } else {

                                                        $btnAccept = '
                                                        <span 
                                                            class="badge float-end ms-2 text-truncate bg-disabled"
                                                        >Accept</span>';
                                                    }

                                                } else {

                                                    if($isAuthorised){

                                                        $btnAccept = '
                                                        <a href="#" 
                                                            class="badge float-end ms-2 text-success border-success 
                                                            badge-success-outline-only btn-item-accept badge-success-sm text-truncate"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                        >Accept</a>';

                                                    } else {

                                                        $btnAccept = '
                                                        <span 
                                                            class="badge float-end ms-2 text-truncate bg-disabled"
                                                        >Accept</span>';
                                                    }
                                                }

                                                // Reject CTA
                                                if ($order->getIsRejectedOnDelivery() == 1) {

                                                    if($isAuthorised){

                                                        $btnReject = '
                                                        <a href="#" 
                                                            class="badge float-end ms-2 text-light border-danger bg-danger 
                                                            btn-item-reject badge-danger-filled-sm text-truncate"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modal_reject_item"
                                                        >Reject</a>';

                                                    } else {

                                                        $btnReject = '
                                                        <span 
                                                            class="badge float-end ms-2 text-truncate bg-disabled"
                                                        >Reject</span>';
                                                    }

                                                } else {

                                                    if($isAuthorised){

                                                        $btnReject = '
                                                        <a href="#" 
                                                            class="badge float-end ms-2 text-danger border-danger 
                                                            badge-danger-outline-only btn-item-reject badge-danger-sm text-truncate"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modal_reject_item"
                                                        >Reject</a>';

                                                    } else {

                                                        $btnReject = '
                                                        <span href="#" 
                                                            class="badge float-end ms-2 text-truncate bg-disabled"
                                                        >Reject</span>';
                                                    }
                                                }

                                                // Qty CTA
                                                if ($order->getIsQuantityAdjust() == 1) {

                                                    if($isAuthorised){

                                                        $btnQty = '
                                                        <a href="#" 
                                                            class="badge float-end ms-2 text-light border-warning 
                                                            bg-warning btn-item-qty badge-warning-filled-sm text-truncate"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                        >Adjust Quantity</a>';

                                                    } else {

                                                        $btnQty = '
                                                        <span 
                                                            class="badge float-end ms-2 text-truncate bg-disabled"
                                                        >Adjust Quantity</span>';
                                                    }

                                                } else {

                                                    if($isAuthorised){

                                                        $btnQty = '
                                                        <a href="#" 
                                                            class="badge float-end ms-2 text-warning border-warning 
                                                            badge-warning-outline-only btn-item-qty badge-warning-sm text-truncate"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                        >Adjust Quantity</a>';

                                                    } else {

                                                        $btnQty = '
                                                        <span 
                                                            class="badge float-end ms-2 text-truncate bg-disabled"
                                                            data-order-id="' . $orderId . '"
                                                            data-item-id="' . $order->getId() . '"
                                                        >Adjust Quantity</span>';
                                                    }
                                                }

                                                $response .= $btnAccept . $btnQty . $btnReject;

                                            // Closed orders
                                            } elseif($orderStatusId == 8){

                                                $btnStatus = '';

                                                // Accept
                                                if ($order->getIsAcceptedOnDelivery() == 1) {

                                                    if($isAuthorised){

                                                        $btnStatus = '
                                                        <span 
                                                            class="badge float-end ms-2 text-light border-success 
                                                            bg-success btn-item-accept w-sm-100 p-2 p-sm-1 badge-success-filled-sm text-truncate"
                                                        >Accepted</span>';

                                                    } else {

                                                        $btnStatus = '
                                                        <span 
                                                            class="badge float-end ms-2 w-sm-100 p-2 p-sm-1 text-truncate bg-disabled"
                                                        >Accepted</span>';
                                                    }

                                                }

                                                // Reject
                                                if ($order->getIsRejectedOnDelivery() == 1) {

                                                    $btnStatus = '
                                                    <span 
                                                        class="badge float-end ms-2 text-light border-danger bg-danger btn-item-reject"
                                                    >Rejected</span>';
                                                }

                                                $response .= $btnStatus;

                                            // Shipped
                                            } else {

                                                if ($order->getIsAccepted() == 1) {

                                                    if($isAuthorised){

                                                        $response .= '
                                                        <span 
                                                            class="badge float-end ms-2 text-success border border-success text-light bg-success w-sm-100 p-2 p-sm-1"
                                                        >Accepted</span>';

                                                    } else {

                                                        $response .= '
                                                        <span 
                                                            class="badge float-end ms-2 text-success border border-success text-light bg-success w-sm-100 p-2 p-sm-1 bg-disabled"
                                                        >Accepted</span>';
                                                    }
                                                }

                                                if ($order->getIsCancelled() == 1) {

                                                    if($isAuthorised){

                                                        $response .= '
                                                        <span 
                                                            class="badge float-end ms-2 text-success border border-danger text-light bg-danger badge-danger-sm"
                                                        >Cancelled</span>';

                                                    } else {

                                                        $response .= '
                                                        <span 
                                                            class="badge float-end ms-2 text-success border border-danger text-light bg-danger badge-danger-sm bg-disabled"
                                                        >Cancelled</span>';
                                                    }
                                                }
                                            }

                                        // Accept, Renegotiate and Cancel
                                        } else {

                                            $response .= '
                                            <a href="#" 
                                                class="badge float-end ms-2 text-success border-1 text-light order_item_accept ' . $badgeAccept . '"
                                                data-order-id="' . $orderId . '"
                                                data-item-id="' . $order->getId() . '"
                                                id="order_item_accept_' . $order->getId() . '"
                                            >Accept</a>
                                            <a href="#" 
                                                class="badge float-end ms-2 text-warning border-1 text-light order_item_renegotiate ' . $badgeRenegotiate . '"
                                                data-order-id="' . $orderId . '"
                                                data-item-id="' . $order->getId() . '"
                                                id="order_item_renegotiate_' . $order->getId() . '"
                                            >Renegotiate</a>
                                            <a href="#" 
                                                class="badge float-end text-light order_item_cancel ' . $badgeCancelled . '"
                                                data-order-id="' . $orderId . '"
                                                data-item-id="' . $order->getId() . '"
                                                id="order_item_cancel_' . $order->getId() . '"
                                            >Cancel</a>';
                                        }

                                    // Pending Distributor
                                    } else {

                                        $response .= '<span class="badge bg-dark-grey float-end w-sm-100 p-2 p-sm-1 text-truncate">Pending Distributor Confirmation</span>';
                                    }

                                $response .= '
                                </div>
                            </div>';
                        }

                    $response .= '    
                    </div>
                    <!-- Chat -->
                    <div class="col-12 col-md-3 col-cell p-0 border-bottom border-right">
                        <table class="table table-borderless h-100 mb-0">
                            <tr>
                                <td class="link-secondary table-primary border-bottom" style="height: 30px; background: #f4f8fe">
                                    '. $this->encryptor->decrypt($order->getOrders()->getClinic()->getClinicName()) .'
                                </td>
                            </tr>
                            <tr>
                                <td 
                                    class="border-bottom position-relative p-0" 
                                    id="distributor_chat_container"
                                >
                                    '. $messages .'
                                </td>
                            </tr>
                            <tr>
                                <td style="height: 30px">
                                    <div class="input-group">
                                        <input 
                                            type="text" 
                                            id="chat_field" 
                                            class="form-control form-control-sm border-0"  
                                            autocomplete="off"
                                            data-distributor-id="'. $distributorId .'"
                                            data-order-id="'. $orderId .'"
                                            data-clinic-id="'. $orders[0]->getOrders()->getClinic()->getId() .'"
                                        />
                                        <button 
                                            type="button" 
                                            class="btn btn-light btn-sm chat-send" 
                                            id="btn_chat_send"
                                            data-order-id="'. $orderId .'"
                                            data-distributor-id="'. $distributorId .'"
                                        >
                                            <i class="fa-solid fa-paper-plane me-0 me-md-2 text-primary"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Reject Item modal -->
        <div class="modal fade" id="modal_reject_item" tabindex="-1" aria-labelledby="modal_reject_item" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form name="form_reject_item" method="post">
                        <input type="hidden" name="reject_item_id" id="reject_item_id">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <button type="button" class="btn-close float-end me-2 position-absolute end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                                <!-- Reject -->
                                <div class="col-12">
                                    <label class="pt-4">Reason For Rejection*</label>
                                    <textarea 
                                        id="reject_reason"
                                        type="text" 
                                        name="reject_reason"
                                        class="form-control"
                                    ></textarea>
                                    <div class="hidden_msg" id="error_reject_reason">
                                        Required Field
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">CANCEL</button>
                            <button 
                                type="submit" 
                                class="btn btn-primary" 
                                data-item-id="'. $order->getId() .'"
                            >SAVE</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/orders', name: 'clinic_get_order_list')]
    public function clinicGetOrdersAction(Request $request): Response
    {
        $clinic = $this->getUser()->getClinic();
        $currency = $clinic->getCountry()->getCurrency();
        $orders = $this->em->getRepository(Orders::class)->findClinicOrders(
            $clinic->getId(),$request->request->get('distributor_id'),
            $request->request->get('date'), $request->request->get('status')
        );
        $results = $this->pageManager->paginate($orders[0], $request, self::ITEMS_PER_PAGE);
        $distributors = $this->em->getRepository(OrderItems::class)->findDistributorsByClinicOrders($clinic->getId());
        $statuses = $this->em->getRepository(Status::class)->findAll();

        $html = '
        <div class="col-12">
            <div class="row">
                <div class="col-12 text-center pt-3 pb-3 form-control-bg-grey" id="order_header">
                    <h4 class="text-primary text-truncate">Manage Fluid Orders</h4>
                    <span class="text-primary d-none d-sm-inline">
                        Manage All Your Orders In One Place
                    </span>
                </div>
            </div>';

            $distributorsSelect = '
            <select class="form-control me-2 distributor_select">';

            $distributorsSelect .= '
            <option value = "">Distributor</option>
                        ';

            foreach ($distributors as $distributor){

                $distributorsSelect .= '
                <option value = "'. $distributor->getDistributor()->getId() .'">
                                '. $this->encryptor->decrypt($distributor->getDistributor()->getDistributorName()) .'
                            </option>
                            ';
            };

            $distributorsSelect .= '
            </select>';

            $statusSelect = '
            <select class="form-control me-2 ms-3 status_select">';

            $statusSelect .= '
            <option value = "">Status</option>
                        ';

            foreach ($statuses as $status){

                $statusSelect .= '
                <option value = "'. $status->getId() .'">
                    '. $status->getStatus() .'
                </option>
                            ';
            };

            $statusSelect .= '
            </select>';

            $html .= '
            <!-- Filters -->
            <div class="row bg-light border-left border-right border-top">
                <div class="col-12 col-sm-12 col-md-8 offset-sm-0 offset-md-2 d-flex justify-content-center pt-3 pb-3 d-none d-sm-flex">
                    '. $distributorsSelect .'
                    <input 
                        type="text" 
                        class="form-control ms-2 datepicker" 
                        name="datetimes" 
                        autocomplete="off"
                        id="datepicker"
                        placeholder="Date"
                        value="Date"
                    >
            
                    '. $statusSelect .'
            
                    <button 
                        class="btn btn-primary ms-3 distributor_search"
                        data-clinic-id="'. $clinic->getId() .'"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <button 
                        class="btn btn-secondary ms-3 distributor_refresh"
                        data-clinic-id="'. $clinic->getId() .'"
                    >
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
                
                <div class="col-12 d-block d-sm-none">
                    <div class="row border-bottom">
                        <div role="button" class="col-12 text-danger pt-3 pb-3" id="filter_orders">
                            <i class="fa-solid fa-filter me-3"></i>Filter Orders
                        </div>
                    </div>
                    <div class="row hidden border-bottom" id="filter_row">
                        <div class="col-12 col-sm-5 d-flex pt-3 d-block d-sm-none">
                            '. $distributorsSelect .'
                        </div>
                        <div class="col-12 col-sm-5 d-flex pt-3 d-block d-sm-none">
                            <input 
                                type="text" 
                                class="form-control datepicker" 
                                name="datetimes" 
                                id="datepicker_mobile" 
                                autocomplete="off"
                                value="Date"
                                placeholder="Date"
                            >
                        </div>
                        <div class="col-12 col-sm-5 d-flex pt-3 d-block d-sm-none">
                            '. $statusSelect .'
                        </div>
                        <div class="col-12 pt-3 d-block d-sm-none">
                            <button 
                                class="btn btn-primary distributor_search w-sm-100 text-center"
                                data-clinic-id="'. $clinic->getId() .'"
                            >
                                <i class="fa-solid fa-magnifying-glass me-3"></i>
                                SEARCH
                            </button>
                        </div>
                        <div class="col-12 pt-3 pb-3 d-block d-sm-none">
                            <button 
                                class="btn btn-secondary distributor_refresh w-sm-100 text-center"
                                data-clinic-id="'. $clinic->getId() .'"
                            >
                                <i class="fa-solid fa-rotate me-3"></i>
                                CANCEL
                            </button>
                        </div>
                    </div>
                </div>
            </div>';

            if(count($results) > 0) {

                $html .= '
                <!-- Orders -->
                <div class="row d-none d-xl-block">
                    <div class="col-12 bg-light border-top border-bottom border-right border-left">
                        <div class="row">
                            <div class="col-12 col-sm-1 pt-3 pb-3 text-primary fw-bold">
                                #Id
                            </div>
                            <div class="col-12 col-sm-4 pt-3 pb-3 text-primary fw-bold">
                                Distributor
                            </div>
                            <div class="col-12 col-sm-2 pt-3 pb-3 text-primary fw-bold">
                                Total
                            </div>
                            <div class="col-12 col-sm-2 pt-3 pb-3 text-primary fw-bold">
                                Date
                            </div>
                            <div class="col-12 col-sm-2 pt-3 pb-3 text-primary fw-bold">
                                Status
                            </div>
                        </div>    
                    </div>
                </div>      
                <div class="row">
                    <div class="col-12 border-right bg-light col-cell border-left border-right border-bottom">';

                foreach ($results as $order) {

                    $html .= '
                    <!-- Orders -->
                    <div class="row border-bottom t-row">
                        <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">#Id </div>
                        <div class="col-8 col-md-10 col-xl-1 t-cell text-truncate border-list pt-3 pb-3">
                            ' . $order->getId() . '
                        </div>
                        <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Distributor </div>
                        <div class="col-8 col-md-10 col-xl-4 t-cell text-truncate border-list pt-3 pb-3">
                            ' . $this->encryptor->decrypt($order->getOrderItems()[0]->getDistributor()->getDistributorName()) . '
                        </div>
                        <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Total </div>
                        <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                            ' . $currency .' '. number_format($order->getTotal(),2) . '
                        </div>
                        <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Daste </div>
                        <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                            ' . $order->getCreated()->format('Y-m-d') . '
                        </div>
                        <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Status </div>
                        <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                            ' . ucfirst($order->getOrderStatuses()[0]->getStatus()->getStatus()) . '
                        </div>
                        <div class="col-12 col-sm-1 pt-3 pb-3 text-end border-list">
                            <a 
                                href="' . $this->getParameter('app.base_url') . '/clinics/order/' . $order->getId() . '/' . $order->getOrderStatuses()[0]->getDistributor()->getId() . '" 
                                class="pe-0 pe-sm-3 float-end"
                                id="order_detail_link"
                                data-order-id="' . $order->getId() . '"
                                data-distributor-id="' . $order->getOrderStatuses()[0]->getDistributor()->getId() . '"
                                data-clinic-id="' . $order->getClinic()->getId() . '"
                            >
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                        </div>
                    </div>';
                }

            } else {

                $html .= '
                <div class="row border-left border-right border-top border-bottom bg-light">
                    <div class="col-12 text-center mt-3 mb-3 pt-3 pb-3 text-center">
                        You don\'t have any orders available. 
                    </div>
                </div>';
            }

            $html .= '
                </div>
            </div>
        </div>';

        // Pagination
        $pagination = $this->getPagination(
            $request->request->get('page_id'), $results, '/clinics/orders/',
            $request->request->get('clinic_id'), 'clinic'
        );

        $response = [
            'pagination' => $pagination,
            'html' => $html
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/update-order-item-status', name: 'clinic_update_order_item_status')]
    public function clinicUpdateOrderItemAction(Request $request): Response
    {
        $data = $request->request;
        $orderId = $data->get('order_id');
        $itemId = $data->get('item_id');
        $link = $data->get('link');
        $orderItem = $this->em->getRepository(OrderItems::class)->find($itemId);
        $distributorId = $orderItem->getDistributor()->getId();
        $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
        $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());
        $orderItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $class = '';

        if($link == 'accept'){

            $orderItem->setIsAccepted(1);
            $orderItem->setIsRenegotiate(0);
            $orderItem->setIsCancelled(0);

            $class = 'bg-success badge-success-filled-sm';
        }

        if($link == 'renegotiate'){

            $orderItem->setIsAccepted(0);
            $orderItem->setIsRenegotiate(1);
            $orderItem->setIsCancelled(0);

            $class = 'bg-warning text-light badge-warning-filled-sm';

            //$this->distributorSendNotification($orderId,$distributorId);
        }

        if($link == 'cancelled'){

            $orderItem->setIsAccepted(0);
            $orderItem->setIsRenegotiate(0);
            $orderItem->setIsCancelled(1);

            $class = 'bg-danger text-light badge-danger-filled-sm';
        }

        $orderItem->setOrderPlacedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($orderItem);
        $this->em->flush();

        // Order Status
        $accepted = 0;
        $negotiating = 0;
        $cancelled = 0;
        $statusId = 0;
        $actionRequired = false;

        foreach($orderItems as $item){

            $accepted += $item->getIsAccepted();
            $negotiating += $item->getIsRenegotiate();
            $cancelled += $item->getIsCancelled();

            if($item->getIsAccepted() == 0 && $item->getIsRenegotiate() == 0 && $item->getIsCancelled() == 0) {

                $actionRequired = true;
                $status = 'Pending';

                break;
            }
        }

        // Pending
        if($accepted == 0 && $negotiating == 0 && $cancelled == 0) {

            $statusId = 2;

        // Negotiating
        } elseif($negotiating > 0 && !$actionRequired){

            $statusId = 4;

        // Accepted
        } elseif($accepted > 0 && $negotiating == 0 && $cancelled >= 0 && !$actionRequired){

            $statusId = 1;

        // Cancelled
        } elseif($accepted == 0 && $negotiating == 0 && $cancelled > 0 && !$actionRequired){

            $statusId = 8;

        // Pending
        } elseif($actionRequired){

            $statusId = 2;
        }

        $status = $this->em->getRepository(Status::class)->find($statusId);
        $orderStatus->setStatus($status);

        $this->em->persist($orderStatus);
        $this->em->flush();

        $this->sendNotification(
            $orderItem->getOrders(), $orderItem->getDistributor(),
            $orderItem->getOrders()->getClinic(), 'Order Update', 1, 0
        );

        $btn = $this->btnConfirmOrder($orderItems, $orderId, $distributorId);

        $response = [
            'class' => $class,
            'btn' => $btn
        ];
        $this->generatePpPdfAction($orderId, $distributorId, 'Draft');
        return new JsonResponse($response);
    }

    #[Route('/distributors/update-order-item-status', name: 'distributor_update_order_item_status')]
    public function distributorUpdateOrderItemAction(Request $request): Response
    {
        $orderItem = $this->em->getRepository(OrderItems::class)->find($request->request->get('item_id'));
        $orderId = $orderItem->getOrders()->getId();
        $distributorId = $orderItem->getDistributor()->getId();
        $allItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $totalItemCount = count($allItems);
        $confirmedCount = 0;

        $orderItem->setIsConfirmedDistributor($request->request->get('confirmed_status'));

        $this->em->persist($orderItem);
        $this->em->flush();

        // Send notification to clinic if all items confirmed
        foreach($allItems as $item){

            if($item->getIsConfirmedDistributor() == 1){

                $confirmedCount += 1;
            }
        }

        $this->sendNotification(
            $orderItem->getOrders(), $orderItem->getDistributor(),
            $orderItem->getOrders()->getClinic(), 'Order Update',0,1
        );

        $flash = '<b><i class="fas fa-check-circle"></i> Item status updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'flash' => $flash,
            'distributor_id' => $orderItem->getDistributor()->getId(),
            'order_id' => $orderItem->getOrders()->getId(),
            'clinic_id' => $orderItem->getOrders()->getClinic()->getId()
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/update-expiry-date', name: 'distributor_update_expiry_date')]
    public function distributorUpdateExpiryDateAction(Request $request): Response
    {
        $orderItem = $this->em->getRepository(OrderItems::class)->find($request->request->get('item_id'));
        $expiryDate = $request->request->get('expiry_date');

        $orderItem->setExpiryDate(\DateTime::createFromFormat('Y-m-d', $expiryDate));

        //$this->sendNotification($orderItem->getOrders(), $orderItem->getDistributor(), $orderItem->getOrders()->getClinic(), 'Order Update');

        $this->em->persist($orderItem);
        $this->em->flush();

        $flash = '<b><i class="fas fa-check-circle"></i> Expiry date updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'flash' => $flash,
            'distributor_id' => $orderItem->getDistributor()->getId(),
            'order_id' => $orderItem->getOrders()->getId(),
            'clinic_id' => $orderItem->getOrders()->getClinic()->getId()
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/update-item-price', name: 'distributor_update_price')]
    public function distributorUpdatePriceAction(Request $request): Response
    {
        $orderItem = $this->em->getRepository(OrderItems::class)->find($request->request->get('item_id'));
        $order = $orderItem->getOrders();
        $price = $request->request->get('price');

        $orderItem->setUnitPrice($price);
        $orderItem->setTotal($price * $orderItem->getQuantity());

        $this->em->persist($orderItem);
        $this->em->flush();

        $sumTotal = $this->em->getRepository(OrderItems::class)->findSumTotalPdfOrderItems(
            $orderItem->getOrders()->getId(),
            $orderItem->getDistributor()->getId()
        );

        $order->setSubTotal($sumTotal[0]['totals']);
        $order->setTotal($sumTotal[0]['totals'] + $order->getDeliveryFee() + $order->getTax());

        $this->em->persist($order);
        $this->em->flush();

        //$this->sendNotification($order, $orderItem->getDistributor(), $orderItem->getOrders()->getClinic(), 'Order Update');

        $flash = '<b><i class="fas fa-check-circle"></i> Price updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'flash' => $flash,
            'distributor_id' => $orderItem->getDistributor()->getId(),
            'order_id' => $orderItem->getOrders()->getId(),
            'clinic_id' => $orderItem->getOrders()->getClinic()->getId()
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/update-item-qty', name: 'distributor_update_qty')]
    public function distributorUpdateQtyAction(Request $request): Response
    {
        $orderItem = $this->em->getRepository(OrderItems::class)->find($request->request->get('item_id'));
        $order = $orderItem->getOrders();
        $qty = $request->request->get('qty');

        $orderItem->setQuantity($qty);
        $orderItem->setQuantityDelivered($qty);
        $orderItem->setTotal($qty * $orderItem->getUnitPrice());

        $this->em->persist($orderItem);
        $this->em->flush();

        $sumTotal = $this->em->getRepository(OrderItems::class)->findSumTotalPdfOrderItems(
            $orderItem->getOrders()->getId(),
            $orderItem->getDistributor()->getId()
        );

        $order->setSubTotal($sumTotal[0]['totals']);
        $order->setTotal($sumTotal[0]['totals'] + $order->getDeliveryFee() + $order->getTax());

        $this->em->persist($order);
        $this->em->flush();

        //$this->sendNotification($order, $orderItem->getDistributor(), $orderItem->getOrders()->getClinic(), 'Order Update');

        $flash = '<b><i class="fas fa-check-circle"></i> Quantity updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'flash' => $flash,
            'distributor_id' => $orderItem->getDistributor()->getId(),
            'order_id' => $orderItem->getOrders()->getId(),
            'clinic_id' => $orderItem->getOrders()->getClinic()->getId()
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/get-order-last-updated', name: 'get_order_last_update')]
    public function getOrderLastUpdatedAction(Request $request): Response
    {
        $data = $request->request;
        $orderId = $data->get('order_id');
        $order = $this->em->getRepository(OrderItems::class)->findOneBy([
            'orders' => $orderId
        ],
        [
            'modified' => 'DESC'
        ]);

        $response = $order->getModified()->format('Y-n-d H:i:s');

        return new JsonResponse($response);
    }

    #[Route('/clinics/confirm_order', name: 'clinic_confirm_order')]
    public function clinicsConfirmOrderAction(Request $request, MailerInterface $mailer): Response{
        $data = $request->request;
        $orderId = $data->get('order_id');
        $clinicId = $data->get('clinic_id');
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $distributorId = $data->get('distributor_id');
        $order = $this->em->getRepository(OrderItems::class)->findByDistributorOrder($orderId, $distributorId, 'Draft');
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $status = $this->em->getRepository(Status::class)->find(5);
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'orders' => $orderId,
            'distributor' => $distributorId
        ]);
        $refreshToken = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $distributorId,
        ]);
        $distributorClinics = $this->em->getRepository(DistributorClinics::class)->findOneBy([
            'clinic' => $clinicId,
            'distributor' => $distributorId,
        ]);

        // Generate PO
        $file = $this->generatePpPdfAction($orderId, $distributorId, 'Draft');

        // Generate Sales Order
        if($distributor->getApiDetails() != null && $distributor->getTracking()->getId() == 1) {

            $curl = curl_init();
            $organizationId = $distributor->getApiDetails()->getOrganizationId();
            $customerId = $distributorClinics->getClientId();

            $items = '';

            foreach ($order as $item) {

                $items .= '
            {
                "item_id": ' . $item->getItemId() . ',
                "quantity": ' . $item->getQuantity() . ',
                "unit": "qty",
                "rate": ' . $item->getUnitPrice() . ',
            },';
            }

            // Get Access Token
            $accessToken = $this->zohoRefreshToken($refreshToken->getRefreshTokens()->first()->getToken(), $distributorId);
            file_put_contents(__DIR__ . '/../../public/zoho.log', $accessToken . "\n", FILE_APPEND);

            // Create Sales Order
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://inventory.zoho.com/api/v1/salesorders?organization_id=' . $organizationId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                "customer_id": ' . $customerId . ',
                "date": "' . date('Y-m-d') . '",
                "reference_number": "FL-' . $orderId . '",
                "line_items": [
                    ' . trim($items, ',') . '
                ]
            }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json;charset=UTF-8',
                    'Authorization: Zoho-oauthtoken ' . $accessToken
                ),
            ));

            $response = curl_exec($curl);
            file_put_contents(__DIR__ . '/../../public/zoho.log', $response . "\n", FILE_APPEND);

            curl_close($curl);
        }

        $clinic_html = '
        Your order with '. $this->encryptor->decrypt($distributor->getDistributorName()) .' has been accepted and  will be dispatched within 24 hours.
        <br>
        <br>
        <a href="'. $this->getParameter('app.base_url') .'/clinics/order/'. $orderId .'/'. $distributorId .'">
            View Order
        </a><br>
        ';

        $distributor_html = '
        Your order for '. $this->encryptor->decrypt($clinic->getClinicName()) .' has been accepted.
        <br>
        <br>
        <a href="'. $this->getParameter('app.base_url') .'/distributors/order/'. $orderId .'">
            View Order
        </a><br>
        ';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $distributor_html,
        ]);

        // Distributor Email
        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($this->encryptor->decrypt($distributor->getEmail()))
            ->attachFromPath(__DIR__ . '/../../public/pdf/' . $file)
            ->subject('Fluid Order - PO  '. $order[0]->getPoNumber())
            ->html($html->getContent());

        $mailer->send($email);

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $clinic_html,
        ]);

        // Clinic Email
        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($this->encryptor->decrypt($clinic->getEmail()))
            ->attachFromPath(__DIR__ . '/../../public/pdf/' . $file)
            ->subject('Fluid Order - PO  '. $order[0]->getPoNumber())
            ->html($html->getContent());

        $mailer->send($email);

        // Update Status
        $orderStatus->setStatus($status);

        $this->em->persist($orderStatus);
        $this->em->flush();

        $orders = $this->forward('App\Controller\OrdersController::clinicGetOrdersAction')->getContent();
        $flash = '<b><i class="fas fa-check-circle"></i> Purchase order successfully sent.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        $response = [
            'orders' => json_decode($orders),
            'flash' => $flash,
            'clinic_id' => $clinicId,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/update-order-status', name: 'clinic_update_order_status')]
    public function clinicsUpdateOrderStatusAction(Request $request): Response{

        $data = $request->request;
        $statusId = (int) $data->get('order_status');
        $distributorId = (int) $data->get('distributor_id');
        $orderId = (int) $data->get('order_id');
        $orderStatus = $this->em->getRepository(OrderStatus::class)->findOneBy([
            'distributor' => $distributorId,
            'orders' => $orderId
        ]);
        $status = $this->em->getRepository(Status::class)->find($statusId);

        $orderStatus->setStatus($status);

        $this->em->persist($orderStatus);
        $this->em->flush();

        $response = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
            'distributor_id' => $distributorId,
            'order_id' => $orderId
        ])->getContent();

        // Send Notification
        $badge = 'Order Update';

        if ($statusId == 7)
        {
            $badge = 'Order Delivered';

        }
        elseif ($statusId == 8)
        {
            $badge = 'Order Closed';
        }

        $this->sendNotification(
            $orderStatus->getOrders(),$orderStatus->getDistributor(),$orderStatus->getOrders()->getClinic(),
            $badge,1, 0
        );

        return new JsonResponse(json_decode(($response)));
    }

    #[Route('/clinics/update-qty-delivered', name: 'clinic_update_qty_delivered')]
    public function clinicsUpdateQtyDeliveredAction(Request $request): Response{

        $data = $request->request;
        $orderItemId = $data->get('item_id');
        $qtyDelivered = $data->get('qty');
        $distributorId = $data->get('distributor_id');
        $orderId = $data->get('order_id');
        $orderItem = $this->em->getRepository(OrderItems::class)->find($orderItemId);
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $stockCount = $this->em->getRepository(DistributorProducts::class)->findByDistributorProductStockCount(
            $orderItem->getProduct()->getId(),
            $orderItem->getDistributor()->getId()
        );

        if($stockCount[0]['stock_count'] < $qtyDelivered){

            $orders = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
                'distributor_id' => $distributorId,
                'order_id' => $orderId
            ])->getContent();

            $response['orders'] = json_decode($orders);
            $response['flash'] = '<b><i class="fa solid fa-circle-xmark"></i> Only '. $stockCount[0]['stock_count'] .' available .<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($response);
        }

        $orderItem->setQuantityDelivered($qtyDelivered);
        $orderItem->setTotal($qtyDelivered * $orderItem->getUnitPrice());

        $this->em->persist($orderItem);
        $this->em->flush();

        $sumTotal = $this->em->getRepository(OrderItems::class)->findSumTotalPdfOrderItems(
            $orderItem->getOrders()->getId(),
            $orderItem->getDistributor()->getId()
        );

        $order->setSubTotal($sumTotal[0]['totals'] ?? 0.00);
        $order->setTotal($sumTotal[0]['totals'] + $order->getDeliveryFee() + $order->getTax());

        $this->em->persist($order);
        $this->em->flush();

        $orders = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
            'distributor_id' => $distributorId,
            'order_id' => $orderId
        ])->getContent();

        $this->distributorSendNotification($orderId, $distributorId);

        $response['orders'] = json_decode($orders);
        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Quantity delivered updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/is-delivered-quantity-correct', name: 'is_delivered_quantity_correct')]
    public function clinicsIsDeliveredQtyCorrectAction(Request $request): Response{

        $data = $request->request;
        $orderItemId = $data->get('item_id');
        $distributorId = $data->get('distributor_id');
        $orderId = $data->get('order_id');
        $qtyIsCorrect = $data->get('is_correct');
        $qtyIsIncorrect = $data->get('is_incorrect');
        $orderItem = $this->em->getRepository(OrderItems::class)->find($orderItemId);

        if($qtyIsCorrect){

            $orderItem->setIsQuantityCorrect(1);
            $orderItem->setIsQuantityIncorrect(0);

        } elseif($qtyIsIncorrect){

            $orderItem->setIsQuantityIncorrect(1);
            $orderItem->setIsQuantityCorrect(0);

        } else {

            $orderItem->setIsQuantityIncorrect(0);
            $orderItem->setIsQuantityCorrect(0);
        }

        $this->em->persist($orderItem);
        $this->em->flush();

        $orders = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
            'distributor_id' => $distributorId,
            'order_id' => $orderId
        ])->getContent();

        $response['orders'] = json_decode($orders);
        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Quantity delivered updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/is-delivered-accept', name: 'is_delivered_accept')]
    public function clinicsIsDeliveredAcceptAction(Request $request): Response
    {
        $orderItemId = $request->request->get('item_id');
        $orderItem = $this->em->getRepository(OrderItems::class)->find($orderItemId);
        $distributorId = $request->request->get('distributor_id');
        $orderId = $request->request->get('order_id');
        $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
        $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());

        if($orderItem->getIsAcceptedOnDelivery() == 1){

            $isAccepted = 0;

        } else {

            $isAccepted = 1;
        }

        $orderItem->setIsAcceptedOnDelivery($isAccepted);
        $orderItem->setIsRejectedOnDelivery(0);
        $orderItem->setIsQuantityAdjust(0);
        $orderItem->setRejectReason('');
        $orderItem->setOrderReceivedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($orderItem);
        $this->em->flush();

        $orderCount = $orderItem->getOrders()->getOrderItems()->count();
        $acceptedItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'isAcceptedOnDelivery' => 1
        ]);
        $rejectedItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'isRejectedOnDelivery' => 1
        ]);

        if(count($acceptedItems) + count($rejectedItems) == $orderCount){

            $this->generatePpPdfAction($orderId, $distributorId, 'Confirmed');
        }

        $orders = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
            'distributor_id' => $distributorId,
            'order_id' => $orderId
        ])->getContent();

        // Send In App Notification
        $this->sendNotification($orderItem->getOrders(),$orderItem->getDistributor(),$orderItem->getOrders()->getClinic(),'Order Update',1, 0);

        $response['orders'] = json_decode($orders);
        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Quantity delivered updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/is-delivered-qty', name: 'is_delivered_qty')]
    public function clinicsIsDeliveredQtyAction(Request $request): Response
    {
        $orderItemId = $request->request->get('item_id');
        $orderItem = $this->em->getRepository(OrderItems::class)->find($orderItemId);
        $distributorId = $request->request->get('distributor_id');
        $orderId = $request->request->get('order_id');
        $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
        $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());

        if($orderItem->getIsQuantityAdjust() == 1){

            $is_adjust = 0;

        } else {

            $is_adjust = 1;
        }

        $orderItem->setRejectReason('');
        $orderItem->setIsQuantityAdjust($is_adjust);
        $orderItem->setIsAcceptedOnDelivery(0);
        $orderItem->setIsRejectedOnDelivery(0);
        $orderItem->setOrderReceivedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($orderItem);
        $this->em->flush();

        $orders = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
            'distributor_id' => $distributorId,
            'order_id' => $orderId
        ])->getContent();

        // Send In App Notification
        $this->sendNotification($orderItem->getOrders(),$orderItem->getDistributor(),$orderItem->getOrders()->getClinic(),'Order Update',1, 0);

        $response['orders'] = json_decode($orders);
        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Quantity delivered updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/reject-item', name: 'clinics_reject_item')]
    public function clinicsRejectItemAction(Request $request): Response
    {
        $orderItemId = $request->request->get('reject_item_id');
        $rejectReason = $request->request->get('reject_reason');
        $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
        $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());
        $orderItem = $this->em->getRepository(OrderItems::class)->find($orderItemId);

        $orderItem->setIsQuantityAdjust(0);
        $orderItem->setIsAcceptedOnDelivery(0);
        $orderItem->setIsRejectedOnDelivery(1);
        $orderItem->setRejectReason($rejectReason);
        $orderItem->setOrderReceivedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($orderItem);
        $this->em->flush();

        $distributorId = $orderItem->getDistributor()->getId();
        $orderId = $orderItem->getOrders()->getId();

        $orderCount = $orderItem->getOrders()->getOrderItems()->count();
        $acceptedItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'isAcceptedOnDelivery' => 1
        ]);
        $rejectedItems = $this->em->getRepository(OrderItems::class)->findBy([
            'orders' => $orderId,
            'isRejectedOnDelivery' => 1
        ]);

        if(count($acceptedItems) + count($rejectedItems) == $orderCount){

            $this->generatePpPdfAction($orderId, $distributorId, 'Confirmed');
        }

        $orders = $this->forward('App\Controller\OrdersController::clinicOrderDetailAction', [
            'distributor_id' => (int) $distributorId,
            'order_id' => (int) $orderId
        ])->getContent();

        // Send In App Notification
        $this->sendNotification($orderItem->getOrders(),$orderItem->getDistributor(),$orderItem->getOrders()->getClinic(),'Order Update',1, 0);


        $response['orders'] = json_decode($orders);
        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Item successfully rejected.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/get-reject-reason', name: 'clinics_get_reject_reason')]
    public function clinicsGetRejectReasonAction(Request $request): Response
    {
        $orderItemId = $request->request->get('item_id');

        $orderItem = $this->em->getRepository(OrderItems::class)->find($orderItemId);

        if($orderItem->getRejectReason() == null){

            $response = '';

        } else {

            $response = $orderItem->getRejectReason();
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

    public function generatePpPdfAction($orderId, $distributorId, $status)
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

    public function sendNotification($order, $distributor, $clinic, $badge, $isRead = 1, $isReadDistributor = 1)
    {
        // Clinic in app notification
        $notification = new Notifications();

        $notification->setClinic($order->getClinic());
        $notification->setIsActive(1);
        $notification->setIsRead($isRead);
        $notification->setIsReadDistributor($isReadDistributor);
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
                        <span class="badge bg-success me-3">'. $badge .'</span>
                    </a>
                </td>
                <td>
                    <a 
                        href="#"
                        data-order-id="'. $order->getId() .'"$notification
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
        $sendEmailNotification = true;

        foreach ($order->getOrderItems() as $orderItem)
        {
            if($orderItem->getIsAccepted() == 0)
            {
                $sendEmailNotification = false;

                break;
            }
        }

        if ($sendEmailNotification)
        {
            $to = $this->encryptor->decrypt($clinic->getEmail());
            $orderUrl = $this->getParameter('app.base_url') . '/clinics/order/'. $order->getId() .'/'. $distributor->getId();

            $html = '<p>Please <a href="'. $orderUrl .'">click here</a> in order to view the progress of your order</p><br>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html'  => $html,
            ]);

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($to)
                ->subject('Fluid Order - PO  '. $order->getOrderItems()[0]->getPoNumber())
                ->html($html->getContent());

            $this->mailer->send($email);
        }
    }

    public function distributorSendNotification($orderId, $distributorId)
    {
        // Email Notifications
        $order = $this->em->getRepository(Orders::class)->find($orderId);
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $to = $this->encryptor->decrypt($distributor->getEmail());
        $orderUrl = $this->getParameter('app.base_url') . '/distributors/order/'. $order->getId();

        $html = '<p>Please <a href="'. $orderUrl .'">click here</a> in order to view the progress of your order</p><br>';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $html,
        ]);

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($to)
            ->subject('Fluid Order - PO  '. $order->getOrderItems()[0]->getPoNumber())
            ->html($html->getContent());

        $this->mailer->send($email);
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
