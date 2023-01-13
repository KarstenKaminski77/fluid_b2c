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
    private $encryptor;
    
    public function __construct(EntityManagerInterface $entityManager, PaginationManager $pagination, Encryptor $encryptor)
    {
        $this->pageManager = $pagination;
        $this->em = $entityManager;
        $this->encryptor = $encryptor;
    }

    #[Route('/clinics/inventory/inventory-add-to-basket', name: 'inventory_add_to_basket')]
    public function addToBasketAction(Request $request): Response
    {
        $distributorId = $request->get('distributor_id');
        $basketId = $request->get('basket_id');
        $productId = $request->get('product_id');
        $qty = $request->get('qty');
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $product = $this->em->getRepository(Products::class)->find($productId);
        $distributorProducts = $this->em->getRepository(DistributorProducts::class)->findBy([
            'product' => $productId,
            'distributor' => $distributorId
        ]);
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $isDefault = $basket->getIsDefault() ?? 0;

        if($basket == null){

            $basket = new Baskets();

            $basket->setName($request->get('basket_name'));
        }

        $basket->setClinic($clinic);
        $basket->setDistributor($distributor);
        $basket->setStatus($request->get('status'));
        $basket->setIsDefault($isDefault);
        $basket->setSavedBy($this->encryptor->encrypt($this->getUser()->getFirstName() .' '. $this->getUser()->getLastName()));

        $this->em->persist($basket);
        $this->em->flush();

        $basketItem = $this->em->getRepository(BasketItems::class)->findOneBy([
            'basket' => $basket,
            'product' => $product,
            'distributor' => $distributor
        ]);

        if($basketItem == null){

            $basketItem = new BasketItems();
        }

        $qtyError = '';

        if($distributorProducts[0]->getStockCount() < $qty && ($distributorProducts[0]->getDistributor()->getTracking()->getId() == 1 || $distributorProducts[0]->getDistributor()->getTracking()->getId() == 2)){

            $qty = $distributorProducts[0]->getStockCount();
            $qtyError = 'Only '. $qty .' units in stock, please select '. $qty .' or less';

            $response = [
                'product_id' => $product->getId(),
                'distributor_id' => $distributor->getId(),
                'error' => $qtyError,
            ];

            return new JsonResponse($response);
        }

        $basketItem->setBasket($basket);
        $basketItem->setDistributor($distributor);
        $basketItem->setProduct($product);
        $basketItem->setName($product->getName());
        $basketItem->setQty($qty);
        $basketItem->setUnitPrice($request->get('price'));
        $basketItem->setTotal($request->get('qty') * $request->get('price'));
        $basketItem->setItemId($distributorProducts[0]->getItemId());

        $this->em->persist($basketItem);
        $this->em->flush();

        // Get total items in basket
        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basket->getId());

        $basket->setTotal($totals[0]['total']);

        $this->em->persist($basket);
        $this->em->flush();

        $response = [
            'message' => '<b><i class="fas fa-check-circle"></i> '. $product->getName() .' added to your basket.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basket_count' => $totals[0]['item_count'],
            'product_id' => $product->getId(),
            'distributor_id' => $distributor->getId(),
            'error' => $qtyError,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/inventory-update-basket', name: 'inventory_update_basket')]
    public function updateBasketAction(Request $request): Response
    {
        $itemId = $request->request->get('item-id');
        $basketItem = $this->em->getRepository(BasketItems::class)->find($itemId);
        $basketId = $basketItem->getBasket()->getId();
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);

        if($basketItem != null)
        {
            $productId = $basketItem->getProduct()->getId();
            $distributorId = $basketItem->getDistributor()->getId();
            $qty = (int) $request->request->get('qty');

            $distributorProducts = $this->em->getRepository(DistributorProducts::class)->findBy([
                'product' => $productId,
                'distributor' => $distributorId,
            ]);

            if(
                $distributorProducts[0]->getStockCount() < $qty && ($distributorProducts[0]->getDistributor()->getTracking() == 1 ||
                    $distributorProducts[0]->getDistributor()->getTracking() == 2)
            ){
                $qty = $distributorProducts[0]->getStockCount();
                $qtyError = 'Only '. $qty .' units in stock, please select '. $qty .' or less';

                $response = [
                    'error' => $qtyError,
                    'message' => '',
                    'basketId' => $basketId,
                    'itemId' => $itemId,
                ];

                return new JsonResponse($response);
            }

            $basketItem->setQty($qty);
            $basketItem->setTotal($basketItem->getUnitPrice() * $qty);

            $this->em->persist($basketItem);
            $this->em->flush();
        }

        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basketId);

        if($basket != null)
        {
            $basket->setTotal(number_format((float) $totals[0]['total'],2, '.','') ?? 0.00);

            $this->em->persist($basket);
            $this->em->flush();

        }

        $response = [
            'error' => '',
            'message' => '<b><i class="fas fa-check-circle"></i> '. $basketItem->getProduct()->getName() .' updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basketId,
            'itemId' => '',
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/inventory-remove-basket-item', name: 'inventory_remove_basket_item')]
    #[Route('/retail/inventory/inventory-remove-basket-item', name: 'inventory_remove_basket_item_retail')]
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

    #[Route('/clinics/inventory/inventory-clear-basket', name: 'inventory_clear_basket')]
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

    #[Route('/clinics/inventory/save-item', name: 'save_item')]
    public function saveItemAction(Request $request): Response
    {
        $product = $this->em->getRepository(Products::class)->find($request->request->get('product-id'));
        $distributor = $this->em->getRepository(Distributors::class)->find($request->request->get('distributor-id'));
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $basketItems = $this->em->getRepository(BasketItems::class)->find($request->request->get('item-id'));
        $basketId = $basketItems->getBasket()->getId();
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $create = false;

        // Ensure item has not already been saved
        $clinicProducts = $this->em->getRepository(ClinicProducts::class)->findOneBy([
            'clinic' => $clinic,
            'product' => $product,
            'distributor' => $distributor
        ]);

        if($clinicProducts == null) {

            $clinicProducts = new ClinicProducts();
            $create = true;
        }

        $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
        $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());

        $clinicProducts->setProduct($product);
        $clinicProducts->setDistributor($distributor);
        $clinicProducts->setClinic($clinic);
        $clinicProducts->setItemId($basketItems->getItemId());
        $clinicProducts->setName($basketItems->getName());
        $clinicProducts->setQty($basketItems->getQty());
        $clinicProducts->setUnitPrice($basketItems->getUnitPrice());
        $clinicProducts->setTotal($basketItems->getQty() * $basketItems->getUnitPrice());
        $clinicProducts->setSavedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($clinicProducts);
        $this->em->flush();

        $this->em->remove($basketItems);
        $this->em->flush();

        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basketId);
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy([
            'basket' => $basketId
        ]);

        if(count($basketItems) > 0){

            $basket->setTotal(number_format($totals[0]['total'],2));

        } else {

            $basket->setTotal(0,2);
        }

        $this->em->persist($basket);
        $this->em->flush();

        if($create){

            $message = '<b><i class="fas fa-check-circle"></i> '. $product->getName() .'</b> saved for later.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $message = '<b><i class="fas fa-check-circle"></i> '. $product->getName() .'</b> has already been saved for later.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $response = [
            'message' => $message,
            'basketId' => $basketId
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/save-all-items', name: 'save_all_items')]
    public function saveAllItemAction(Request $request): Response
    {
        $basketId = $request->request->get('basket-id');
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy(['basket' => $basketId]);

        foreach($basketItems as $item){

            $itemId = $item->getId();
            $clinicProducts = $this->em->getRepository(ClinicProducts::class)->findOneBy([
                'product' => $item->getProduct(),
                'distributor' => $item->getDistributor(),
                'clinic' => $clinic,
            ]);

            if($clinicProducts == null) {

                $clinicProducts = new ClinicProducts();

            }

            $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
            $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());

            $clinicProducts->setClinic($clinic);
            $clinicProducts->setDistributor($item->getDistributor());
            $clinicProducts->setItemId($item->getItemId());
            $clinicProducts->setProduct($item->getProduct());
            $clinicProducts->setName($item->getName());
            $clinicProducts->setQty($item->getQty());
            $clinicProducts->setUnitPrice($item->getUnitPrice());
            $clinicProducts->setTotal($item->getQty() * $item->getUnitPrice());
            $clinicProducts->setSavedBy($this->encryptor->encrypt($firstName .' '. $lastName));

            $this->em->persist($clinicProducts);
            $this->em->flush();

            $basketItem = $this->em->getRepository(BasketItems::class)->find($item->getId());

            $this->em->remove($basketItem);
            $this->em->flush();
        }

        $response = [
            'message' => '<b><i class="fas fa-check-circle"></i></b> All items saved for later.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basketId
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/restore-item', name: 'restore_item')]
    public function restoreItemAction(Request $request): Response
    {
        $product = $this->em->getRepository(Products::class)->find($request->request->get('product-id'));
        $distributor = $this->em->getRepository(Distributors::class)->find($request->request->get('distributor-id'));
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $clinicProducts = $this->em->getRepository(ClinicProducts::class)->find($request->request->get('item-id'));
        $basket = $this->em->getRepository(Baskets::class)->find($request->request->get('basket-id'));

        $basketItem = new BasketItems();

        $basketItem->setProduct($product);
        $basketItem->setDistributor($distributor);
        $basketItem->setItemId($clinicProducts->getItemId());
        $basketItem->setBasket($basket);
        $basketItem->setName($product->getName());
        $basketItem->setQty($clinicProducts->getQty());
        $basketItem->setUnitPrice($clinicProducts->getUnitPrice());
        $basketItem->setTotal($clinicProducts->getTotal());

        $this->em->persist($basketItem);
        $this->em->flush();

        $this->em->remove($clinicProducts);
        $this->em->flush();

        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basket->getId());
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy([
            'basket' => $basket->getId(),
        ]);

        if(count($basketItems) > 0){

            $basket->setTotal((float) $totals[0]['total']);

        } else {

            $basket->setTotal(0,2);
        }

        $this->em->persist($basket);
        $this->em->flush();

        $response = [
            'message' => '<b><i class="fas fa-check-circle"></i> '. $product->getName() .'</b> moved to basket.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basket->getId()
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/restore-all-items', name: 'restore_all_items')]
    public function restoreAllItemsAction(Request $request): Response
    {
        $basketId = $request->request->get('basket_id');
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $clinicProducts = $this->em->getRepository(ClinicProducts::class)->findBy([
            'clinic' => $this->getUser()->getClinic()
        ]);

        foreach($clinicProducts as $product){

            $basketItem = new BasketItems();

            $basketItem->setBasket($basket);
            $basketItem->setProduct($product->getProduct());
            $basketItem->setDistributor($product->getDistributor());
            $basketItem->setItemId($product->getItemId());
            $basketItem->setName($product->getName());
            $basketItem->setQty($product->getQty());
            $basketItem->setUnitPrice($product->getUnitPrice());
            $basketItem->setTotal($product->getQty() * $product->getUnitPrice());

            $this->em->persist($basketItem);
            $this->em->flush();

            $removeBasket = $this->em->getRepository(ClinicProducts::class)->find($product->getId());

            $this->em->remove($removeBasket);
            $this->em->flush();
        }

        $totals = $this->em->getRepository(BasketItems::class)->getTotalItems($basketId);
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy([
            'basket' => $basketId,
        ]);

        if(count($basketItems) > 0){

            $basket->setTotal((float)$totals[0]['total']);

        } else {

            $basket->setTotal(0,2);
        }

        $this->em->persist($basket);
        $this->em->flush();

        $response = [
            'message' => '<b><i class="fas fa-check-circle"></i></b> All saved items moved to basket.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basket_id' => $basketId
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/remove-saved-item', name: 'remove_saved_item')]
    public function removeSavedItemAction(Request $request): Response
    {
        $item = $this->em->getRepository(ClinicProducts::class)->find($request->request->get('item-id'));
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $product = $item->getProduct()->getName();

        $this->em->remove($item);
        $this->em->flush();

        $basket = $this->em->getRepository(Baskets::class)->findOneBy([
            'clinic' => $clinic,
            'name' => 'Fluid Commerce',
            'status' => 'active'
        ]);

        $response = [
            'message' => '<b><i class="fas fa-check-circle"></i> '. $product .'</b> removed.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basket->getId(),
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/save-basket', name: 'save_basket')]
    public function saveBasketAction(Request $request): Response
    {
        $basketName = $request->request->get('basket_name');
        $basketId = $request->request->get('basket_id');
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $firstName = $this->encryptor->decrypt($this->getUser()->getFirstName());
        $lastName = $this->encryptor->decrypt($this->getUser()->getLastName());
        $savedBy = $this->encryptor->encrypt($firstName .' '. $lastName);
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy(['basket' => $basketId]);
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $create = false;

        // Create new basket
        $basketNew = new Baskets();

        $basketNew->setName($basketName);
        $basketNew->setClinic($clinic);
        $basketNew->setTotal($basket->getTotal());
        $basketNew->setSavedBy($savedBy);
        $basketNew->setStatus('active');
        $basketNew->setIsDefault(0);

        $this->em->persist($basketNew);
        $this->em->flush();

        foreach($basketItems as $item){

            $basketItemsNew = new BasketItems();
            $product = $this->em->getRepository(Products::class)->find($item->getProduct());
            $distributor = $this->em->getRepository(Distributors::class)->find($item->getDistributor());

            $basketItemsNew->setBasket($basketNew);
            $basketItemsNew->setProduct($product);
            $basketItemsNew->setDistributor($distributor);
            $basketItemsNew->setName($item->getName());
            $basketItemsNew->setQty($item->getQty());
            $basketItemsNew->setUnitPrice($item->getUnitPrice());
            $basketItemsNew->setTotal($item->getQty() * $item->getUnitPrice());
            $basketItemsNew->setItemId($item->getItemId());

            $this->em->persist($basketItemsNew);
            $this->em->flush();
        }

        // Clear Basket
        if((int) $request->request->get('clear') == 1){

            $basket->setTotal('0.00');
            $basket->setSavedBy($savedBy);

            foreach($basketItems as $item){

                $basketItem = $this->em->getRepository(BasketItems::class)->find($item->getId());

                $this->em->remove($basketItem);
                $this->em->flush();
            }
        }

        $response = [
            'message' => '<b><i class="fas fa-check-circle"></i> '. $product->getName() .'</b> saved for later.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            'basketId' => $basketNew->getId()

        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/get-saved-baskets', name: 'get_saved_baskets')]
    public function getSavedBasketsAction(Request $request): Response
    {
        $response = $this->getSavedbasketsRightColumn();

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/get-saved-basket-details', name: 'get_saved_basket_details')]
    public function getSavedBasketDetailsAction(Request $request): Response
    {
        $savedBasket = $this->em->getRepository(BasketItems::class)->findBy(
            [
                'basket' => $request->request->get('basket_id')
            ]);

        if(count($savedBasket) > 0)
        {
            $response = '
            <div class="row border-bottom-dashed">
                <div class="col-3 pt-3 pb-3">
                    <b>Name</b>
                </div>
                <div class="col-3 pt-3 pb-3">
                    <b>Unit Price</b>
                </div>
                <div class="col-2 pt-3 pb-3">
                   <b>Quantity</b>
                </div>
                <div class="col-2 pt-3 pb-3">
                    <b>Total Price</b>
                </div>
                <div class="col-2 pt-3 pb-3" style="padding-top: 3px">
                    <b>Status</b>
                </div>
            </div>';

            $i = 0;

            foreach ($savedBasket as $basket)
            {
                $stockCount = $basket->getProduct()->getDistributorProducts()[0]->getStockCount();
                $status = 'Out Of Stock';

                if($stockCount > 1)
                {
                    $status = $stockCount . ' In Stock';
                }

                $i++;
                $unitPrice = $basket->getProduct()->getDistributorProducts()[0]->getUnitPrice();

                $response .= '
                <div class="row border-bottom-dashed">
                    <div class="col-3 pt-3 pb-3">
                        '. $basket->getProduct()->getName() .' '. $basket->getProduct()->getDosage() .' '. $basket->getProduct()->getUnit() .'
                    </div>
                    <div class="col-3 pt-3 pb-3">
                        $' . number_format($unitPrice, 2) . '
                    </div>
                    <div class="col-2 pt-3 pb-3">
                       ' . $basket->getQty() . '
                    </div>
                    <div class="col-2 pt-3 pb-3">
                        $' . number_format($unitPrice * $basket->getQty(), 2) . '
                    </div>
                    <div class="col-2 pt-3 pb-3" style="padding-top: 3px">
                        '. $status .'
                    </div>
                </div>';
            }
        }
        else
        {
            $response = '
            <div class="row border-bottom-dashed">
                <div class="col-12 pt-3 pb-3 text-center">
                    <p></p>
                    <h5>Your basket at Fluid Commerce is currently empty </h5><br>
                    Were you expecting to see items here? View copies of the items most recently added<br> 
                    to your basket and restore a basket if needed.
                    <p></p>
                </div>
            </div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory/update-saved-baskets', name: 'update_saved_baskets')]
    public function updateSavedBasketsAction(Request $request): Response
    {
        $basket = $this->em->getRepository(Baskets::class)->find($request->request->get('basket_id'));
        $firstName = $this->getUser()->getFirstName();
        $lastName = $this->getUser()->getLastName();

        $basket->setName($request->request->get('basket_name'));
        $basket->setSavedBy($this->encryptor->encrypt($firstName .' '. $lastName));

        $this->em->persist($basket);
        $this->em->flush();

        $response = $this->getBasketLeftColumn($request);

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

    #[Route('/clinics/inventory/delete-saved-basket', name: 'delete_saved_basket')]
    public function deleteBasketAction(Request $request): Response
    {
        $basketId = $request->request->get('basket_id');
        $basketItems = $this->em->getRepository(BasketItems::class)->findBy(['basket' => $basketId]);
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $baskets= $this->em->getRepository(Baskets::class)->findBy([
            'clinic' => $this->getUser()->getClinic()->getId()
        ]);

        if(count($basketItems) > 0){

            foreach($basketItems as $item){

                $this->em->remove($item);
                $this->em->flush();
            }
        }

        $this->em->remove($basket);
        $this->em->flush();

        $response = [
            'left_col' => $this->getBasketLeftColumn($request),
            'right_col' => $this->getSavedbasketsRightColumn(),
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/get/basket', name: 'get_basket')]
    public function getBasketAction(Request $request): Response
    {
        $clinicId = $this->getUser()->getClinic()->getId();
        $basketId = $request->request->get('basket_id') ?? $request->get('basket_id');
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $baskets = $this->em->getRepository(Baskets::class)->findActiveBaskets($clinicId);
        $currency = $this->getUser()->getClinic()->getCountry()->getCurrency();
        $clinicTotals = $this->em->getRepository(Baskets::class)->getClinicTotalItems($clinicId);
        $savedItems = $this->em->getRepository(ClinicProducts::class)->findBy([
            'clinic' => $this->getUser()->getClinic()->getId()
        ]);

        $totalClinic = number_format($clinicTotals[0]['total'] ?? 0,2);
        $countClinic = $clinicTotals[0]['item_count'] ?? 0;

        // Permissions
        $permissions = json_decode($request->request->get('permissions'), true);

        $basketPermission = true;
        $disabled = '';

        if(!in_array(1, $permissions)){

            $disabled = 'disabled';
            $basketPermission = false;
        }

        $response = '
        <!-- Basket Name -->
        <div class="row">
            <div class="col-12 text-center pt-3 pb-3 form-control-bg-grey" id="basket_header">
                <h4 class="text-primary">'. $basket->getName() .' Basket</h4>
                <span class="text-primary">
                    Manage All Your Shopping Carts In One Place
                </span>
            </div>
        </div>';

        $response .= '
        <div class="row">
            <div class="col-12 half-border">
                <div class="row border-xy">
                    <!-- Left Column -->
                    <div class="col-12 col-md-2 col-100" id="basket_left_col">
                        <div class="row border-bottom text-center py-3">
                            <b>All Baskets</b>
                        </div>
                        <div class="row" style="background: #f4f8fe">
                            <div class="col-6 border-bottom pt-1 pb-1 text-center">
                                <span class="d-block text-primary">'. $countClinic .'</span>
                                <span class="d-block">Items</span>
                            </div>
                            <div class="col-6 border-bottom pt-1 pb-1 text-center">
                                <span class="d-block text-primary">'. $currency .' '. $totalClinic .'</span>
                                <span class="d-block text-truncate">Subtotal</span>
                            </div>
                        </div>';

                        foreach($baskets as $individualBasket)
                        {
                            $count = $individualBasket->getBasketItems()->count();
                            $bgPrimary = '';
                            $active = '';

                            if($count > 0){

                                $bgPrimary = 'bg-primary';
                            }

                            if($individualBasket->getId() == $basketId){

                                $active = 'active-basket';
                            }

                            $response .= '
                            <div class="row">
                                <div class="col-12 border-bottom '. $active .'">
                                    <a href="#" data-basket-id="'. $individualBasket->getId() .'" class=" pt-3 pb-3 d-block basket-link">
                                        <div class="row">
                                            <div class="col-10 text-truncate pe-0">
                                                <span class="align-baseline">'. $individualBasket->getName() .'</span>
                                            </div>
                                            <div class="col-2 ps-0">
                                                <span class="float-end basket-item-count-empty '. $bgPrimary .'">
                                                    '. $count .'
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>';
                        }

                        if($basketPermission){

                            $individualBasket = 'a href="#" class="saved_baskets_link"';
                            $closingTag = 'a';
                            $textDisabled = '';

                        } else {

                            $individualBasket = 'span class="text-disabled"';
                            $closingTag = 'span';
                            $textDisabled = 'text-disabled';
                        }

                        $response .= '
                        <div class="row border-bottom">
                            <div class="col-12 h-100">
                                <'. $individualBasket .' href="#" class="saved_baskets_link" data-basket-id="'. $basketId .'">
                                    <div class="row align-items-center">
                                        <div class="d-block d-md-none d-lg-block col-4 pt-3 pb-3 saved-baskets text-truncate '. $textDisabled .'">
                                            <i class="fa-regular fa-basket-shopping"></i>
                                        </div>
                                        <div class="col-8 pt-3 pb-3 text-truncate">
                                            <h6 class="text-primary text-truncate '. $textDisabled .'" style="font-size: 0.95rem">
                                                Saved Baskets
                                            </h6>
                                            <span class="info '. $textDisabled .'">View baskets</span>
                                        </div>
                                    </div>
                                </'. $closingTag .'>
                            </div>
                        </div>
                    </div>
                
                    <!-- Right Column -->
                    <div class="col-12 col-md-10 col-100 border-left position-relative" id="basket_items">
                        <!-- Basket Actions Upper Row -->
                        <div class="row" id="basket_action_row_1">
                            <div class="col-12 d-flex justify-content-center border-bottom pt-3 pb-3">';

                            $response .= '
                            <a href="#" id="print_basket">
                                <i class="fa-regular fa-print me-5 me-md-2"></i>
                                <span class=" d-none d-md-inline-block pe-4">Print</span>
                            </a>';

                            if($basketPermission){

                                $response .= '
                                <a href="#" class="saved_baskets_link" data-basket-id="'. $basketId .'">
                                    <i class="fa-regular fa-basket-shopping me-5  me-md-2"></i>
                                    <span class=" d-none d-md-inline-block pe-4">
                                        Saved Baskets
                                    </span>
                                </a>';

                            } else {

                                $response .= '
                                <span class="saved_baskets_link text-disabled">
                                    <i class="fa-regular fa-basket-shopping me-5  me-md-2 text-disabled"></i>
                                    <span class=" d-none d-md-inline-block pe-4 text-disabled">
                                        Saved Baskets
                                    </span>
                                </span>';
                            }

                            $response .= '
                            <a href="#" id="return_to_search" data-basket-id="">
                                <i class="fa-solid fa-magnifying-glass me-0 me-md-2"></i><span class=" d-none d-md-inline-block pe-4">Back To Search</span>
                            </a>
                        </div>
                    </div>';

        if(count($basket->getBasketItems()) > 0) {

            $response .= '
            <!-- Basket Actions Lower Row -->
            <div class="row" id="basket_action_row_2">
                <div class="col-12 d-flex justify-content-center border-bottom pt-3 pb-3">';

                    if($basketPermission){

                        $response .= '
                        <a href="#" class="save-all-items" data-basket-id="' . $basketId . '">
                            <i class="fa-regular fa-bookmark me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Save All For Later</span>
                        </a>';

                    } else {

                        $response .= '
                        <span class="save-all-items text-disabled">
                            <i class="fa-regular fa-bookmark me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Save All For Later</span>
                        </span>';
                    }

                    if($basketPermission){

                        $response .= '
                        <a href="#" class="clear-basket" data-basket-id="' . $basketId . '">
                            <i class="fa-regular fa-trash-can me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Clear Basket</span>
                        </a>';

                    } else {

                        $response .= '
                        <span class="clear-basket text-disabled">
                            <i class="fa-regular fa-trash-can me-5 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-4">Clear Basket</span>
                        </span>';
                    }

                    $response .= '
                    <a href="#" class="refresh-basket" data-basket-id="'. $basketId .'">
                        <i class="fa-solid fa-arrow-rotate-right me-5 me-md-2"></i>
                        <span class=" d-none d-md-inline-block pe-4">Refresh Basket</span>
                    </a>';

                    if($basketPermission){

                        $response .= '
                        <a href="#" data-bs-toggle="modal" data-bs-target="#modal_save_basket">
                            <i class="fa-regular fa-basket-shopping me-0 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-0">Save Basket</span>
                        </a>';

                    } else {

                        $response .= '
                        <span class="text-disabled">
                            <i class="fa-regular fa-shopping-basket me-0 me-md-2"></i>
                            <span class=" d-none d-md-inline-block pe-0">Save Basket</span>
                        </span>';
                    }

                $response .= '
                </div>
            </div>';
        }

        $basketSummary = false;
        $col = '12';

        if(count($basket->getBasketItems()) > 0) {

            $basketSummary = true;
            $col = '9 border-right';
        }

        $response .= '
        <!-- Basket Items -->
        <div class="row col-container d-flex border-0 m-0">
            <div class="col-12 col-lg-'. $col .' col-cell px-0 pe-sm-2 border-right-0" id="basket_inner">';

        $i = -1;
        $checkoutDisabled = '';
        $checkoutBtnDisabled = '';
        $checkout = true;

        if(count($basket->getBasketItems()) > 0) {

            foreach ($basket->getBasketItems() as $item) {

                $i++;
                $product = $basket->getBasketItems()[$i]->getProduct();
                $trackingId = $item->getDistributor()->getTracking()->getId();
                $shippingPolicy = $item->getDistributor()->getShippingPolicy() ?? $this->encryptor->decrypt($item->getDistributor()->getDistributorName()) ." hasn't updated their shipping policy.";
                $distributorProduct = $this->em->getRepository(DistributorProducts::class)->findOneBy([
                    'product' => $item->getProduct()->getId(),
                    'distributor' => $item->getDistributor()->getId(),
                ]);

                // If in stock
                if($distributorProduct->getStockCount() > 0){

                    $stockBadge = '<span class="badge bg-success me-0 me-sm-2 badge-success-filled-sm">In Stock</span>';

                } else {

                    if($trackingId == 1 || $trackingId == 2) {

                        $stockBadge = '<span class="badge bg-danger me-2">Out Of Stock</span>';
                        $disabled = 'disabled';
                        $checkout = false;

                    } else {

                        $stockBadge = '<span class="badge bg-success me-0 me-sm-2 badge-success-filled-sm">In Stock</span>';
                    }
                }

                // Product Image
                $productImages = $product->getProductImages();
                $image = 'image-not-found.jpg';

                if(count($productImages) > 0){

                    $image = $this->em->getRepository(ProductImages::class)->findOneBy([
                        'product' => $product->getId(),
                        'isDefault' => 1
                    ])->getImage();
                }

                $response .= '
                <div class="row">
                    <!-- Thumbnail -->
                    <div class="col-12 col-sm-2 text-center pt-3 pb-3 mt-3">
                        <img class="img-fluid basket-img" src="/images/products/' . $image . '">
                    </div>
                    <div class="col-12 col-sm-10 pt-3 pb-3">
                        <!-- Product Name and Qty -->
                        <div class="row">
                            <!-- Product Name -->
                            <div class="col-12 col-sm-6 col-md-12 col-lg-7 pt-3 pb-3 text-center text-sm-start">
                                <span class="info">
                                    '. $this->encryptor->decrypt($distributorProduct->getDistributor()->getDistributorName()) .'
                                </span>
                                <h6 class="fw-bold text-primary lh-base">
                                    ' . $product->getName() . ': ' . $product->getDosage() . ' ' . $product->getUnit() . '
                                </h6>
                            </div>
                            <!-- Product Quantity -->
                            <div class="col-12 col-sm-6 col-md-12 col-lg-5 pt-3 pb-3">
                                <div class="row">
                                    <div class="col-3 text-center text-sm-end text-md-start text-lg-start">
                                        ' . number_format($item->getUnitPrice(),2) . '
                                    </div>
                                    <div class="col-4">
                                        <input
                                            type="number"
                                            list="qty_list_' . $product->getId() . '"
                                            data-basket-item-id="' . $item->getId() . '"
                                            name="qty"
                                            class="form-control form-control-sm basket-qty"
                                            value="' . $item->getQty() . '"
                                            ng-value="' . $item->getQty() . '"
                                            '. $disabled .'
                                        >
                                        <div class="hidden_msg" id="stock_count_error_'. $item->getId() .'"></div>
                                    </div>
                                    <div class="col-5 text-center text-sm-start text-md-end fw-bold text-truncate">' . $currency .' '. number_format($item->getTotal(),2) . '</div>
                                </div>
                            </div>
                        </div>
                        <!-- Item Actions -->
                        <div class="row">
                            <div class="col-12">
                                <!-- In Stock -->
                                '. $stockBadge .'
                                <!-- Shipping Policy -->
                                <span
                                    class="badge bg-dark-grey badge-pending-filled-sm" class="btn btn-secondary" data-bs-trigger="hover"
                                    data-bs-container="body" data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true"
                                    data-bs-content="'. $shippingPolicy .'"
                                >
                                    Shipping Policy
                                </span>
                                ';

                                    if($basketPermission) {

                                        $response .= '
                                        <!-- Remove Item -->
                                        <span class="badge bg-danger float-end badge-danger-filled-sm">
                                            <a href="#" class="remove-item text-white" data-item-id="' . $item->getId() . '">Remove</a>
                                        </span>

                                        <!-- Save Item -->
                                        <span class="badge badge-light badge-light-sm float-end me-0 me-sm-2">
                                            <a
                                                href="#"
                                                class="link-secondary save-item"
                                                data-basket-id="' . $basketId . '"
                                                data-product-id="' . $product->getId() . '"
                                                data-distributor-id="' . $item->getDistributor()->getId() . '"
                                                data-item-id="' . $item->getId() . '"
                                            >
                                                Save Item For later
                                            </a>
                                        </span>';

                                    } else {

                                        $response .= '
                                        <!-- Remove Item -->
                                        <span class="badge badge-light badge-light-sm float-end bg-disabled">
                                            <span class="remove-item">Remove</span>
                                        </span>

                                        <!-- Save Item -->
                                        <span class="badge badge-light badge-light-sm float-end me-0 me-sm-2 bg-disabled">
                                            <span
                                                class="link-secondary save-item"
                                            >
                                                Save Item For later
                                            </span>
                                        </span>';
                                    }

                                $response .= '
                                </span>
                            </div>
                        </div>
                    </div>
                </div>';
            }
        } else {

            $response .= '
            <div class="row">
                <div class="col-12 text-center pt-4">
                    <p>
                    <h5>Your basket at Fluid Commerce is currently empty </h5><br>
                    Were you expecting to see items here? View copies of the items most recently added<br>
                    to your basket and restore a basket if needed.
                    </p>
                </div>
            </div>';
        }

        $response .= '
        </div>';

        if($basketSummary) {

            $checkoutError = '';

            if(!$checkout){

                $checkoutDisabled = 'disabled';
                $checkoutBtnDisabled = 'btn-secondary disabled';
                $checkoutError = '
                <div class="text-danger mt-3">
                    One or more items in your basket is currently out of stock. Remove or save the item for later
                    to proceed to checkout. 
                </div>';
            }

            $response .= '
            <!-- Basket Summary -->
            <div class="col-12 col-lg-3 pt-3 pb-3 pe-0 col-cell" id="basket_summary">
                <div class="row">
                    <div class="col-12 text-truncate ps-0 ps-sm-2">
                        <span class="info">Subtotal:</span>
                        <h5 class="d-inline-block text-primary float-end">' . $currency .' '. number_format($basket->getTotal(),2) . '</h5>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 info ps-0 ps-sm-2">
                        Shipping: <span class="float-end fw-bold">'. $currency .' 0.00</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 pt-4 text-center ps-0 ps-sm-2">';

                        if(in_array(3, $permissions)){

                            $response .= '
                            <a
                                href=""
                                class="btn btn-primary w-100 '. $checkoutBtnDisabled .'"
                                id="btn_checkout"
                                data-basket-id="'. $basketId .'"
                                '. $checkoutDisabled .'
                            >
                                PROCEED <i class="fa-solid fa-circle-right ps-2"></i>
                            </a>';

                        } else {

                            $response .= '
                            <span
                                class="btn btn-primary w-100 btn-disabled cursor-disabled"
                            >
                                PROCEED <i class="fa-solid fa-circle-right ps-2"></i>
                            </span>';
                        }

                        $response .= '
                        '. $checkoutError .'
                    </div>
                </div>
            </div>';
        }

        $response .= '
        </div>';

        // Saved Items
        if(count($savedItems) > 0){

            $plural = '';

            if(count($savedItems) > 1){

                $plural = 's';
            }

            $response .= '
            <div class="row" style="background: #f4f8fe; position: absolute; left: 12px; right: 0; bottom: 0" id="saved_items">
                <div class="col-12 border-bottom border-top pt-3 pb-3 text-center text-sm-start">
                    <a href="" id="saved_items_link">Items Saved for Later ('. count($savedItems) .' Item'. $plural .')</a>
                </div>
            </div>
            <div class="row" id="saved_items_container">
                <div class="col-12 border-bottom border-top pt-3 pb-3 position-relative">
                    <div class="row">
                        <div class="col-12">';

                        if($basketPermission){

                            $response .= '
                            <a
                                href=""
                                class="btn btn-primary btn-sm w-sm-100 float-end restore-all"
                                id="restore_all"
                                data-basket-id="'. $basketId .'"
                            >
                                Move All To Basket
                            </a>';

                        } else {

                            $response .= '
                            <button
                                class="btn btn-primary btn-sm w-sm-100 float-end btn-disabled bg-disabled"
                            >
                                Move All To Basket
                            </button>';
                        }

                        $response .= '
                        </div>
                    </div>';

            foreach($savedItems as $item){

                $product = $item->getProduct();

                // Product Image
                $productImages = $product->getProductImages();
                $image = 'image-not-found.jpg';

                if(count($productImages) > 0){

                    $image = $this->em->getRepository(ProductImages::class)->findOneBy([
                        'product' => $product->getId(),
                        'isDefault' => 1
                    ])->getImage();
                }

                $response .= '
                <div class="row saved-item-row">
                    <!-- Thumbnail -->
                    <div class="col-12 col-sm-2 text-center pt-3 pb-3">
                        <img class="img-fluid basket-img" src="/images/products/' . $image . '">
                    </div>
                    <div class="col-12 col-sm-10 pt-3 pb-3">
                        <div class="row">
                            <!-- Product Name -->
                            <div class="col-12 col-sm-7">
                                <h6 class="fw-bold text-center text-sm-start text-primary lh-base mb-0">
                                    ' . $product->getName() . ': ' . $product->getDosage() . ' ' . $product->getUnit() . ', Each
                                </h6>
                                Saved on '. $item->getModified()->format('M jS Y') .' by '. $this->encryptor->decrypt($item->getSavedBy()) .'<br>';

                                if($basketPermission){

                                    $response .= '
                                    <span class="badge badge-light me-2 mt-2 badge-light-sm">
                                        <a
                                            href="#"
                                            class="link-secondary restore-item"
                                            data-basket-id="'. $basketId .'" data-product-id="'. $product->getId() .'"
                                            data-distributor-id="'. $item->getDistributor()->getId() .'"
                                            data-item-id="'. $item->getId() .'"
                                        >
                                            Move To Basket
                                        </a>
                                    </span>

                                    <span class="badge bg-danger mt-2 badge-danger-filled-sm">
                                        <a
                                            href="#" class="text-white remove-saved-item"
                                            data-basket-id=""
                                            data-item-id="'. $item->getId() .'"
                                        >
                                            Remove
                                        </a>
                                    </span>';

                                } else {

                                    $response .= '
                                    <span class="badge badge-light me-2 mt-2 badge-light-sm bg-disabled">
                                        <span 
                                            href="#" 
                                            class="link-secondary"
                                        >
                                            Move To Basket
                                        </span>
                                    </span>
                                    
                                    <span class="badge badge-light me-2 mt-2 badge-light-sm bg-disabled">
                                        Remove
                                    </span>';
                                }

                                $response .= '
                                </div>
                            </div>
                        </div>
                    </div>';
            }
        }

        $response .= '
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal Save Basket -->
        <div class="modal fade" id="modal_save_basket" tabindex="-1" aria-labelledby="save_basket_label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form name="form_save_basket" id="form_save_basket" method="post">
                        <input type="hidden" name="basket_id" value="'. $basketId .'">
                        <div class="modal-header basket-modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pb-0">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6>Clear current basket?</h6>
                                    After you save this basket for later, would you like to clear this basket?
                                </div>
                                <div class="col-12 mb-0">
                                    <input type="text" class="form-control" name="basket_name" id="basket_name" placeholder="Basket Name">
                                </div>
                                <div class="hidden_msg" id="error_basket_name">
                                    Please enter name for the basket
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-primary btn-sm save-basket" name="basket_new_save_clear" data-basket-clear="1">SAVE AND CLEAR</button>
                            <button type="submit" class="btn btn-danger btn-sm save-basket" name="basket_new_save" data-basket-clear="0">SAVE</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/checkout', name: 'checkout_shipping')]
    public function shippingCheckoutAction(Request $request): Response
    {
        $user = $this->em->getRepository(ClinicUsers::class)->find($this->getUser()->getId());
        $clinicId = $this->getUser()->getClinic()->getId();
        $basketId = $request->request->get('basket_id') ?? $request->get('basket_id');
        $basket = $this->em->getRepository(Baskets::class)->find($basketId);
        $baskets = $this->em->getRepository(Baskets::class)->findBy(['clinic' => $clinicId]);
        $clinicTotals = $this->em->getRepository(Baskets::class)->getClinicTotalItems($clinicId);
        $savedItems = $this->em->getRepository(ClinicProducts::class)->findBy([
            'clinic' => $this->getUser()->getClinic()->getId()
        ]);

        $totalClinic = number_format($clinicTotals[0]['total'] ?? 0,2);
        $countClinic = $clinicTotals[0]['item_count'] ?? 0;

        $response = '
        <div class="row">
            <!-- Left Column -->
            <div class="col-12 col-md-2 col-100" id="basket_left_col">
                <div class="row border-bottom text-center pt-2 pb-2">
                    <b>All Baskets</b>
                </div>
                <div class="row" style="background: #f4f8fe">
                    <div class="col-6 border-bottom pt-1 pb-1 text-center">
                        <span class="d-block text-primary">'. $countClinic .'</span>
                        <span class="d-block text-truncate">Items</span>
                    </div>
                    <div class="col-6 border-bottom pt-1 pb-1 text-center">
                        <span class="d-block text-primary">$'. $totalClinic .'</span>
                        <span class="d-block text-truncate">Subtotal</span>
                    </div>
                </div>';

        foreach($baskets as $individualBasket){

            $count = $individualBasket->getBasketItems()->count();
            $bgPrimary = '';
            $active = '';

            if($count > 0){

                $bgPrimary = 'bg-primary';
            }

            if($individualBasket->getId() == $basketId){

                $active = 'active-basket';
            }

            $response .= '
            <div class="row">
                <div class="col-12 border-bottom '. $active .'">
                    <a href="#" data-basket-id="'. $individualBasket->getId() .'" class=" pt-3 pb-3 d-block basket-link">
                        <span class="d-inline-block align-baseline text-truncate">'. $individualBasket->getName() .'</span>
                        <span class="float-end basket-item-count-empty '. $bgPrimary .'">
                            '. $count .'
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
                <div class="col-8 col-sm-12 col-md-8 pt-3 pb-3">
                    <h6 class="text-primary">Saved Baskets</h6>
                    <span class="info">View baskets</span>
                </div>
            </div>
        </div>
        <!-- Right Column -->
        <div class="col-12 col-md-10 col-100 border-left position-relative" id="basket_items">
            <!-- Basket Name -->
            <div class="row">
                <div class="col-12 bg-primary bg-gradient text-center pt-3 pb-3">
                    <h4 class="text-white">'. $basket->getName() .' Basket</h4>
                    <span class="text-white">
                        Manage All Your Shopping Carts In One Place
                    </span>
                </div>
            </div>
            <!-- Basket Actions Upper Row -->
            <div class="row">
                <div class="col-12 d-flex justify-content-evenly border-bottom pt-3 pb-3">
                    <a href="#">
                        <i class="fa-solid fa-arrow-rotate-right me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Refresh Basket</span>
                    </a>
                    <a href="#" id="print_basket">
                        <i class="fa-solid fa-print me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Print</span>
                    </a>
                    <a href="#" id="saved_baskets_link" data-basket-id="'. $basketId .'">
                        <i class="fa-regular fa-basket-shopping me-0 me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Saved Baskets</span>
                    </a>
                    <a href="#" id="return_to_search">
                        <i class="fa-solid fa-magnifying-glass me-0 me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Back To Search</span>
                    </a>
                </div>
            </div>';

        if(count($basket->getBasketItems()) > 0) {

            $response .= '
            <!-- Basket Actions Lower Row -->
            <div class="row">
                <div class="col-12 d-flex justify-content-evenly border-bottom pt-3 pb-3">
                    <a href="#" class="save-all-items" data-basket-id="' . $basketId . '">
                        <i class="fa-regular fa-bookmark me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Save All For Later</span>
                    </a>
                    <a href="#" class="clear-basket" data-basket-id="' . $basketId . '">
                        <i class="fa-solid fa-trash-can me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Clear Basket</span>
                    </a>
                    <a href="#" class="refresh-basket" data-basket-id="'. $basketId .'">
                        <i class="fa-solid fa-arrow-rotate-right me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Refresh Basket</span>
                    </a>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#modal_save_basket">
                        <i class="fa-regular fa-basket-shopping me-md-2"></i><span class=" d-none d-md-inline-block pe-3">Save Basket</span>
                    </a>
                </div>
            </div>';
        }

        $basketSummary = false;
        $col = '12';

        if(count($basket->getBasketItems()) > 0) {

            $basketSummary = true;
            $col = '9 border-right';
        }

        $response .= '
        <!-- Basket Items -->
        <div class="row col-container d-flex border-0 m-0">
            <div class="col-12 col-md-'. $col .' col-cell ps-0">';

        $i = -1;

        if(count($basket->getBasketItems()) > 0) {

            foreach ($basket->getBasketItems() as $item) {

                $i++;

                $product = $basket->getBasketItems()[$i]->getProduct();
                $shippingPolicy = $item->getDistributor()->getShippingPolicy() ?? $this->encryptor->decrypt($item->getDistributor()->getDistributorName()) ."hasn\'t updated their shipping policy.";

                if($product->getStockCount() > 0){

                    $stockBadge = '<span class="badge bg-success me-2">In Stock</span>';

                } else {

                    $stockBadge = '<span class="badge bg-danger me-2">Out Of Stock</span>';
                }

                $response .= '
                <div class="row">
                    <!-- Thumbnail -->
                    <div class="col-12 col-sm-2 text-center pt-3 pb-3">
                        <img class="img-fluid basket-img" src="/images/products/' . $product->getImage() . '">
                    </div>
                    <div class="col-12 col-sm-10 pt-3 pb-3">
                        <!-- Product Name and Qty -->
                        <div class="row">
                            <!-- Product Name -->
                            <div class="col-12 col-sm-7 pt-3 pb-3">
                                <h6 class="fw-bold text-center text-sm-start text-primary lh-base">
                                    ' . $product->getName() . ': ' . $product->getDosage() . ' ' . $product->getUnit() . ', Each
                                </h6>
                            </div>
                            <!-- Product Quantity -->
                            <div class="col-12 col-sm-5 pt-3 pb-3">
                                <div class="row">
                                    <div class="col-4 text-center text-sm-end">$' . number_format($item->getUnitPrice(),2) . '</div>
                                    <div class="col-4">
                                        <input 
                                            type="text" 
                                            list="qty_list_' . $product->getId() . '" 
                                            data-basket-item-id="' . $item->getId() . '" 
                                            name="qty" 
                                            class="form-control basket-qty" 
                                            value="' . $item->getQty() . '" 
                                            ng-value="' . $item->getQty() . '"
                                        >
                                        <datalist id="qty_list_' . $product->getId() . '">
                                            <option>1</option>
                                            <option>2</option>
                                            <option>3</option>
                                            <option>4</option>
                                            <option>5</option>
                                            <option>6</option>
                                            <option>7</option>
                                            <option>8</option>
                                            <option>9</option>
                                            <option>10</option>
                                            <option>11</option>
                                            <option>12</option>
                                            <option>13</option>
                                            <option>14</option>
                                            <option>15</option>
                                            <option>16</option>
                                            <option>17</option>
                                            <option>18</option>
                                            <option>19</option>
                                            <option>20</option>
                                            <option id="qty_custom">Enter Quantity</option>
                                        </datalist>
                                    </div>
                                    <div class="col-4 text-center text-sm-start fw-bold">$' . number_format($item->getTotal(),2) . '</div>
                                </div>
                            </div>
                        </div>
                        <!-- Item Actions -->
                        <div class="row">
                            <div class="col-12">
                                <!-- In Stock -->
                                '. $stockBadge .'
                                <!-- Shipping Policy -->
                                <span class="badge bg-dark-grey" class="btn btn-secondary" data-bs-trigger="hover"
                                      data-bs-container="body" data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true"
                                      data-bs-content="'. $shippingPolicy .'">Shipping Policy</span>
                                <!-- Remove Item -->
                                <span class="badge bg-danger float-end">
                                    <a href="#" class="remove-item text-white" data-item-id="' . $item->getId() . '">Remove</a>
                                </span>
                                <!-- Save Item -->
                                <span class="badge badge-light float-end me-2">
                                    <a href="#" class="link-secondary save-item" data-basket-id="'. $basketId .'" data-product-id="'. $product->getId() .'" data-distributor-id="'. $item->getDistributor()->getId() .'" data-item-id="' . $item->getId() . '">Save Item For later</a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>';
            }
        } else {

            $response .= '
            <div class="row">
                <div class="col-12 text-center pt-4">
                    <p>
                    <h5>Your basket at Fluid Commerce is currently empty </h5><br>
                    Were you expecting to see items here? View copies of the items most recently added<br> 
                    to your basket and restore a basket if needed.
                    </p>
                </div>
            </div>';
        }

        $response .= '
                    </div>';

        if($basketSummary) {

            $response .= '
            <!-- Basket Summary -->
            <div class="col-12 col-md-3 pt-3 pb-3 pe-0 col-cell">
                <div class="row">
                    <div class="col-12 text-truncate ps-0 ps-sm-2">
                        <span class="info">Subtotal:</span>
                        <h5 class="d-inline-block text-primary float-end">$' . number_format($basket->getTotal(),2) . '</h5>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 info ps-0 ps-sm-2">
                        Shipping: <span class="float-end fw-bold">$6.99</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 pt-4 text-center ps-0 ps-sm-2">
                        <a href="" class="btn btn-primary w-100" id="btn_checkout">
                            PROCEED TO CHECKOUT <i class="fa-solid fa-circle-right ps-2"></i>
                        </a>
                    </div>
                </div>
            </div>';
        }

        $response .= '
                </div>';

        // Saved Items
        if(count($savedItems) > 0){

            $plural = '';

            if(count($savedItems) > 1){

                $plural = 's';
            }

            $response .= '
            <div class="row" style="background: #f4f8fe; position: absolute; left: 12px; right: 0; bottom: 0">
                <div class="col-12 border-bottom border-top pt-3 pb-3">
                    <a href="" id="saved_items_link">Items Saved for Later ('. count($savedItems) .' Item'. $plural .')</a>
                </div>
            </div>
            <div class="row" id="saved_items_container">
                <div class="col-12 border-bottom border-top pt-3 pb-3 position-relative">
                    <div class="row">
                        <div class="col-12">
                            <a href="" class="btn btn-primary btn-sm w-sm-100 float-end restore-all" id="restore_all" data-basket-id="'. $basketId .'">
                                Move All To Basket
                            </a>
                        </div>
                    </div>    
                ';

            foreach($savedItems as $item){

                $product = $item->getProduct();

                $response .= '
                    <div class="row">
                        <!-- Thumbnail -->
                        <div class="col-12 col-sm-2 text-center pt-3 pb-3">
                            <img class="img-fluid basket-img" src="/images/products/' . $product->getImage() . '">
                        </div>
                        <div class="col-12 col-sm-10 pt-3 pb-3">
                            <div class="row">
                                <!-- Product Name -->
                                <div class="col-12 col-sm-7">
                                    <h6 class="fw-bold text-center text-sm-start text-primary lh-base mb-0">
                                        ' . $product->getName() . ': ' . $product->getDosage() . ' ' . $product->getUnit() . ', Each
                                    </h6>
                                    Saved onxxx '. $item->getModified()->format('M jS Y') .' by '. $this->encryptor->decrypt($item->getSavedBy()) .'<br>
                                    <span class="badge badge-light me-2 mt-2 badge-light-sm">
                                        <a 
                                            href="#" class="link-secondary restore-item" 
                                            data-basket-id="'. $basketId .'" 
                                            data-product-id="'. $product->getId() .'" 
                                            data-distributor-id="'. $item->getDistributor()->getId() .'" 
                                            data-item-id="'. $item->getId() .'"
                                        >
                                            Move To Basket
                                        </a>
                                    </span>
                                    <span class="badge bg-danger mt-2 badge-danger-filled-sm">
                                        <a 
                                            href="#" class="text-white remove-saved-item" 
                                            data-basket-id="" 
                                            data-item-id="'. $item->getId() .'"
                                        >
                                            Remove
                                        </a>
                                    </span>
                                </div>
                            </div>
                       
                        </div>
                    </div>';
            }
        }

        $response .= '     
                    </div>
                </div>   
            </div>
        </div>
        <!-- Modal Save Basket -->
        <div class="modal fade" id="modal_save_basket" tabindex="-1" aria-labelledby="save_basket_label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form name="form_save_basket" id="form_save_basket" method="post">
                        <input type="hidden" name="basket_id" value="'. $basketId .'">
                        <div class="modal-header basket-modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pb-0">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6>Clear current basket?</h6>
                                    After you save this basket for later, would you like to clear this basket?
                                </div>
                                <div class="col-12 mb-0">
                                    <input type="text" class="form-control" name="basket_name" id="basket_name" placeholder="Basket Name">
                                </div>
                                <div class="hidden_msg" id="error_basket_name">
                                    Please enter name for the basket
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-primary btn-sm save-basket" name="basket_new_save_clear" data-basket-clear="1">SAVE AND CLEAR</button>
                            <button type="submit" class="btn btn-danger btn-sm save-basket" name="basket_new_save" data-basket-clear="0">SAVE</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        return new JsonResponse($response);
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
        $clinicId = $data->get('clinic-id');
        $productId = $data->get('product-id');
        $price = $data->get('price');
        $qty = $data->get('qty');
        $basket = $this->em->getRepository(Baskets::class)->findOneBy([
            'retailUser' => $retailUserId
        ]);
        $product = $this->em->getRepository(Products::class)->find($productId);

        // Create new basket if one doesn't exist
        if($basket == null)
        {
            $basket = new Baskets();

            $basket->setClinic($clinicId);
            $basket->setDistributor(null);
            $basket->setRetailUser($retailUser);
            $basket->setStatus('active');
            $basket->setName('fluid Commerce');
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
        $basketItem->setDistributor(null);
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
                                                    '. $this->encryptor->decrypt($basketItem->getBasket()->getClinic()->getClinicName()) .'
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
