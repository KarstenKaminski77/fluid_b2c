<?php

namespace App\Controller;

use App\Entity\BasketItems;
use App\Entity\Baskets;
use App\Entity\ClinicProducts;
use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\ListItems;
use App\Entity\ProductImages;
use App\Entity\Products;
use App\Entity\RetailUsers;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BasketController extends AbstractController
{
    private $pageManager;
    private $em;
    private $emRemote;
    private $encryptor;
    
    public function __construct(ManagerRegistry $entityManager, PaginationManager $pagination, Encryptor $encryptor)
    {
        $this->pageManager = $pagination;
        $this->em = $entityManager->getManager('default');
        $this->emRemote = $entityManager->getManager('remote');
        $this->encryptor = $encryptor;
    }

    #[Route('/retail/inventory-remove-basket-item', name: 'inventory_remove_basket_item_retail')]
    public function removeBasketItemAction(Request $request): Response
    {
        $basketItemId = $request->request->get('item-id');
        $basketItem = $this->em->getRepository(BasketItems::class)->find($basketItemId);
        $basketId = $basketItem->getBasket()->getId();
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);

        if($basketItem != null){

            $this->em->remove($basketItem);
            $this->em->flush();
        }

        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basketId);

        if($basket->getBasketItems()->count() > 0){

            $basket->setTotal((float) number_format($totals[0]['total'],2));

            $this->em->persist($basket);
            $this->em->flush();

        }

        $response = [

            'message' => '<b><i class="fas fa-check-circle"></i> '. $basketItem->getProduct()->getName() .' removed.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basketId,
        ];

        return new JsonResponse($response);
    }

    #[Route('/retail/inventory/inventory-clear-basket', name: 'inventory_clear_basket')]
    public function clearBasketAction(Request $request): Response
    {
        $basketId = $request->request->get('basket-id');
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy(['basket' => $basketId]);
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);

        if($basketItems != null){

            foreach($basketItems as $item) {

                $this->em->remove($item);
            }

            $this->em->flush();
        }

        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basketId);

        if($basket != null){

            $basket->setTotal($total = number_format($totals[0]['total'] ?? 0,2));

            $this->em->persist($basket);
            $this->em->flush();

        }

        $response = [

            'message' => '<b><i class="fas fa-check-circle"></i> All items removed from basket.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basketId,
        ];

        return new JsonResponse($response);
    }

    private function getSavedbasketsRightColumn()
    {
        $savedBaskets = $this->em->getRepository(Baskets::class)->findBy([
            'clinic' => $this->getUser()->getClinic()->getId(),
            'status' => 'active'
        ]);
        $response = '';

        if(count($savedBaskets) > 1)
        {
            $response .= '
            <!-- Basket Items -->
            <div class="row border-bottom bg-secondary d-none d-sm-flex">
                <div class="col-3 pt-3 pb-3">
                    <h6 class="text-primary m-0" style="padding-top: 3px">Basket Name</h6>
                </div>
                <div class="col-3 pt-3 pb-3">
                    <h6 class="text-primary m-0" style="padding-top: 3px">Saved By</h6>
                </div>
                <div class="col-2 pt-3 pb-3">
                    <h6 class="text-primary m-0" style="padding-top: 3px">Subtotal</h6>
                </div>
                <div class="col-2 pt-3 pb-3">
                    <h6 class="text-primary m-0" style="padding-top: 3px">Items</h6>
                </div>
                <div class="col-2 pt-3 pb-3" style="padding-top: 3px"></div>
            </div>';

            foreach ($savedBaskets as $basket)
            {
                if ($basket->getName() == 'Fluid Commerce')
                {
                    continue;
                }

                $response .= '
                <!-- Saved Baskets -->
                <div class="row border-bottom-dashed saved_basket_header" role="button" id="saved_basket_header_' . $basket->getId() . '">
                    <div class="col-3 pt-3 pb-3 saved-basket-link text-truncate" id="saved_basket_first_' . $basket->getId() . '" data-basket-id="' . $basket->getId() . '">
                        <span id="basket_name_string_' . $basket->getId() . '">' . $basket->getName() . '</span>
                        <span id="basket_name_input_' . $basket->getId() . '" style="display:none"><input type="text" class="form-control form-control-sm" id="basket_name_' . $basket->getId() . '" value="' . $basket->getName() . '"></span>
                    </div>
                    <div class="col-3 pt-3 pb-3 saved-basket-link text-truncate" data-basket-id="' . $basket->getId() . '">
                        ' . $this->encryptor->decrypt($basket->getSavedBy()) . '
                    </div>
                    <div class="col-2 pt-3 pb-3 saved-basket-link" data-basket-id="' . $basket->getId() . '">
                       ' . number_format($basket->getTotal(), 2) . '
                    </div>
                    <div class="col-1 pt-3 pb-3 saved-basket-link" data-basket-id="' . $basket->getId() . '">
                        ' . $basket->getBasketItems()->count() . '
                    </div>
                    <div class="col-3 pt-3 pb-3">
                        <a href="" class="basket-edit" data-basket-id="' . $basket->getId() . '">
                            <i class="fa-solid fa-pencil float-end me-0 me-sm-3"></i>
                        </a>
                        <a href="" class="basket-delete" data-basket-id="' . $basket->getId() . '">
                            <i class="fa-solid fa-trash-can text-danger float-end me-4 me-sm-4"></i>
                        </a>
                    </div>
                </div>
                <!-- Baskets -->
                <div class="saved-basket-panel" id="saved_basket_panel_' . $basket->getId() . '" style="display: none">This basket is empty...</div>';
            }
        }
        else
        {
            $response .= '
            <div class="row d-none d-sm-flex">
                <div class="col-12 pt-3 pb-3 text-center">
                    <p></p>
                    <h5>You don\'t currently have any saved baskets</h5><br>
                    Were you expecting to see items here? View copies of the items most recently added<br> 
                    to your basket and restore a basket if needed.
                    <p></p>
                </div>
            </div>
            ';
        }

        return $response;
    }

    private function getBasketLeftColumn($request)
    {
        $clinicId = $this->getUser()->getClinic()->getId();
        $baskets = $this->em->getRepository(Baskets::class)->findBy([
            'clinic' => $clinicId,
            'status' => 'active',
        ]);
        $clinicTotals = $this->em->getRepository(Baskets::class)->getClinicTotalItems($clinicId);
        $totalClinic = number_format($clinicTotals[0]['total'] ?? 0,2);
        $countClinic = $clinicTotals[0]['item_count'] ?? 0;

        $response = '
        <div class="row border-bottom text-center pt-2 pb-2">
            <b>All Baskets</b>
        </div>
        <div class="row" style="background: #f4f8fe">
            <div class="col-6 border-bottom pt-1 pb-1 text-center">
                <span class="d-block text-primary">'. $countClinic .'</span>
                <span class="d-block text-truncate">Items</span>
            </div>
            <div class="col-6 border-bottom pt-1 pb-1 text-center">
                <span class="d-block text-primary">$'. number_format($totalClinic,2) .'</span>
                <span class="d-block text-truncate">Subtotal</span>
            </div>
        </div>';

        foreach($baskets as $basket) {

            $active = '';
            $background = '';

            if($basket->getId() == $request->request->get('basket_id')){

                $active = 'active-basket';
            }

            if($basket->getBasketItems()->count() > 0){

                $background = 'bg-primary';
            }

            $response .= '
            <div class="row">
                <div class="col-12 border-bottom '. $active .'">
                    <a href="#" data-basket-id="'. $basket->getId() .'" class=" pt-3 pb-3 d-block basket-link">
                        <span class="d-inline-block align-baseline">'. $basket->getName() .'</span>
                        <span class="float-end basket-item-count-empty '. $background .'">
                            '. $basket->getBasketItems()->count() .'
                        </span>
                    </a>
                </div>
            </div>';
        }

        $response .= '
        <div class="row border-bottom">
            <div class="col-4 col-sm-12 col-md-4 pt-3 pb-3 saved-baskets">
                <i class="fa-regular fa-basket-shopping"></i>
            </div>
            <div class="col-8 col-sm-12 col-md-8 pt-3 pb-3 text-truncate">
                <h6 class="text-primary">Saved Baskets</h6>
                <span class="info">View baskets</span>
            </div>
        </div>';

        return $response;
    }

    #[Route('/retail/add-to-basket', name: 'retail_add_to_basket')]
    public function retailAddToBasketAction(Request $request): Response
    {
        $response = [];
        $data = $request->request;
        $retailUserId = $this->getUser()->getId();
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);
        $firstName = $this->encryptor->decrypt($retailUser->getFirstName());
        $lastName = $this->encryptor->decrypt($retailUser->getLastName());
        $clinicId = (int) $data->get('clinic-id');
        $productId = (int) $data->get('product-id');
        $listItemId = (int) $data->get('list-item-id');
        $price = $data->get('price');
        $qty = (int) $data->get('qty');
        $basket = $this->em->getRepository(Baskets::class)->findOneBy([
            'retailUser' => $retailUserId
        ]);
        $product = $this->em->getRepository(Products::class)->find($productId);
        $listItem = $this->emRemote->getRepository(ListItems::class)->find($listItemId);
        $distributor = $listItem->getDistributor();

        // Create new basket if one doesn't exist
        if($basket == null)
        {
            $basket = new Baskets();

            $basket->setClinic($clinicId);
            $basket->setDistributor(null);
            $basket->setRetailUser($retailUser);
            $basket->setStatus('active');
            $basket->setName('Fluid Commerce');
            $basket->setIsDefault(1);
            $basket->setTotal(0.00);
        }

        $basket->setSavedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($basket);
        $this->em->flush();

        // Add item to basket
        $basketItem = $this->em->getRepository(BasketItems::class)->findOneBy([
            'basket' => $basket->getId(),
            'product' => $productId,
        ]);

        if($basketItem == null)
        {
            $basketItem = new BasketItems();
        }

        $basketItem->setBasket($basket);
        $basketItem->setProduct($product);
        $basketItem->setDistributorId($distributor->getId());
        $basketItem->setName($product->getName());
        $basketItem->setQty($qty);
        $basketItem->setUnitPrice($price);
        $basketItem->setTotal($price * $qty);
        $basketItem->setItemId(0);

        $this->em->persist($basketItem);
        $this->em->flush();

        $basketItems = $this->em->getRepository(BasketItems::class)->findBy([
            'basket' => $basket->getId(),
        ]);
        $basketTotal = 0.00;

        foreach($basketItems as $basketItem)
        {
            $basketTotal += $basketItem->getTotal();
        }

        $basket->setTotal($basketTotal);

        $this->em->persist($basket);
        $this->em->flush();

        $response['flash'] = '<b><i class="fas fa-check-circle"></i> '. $product->getName() .' added to your basket.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/retail/get-basket', name: 'retail_get_basket')]
    public function retailGetBasketAction(Request $request): Response
    {
        $response = [];
        $data = $request->request;
        $retailUserId = $this->getUser()->getId();
        $basketId = $data->get('basket-id');
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);
        $clinic = $this->emRemote->getRepository(Clinics::class)->find($retailUser->getClinicId());
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $currency = $retailUser->getCountry()->getCurrency();
        $subTotal = 0.00;
        $href = '';
        $textDisabled = 'text-disabled';

        if($basket->getBasketItems()->count() > 0)
        {
            $href = 'href="#"';
            $textDisabled = '';
        }

        $response['html'] = '
        <!-- Basket Name -->
        <div class="row">
            <div class="col-12 text-center pt-3 pb-3 form-control-bg-grey" id="basket_header">
                <h4 class="text-primary">Fluid Commerce Basket</h4>
                <span class="text-primary">
                    Manage All Your Shopping Carts In One Place
                </span>
            </div>
        </div>
        <!-- Basket Actions Row -->
        <div class="row px-3">
            <div class="col-12 half-border" id="half_border_row">
                <div class="row" id="basket_action_row_1">
                    <div class="col-12 d-flex justify-content-center border-xy bg-white py-3">
                        <a '. $href .' class="'. $textDisabled .'" 
                            id="print_basket" 
                            data-basket-id="'. $basketId .'"
                            data-action="click->retail-basket#onClickPrintBasket"
                        >
                            <i class="fa-regular fa-print me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Print</span>
                        </a>
                        <a 
                            '. $href .' 
                            class="clear-basket 
                            '. $textDisabled .'" 
                            data-basket-id="'. $basketId .'"
                            data-action="click->retail-basket#onClickClearBasket"
                        >
                            <i class="fa-regular fa-trash-can me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Clear Basket</span>
                        </a>
                        <a 
                            href="#" 
                            id="return_to_search" 
                            data-basket-id="'. $basketId .'"
                            data-action="click->retail-basket#onClickBackToSearch"
                        >
                            <i class="fa-solid fa-magnifying-glass me-0 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Back To Search</span>
                        </a>
                    </div>
                </div>';

                if($basket->getBasketItems()->count() > 0)
                {
                    $response['html'] .= '
                    <div class="row" id="basket_items">
                        <div class="col-12 col-lg-9 border-right border-left border-right border-bottom bg-white col-cell">';

                    if($basket->getBasketItems()->count() > 0)
                    {
                        foreach ($basket->getBasketItems() as $basketItem)
                        {
                            $total = number_format($basketItem->getQty() * $basketItem->getUnitPrice(), 2);
                            $firstImage = $this->em->getRepository(ProductImages::class)->findOneBy([
                                'product' => $basketItem->getProduct()->getId(),
                                'isDefault' => 1
                            ]);

                            if($firstImage == null){

                                $firstImage = 'image-not-found.jpg';

                            } else {

                                $firstImage = $firstImage->getImage();
                            }

                            $response['html'] .= '
                            <div class="col-12">
                                <div class="row">
                                    <!-- Thumbnail -->
                                    <div class="col-12 col-sm-2 text-center pt-3 pb-3 mt-3">
                                        <img class="img-fluid basket-img" src="/images/products/'. $firstImage .'">
                                    </div>
                                    <div class="col-12 col-sm-10 pt-3 pb-3">
                                        <!-- Product Name and Qty -->
                                        <div class="row">
                                            <!-- Product Name -->
                                            <div class="col-12 col-sm-6 col-md-12 col-lg-7 pt-3 pb-3 text-center text-sm-start">
                                                <span class="info">
                                                    '. $this->encryptor->decrypt($clinic->getClinicName()) .'
                                                </span>
                                                <h6 class="fw-bold text-primary lh-base my-0">
                                                    '. $basketItem->getProduct()->getName() .'
                                                </h6>
                                            </div>
                                            <!-- Product Quantity -->
                                            <div class="col-12 col-sm-6 col-md-12 col-lg-5 pt-3 pb-3 d-table">
                                                <div class="row d-table-row">
                                                    <div class="col-3 text-center text-sm-end text-md-start text-lg-start d-table-cell align-bottom">
                                                        '. number_format($basketItem->getUnitPrice(), 2) .'
                                                    </div>
                                                    <div class="col-4 d-table-cell align-bottom">
                                                        <input 
                                                            type="number" 
                                                            name="qty" 
                                                            class="form-control form-control-sm basket-qty" 
                                                            value="'. $basketItem->getQty() .'"
                                                            data-basket-item-id="'. $basketItem->getId() .'"
                                                            data-action="change->retail-basket#onChangeQty"
                                                        >
                                                        <div class="hidden_msg" id="stock_count_error_6"></div>
                                                    </div>
                                                    <div class="col-5 text-center text-sm-start text-md-end fw-bold text-truncate d-table-cell align-bottom">
                                                        '. $currency .' '. $total .'
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Item Actions -->
                                        <div class="row">
                                            <div class="col-12">
                                                <!-- Shipping Policy -->
                                                <span 
                                                    class="badge bg-dark-grey badge-pending-filled-sm" 
                                                    data-bs-trigger="hover" 
                                                    data-bs-container="body" 
                                                    data-bs-toggle="popover" 
                                                    data-bs-placement="top" 
                                                    data-bs-html="true" 
                                                    data-bs-content=""
                                                >
                                                    Shipping Policy
                                                </span>
                                                <!-- Remove Item -->
                                                <span class="badge bg-danger float-end badge-danger-filled-sm">
                                                    <a 
                                                        href="#" 
                                                        class="remove-item text-white" 
                                                        data-item-id="'. $basketItem->getId() .'"
                                                        data-action="retail-basket#onClickRemoveItem"
                                                    >Remove</a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                        }
                    }

                    $response['html'] .= '
                        <!-- Basket Summary -->
                        </div>
                        <div class="col-12 col-lg-3 py-3 px-sm-3 bg-white border-right border-bottom" id="basket_summary">
                            <div class="row">
                                <div class="col-12 text-truncate ps-sm-2">
                                    <span class="info">Subtotal:</span>
                                    <h5 class="d-inline-block text-primary float-end pe-2">'. $currency .' '. number_format($basket->getTotal(),2) .'</h5>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 text-truncate ps-sm-2">
                                    <span class="info">Shipping:</span> 
                                    <span class="float-end fw-bold pe-2">AED 0.00</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 pt-4 text-center ps-2">
                                    <a 
                                        href="" 
                                        class="btn btn-primary w-100 " 
                                        id="btn_checkout" 
                                        data-basket-id="'. $basketId .'"
                                        data-action="click->retail-checkout#onClickProceedToCheckout"
                                    >
                                        PROCEED <i class="fa-solid fa-circle-right ps-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                else
                {
                    $response['html'] .= '
                    <!-- Basket Items -->
                    <div class="row">
                        <div class="col-12 text-center pt-4 border-left border-right border-bottom bg-white">
                            <p>
                            </p><h5>Your basket at Fluid Commerce is currently empty </h5><br>
                            Were you expecting to see items here? View copies of the items most recently added<br>
                            to your basket and restore a basket if needed.
                            <p></p>
                        </div>
                    </div>';
                }

        $response['html'] .= '
                </div>
            </div>';

        return new JsonResponse($response);
    }

    #[Route('/retail/update-basket', name: 'retail_update_basket')]
    public function retailUpdateBasketAction(Request $request): Response
    {
        $itemId = $request->request->get('item-id');
        $basketItem = $this->em->getRepository(BasketItems::class)->find($itemId);
        $productName = $basketItem->getProduct()->getName();
        $basketId = $basketItem->getBasket()->getId();
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $currency = $this->getUser()->getCountry()->getCurrency();
        $subTotal = 0.00;
        $total = 0.00;

        if($basketItem != null){

            $qty = (int) $request->request->get('qty');
            $basketItem = $this->em->getRepository(BasketItems::class)->find($itemId);
            $total = $basketItem->getUnitPrice() * $qty;

            $basketItem->setQty($qty);
            $basketItem->setTotal($total);

            $this->em->persist($basketItem);
            $this->em->flush();
        }

        $basketItems = $this->em->getRepository(BasketItems::class)->findBy([
            'basket' => $basketId
        ]);

        foreach($basketItems as $basketItem)
        {
            $subTotal += $basketItem->getTotal();
        }

        if($basket != null){

            $subTotal = number_format((float) $subTotal,2, '.','') ?? 0.00;

            $basket->setTotal($subTotal);

            $this->em->persist($basket);
            $this->em->flush();

        }

        $response = [
            'total' => $currency .' '. $total,
            'subTotal' => $currency .' '. $subTotal,
            'flash' => '<b><i class="fas fa-check-circle"></i> '. $productName .' updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>'
        ];

        return new JsonResponse($response);
    }

    private function setSavedBy()
    {
        return $this->getUser()->getFirstName() .' '. $this->getUser()->getLastName();
    }
}
