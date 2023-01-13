<?php

namespace App\Controller;

use App\Entity\ApiDetails;
use App\Entity\Baskets;
use App\Entity\Categories;
use App\Entity\Categories1;
use App\Entity\Categories2;
use App\Entity\Categories3;
use App\Entity\ClinicProducts;
use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\DistributorClinics;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\ListItems;
use App\Entity\Lists;
use App\Entity\Manufacturers;
use App\Entity\OrderItems;
use App\Entity\Orders;
use App\Entity\ProductFavourites;
use App\Entity\ProductImages;
use App\Entity\ProductManufacturers;
use App\Entity\ProductNotes;
use App\Entity\ProductRetail;
use App\Entity\ProductReviews;
use App\Entity\Products;
use App\Entity\ProductsSpecies;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Vich\UploaderBundle\Handler\DownloadHandler;

class ProductsController extends AbstractController
{
    const ITEMS_PER_PAGE = 10;
    private $pageManager;
    private $em;
    private $requestStack;
    private $encryptor;

    public function __construct(
        EntityManagerInterface $entityManager, PaginationManager $pagination,
        RequestStack $requestStack, Encryptor $encryptor
    )
    {
        $this->pageManager = $pagination;
        $this->em = $entityManager;
        $this->requestStack = $requestStack;
        $this->encryptor = $encryptor;
    }

    #[Route('/clinics/inventory', name: 'search_results')]
    #[Route('/clinics/inventory/lists', name: 'clinics_search_lists')]
    #[Route('/clinics/inventory/list/{list_id}', name: 'search_lists')]
    #[Route('/clinics/analytics', name: 'clinic_analytics')]
    #[Route('/clinics/basket/{basket_id}', name: 'clinic_basket')]
    #[Route('/clinics/saved/baskets', name: 'clinic_saved_basket')]
    #[Route('/clinics/account', name: 'clinic_account_settings')]
    #[Route('/clinics/users', name: 'clinic_users')]
    #[Route('/clinics/addresses', name: 'clinic_addresses')]
    #[Route('/clinics/communication-methods', name: 'clinic_communication_methods')]
    #[Route('/clinics/order/{order_id}/{distributor_id}', name: 'clinic_order_details')]
    #[Route('/clinics/orders/{clinic_id}', name: 'clinic_orders_list')]
    #[Route('/clinics/inventory/manage/list/{list_id}', name: 'clinic_edit_shopping_list')]
    #[Route('/clinics/manage-inventory', name: 'clinics_manage_inventory')]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLINIC');
        $clinic = $this->getUser()->getClinic();
        $user = $this->em->getRepository(ClinicUsers::class)->find($this->getUser()->getId());
        $response = 'Please use the search bar above....';
        $distributors = $this->em->getRepository(Distributors::class)->findAll();
        $manufacturers = $this->em->getRepository(Manufacturers::class)->findBy([], ['name' => 'ASC']);
        $basket = $this->em->getRepository(Baskets::class)->findClinicDefaultBasket($clinic->getId());
        $clinicOrderDetails = false;
        $clinicOrderList = false;
        $charts = $this->forward('App\Controller\ChartsController::getChartsAction')->getContent();
        $distributorProducts = $this->em->getRepository(DistributorClinics::class)->findBy([
            'clinic' => $clinic->getId()
        ]);

        $permissions = [];

        foreach($user->getClinicUserPermissions() as $permission){

            $permissions[] = $permission->getPermission()->getId();
        }

        if(substr($request->getPathInfo(),0,16) == '/clinics/orders/'){

            $clinicOrderList = true;
        }

        if(substr($request->getPathInfo(),0,15) == '/clinics/order/'){

            $clinicOrderDetails = true;
        }

        $count_1 = (int) ceil(count($manufacturers) / 2);
        $count_2 = (int) floor(count($manufacturers) / 2);

        $man_first = array_slice($manufacturers, 0, $count_1);
        $man_second = array_slice($manufacturers, $count_1, $count_2);

        return $this->render('frontend/clinics/index.html.twig',[
            'user' => $user,
            'response' => $response,
            'distributors' => $distributors,
            'man_1' => $man_first,
            'man_2' => $man_second,
            'basket_id' => $basket[0]->getId(),
            'clinic_order_details' => $clinicOrderDetails,
            'clinicOrderList' => $clinicOrderList,
            'clinic_id' => $clinic->getId(),
            'charts' => $charts,
            'permissions' => $permissions,
            'distributorProducts' => $distributorProducts,
            'clinic' => $clinic,
        ]);
    }

    #[Route('/clinics/search-inventory', name: 'search_inventory')]
    public function getSearchInventoryAction(Request $request, int $page_no = 1): Response
    {
        $user = $this->em->getRepository(ClinicUsers::class)->find($this->getUser()->getId());
        $clinic = $this->getUser()->getClinic();
        $html = '';
        $listId = '';
        $level = 0;
        $keywords = $request->get('keyword');
        $categoryKeywords = $request->request->get('keyword');
        $arraySearch = $request->request->get('search-array');
        $shoppingListId = $request->request->get('list-id');
        $currency = $this->getUser()->getClinic()->getCountry()->getCurrency();
        $category = null;
        $filterLists['categoryList'] = null;
        $filterLists['distributorsList'] = null;
        $filterLists['manufacturersList'] = null;
        $filterLists['favouriteCount'] = null;
        $filterLists['inStockCount'] = null;
        $productIds = null;

        if($arraySearch != null) {

            // Categories
            if(array_key_exists('category', $arraySearch[0])) {

                $category = $arraySearch[0]['category'];
            }

            // Keywords
            $arraySearch[0]['categoryKeyword'] = $categoryKeywords;

            // Distributors
            if(array_key_exists('distributors', $arraySearch[0])) {

                $distributors = $arraySearch[0]['distributors'];
            }
        }

        if($keywords != null || $arraySearch != null || $shoppingListId != null) {

            // Return keyword search
            if($keywords != null && $arraySearch == null) {

                $products = $this->em->getRepository(Products::class)->findByKeystring(
                    $keywords,
                    $this->getUser()->getClinic()->getCountry()->getId(),
                );

            // Return saved list
            } elseif($shoppingListId != null){

                $listItems = $this->em->getRepository(ListItems::class)->findByListId($request->get('list-id'));
                $listId = 'data-list-id="'. $request->get('list-id') .'"';
                $productIds = [];

                foreach($listItems[1] as $item){

                    $productIds[] = $item->getProduct()->getId();
                }

                $products = $this->em->getRepository(Products::class)->findByListId($productIds);

            // Apply Filters
            } elseif($arraySearch[0] != null){

                $level = $category[0]['level'] ?? 0;

                $products = $this->em->getRepository(Products::class)->findByFilter($arraySearch, $level);
            }

            $results = $this->pageManager->paginate($products[0], $request, self::ITEMS_PER_PAGE);
            $filterLists = $this->getProductFilters($products[1], $level, $arraySearch);

            if(count($results) == 0) {

                $html = 'No results found...';
            }

            $i = 0;
            $productIds = [];

            foreach($results as $product){

                $i++;
                $productIds[] = $product->getId();
                $favourite = $this->em->getRepository(Lists::class)->findOneBy([
                    'listType' => 'favourite',
                    'clinic' => $user->getClinic()->getId()
                ]);
                $retail = $this->em->getRepository(Lists::class)->findOneBy([
                    'listType' => 'retail',
                    'clinic' => $user->getClinic()->getId()
                ]);
                $productNotes = $this->em->getRepository(ProductNotes::class)->findNotes($product->getId(), $user->getClinic()->getId());
                $countReviews = $this->em->getRepository(ProductReviews::class)->findBy([
                    'product' => $product->getId(),
                    'isApproved' => 1,
                ]);
                $countNotes = $product->getProductNotes()->count();
                $countClinicsBought = $this->em->getRepository(OrderItems::class)->findBy([
                    'product' => $product->getId()
                ]);
                $productFavourite = $this->em->getRepository(ProductFavourites::class)->findOneBy([
                    'product' => $product->getId(),
                    'clinic' => $this->getUser()->getClinic()->getId()
                ]);
                $productRetail = $this->em->getRepository(ProductRetail::class)->findOneBy([
                    'product' => $product->getId(),
                    'clinic' => $this->getUser()->getClinic()->getId()
                ]);
                $firstImage = $this->em->getRepository(ProductImages::class)->findOneBy([
                    'product' => $product->getId(),
                    'isDefault' => 1
                ]);

                // Create Retail List
                if($retail == null)
                {
                    $retail = new Lists();

                    $retail->setClinic($clinic);
                    $retail->setListType('retail');
                    $retail->setName('Retail Items');
                    $retail->setItemCount(0);
                    $retail->setIsProtected(1);

                    $this->em->persist($retail);
                    $this->em->flush();
                }

                if($firstImage == null){

                    $firstImage = 'image-not-found.jpg';

                } else {

                    $firstImage = $firstImage->getImage();
                }

                $productManufacturers = $product->getProductManufacturers();

                if(count($productManufacturers) == 1){

                    $manufacturer = $this->encryptor->decrypt($productManufacturers[0]->getManufacturers()->getName());

                } else {

                    $manufacturer = 'Multiple Manufacturers';
                }

                $note = '';
                $class = '';
                $reviewCount = '';
                $noteCount = '';

                if(count($countReviews) > 0){

                    $reviewCount = '
                    <span 
                        class="position-absolute text-opacity-25 start-25 start-sm-100 translate-middle badge border rounded-circle bg-primary"
                        style="z-index: 999"
                    >
                        '. count($countReviews) .'
                    </span>';
                }

                if($countNotes > 0){

                    $noteCount = '
                    <span 
                        class="position-absolute text-opacity-25 start-25 start-sm-100 translate-middle badge border rounded-circle bg-primary"
                        style="z-index: 999"
                    >
                        '. $countNotes .'
                    </span>';
                }

                // Favourite Icon
                if($productFavourite == null){

                    $favouriteIcon = 'icon-unchecked';
                    $dataFavourite = 'false';
                    $dataDistributor = '';

                } else {

                    $listItem = $this->em->getRepository(ListItems::class)->findOneBy([
                        'product' => $product->getId(),
                        'list' => $favourite->getId()
                    ]);

                    $favouriteIcon = 'icon-unchecked';
                    $dataFavourite = 'false';
                    $dataDistributor = '';

                    if($listItem != null) {

                        $favouriteIcon = 'text-danger';
                        $dataFavourite = 'true';
                        $dataDistributor = 'data-distributor-id="' . $listItem->getDistributor()->getId() . '"';
                    }
                }

                // Retail Icon
                if($productRetail == null){

                    $retailIcon = 'icon-unchecked';
                    $dataRetail = 'false';
                    $dataDistributorRetail = '';

                } else {

                    $listItem = $this->em->getRepository(ListItems::class)->findOneBy([
                        'product' => $product->getId(),
                        'list' => $retail->getId()
                    ]);

                    $retailIcon = 'icon-unchecked';
                    $dataRetail = 'false';
                    $dataDistributorRetail = '';

                    if($listItem != null) {

                        $retailIcon = 'text-danger';
                        $dataRetail = 'true';
                        $dataDistributorRetail = 'data-distributor-id="' . $listItem->getDistributor()->getId() . '"';
                    }
                }

                // Product Notes
                if($productNotes == null){

                    $class = 'hidden_msg';

                } else {

                    $firstName = $this->encryptor->decrypt($productNotes[0]->getClinicUser()->getFirstName());
                    $lastLame = $this->encryptor->decrypt($productNotes[0]->getClinicUser()->getLastName());
                    $noteString = $productNotes[0]->getNote();
                    $note = '<i class="fa-solid fa-pen-to-square"></i> <b>Notes From '. $firstName .' '. $lastLame .':</b> '. $noteString;
                }

                $name = $product->getName() .' - '. $product->getSize() . $product->getUnit();

                // Dosage
                $dosage = '';

                if($product->getDosage() != null && $product->getDosageUnit() != null)
                {
                    $dosage = '<p class="" id="dosage_'. $product->getId() .'"><b>Dosage:</b> '. $product->getDosage();
                    $dosage .= $product->getDosageUnit() .' '. $product->getForm() .' / '. $product->getActiveIngredient() .'</p>';
                }

                // Species
                $species = '';

                if($product->getProductSpecies() != null)
                {
                    foreach($product->getProductSpecies() as $productSpecies)
                    {
                        $species .= '<button class="btn bg-transparent border-xy ms-3">';
                        $species .= '   <i class="'. $productSpecies->getSpecies()->getIcon() .' fa-fw info" style="font-size: 20px !important;"></i>';
                        $species .= '</button>';
                    }
                }

                $html .= '
                <div class="row">
                    <div class="col-12 half-border mb-4">
                        <div class="row prd-container">
                            <div class="alert-warning p-2 '. $class .'" id="product_notes_label_'. $product->getId() .'">'. $note .'</div>
                            <!-- Product main container -->
                            <div class="col-12 col-sm-9 ps-3 text-center text-sm-start bg-white border-sm-xy">
                                <div class="row">
                                    <!-- Thumbnail -->
                                    <div class="col-12 col-sm-2 pt-3 text-center position-relative">
                                        <div class="carousel-item text-center active">
                                            <a 
                                                href="#"
                                                class="open-carousel"
                                                data-product-id="' . $product->getId() . '"
                                            >
                                                <img 
                                                    src="/images/products/'. $firstImage .'" 
                                                    alt="" 
                                                    class="img-fluid active" 
                                                    style="max-height:140px; height: unset !important;"
                                                >
                                            </a>
                                        </div>            
                                        <a 
                                            href="" 
                                            class="favourite '. $favouriteIcon .'"
                                            data-product-id="'. $product->getId() .'"
                                            data-list-id="'. $favourite->getId() .'"
                                            data-favourite="'. $dataFavourite .'"
                                            id="favourite_'. $product->getId() .'"
                                            '. $dataDistributor .'
                                        >
                                            <i class="fa-solid fa-heart"></i>
                                        </a>
                                        <a 
                                            href="" 
                                            class="retail '. $retailIcon .'"
                                            data-product-id="'. $product->getId() .'"
                                            data-list-id="'. $retail->getId() .'"
                                            data-retail="'. $dataRetail .'"
                                            id="retail_'. $product->getId() .'"
                                            '. $dataDistributorRetail .'
                                        >
                                            <i class="fa-solid fa-circle-dollar"></i>
                                        </a>
                                    </div>
                                    <!-- Description -->
                                    <div class="col-12 col-sm-10 pt-3 pb-3">
                                       <div class="row">
                                           <div class="col-12">
                                                <h4>'. $name .'</h4>
                                                <p><span class="pe-2">'. $manufacturer .' <span class="from_'. $product->getId() .'"></span></p>
                                            </div>
                                       </div>
                                       <div class="row">
                                           <div class="col-12 col-md-6">
                                                '. $dosage .'
                                            </div>
                                            <div class="col-12 col-md-6 text-md-end justify-content-evenly mb-2 mb-md-0">
                                                '. $species .'
                                            </div>
                                       </div>
                                       
                                        <!-- Product rating -->
                                        <div id="parent_'. $product->getId() .'" class="mb-3 mt-2 d-inline-block">
                                            <i class="star star-under fa fa-star">
                                                <i class="star star-over fa fa-star"></i>
                                            </i>
                                            <i class="star star-under fa fa-star">
                                                <i class="star star-over fa fa-star"></i>
                                            </i>
                                            <i class="star star-under fa fa-star">
                                                <i class="star star-over fa fa-star"></i>
                                            </i>
                                            <i class="star star-under fa fa-star">
                                                <i class="star star-over fa fa-star"></i>
                                            </i>
                                            <i class="star star-under fa fa-star">
                                                <i class="star star-over fa fa-star"></i>
                                            </i>
                                        </div>
                                        '. $this->forward('App\Controller\ProductReviewsController::getReviewsOnLoadAction', [
                                            'product_id' => $product->getId()
                                        ])->getContent() .'
                                    </div>
            
                                    <!-- Collapsable panel buttons -->
                                    <div class="col-12 search-panels-header">
                                        <!-- Description -->
                                        <button class="btn btn-sm btn-white info ps-0 pe-4 pe-sm-0 me-0 me-sm-3 btn_details" type="button" data-product-id="'. $product->getId() .'">
                                            <i class="fa-regular fa-circle-question"></i> <span class="d-none d-sm-inline">Details</span>
                                        </button>
                                        <!-- Order Lists -->
                                        <button class="btn btn-sm btn-white info pe-4 pe-sm-0 me-0 me-sm-3 btn_lists" type="button" data-product-id="'. $product->getId() .'">
                                            <i class="fa-regular fa-clipboard-list-check"></i> <span class="d-none d-sm-inline">Lists</span>
                                        </button>
                                        <!-- Tracking -->
                                        <button class="btn btn-sm btn-white info pe-4 pe-sm-0 me-0 me-sm-3 btn_track" type="button" data-product-id="'. $product->getId() .'">
                                            <i class="fa-regular fa-eye"></i> <span class="d-none d-sm-inline">Track</span>
                                        </button>
                                        <!-- Notes -->
                                        <button 
                                            class="btn btn-sm btn-white info pe-4 pe-sm-0 me-0 me-sm-3 btn_notes position-relative" 
                                            type="button" 
                                            data-product-id="'. $product->getId() .'"
                                            id="btn_note_'. $product->getId() .'"
                                        >
                                            <i class="fa-regular fa-pencil"></i> <span class="d-none d-sm-inline">Notes</span>
                                            '. $noteCount .'
                                        </button>
                                        <!-- Reviews -->
                                        <button 
                                            class="btn btn-sm btn-white info pe-4 pe-sm-0 btn_reviews position-relative" 
                                            type="button" 
                                            data-product-id="'. $product->getId() .'"
                                        >
                                            <i class="fa-regular fa-star"></i> <span class="d-none d-sm-inline">Reviews</span>
                                            '. $reviewCount .'
                                        </button>
                                        <div class="d-inline-block float-end text-end text-secondary">
                                            <span 
                                                data-bs-trigger="hover"
                                                data-bs-container="body" 
                                                data-bs-toggle="popover" 
                                                data-bs-placement="top" 
                                                data-bs-html="true"
                                                data-bs-content="<b>'. count($countClinicsBought) .'</b> clinics have recently purchased this item"
                                            >
                                                <i class="fa-regular fa-chart-line-up text-secondary me-2"></i>'. count($countClinicsBought) .'
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
            
                            <!-- Distributors -->
                            <div 
                                class="col-12 col-sm-3 mt-0 pt-3 pe-4 border-sm-e border-bottom search-result-distributors border-sm-t" 
                                id="search_result_distributors_'. $product->getId() .'"
                            >
                                <div class="position-relative">
                                    <img src="/images/distributor-skeleton.png" class="img-fluid">
                                    <i class="fa-regular fa-shield stock-icon position-absolute start-0 end-0 text-center" style="color: #cecece; line-height: 65px !important;"></i>
                                </div>
                            </div>
            
                            <!-- Panels -->
                            <div class="col-12 ps-3 pe-3 border-left border-right border-bottom bg-white">
                                <div class="col-12 search-panels-container" id="search_panels_container_'. $product->getId() .'" style="display:none;">
            
                                    <!-- Description -->
                                    <div class="hidden" id="details_'. $product->getId() .'">
                                        <h5 class="pb-3 pt-3">Item Description</h5>
                                        '. $product->getDescription() .'
                                    </div>
            
                                    <!-- Order Lists -->
                                    <div class="collapse panel_lists" id="lists_'. $product->getId() .'">
                                        <h5 class="pb-3 pt-3">Order Lists</h5>
                                        <p id="lists_no_data_'. $product->getId() .'">
                                            You do not currently have any Order Lists on Fluid
                                            <br><br>
                                            Have Order Lists with your suppliers? We\'ll import them!
                                            Send us a message using the chat icon in the lower right corner and we will
                                            help import you lists!
            
                                            You can also create new lists using the Create List button below
                                        </p>
                                    </div>
            
                                    <!-- Track -->
                                    <div class="collapse" id="track_'. $product->getId() .'">
                                        <h5 class="pb-3 pt-3">Availability Tracker</h5>
                                        <p>
                                        Create custom alerts when a backordered item comes back in stock. Set a notification 
                                        for how you would like to be notified and which suppliers you would like to track. 
                                        Once an item comes back in stock and you are notified, the tracker will automatically 
                                        turn off. You can also view a list of all tracked items in your shopping list. 
                                        Note: Fluid cannot track the availability of items that are drop shipped directly 
                                        from the vendor.
                                        </p>
                                    </div>
            
                                    <!-- Notes -->
                                    <div class="collapse" id="notes_'. $product->getId() .'">
                                        <h3 class="pb-3 pt-3">Item Notes</h3>
                                    </div>
            
                                    <!-- Reviews -->
                                    <div class="collapse review_panel" id="reviews_'. $product->getId() .'">
                                        <h3 class="pb-3 pt-3">Reviews</h3>
                                        <h5>No reviews yet!</h5>
                                        <p id="reviews_no_data">Reviews help thousands of veterinary purchasers know about your experience
                                            with this. Submit your review below.
                                            <br><br>
                                            <a href="" class="btn btn-primary btn_create_review" data-bs-toggle="modal" data-product-id="'. $product->getId() .'" data-bs-target="#modal_review">
                                                WRITE A REVIEW
                                            </a>
                                        </p>
                                    </div>
                                    <br>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Image Gallery -->
                <div class="modal fade" id="modal_gallery_'. $product->getId() .'" tabindex="-1" aria-labelledby="gallery_label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <span 
                                class="flash-close carousel-close"
                                role="button"
                                data-carousel-id="'. $product->getId() .'"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </span>
                            <div 
                                class="modal-body"
                                id="modal_body_'. $product->getId() .'"
                            >
                                
                            </div>
                        </div>
                    </div>
                </div>';
            }

            $html .= '
            <!-- Modal Review -->
            <div class="modal fade" id="modal_review" tabindex="-1" aria-labelledby="review_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header basket-modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form name="form_review" id="form_review" method="post">
                            <input type="hidden" name="review_product_id" id="review_product_id" value="0">
                            <input type="hidden" name="rating" id="rating" value="0">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12 col-sm-4 mb-0">
                                        <h6>Review guidelines</h6>
                                        <br>
                                        <ul>
                                            <li>
                                                <b>Be Helpful and Relevant</b> - Reviews are intended to provide helpful,
                                                meaningful content to customers.
                                                <br>
                                                <br>
                                            </li>
                                            <li>
                                                <b>Be Honest</b> - In order to preserve the integrity of our reviews, content
                                                should be an accurate representation of your experience with this item.
                                                Fluid strictly forbids commercial solicitations or compensation in exchange
                                                for positive reviews.
                                                <br>
                                                <br>
                                            </li>
                                            <li>
                                                <b>Stay Relevant</b> - Reviews should focus on the pros and cons of the item.
                                                Reviews focusing on the supplier or manufacturer directly will not be
                                                approved.
                                                <br>
                                                <br>
                                            </li>
                                            <li>
                                                <b>Acknowledge</b> - Please note that by submitting you acknowledge that
                                                your review may be used by the manufacturer in future marketing materials
                                                for this brand.
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-12 col-sm-8 mb-0 ps-3 ps-sm-5">
                                        <h5>Write a review for:</h5>
                                        <h6 class="text-primary">Terbinafine Tablets: 250mg, 100 Count</h6>
                                        <br>
                                        RATE THIS ITEM
                                        <div id="review_rating" class="mb-3 mt-2">
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-1"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-1"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-2"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-2"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-3"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-3"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-4"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-4"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-5"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-5"></i>
                                            </div>
                                            <div class="hidden_msg" id="error_rating">
                                                Please click to rate this item
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <label>Review</label>
                                                <textarea rows="4" name="review" id="review" class="form-control"></textarea>
                                                <div class="hidden_msg" id="error_review">
                                                    Required Field
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger btn-sm w-sm-100" data-bs-dismiss="modal">CANCEL</button>
                                <button type="submit" class="btn btn-primary btn-sm w-sm-100">CREATE REVIEW</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';

            $currentPage = $request->request->get('page-no');
            $lastPage = $this->pageManager->lastPage($results);

            $html .= '
                <!-- Pagination -->
                <div class="row">
                    <div class="col-12">';

            if($lastPage > 1) {

                $previousPageNo = $currentPage - 1;
                $url = '/clinics/inventory/';
                $previousPage = $url . $previousPageNo;

                $html .= '
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

                $html .= '
                <li class="page-item '. $disabled .'">
                    <a class="page-link" '. $listId .' aria-disabled="'. $dataDisabled .'" data-page-id="'. $currentPage - 1 .'" href="'. $previousPage .'">
                        <span aria-hidden="true">&laquo;</span> Previous
                    </a>
                </li>';

                for($i = 1; $i <= $lastPage; $i++) {

                    $active = '';

                    if($i == (int) $currentPage){

                        $active = 'active';
                    }

                    $html .= '
                    <li class="page-item '. $active .'">
                        <a class="page-link" '. $listId .' data-page-id="'. $i .'" href="'. $url . $i .'">'. $i .'</a>
                    </li>';
                }

                $disabled = 'disabled';
                $dataDisabled = 'true';

                if($currentPage < $lastPage) {

                    $disabled = '';
                    $dataDisabled = 'false';
                }

                $html .= '
                <li class="page-item '. $disabled .'">
                    <a class="page-link" '. $listId .' aria-disabled="'. $dataDisabled .'" data-page-id="'. $currentPage + 1 .'" href="'. $url . $currentPage + 1 .'">
                        Next <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>';

                $html .= '
                        </ul>
                    </nav>
                </div>';
            }

        } else {

            $html = 'Please use the search bar above';
        }

        $response = [
            'html' => $html,
            'listId' => $request->get('list-id'),
            'categoryList' => $filterLists['categoryList'],
            'distributorsList' => $filterLists['distributorsList'],
            'manufacturersList' => $filterLists['manufacturersList'],
            'favouriteCount' => $filterLists['favouriteCount'],
            'inStockCount' => $filterLists['inStockCount'],
            'level' => $level,
            'productIds' => $productIds,
        ];

        return new JsonResponse($response);
    }

    #[Route('clinics/product-favourite', name: 'product_favourite')]
    public function productfavourite(Request $request): Response
    {
        $data = $request->request;
        $clinic = $this->getUser()->getClinic();
        $productId = $data->get('product_id');
        $product = $this->em->getRepository(Products::class)->find($productId);
        $list = $this->em->getRepository(Lists::class)->findOneBy([
            'clinic' => $this->getUser()->getClinic(),
            'listType' => 'favourite',
        ]);

        // Create favourite shopping list
        if($list == null){

            $list = new Lists();

            $list->setClinic($clinic);
            $list->setName('Favourite Items');
            $list->setListType('favourite');
            $list->setIsProtected(1);
            $list->setItemCount(0);

            $this->em->persist($list);
            $this->em->flush();
        }

        // List items
        $listItem = $this->em->getRepository(ListItems::class)->findOneBy([
            'product' => $product,
            'list' => $list->getId(),
        ]);

        if($listItem == null){

            $listItem = new ListItems();

            $listItem->setProduct($product);
            $listItem->setList($list);
            $listItem->setName($product->getName());
            $listItem->setQty(1);

            $this->em->persist($listItem);

        } else {

            $this->em->remove($listItem);
        }

        $this->em->flush();

        $productFavourite = $this->em->getRepository(ProductFavourites::class)->findOneBy([
            'product' => $productId,
            'clinic' => $clinic->getId()
        ]);

        if($productFavourite == null){

            $productFavourite = new ProductFavourites();

            $productFavourite->setClinic($clinic);
            $productFavourite->setProduct($product);

            $this->em->persist($productFavourite);

            $response = true;

        } else {

            $this->em->remove($productFavourite);

            $response = false;
        }

        $this->em->flush();

        return new JsonResponse($response);
    }

    #[Route('clinics/access-denied', name: 'clinics_access_denied')]
    public function accessDeniedAction(): Response
    {
        $clinic = $this->getUser()->getClinic();
        $user = $this->em->getRepository(ClinicUsers::class)->find($this->getUser()->getId());
        $distributors = $this->em->getRepository(Distributors::class)->findAll();
        $manufacturers = $this->em->getRepository(Manufacturers::class)->findBy([], ['name' => 'ASC']);
        $categories = $this->em->getRepository(Categories::class)->findAll();
        $basket = $this->em->getRepository(Baskets::class)->findOneBy([
            'clinic' => $clinic->getId(),
            'name' => 'Fluid Commerce',
            'status' => 'active'
        ]);

        $count1 = (int) ceil(count($manufacturers) / 2);
        $count2 = (int) floor(count($manufacturers) / 2);

        $manFirst = array_slice($manufacturers, 0, $count1);
        $manSecond = array_slice($manufacturers, $count1, $count2);

        $permissions = [];

        foreach($user->getClinicUserPermissions() as $permission){

            $permissions[] = $permission->getPermission()->getId();
        }

        return $this->render('bundles/TwigBundle/Exception/access_denied_clinics.html.twig',[
            'user' => $user,
            'categories' => $categories,
            'distributors' => $distributors,
            'man_1' => $manFirst,
            'man_2' => $manSecond,
            'basket_id' => $basket->getId(),
            'clinic_id' => $clinic->getId(),
            'permissions' => $permissions,
        ]);
    }

    #[Route('/clinics/list/inventory-search', name: 'clinics_list_inventory_search')]
    public function clinicListsInventorySearchAction(Request $request): Response
    {
        $products = $this->em->getRepository(Products::class)->findBySearch($request->get('keyword'));
        $select = '<ul id="product_list">';

        foreach($products as $product){

            $id = $product->getId();
            $name = $product->getName();
            $listId = $request->request->get('list_id');
            $dosage = '';
            $size = '';

            if(!empty($product->getDosage())) {

                $unit = '';

                if(!empty($product->getUnit())) {

                    $unit = $product->getUnit();
                }

                $dosage = ' | '. $product->getDosage() . $unit;
            }

            if(!empty($product->getSize())) {

                $size = ' | '. $product->getSize();
            }

            $select .= '
            <li data-product-id="'. $id .'" data-list-id="'. $listId .'" data-retail="false" class="list-item">
                '. $name . $dosage . $size .'
            </li>';
        }

        $select .= '</ul>';

        return new Response($select);
    }

    #[Route('/clinics/product/get-distributors', name: 'product_get_distributors')]
    public function getProductDistributors(Request $request): Response
    {
        $productId = $request->request->get('product-id') ?? 18;
        $clinicId = $this->getUser()->getClinic()->getId();
        $product = $this->em->getRepository(Products::class)->find($productId);
        $user = $this->em->getRepository(ClinicUsers::class)->find($this->getUser()->getId());
        $currency = $this->getUser()->getClinic()->getCountry()->getCurrency();
        $firstImage = $this->em->getRepository(ProductImages::class)->findOneBy([
            'product' => $product->getId(),
            'isDefault' => 1
        ]);

        // Get default image
        if($firstImage == null){

            $firstImage = 'image-not-found.jpg';

        } else {

            $firstImage = $firstImage->getImage();
        }

        $name = $product->getName() .' ';
        $dosage = '';

        // Check if clinic is connected to distributor
        $distributorClinicsRepo = $this->em->getRepository(DistributorClinics::class)->findBy([
            'clinic' => $clinicId,
            'isActive' => 1,
        ]);
        $distributorClinics = '';

        if(is_array($distributorClinicsRepo) && count($distributorClinicsRepo) > 0){

            $distributorClinics = [];

            foreach($distributorClinicsRepo as $value){

                $distributorClinics[] = (int) $value->getDistributor()->getId();
            }
        }

        // Permissions
        $permissions = [];
        $basketPermission = true;
        $i = 0;

        foreach($user->getClinicUserPermissions() as $permission){

            $permissions[] = $permission->getPermission()->getId();
        }

        if(!in_array(1, $permissions)){

            $basketPermission = false;
        }

        foreach($product->getDistributorProducts() as $distributor) {

            $trackingId = $distributor->getDistributor()->getTracking()->getId();
            $distributorId = $distributor->getDistributor()->getId();
            $unitPrice = $distributor->getUnitPrice() ?? 0.00;
            $stockLevel = $distributor->getStockCount() ?? 0;
            $shippingPolicy = $distributor->getDistributor()->getShippingPolicy() ?? '<p>Shipping policy has not been updated</p>';
            $taxPolicy = $distributor->getDistributor()->getSalesTaxPolicy() ?? '<p>Sales tax policy has not been updated</p>';
            $i++;

            if (
                ($distributor->getDistributor()->getApiDetails() != null && $trackingId == 1)
                || ($trackingId == 2 || $trackingId == 3)
            ) {

                // Only show stock levels if fully / semi tracked
                if($trackingId == 1){

                    // Retrieve price & stock from api
                    $priceStockLevels = json_decode($this->forward('App\Controller\ProductsController::zohoRetrieveItem',[
                        'distributorId' => $distributorId,
                        'itemId' => $distributor->getItemId(),
                    ])->getContent(), true);

                    If($priceStockLevels != null && is_array($priceStockLevels)){

                        $distributorProduct = $this->em->getRepository(DistributorProducts::class)->findOneBy([
                            'distributor' => $distributorId,
                            'product' => $distributor->getProduct()->getId()
                        ]);

                        // Update price & stock
                        $distributorProduct->setUnitPrice($priceStockLevels['unitPrice']);
                        $distributorProduct->setStockCount($priceStockLevels['stockLevel']);

                        $this->em->persist($distributorProduct);
                        $this->em->flush();

                        $stockLevel = $priceStockLevels['stockLevel'];
                        $unitPrice = $priceStockLevels['unitPrice'];
                    }

                // Semi tracked
                } elseif($trackingId == 2){

                    $stockLevel = $distributor->getStockCount();
                    $unitPrice = $distributor->getUnitPrice();

                // Not tracked
                } elseif($trackingId == 3){

                    $stockLevel = 0;
                    $unitPrice = $distributor->getUnitPrice();

                }

                $disabled = '';
                $btnDisabled = '';

                if($trackingId == 1){

                    $stockIcon = 'fa-shield-check in-stock';
                    $trackingCopy = 'Fully Tracked';
                    $stockCopy = '
                    <span class="is_available">' . $stockLevel . ' In Stock</span> This item is in stock and ready to ship';

                } elseif($trackingId == 2){

                    $stockIcon = 'fa-shield-check text-info';
                    $trackingCopy = 'Semi Fully Tracked';
                    $stockCopy = '<span class="is_available">In Stock</span> This item is in stock and ready to ship';

                } else {

                    $stockIcon = 'fa-shield-exclamation text-muted';
                    $trackingCopy = 'Not Tracked';
                    $stockCopy = '<span class="is_available">In Stock</span> This item is in stock and ready to ship';
                }

                if($distributor->getDistributor()->getLogo() != null){

                    $logo = '<img src="/images/logos/' . $distributor->getDistributor()->getLogo() . '" class="img-fluid mh-30">';

                } else {

                    $logo = '<p><i class="fa-thin fa-image-slash fs-4 mh-30" style="line-height: 30px"></i></p>';
                }

                if ($stockLevel == 0 && ($trackingId == 1 || $trackingId == 2)) {

                    $stockIcon = 'fa-shield-xmark out-of-stock';
                    $disabled = 'disabled';
                    $stockCopy = '<span class="not_available">Out Of Stock</span> This item is out of stock';
                    $inStock = false;
                    $btnDisabled = 'btn-secondary disabled';
                }

                if (!$basketPermission) {

                    $disabled = 'disabled';
                }

                $style = '';

                if($i == count($product->getDistributorProducts()))
                {
                    $style = 'style="border-bottom: none !important"';
                }

                $response['html'] = '
                <a href=""
                   class="basket_link"
                   data-product-id="' . $product->getId() . '"
                   data-distributor-id="' . $distributorId . '"
                   data-bs-toggle="modal"
                   data-bs-target="#modal_add_to_basket_' . $productId . '_' . $distributorId . '"
                >
                <div class="row distributor-store-row py-3" '. $style .'>
                    <div class="col-4">
                        '. $logo .'
                    </div>
                    <div
                        class="col-4 text-center"
                        data-bs-trigger="hover"
                        data-bs-container="body"
                        data-bs-toggle="popover"
                        data-bs-placement="top"
                        data-bs-html="true"
                        data-bs-content="'. $trackingCopy .'"
                        data-bs-original-title="">
                        <i class="fa-regular mh-30 stock-icon ' . $stockIcon . '"></i>
                    </div>
                    <div class="col-4 text-end">
                        <p class="m-0">' . $currency . ' ' . number_format($unitPrice, 2) . '</p>
                    </div>
                </div>
            </a>

            <!-- Modal Add To Basket -->
            <div class="modal fade" action="" id="modal_add_to_basket_' . $productId . '_' . $distributorId . '" tabindex="-1" aria-labelledby="basket_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header basket-modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>';

                if (
                    $distributor->getDistributor() != null &&
                    (is_array($distributorClinics) && in_array($distributor->getDistributor()->getId(), $distributorClinics) && $trackingId == 1) ||
                    ($trackingId == 2 || $trackingId == 3)
                ) {

                    $response['html'] .= '
                    <form name="form_add_to_basket" id="form_add_to_basket_' . $productId . '_' . $distributorId . '" method="post">
                        <input type="hidden" name="product_id" value="' . $product->getId() . '">
                        <input type="hidden" name="distributor_id" value="' . $distributorId . '">
                        <input type="hidden" name="price" value="' . number_format($unitPrice, 2) . '">
                        <input type="hidden" name="status" value="active">
                        <input type="hidden" name="basket_name" value="Fluid Commerce">
                        <input type="hidden" name="basket_id" class="basket-id" value="0">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-12 col-sm-5 text-center" id="basket_thumbnail text-center">
                                    <img src="/images/products/' . $firstImage . '" class="text-center" style="max-height: 250px !important;">
                                </div>
                                <div class="col-12 col-sm-7 text-center text-sm-start mt-3 mt-sm-0">
                                    <h4 id="basket_item_name">
                                        ' . $name . $dosage . '
                                    </h4>
                                    <h5 id="basket_item_price" class="text-primary modal_price text-center text-sm-start">
                                        ' . $currency . ' ' . number_format($unitPrice, 2) . '
                                    </h5>
                                    <div class="modal_availability">
                                        ' . $stockCopy . '
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <input
                                                type="number"
                                                name="qty"
                                                id="qty_' . $productId . '_' . $distributorId . '"
                                                class="form-control modal-basket-qty"
                                                value="1"
                                                ' . $disabled . '
                                            />
                                            <div class="hidden_msg" id="error_qty_' . $productId . '_' . $distributorId . '">
                                                Required Field
                                            </div>
                                            <div class="hidden_msg" id="error_stock_' . $productId . '_' . $distributorId . '">
                                            </div>
                                        </div>
                                        <div class="col-6">';

                    $popover = '';

                    if (!in_array(1, $permissions)) {

                        $response['html'] .= '
                        <span
                            class="btn btn-disabled w-100 text-truncate"
                            data-bs-trigger="hover"
                            data-bs-container="body"
                            data-bs-toggle="popover"
                            data-bs-placement="top"
                            data-bs-html="true"
                            data-bs-content="Authorization Denied."
                        >
                            ADD TO BASKET
                        </span>';

                    } else {

                        $response['html'] .= '
                        <button
                            type="submit"
                            class="btn btn-primary w-100 text-truncate ' . $btnDisabled . '"
                            ' . $disabled . '
                        >
                            ADD TO BASKET
                        </button>';
                    }

                    $response['html'] .= '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer d-block">
                            <div class="row">
                                <div class="col-12 col-sm-6 text-start" style="padding-bottom: 0.75rem">
                                    <a href="" class="me-4 d-inline-block btn_item_facts">
                                        Item Facts
                                    </a>
                                    <a href="" class="me-4 d-inline-block btn_shipping">
                                        Shipping
                                    </a>
                                    <a href="" class="d-inline-block btn_taxes">
                                        Taxes
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6 text-end" style="padding-bottom: 0.75rem">
                                    <i class="fa-regular fa-user me-3"></i> <b>' . $this->encryptor->decrypt($distributor->getDistributor()->getDistributorName()) . '</b>
                                </div>

                                <!-- Panel Item Facts -->
                                <div class="col-12 modal_availability" id="panel_item_facts_' . $productId . '_' . $distributorId . '">
                                    <div class="row mt-sm-4">
                                        <div class="col-12 col-sm-5">
                                            <div class="row">
                                                <div class="col-4 fw-bold">
                                                    Unit Price
                                                </div>
                                                <div class="col-8 text-end">
                                                    ' . $currency . ' ' . number_format($distributor->getUnitPrice() ?? 0.00 / $product->getSize(), 2) . '
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-none d-sm-block col-sm-2"></div>
                                        <div class="col-12 col-sm-5">
                                            <div class="row">
                                                <div class="col-4 fw-bold">
                                                    Manufacturer
                                                </div>
                                                <div class="col-8 text-end">
                                                    ' . $this->encryptor->decrypt($distributor->getDistributor()->getDistributorName()) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-sm-4">
                                        <div class="col-12 col-sm-5">
                                            <div class="row">
                                                <div class="col-4 fw-bold">
                                                    Fluid ID
                                                </div>
                                                <div class="col-8 text-end">
                                                    ' . $distributor->getDistributor()->getId() . '
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-none d-sm-block col-sm-2"></div>
                                        <div class="col-12 col-sm-5">
                                            <div class="row">
                                                <div class="col-4 fw-bold">
                                                    SKU
                                                </div>
                                                <div class="col-8 text-end">
                                                    ' . $distributor->getSku() . '
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="row mt-sm-4">
                                        <div class="col-12 col-sm-5">
                                            <div class="row">
                                                <div class="col-4 fw-bold">
                                                    <span class="text-truncate">Seller Profile</span>
                                                </div>
                                                <div class="col-8 text-end">
                                                    <a href="">' . $this->encryptor->decrypt($distributor->getDistributor()->getDistributorName()) . '</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-none d-sm-block col-sm-2"></div>
                                        <div class="col-12 col-sm-5">
                                            <div class="row">
                                                <div class="col-4 fw-bold">
                                                    List Price
                                                </div>
                                                <div class="col-8 text-end">
                                                    ' . $currency . ' ' . number_format($distributor->getUnitPrice(), 2) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Panel shipping -->
                                <div class="col-12" id="panel_shipping_' . $productId . '_' . $distributorId . '">
                                    '. $shippingPolicy .'
                                </div>

                                <!-- Panel Taxes -->
                                <div class="col-12 modal_availability border-bottom-0" id="panel_taxes_' . $productId . '_' . $distributorId . '">
                                    '. $taxPolicy .'
                                </div>
                            </div>
                        </div>
                    </form>';

                } else {

                    if ($distributor->getDistributor()->getLogo() != null) {

                        $logo = $distributor->getDistributor()->getLogo();

                    } else {

                        $logo = 'image-not-found.jpg';
                    }

                    $response['html'] .= '
                    <div class="row">
                        <div class="col-12 text-center pt-2 pb-4">
                            <img src="/images/logos/' . $logo . '" class="img-fluid" style="max-width: 150px">
                            <h4 class="pt-4 pb-4">
                                You\'re Not Currently Connected to
                                ' . $this->encryptor->decrypt($distributor->getDistributor()->getDistributorName()) . '
                            </h4>
                            <buttton
                                class="btn btn-primary distributor-clinic-connect"
                                data-clinic-id="' . $this->getUser()->getClinic()->getId() . '"
                                data-distributor-id="' . $distributor->getDistributor()->getId() . '"
                                data-product-id="' . $productId . '"
                            >
                                CONNECT WITH ' . strtoupper($this->encryptor->decrypt($distributor->getDistributor()->getDistributorName())) . '
                            </buttton>
                        </div>
                    </div>
                    ';
                }

                $response['html'] .= '
                    </div>
                </div>
            </div>';
            }
        }

        // Get the lowest price
        $per = strtolower($product->getForm());
        $lowestPrice = $this->em->getRepository(DistributorProducts::class)->getLowestPrice($productId);
        $price = number_format($lowestPrice[0]['unitPrice'], 3) / $product->getSize();

        $response['from'] = '';

        if($product->getSize() != null && $product->getUnit())
        {
            $price = number_format($price ?? 0.00 / $product->getSize(), 2);
            $response['from'] = 'From <b>'. $currency .' '. $price .' </b>/ '. $product->getUnit();
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/product/get-gallery', name: 'product_gallery')]
    public function clinicGetProductGalleryAction(Request $request): Response
    {
        $productId = $request->request->get('productId');
        $productImages = $this->em->getRepository(ProductImages::class)->findBy([
            'product' => $productId,
        ]);

        $response = '
        <div 
            id="carousel_'. $productId .'" 
            class="carousel carousel-dark slide carousel-fade" 
            data-bs-ride="carousel" 
            data-bs-interval="false"
        >';

            if(count($productImages) > 0) {

                $response .= '
                <div class="carousel-indicators">';

                $i = 0;
                
                foreach ($productImages as $image) {

                    $class = '';
                    $current = '';


                    if ($image->getIsDefault() == 1) {

                        $class = 'active';
                        $current = 'aria-current="true"';
                    }

                    $response .= '
                    <button 
                        type="button" 
                        data-bs-target="#carousel_'. $productId .'" 
                        data-bs-slide-to="'. $i .'" class="'. $class .'" 
                        '. $current .' 
                        aria-label="'. $i + 1 .'"></button>';

                    $i++;
                }

                $response .= '
                </div>';
            }

            $response .= '
            <div class="carousel-inner">';

            $count = 0;

            if(count($productImages) > 0) {

                foreach ($productImages as $image) {

                    $count++;
                    $class = '';
                    $ext = pathinfo($image->getImage(), PATHINFO_EXTENSION);

                    if ($image->getIsDefault() == 1) {

                        $class = 'active';
                    }

                    if($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' || $ext == 'gif' || $ext == 'webp') {

                        $response .= '
                        <div class="carousel-item text-center ' . $class . '">
                            <img 
                                src="/images/products/' . $image->getImage() . '" 
                                alt="' . $image->getProduct()->getName() . '" 
                                class="img-fluid ' . $class . '" 
                                style="max-height: 400px"
                            >
                        </div>';

                    } elseif($ext == 'pdf') {

                        $url = $this->generateUrl('product_download', ['fileId' => $image->getId()]);

                        $response .= '
                        <div class="carousel-item text-center ' . $class . '">      
                            <a href="'. $url .'">
                                <img class="img-fluid" src="/images/download_pdf_button.png">
                            </a>
                        </div>';

                    } else {

                        $response .= '
                        <div class="carousel-item text-center ' . $class . '">
                            <div class="ratio ratio-16x9">
                                <iframe 
                                    class="embed-responsive-item" 
                                    src="'. $image->getImage() .'?controls=0&showinfo=0" 
                                    id="video"  
                                    allowscriptaccess="always" 
                                    allowfullscreen="0"
                                    allow="autoplay"></iframe>
                            </div>
                        </div>';
                    }
                }

            } else {

                $response .= '
                <div class="carousel-item text-center active">
                    <img 
                        src="/images/products/image-not-found.jpg" 
                        alt="Los Angeles" 
                        class="img-fluid active" 
                        style="max-height:140px"
                    >
                </div>';
            }

            $response .= '
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carousel_'. $productId .'" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carousel_'. $productId .'" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/download/{fileId}', name: 'product_download')]
    public function downloadAction(Request $request): Response
    {
        $fileId = $request->get('fileId');
        $fileField = $this->em->getRepository(ProductImages::class)->find($fileId);
        $path = __DIR__ . '/../../public/images/products/';

        $response = new BinaryFileResponse($path . $fileField->getImage());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($path . $fileField->getImage())
        );

        return $response;
    }

    #[Route('/clinics/ajax-manage-inventory', name: 'clinics_get_inventory')]
    public function clinicGetInventoryAction(Request $request): Response
    {
        $clinic = $this->getUser()->getClinic();
        $list = $this->em->getRepository(Lists::class)->findOneBy([
            'clinic' => $clinic->getId(),
            'listType' => 'retail',
        ]);
        $products = $this->em->getRepository(ListItems::class)->findByListId($list->getId());
        $results = $this->pageManager->paginate($products[0], $request, self::ITEMS_PER_PAGE);
        $paginator = $this->forward('App\Controller\AdminDashBordController::getPagination', [
            'pageId' => 1,
            'results' => $results,
            'url' => '/clinics/manage-inventory/',
        ])->getContent();
        $manufacturers = $this->em->getRepository(ProductManufacturers::class)->findByClinicList($clinic->getId());
        $species = $this->em->getRepository(ProductsSpecies::class)->findByClinic($clinic->getId());

        $response = '
        <div class="row">
            <div class="col-12 text-center">
                <h4 class="text-primary text-truncate">Manage Inventory</h4>
            </div>
        </div>
        <div class="row " id="import_products_row">
            <div class="col-12 w-100">
                <a role="button" id="btn_search_inventory" class="float-end text-primary">
                    <i class="fa-regular fa-magnifying-glass-plus me-2 mb-3"></i>
                    Search Inventory
                </a>
                <a role="button" id="filter_reset" class="float-end text-primary me-4 hidden">
                    <i class="fa-regular fa-rotate-right me-2 mb-3"></i>
                    Reset Filters
                </a>
            </div>
        </div>
        <div class="row hidden" id="inventory_attach_container">
            <div class="col-12">
                <div class="row" id="search_row">
                    <div class="col-12 pt-2 pb-2 bg-light border-left border-right border-top" id="inventory_search_container">
                        <div class="input-group">
                            <input type="text" id="search_inventory_field" class="form-control" placeholder="Search Inventory" autocomplete="off" />
                            <span class="input-group-text">
                                <a href="/clinics/manage-inventory" class="text-primary" id="inventory_clear">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </a>
                            </span>
                        </div>
                        <div id="suggestion_field"></div>
                    </div>
                </div>
        
                <form method="post" id="inventory_item" class="row bg-light border-left border-right hidden">
                    <input type="hidden" name="product-id" id="product_id">
                    <div class="row mb-0 mb-sm-3 pe-0">
        
                        <!-- Dosage -->
                        <div class="col-12 col-sm-6 pe-0 pe-sm-2 pt-2">
                            <label>
                                Dosage
                            </label>
                            <input type="text" class="form-control" id="dosage" disabled value="">
                        </div>
        
                        <!-- Size -->
                        <div class="col-12 col-sm-6 pe-0 pt-2">
                            <label>
                                Size
                            </label>
                            <input type="text" class="form-control" id="size" disabled value="">
                        </div>
                    </div>
        
                    <div class="row mb-0 mb-sm-3 pe-0">
        
                        <!-- Active Ingredient -->
                        <div class="col-12 col-sm-6 pe-0 pe-sm-2 pt-2 pt-sm-0">
                            <label>
                                Active Ingredient
                            </label>
                            <input type="text" class="form-control" id="active_ingredient" disabled value="">
                        </div>
        
                        <!-- Unit -->
                        <div class="col-12 col-sm-6 pe-0 pt-2 pt-sm-0">
                            <label>
                                Unit
                            </label>
                            <input type="text" class="form-control" id="unit" disabled value="">
                        </div>
                    </div>
        
                    <div class="row mb-0 mb-sm-3 pe-0">
        
                        <!-- Distributors -->
                        <div class="col-12 col-sm-6 pe-0 pe-sm-2 pt-2 pt-sm-0">
                            <label>
                                Distributor
                            </label>
                            <select class="form-control" name="distributor-id" id="distributor_id">
                                <option value="">Select a Distributor</option>
                            </select>
                            <div class="hidden_msg" id="error_distributor_id">
                                Required Field
                            </div>
                        </div>
        
                        <!-- SKU -->
                        <div class="col-12 col-sm-6 pe-0 pt-2 pt-sm-0">
                            <label>
                                #SKU
                            </label>
                            <input type="text" class="form-control" name="sku" id="sku" value="" disabled>
                            <div class="hidden_msg" id="error_sku">
                                Required Field
                            </div>
                        </div>
                    </div>
        
                    <div class="row mb-0 pb-sm-3 pe-0">
        
                        <!-- Cost Price -->
                        <div class="col-12 col-sm-6 pe-0 pe-sm-2 pt-2 pt-sm-0">
                            <label>
                                Cost Price
                            </label>
                            <input type="text" class="form-control" name="cost-price" id="cost_price" value="" disabled>
                            <div class="hidden_msg" id="error_cost_price">
                                Required Field
                            </div>
                        </div>
        
                        <!-- Your Price -->
                        <div class="col-12 col-sm-6 pe-0 pt-2 pt-sm-0">
                            <label>
                                Your Price
                            </label>
                            <input type="text" class="form-control" name="your-price" id="your_price" value="">
                            <div class="hidden_msg" id="error_your_price">
                                Required Field
                            </div>
                        </div>
                    </div>
                    <div class="row mb-0 pb-sm-3 pe-0" id="inventory_btn">
                        <div class="col-12 pe-0 pt-2 pt-sm-0">
                            <button 
                                id="btn_inventory" 
                                type="submit" 
                                class="btn btn-primary w-100" 
                                data-list-id="'. $list->getId() .'"
                            >
                                <i class="fa-light fa-floppy-disk me-2"></i>
                                SAVE
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="row">
            <div class="col-12 bg-light border-top border-left border-right">
                <div class="row">
                    <div class="col-12 col-md-3 offset-sm-0 offset-md-3 py-3">
                        <select class="form-control" name="manufacturer-id" id="manufacturer_id">
                            <option value="0">
                                Select a Manufacturer
                            </option>';

                            foreach($manufacturers as $manufacturer)
                            {
                                $response .= '
                                <option value="'. $manufacturer->getManufacturers()->getId() .'">
                                    '. $this->encryptor->decrypt($manufacturer->getManufacturers()->getName()) .'
                                </option>';
                            }

                        $response .= '
                        </select>
                    </div>
                    <div class="col-12 col-md-3 py-3">
                        <select class="form-control" name="species-id" id="species_id">
                            <option value="0">
                                Select a Species
                            </option>';

                            foreach($species as $specie) {
                                $response .= '
                                <option value="' . $specie['species']['id'] . '">
                                    ' . $specie['species']['name'] . '
                                </option>';
                            }

                        $response .= '
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 d-none d-xl-block">
                <div class="row">
                    <div class="col-md-3 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-left border-top">
                        Name
                    </div>
                    <div class="col-md-2 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Distributor
                    </div>
                    <div class="col-md-2 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Active Ingredient
                    </div>
                    <div class="col-md-1 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Dosage
                    </div>
                    <div class="col-md-1 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Size
                    </div>
                    <div class="col-md-1 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Unit
                    </div>
                    <div class="col-md-1 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Price
                    </div>
                    <div class="col-md-1 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-right border-top">

                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12" id="inventory_list">';

            if(count($results) > 0)
            {
                foreach($results as $result)
                {
                    $response .= '
                    <div class="row border-left border-right border-bottom bg-light" id="clinic_product_'. $result->getProduct()->getId() .'">
                        <div class="col-5 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Name:
                        </div>
                        <div class="col-7 col-md-3 col-xl-3 text-truncate border-list pt-3 pb-3" data-bs-trigger="hover" data-bs-container="body" data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true" data-bs-content="'. $result->getProduct()->getName() .'">
                            '. $result->getProduct()->getName() .'
                        </div>
                        <div class="col-5 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Distributor:
                        </div>
                        <div class="col-7 col-md-2 col-xl-2 text-truncate border-list pt-3 pb-3" data-bs-trigger="hover" data-bs-container="body" data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true" data-bs-content="'. $result->getProduct()->getName() .'">
                            '. $this->encryptor->decrypt($result->getDistributor()->getDistributorName()) .'
                        </div>
                        <div class="col-5 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Active Ingredient:
                        </div>
                        <div class="col-7 col-md-2 col-xl-2 text-truncate border-list pt-3 pb-3">
                            '. $result->getProduct()->getActiveIngredient() .'
                        </div>
                        <div class="col-5 col-md-1 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Dosage:
                        </div>
                        <div class="col-7 col-md-1 col-xl-1 text-truncate border-list pt-3 pb-3">
                            '. $result->getProduct()->getDosage() .'
                        </div>
                        <div class="col-5 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Size:
                        </div>
                        <div class="col-7 col-md-1 col-xl-1 text-truncate border-list pt-3 pb-3">
                            '. $result->getProduct()->getSize() .'
                        </div>
                        <div class="col-5 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Unit:
                        </div>
                        <div class="col-7 col-md-1 col-xl-1 text-truncate border-list pt-3 pb-3">
                            '. $result->getProduct()->getUnit() .'
                        </div>
                        <div class="col-5 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                            Price:
                        </div>
                        <div class="col-7 col-md-1 col-xl-1 text-truncate border-list pt-3 pb-3">
                            '. $result->getUnitPrice() .'
                        </div>
                        <div class="col-md-1  t-cell text-truncate border-list pt-3 pb-3">
                            <a 
                                href="" 
                                onclick="selectProductListItem(\''. $result->getProduct()->getId() .'\',\''. $result->getProduct()->getName() .'\');"
                                class="float-end edit-product" 
                                data-product-name="'. $result->getProduct()->getName() .'" 
                                data-product-id="'. $result->getId() .'"
                            >
                                <i class="fa-solid fa-pen-to-square edit-icon"></i>
                            </a>
                            <a 
                                href="" 
                                class="delete-icon float-end delete-clinic-product" 
                                data-bs-toggle="modal" 
                                data-clinic-product-id="'. $result->getProduct()->getId() .'" 
                                data-distributor-id="'. $result->getDistributor()->getId() .'" 
                                data-list-id="'. $result->getList()->getId() .'"
                            >
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                        </div>
                    </div>';
                }
            }
            else
            {
                $response .= '
                <div class="row">
                    <div class="col-12 text-center border-left border-right border-bottom bg-light p-3">
                        You have not selected any products yet.
                    </div>
                </div>';
            }

            $response .= '
            </div>
        </div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/inventory-search-list', name: 'clinic_inventory_search_list')]
    public function clinicInventorySearchListAction(Request $request): Response
    {
        $keywords = $request->get('keywords');
        $products = $this->em->getRepository(DistributorProducts::class)->findDistributorProducts($keywords);
        $select = '';

        if(is_array($products) && count($products) > 0)
        {
            $select .= '<ul id="product_list">';

            foreach($products as $product){

                $id = $product->getProduct()->getId();
                $name = $product->getProduct()->getName();
                $dosage = '';
                $size = '';

                if(!empty($product->getProduct()->getDosage())) {

                    $unit = '';

                    if(!empty($product->getProduct()->getUnit())) {

                        $unit = $product->getProduct()->getUnit();
                    }

                    $dosage = ' | '. $product->getProduct()->getDosage() . $unit;
                }

                if(!empty($product->getProduct()->getSize())) {

                    $size = ' | '. $product->getProduct()->getSize();
                }

                $select .= "<li onClick=\"selectProductListItem('$id', '$name');\" class='search-item'>$name$dosage$size</li>";
            }

            $select .= '</ul>';
        }

        return new Response($select);
    }

    #[Route('/clinics/inventory-get-data', name: 'clinic_inventory_get_data')]
    public function clinicGetInventoryDataAction(Request $request,TokenStorageInterface $tokenStorage): Response
    {
        $clinicId = $this->getUser()->getClinic()->getId();
        $productId = (int) $request->request->get('product-id');
        $distributorId = 0;
        $unitPrice = '';
        $sku = '';
        $costPrice = '';
        $list = $this->em->getRepository(Lists::class)->findOneBy([
            'clinic' => $clinicId,
            'listType' => 'retail',
        ]);
        $listItem = $this->em->getRepository(ListItems::class)->findListItem($clinicId,$list->getId(),$productId);

        if(is_array($listItem) && count($listItem) > 0)
        {
            $distributorId = $listItem[0]->getDistributor()->getId();
            $unitPrice = $listItem[0]->getUnitPrice();
        }

        $product = $this->em->getRepository(Products::class)->find($productId);
        $response = [];
        $select = '<option value="">Select a Distributor</option>';

        if($product != null)
        {
            if($product->getDistributorProducts()->count() > 0)
            {
                foreach($product->getDistributorProducts() as $clinicProducts)
                {
                    $select .= '
                    <option value="'. $clinicProducts->getDistributor()->getId() .'">
                        '. $this->encryptor->decrypt($clinicProducts->getDistributor()->getDistributorName()) .'
                    </option>';
                }
            }

            if($listItem != null)
            {
                $sku = $listItem[0]->getDistributorProduct()->getSku() ?? '';
                $costPrice = $listItem[0]->getProduct()->getUnitPrice() ?? '';
            }

            $response['distributors'] = $select;
            $response['distributorId'] = $distributorId;
            $response['sku'] = $sku;
            $response['unitPrice'] = $unitPrice ?? '';
            $response['costPrice'] = $costPrice;
            $response['productId'] = $productId;
            $response['dosage'] = $product->getDosage();
            $response['size'] = $product->getSize();
            $response['unit'] = $product->getUnit();
            $response['activeIngredient'] = $product->getActiveIngredient();
        }
        else
        {
            $response['message'] = 'Inventory item not found';
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/get-distributor-product', name: 'clinic_get_distributor_product')]
    public function clinicGetDistributorProductAction(Request $request): Response
    {
        $distributorId = $request->request->get('distributor-id');
        $productId = $request->request->get('product-id');
        $response = '';
        $distributorProduct = $this->em->getRepository(DistributorProducts::class)->findByProductDistributorId($productId, $distributorId);

        if(is_array($distributorProduct) && count($distributorProduct) > 0)
        {
            $response =  [
                'sku' => $distributorProduct[0]->getSku(),
                'price' => $distributorProduct[0]->getUnitPrice(),
            ];
        }

        return new JsonResponse($response);
    }

    public function zohoRetrieveItem($distributorId, $itemId): Response
    {
        $response = [];
        $session = $this->requestStack->getSession();

        if(!empty($itemId)) {

            $api = $this->em->getRepository(ApiDetails::class)->findOneBy([
                'distributor' => $distributorId,
            ]);
            $refreshToken = $this->em->getRepository(ApiDetails::class)->findOneBy([
                'distributor' => $distributorId,
            ]);

            if($session->get('accessToken') == null){

                $token = $this->zohoRefreshToken($refreshToken->getRefreshTokens()->first()->getToken(), $distributorId);
                $accessToken = $session->set('accessToken', $token);
            }

            $curl = curl_init();
            $organizationId = $this->encryptor->decrypt($api->getOrganizationId());

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://inventory.zoho.com/api/v1/items/' . $itemId . '?organization_id=' . $organizationId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Zoho-oauthtoken ' . $session->get('accessToken'),
                    'Cookie: BuildCookie_678731371=1; JSESSIONID=7C21A0420124F6FED9D7C8B45DD36745; _zcsr_tmp=1fda6fb7-d450-495c-ba70-f90b5e04fcdd; f73898f234=3dcacfb9d7c2898957ad7458412d98eb; zomcscook=1fda6fb7-d450-495c-ba70-f90b5e04fcdd'
                ),
            ));

            $json = curl_exec($curl);
            $array = json_decode($json, true);

            if($array['code'] == 57){

                $refreshToken = $this->em->getRepository(ApiDetails::class)->findOneBy([
                    'distributor' => $distributorId,
                ]);

                $token = $this->zohoRefreshToken($refreshToken->getRefreshTokens()->first()->getToken(), $distributorId);
                $session->set('accessToken', $token);

                $this->zohoRetrieveItem($distributorId, $itemId);
            }

            $response['unitPrice'] = $array['item']['rate'];
            $response['stockLevel'] = $array['item']['available_stock'];

            return new JsonResponse($response);

            curl_close($curl);
        }

        return $response;
    }

    private function zohoRetrieveItemsByIds($distributorId, $itemIds)
    {
        $response = [];
        $session = $this->requestStack->getSession();

        if(strlen($itemIds) > 0) {

            $api = $this->em->getRepository(ApiDetails::class)->findOneBy([
                'distributor' => $distributorId,
            ]);
            $refreshToken = $this->em->getRepository(ApiDetails::class)->findOneBy([
                'distributor' => $distributorId,
            ]);

            if($session->get('accessToken') == null){

                $token = $this->zohoRefreshToken($refreshToken->getRefreshTokens()->first()->getToken(), $distributorId);
                $session->set('accessToken', $token);
            }

            $curl = curl_init();
            $organizationId = $this->encryptor->decrypt($api->getOrganizationId());

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://inventory.zoho.com/api/v1/itemdetails/?item_ids='. $itemIds .'&organization_id='. $organizationId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Zoho-oauthtoken '. $session->get('accessToken'),
                    'Cookie: BuildCookie_678731371=1; JSESSIONID=76778A660E1D212D9EC294EE38EF3892; _zcsr_tmp=1fda6fb7-d450-495c-ba70-f90b5e04fcdd; f73898f234=3dcacfb9d7c2898957ad7458412d98eb; zomcscook=1fda6fb7-d450-495c-ba70-f90b5e04fcdd'
                ),
            ));

            $json = curl_exec($curl);
            $array = json_decode($json, true);

            file_put_contents(__DIR__ . '/../../public/zoho.log', $json . "\n", FILE_APPEND);

            // Get new access token if expired
            if($array['code'] == 57){

                $refreshToken = $this->em->getRepository(ApiDetails::class)->findOneBy([
                    'distributor' => $distributorId,
                ]);

                $token = $this->zohoRefreshToken($refreshToken->getRefreshTokens()->first()->getToken(), $distributorId);
                $session->set('accessToken', $token);

                $this->zohoRetrieveItemsByIds($distributorId, $itemIds);
            }

            if(array_key_exists('items', $array)) {

                $i = 0;

                foreach($array['items'] as $item) {

                    $response[$i]['unitPrice'] = $item['rate'];
                    $response[$i]['stockLevel'] = $item['available_stock'];

                    $i++;
                }
            }

            curl_close($curl);
        }

        return $response;
    }

    private function getProductFilters($products, $level, $arraySearch):array
    {
        // Get Category Filter
        $categoryIds = [];
        $level = $level + 1;
        $counter = [];

        foreach($products as $prd){

            $categoryIds[] = $prd->getCategory()->getId();
        }

        // Remove duplicate values
        $categoryIds = array_unique($categoryIds);
        $categories = $this->em->getRepository(Categories1::class)->findFirstLevel($categoryIds);

        foreach($products as $prd) {

            if (!isset($counter[$prd->getCategory()->getId()])) {

                $counter[$prd->getCategory()->getId()] = 1;

            } else {

                $counter[$prd->getCategory()->getId()] += 1;
            }
        }

        if($level == 2) {

            foreach($products as $prd) {

                if ($prd->getCategory2() != null && !isset($counter[$prd->getCategory2()->getId()])) {

                    $counter[$prd->getCategory2()->getId()] = 1;

                } else {

                    $counter[$prd->getCategory2()->getId()] += 1;
                }
            }

            $categories = $this->em->getRepository(Categories2::class)->findByParent(
                $arraySearch[0]['category'][0]['categoryId'], $arraySearch[0]['categoryKeyword']
            );

        } elseif($level == 3){

            $counter = [];

            foreach($products as $prd) {

                if (!isset($counter[$prd->getCategory3()->getId()])) {

                    $counter[$prd->getCategory3()->getId()] = 1;

                } else {

                    $counter[$prd->getCategory3()->getId()] += 1;
                }
            }

            $categories = $this->em->getRepository(Categories3::class)->findByParent(
                $arraySearch[0]['category'][0]['categoryId'], $arraySearch[0]['categoryKeyword']
            );
        }

        $categoryList = '';

        foreach($categories[1] as $category) {

            if (array_key_exists($category->getId(), $counter)) {

                $catId = $category->getId();

                $categoryList .= '
                <li 
                    class="pt-0 pb-2 pt-md-0 pb-md-0 category-select"
                    data-category-id="' . $catId . '"
                    data-level="' . $level . '"
                >
                    <label class="ms-1" for="cat_' . $catId . '" role="button">
                        (' . $counter[$category->getId()] . ') ' . $category->getName() . '
                    </label>
                </li>';
            }
        }


        // Distributors
        $distributors = [];
        $distributorProductCount = [];
        $c = 0;
        $distributorsList = '';

        // Build distributor array
        if($arraySearch == null) {

            for ($i = 0; $i < count($products); $i++) {

                foreach ($products[$i]->getDistributorProducts() as $distributorProduct) {

                    if (!array_key_exists($distributorProduct->getDistributor()->getId(), $distributorProductCount)) {

                        $distributorProductCount[$distributorProduct->getDistributor()->getId()] = 1;

                    } else {

                        $distributorProductCount[$distributorProduct->getDistributor()->getId()] += 1;
                    }

                    $distributors[$distributorProduct->getDistributor()->getDistributorName()] = $distributorProduct->getDistributor()->getId();
                }
            }

            foreach($distributors as $key => $value) {

                $class = 'pt-2';

                if($c == 0){

                    $class = 'pt-0';
                }

                $c++;

                $distributorsList .= '
                <li
                    class="'. $class .' pb-2 pt-md-0 pb-md-0 distributor-select"
                    data-distributor-id="'. $value .'"
                >
                    <input
                        class="form-check-input me-2 distributor-checkbox"
                        name="distributor[]"
                        type="checkbox"
                        value="'. $value .'"
                        id="dist_'. $value .'"
                    >
                    <label
                        class="ms-1"
                        for="dist_'. $value .'"
                    >
                        ('. $distributorProductCount[$value] .') '. $this->encryptor->decrypt($key) .'
                    </label>
                </li>';
            }

        } else {

            $selectedDistributors = [];

            if(array_key_exists('selectedDistributors', $arraySearch[0])) {

                $selectedDistributors = $arraySearch[0]['selectedDistributors'];
            }

            if(array_key_exists('distributors', $arraySearch[0])) {

                foreach ($arraySearch[0]['distributors'] as $distributor) {

                    $class = 'pt-2';

                    if ($c == 0) {

                        $class = 'pt-0';
                    }

                    $c++;
                    $checked = '';

                    if (in_array($distributor['id'], $selectedDistributors)) {

                        $checked = 'checked';
                    }

                    $distributorsList .= '
                <li
                    class="' . $class . ' pb-2 pt-md-0 pb-md-0 distributor-select"
                    data-distributor-id="' . $distributor['id'] . '"
                >
                    <input
                        class="form-check-input me-2 distributor-checkbox"
                        name="distributor[]"
                        type="checkbox"
                        value="' . $distributor['id'] . '"
                        id="dist_' . $distributor['id'] . '"
                        ' . $checked . '
                    >
                    <label
                        class="ms-1"
                        for="dist_' . $distributor['id'] . '"
                    >
                        (' . $distributor['count'] . ') ' . $distributor['name'] . '
                    </label>
                </li>';
                }
            }
        }

        // Manufacturers
        $manufacturer = [];
        $productManufacturerCount = [];
        $c = 0;
        $manufacturersList = '';

        // Build manufacturer array
        for ($i = 0; $i < count($products); $i++) {

            foreach ($products[$i]->getProductManufacturers() as $productManufacturer) {

                if (!array_key_exists($productManufacturer->getManufacturers()->getId(), $productManufacturerCount)) {

                    $productManufacturerCount[$productManufacturer->getManufacturers()->getId()] = 1;

                } else {

                    $productManufacturerCount[$productManufacturer->getManufacturers()->getId()] += 1;
                }

                $manufacturer[$productManufacturer->getManufacturers()->getName()] = $productManufacturer->getManufacturers()->getId();
            }
        }

        if($arraySearch == null) {

            foreach($manufacturer as $key => $value) {

                $class = 'pt-2';

                if($c == 0){

                    $class = 'pt-0';
                }

                $c++;

                $manufacturersList .= '
                <li
                    class="'. $class .' pb-2 pt-md-0 pb-md-0 manufacturer-select"
                    data-manufacturer-id="'. $value .'"
                >
                    <input
                        class="form-check-input me-2 manufacturer-checkbox"
                        name="manufacturer[]"
                        type="checkbox"
                        value="'. $value .'"
                        id="man_'. $value .'"
                    >
                    <label
                        class="ms-1"
                        for="man_'. $value .'"
                    >
                        ('. $productManufacturerCount[$value] .') '. $this->encryptor->decrypt($key) .'
                    </label>
                </li>';
            }

        } else {

            $selectedManufacturers = [];

            if(array_key_exists('selectedManufacturers', $arraySearch[0])) {

                $selectedManufacturers = $arraySearch[0]['selectedManufacturers'];
            }

            if(array_key_exists('manufacturers', $arraySearch[0])) {

                foreach ($arraySearch[0]['manufacturers'] as $manufacturer) {

                    $class = 'pt-2';

                    if ($c == 0) {

                        $class = 'pt-0';
                    }

                    $c++;
                    $checked = '';

                    if (in_array($manufacturer['id'], $selectedManufacturers)) {

                        $checked = 'checked';
                    }

                    if(array_key_exists($manufacturer['id'], $productManufacturerCount)){

                        $count = $productManufacturerCount[$manufacturer['id']];

                    } else {

                        $count = 0;
                    }

                    $manufacturersList .= '
                    <li
                        class="' . $class . ' pb-2 pt-md-0 pb-md-0 manufacturer-select"
                        data-manufacturer-id="' . $manufacturer['id'] . '"
                    >
                        <input
                            class="form-check-input me-2 manufacturer-checkbox"
                            name="manufacturer[]"
                            type="checkbox"
                            value="' . $manufacturer['id'] . '"
                            id="dist_' . $manufacturer['id'] . '"
                            ' . $checked . '
                        >
                        <label
                            class="ms-1"
                            for="dist_' . $manufacturer['id'] . '"
                        >
                            (' . $count . ') ' . $manufacturer['name'] . '
                        </label>
                    </li>';
                }
            }
        }

        // Favourites
        $favouriteCount = 0;
        $inStockCount = 0;

        foreach($products as $prd){

            $favouriteCount += count($prd->getProductFavourites());

            foreach($prd->getDistributorProducts() as $distributorProduct){

                if($distributorProduct->getStockCount() > 0){

                    $inStockCount += 1;
                }
            }
        }

        return [
            'categoryList' => $categoryList,
            'distributorsList' => $distributorsList,
            'manufacturersList' => $manufacturersList,
            'favouriteCount' => $favouriteCount,
            'inStockCount' => $inStockCount,
        ];
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
}