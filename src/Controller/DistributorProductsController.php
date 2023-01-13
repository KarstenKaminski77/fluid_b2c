<?php

namespace App\Controller;

use App\Entity\ApiDetails;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\Manufacturers;
use App\Entity\ProductManufacturers;
use App\Entity\Products;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DistributorProductsController extends AbstractController
{
    private $em;
    const ITEMS_PER_PAGE = 10;
    private $pageManager;
    private $requestStack;
    private $encryptor;

    public function __construct(EntityManagerInterface $em, PaginationManager $pagination, RequestStack $requestStack, Encryptor $encryptor) {
        $this->em = $em;
        $this->pageManager = $pagination;
        $this->requestStack = $requestStack;
        $this->encryptor = $encryptor;
    }

    #[Route('/distributors/update-stock', name: 'update_stock')]
    public function updateStockAction(Request $request): Response
    {
        $distributors = $this->em->getRepository(Distributors::class)->findAll();
        $distributorHasApi = 0;
        $distributorNoApi = 0;
        $totalItems = 0;

        if(count($distributors) > 0){

            foreach($distributors as $distributor){

                $api = $distributor->getApiDetails();

                if($api != null){

                    $distributorHasApi += 1;

                    // Get stock levels from API
                    $organizationId = $api->getOrganizationId();
                    $refreshToken = $api->getRefreshTokens()->first()->getToken();
                    $accessToken = $this->zohoGetAccessToken($refreshToken, $distributor->getId());

                    $items = $this->zohoGetAllItems($organizationId, $accessToken, 1, []);
                    $totalItems += count($items);

                    // Update fluid stock items
                    foreach($items as $item){

                        $itemId = $item['itemId'];
                        $stockOnHand = $item['stockOnHand'];

                        if(!empty($itemId)){

                            $distributorProduct = $this->em->getRepository(DistributorProducts::class)->findOneBy([
                                'itemId' => $itemId
                            ]);

                            if($distributorProduct != null){

                                $distributorProduct->setStockCount($stockOnHand);

                                $this->em->persist($distributorProduct);
                            }
                        }
                    }

                    $this->em->flush();

                } else {

                    $distributorNoApi += 1;
                }
            }
        }

        $response = [
            'distributorHasApi' => $distributorHasApi,
            'distributorNoApi' => $distributorNoApi,
            'totalItems' => $totalItems,
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/import-products', name: 'import_products')]
    public function importProductsAction(Request $request): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $api = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $distributorId
        ]);
        $totalItems = 0;

        if($api != null){

            $organizationId = $api->getOrganizationId();
            $refreshToken = $api->getRefreshTokens()->first()->getToken();
            $accessToken = $this->zohoGetAccessToken($refreshToken, $distributorId);

            $items = $this->zohoGetAllItems($organizationId, $accessToken, 1, []);

            if(is_array($items) && count($items) > 0){

                foreach($items as $item) {

                    $productRepo = $this->em->getRepository(Products::class)->findOneBy([
                        'name' => $item['itemName'],
                    ]);
                    $totalItems += 1;
                    $manufacturer = $item['manufacturer'];

                    // Save manufacturer
                    if(!empty($manufacturer)){

                        $manufacturerRepo = $this->em->getRepository(Manufacturers::class)->findOneBy([
                            'name' => $manufacturer
                        ]);

                        if($manufacturerRepo == null){

                            $manufacturerRepo = new Manufacturers();

                            $manufacturerRepo->setName($manufacturer);

                            $this->em->persist($manufacturerRepo);
                            $this->em->flush();
                        }
                    }

                    if($productRepo == null) {

                        $productRepo = new Products();
                    }

                    $productRepo->setName($item['itemName']);
                    $productRepo->setActiveIngredient('TBA');
                    $productRepo->setUnit('TBA');
                    $productRepo->setUnit($item['weightUnit']);
                    $productRepo->setUnitPrice($item['unitPrice']);
                    $productRepo->setStockCount($item['stockOnHand']);
                    $productRepo->setIsActive(1);
                    $productRepo->setIsPublished(0);
                    $productRepo->setExpiryDateRequired(0);

                    $this->em->persist($productRepo);
                }

                $this->em->flush();
            }

            // Update manufacturers
            if(count($items) > 0) {

                foreach ($items as $item) {

                    $manufacturer = $item['manufacturer'];
                    $itemName = $item['itemName'];

                    if(!empty($manufacturer)) {

                        $manufacturerRepo = $this->em->getRepository(Manufacturers::class)->findOneBy([
                            'name' => $manufacturer,
                        ]);
                        $product = $this->em->getRepository(Products::class)->findOneBy([
                            'name' => $itemName,
                        ]);
                        $productManufacturers = $this->em->getRepository(ProductManufacturers::class)->findOneBy([
                            'products' => $product->getId(),
                            'manufacturers' => $manufacturerRepo->getId(),
                        ]);

                        if($productManufacturers == null) {

                            $productManufacturers = new ProductManufacturers();
                        }

                        $productManufacturers->setManufacturers($manufacturerRepo);
                        $productManufacturers->setProducts($product);

                        $this->em->persist($productManufacturers);
                    }
                }

                $this->em->flush();
            }
        }

        $response['totalItems'] = $totalItems;

        return new JsonResponse($response);
    }

    #[Route('/distributors/import-distributor-products', name: 'distributor_import_products')]
    public function importDistributorProductsAction(Request $request): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $api = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $distributorId,
        ]);
        $totalItems = 0;
        $response = '';

        if($api != null && $distributor->getTracking()->getId() != null) {

            $organizationId = $api->getOrganizationId();
            $refreshToken = $api->getRefreshTokens()->first()->getToken();
            $accessToken = $this->zohoGetAccessToken($refreshToken, $distributorId);

            $items = $this->zohoGetAllItems($organizationId, $accessToken, 1, []);

            if (is_array($items) && count($items) > 0) {

                foreach ($items as $item) {

                    $itemName = $item['itemName'];
                    $itemId = $item['itemId'];

                    if(!empty($itemId)){

                        // Find Product ID
                        $product = $this->em->getRepository(DistributorProducts::class)->findOneBy([
                            'itemId' => $itemId,
                        ]);

                        // Only import products with an existing parent
                        if($product != null && $product->getProduct() != null) {

                            $distributorProduct = $this->em->getRepository(DistributorProducts::class)->findOneBy([
                                'distributor' => $distributorId,
                                'product' => $product->getProduct()->getId(),
                            ]);

                            if($distributorProduct == null){

                                $distributorProduct = new DistributorProducts();
                            }

                            $distributorProduct->setDistributor($distributor);
                            $distributorProduct->setProduct($product->getProduct());
                            $distributorProduct->setItemId($item['itemId']);
                            $distributorProduct->setUnitPrice($item['unitPrice']);
                            $distributorProduct->setStockCount($item['stockOnHand']);
                            $distributorProduct->setTaxExempt(1);
                            $distributorProduct->setSku(0);
                            $distributorProduct->setIsActive(0);

                            $this->em->persist($distributorProduct);

                            $totalItems += 1;
                        }
                    }
                }

                $this->em->flush();

                $response = [
                    'totalItems' => $totalItems,
                    'flash' => '<b><i class="fa-solid fa-circle-check"></i></i></b> Products successfully imported.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
                ];
            }
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/delete/distributor-product', name: 'delete_distributor_product')]
    public function deleteDistributorProductAction(Request $request): Response
    {
        $distributorProductId = $request->request->get('distributor-product-id');
        $distributorProduct = $this->em->getRepository(DistributorProducts::class)->find($distributorProductId);

        $this->em->remove($distributorProduct);
        $this->em->flush();

        $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Product successfully deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/distributors/get/distributor-products', name: 'get_distributor_products')]
    public function getDistributorProductsAction(Request $request): Response
    {
        $response = [];
        $html = '';
        $distributorId = $request->request->get('distributor-id');
        $manufacturerId = (int) $request->request->get('manufacturer-id') ?? 0;
        $speciesId = (int) $request->request->get('species-id') ?? 0;
        $productsRepo = $this->em->getRepository(Products::class)->findByManufacturer($distributorId, $manufacturerId, $speciesId);
        $distributorProductsResults = $this->pageManager->paginate($productsRepo[0], $request, self::ITEMS_PER_PAGE);
        $response['distributorProductsPagination'] = $this->getPagination(1, $distributorProductsResults, $distributorId);
        $i = 0;

        foreach ($distributorProductsResults as $product)
        {
            $html .= '
            <div class="row border-left border-right border-bottom bg-light mb-3 mb-md-0" id="distributor_product_'. $product->getId() .'">
                <div class="col-5 col-md-2 d-md-none t-cell fw-bold text-primary text-truncate border-list border-md-top-0 pt-3 pb-3">
                    Name:
                </div>
                <div 
                    class="col-7 col-md-3 col-lg-3 text-truncate border-list border-md-top-0 pt-3 pb-3"
                    data-bs-trigger="hover"
                    data-bs-container="body"
                    data-bs-toggle="popover"
                    data-bs-placement="top"
                    data-bs-html="true"
                    data-bs-content="'. $product->getName() .'"
                >
                    '. $product->getName() .'
                </div>
                <div class="col-5 col-md-2 d-md-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                    Active Ingredient:
                </div>
                <div class="col-7 col-md-2 col-lg-2 text-truncate border-list pt-3 pb-3">
                    '. $product->getActiveIngredient() .'
                </div>
                <div class="col-5 col-md-1 d-md-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                    Dosage:
                </div>
                <div class="col-7 col-md-1 col-lg-1 text-truncate border-list pt-3 pb-3">
                    '. $product->getDosage() .'
                </div>
                <div class="col-5 col-md-2 d-md-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                    Size:
                </div>
                <div class="col-7 col-md-1 col-lg-1 text-truncate border-list pt-3 pb-3">
                    '. $product->getSize() .'
                </div>
                <div class="col-5 col-md-2 d-md-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                    Unit:
                </div>
                <div class="col-7 col-md-1 col-lg-1 text-truncate border-list pt-3 pb-3">
                    '. $product->getUnit() .'
                </div>
                <div class="col-5 col-md-2 d-md-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                    Stock:
                </div>
                <div class="col-7 col-md-1 col-lg-1 text-truncate border-list pt-3 pb-3">
                    '. $product->getdistributorProducts()[0]->getStockCount() .'
                </div>
                <div class="col-5 col-md-2 d-md-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">
                    Price:
                </div>
                <div class="col-7 col-md-1 col-lg-1 text-truncate border-list pt-3 pb-3">
                    '. $product->getdistributorProducts()[0]->getUnitPrice() .'
                </div>
                <div class="col-md-2  t-cell text-truncate pt-3 pb-3">
                    <a
                        href=""
                        class="float-end update-product"
                        data-product-name="'. $product->getName() .'"
                        data-product-id="'. $product->getId() .'"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-end delete-distributor-product"
                        data-bs-toggle="modal"
                        data-distributor-product-id="'. $product->getId() .'"
                        data-bs-target="#modal_product_delete"
                        >
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                </div>
            </div>';
        }

        $response['html'] = $html;

        return new JsonResponse($response);
    }

    public function zohoGetAllItems($organizationId, $accessToken, $page, $list)
    {
        $curl = curl_init();

        $orgId = $this->encryptor->decrypt($organizationId);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/items?organization_id='. $orgId .'&page='. $page .'&per_page=200',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Zoho-oauthtoken '. $accessToken,
            ),
        ));

        $json = curl_exec($curl);
        $response = json_decode($json, true);

        curl_close($curl);

        if(array_key_exists('items', $response) && is_array($response['items']) && count($response['items']) > 0){

            foreach($response['items'] as $item){

                $stockOnHand = $item['stock_on_hand'] ?? 0;

                $list[] = [
                    'itemId' => $item['item_id'],
                    'itemName' => $item['item_name'],
                    'stockOnHand' => $stockOnHand,
                    'unitPrice' => $item['rate'],
                    'manufacturer' => $item['manufacturer'],
                    'weightUnit' => $item['weight_unit'],
                ];
            }
        }

        if($response['code'] == 0){

            if($response['page_context']['has_more_page']){

                $page = $page += 1;

                return $this->zohoGetAllItems($organizationId, $accessToken, $page, $list);

            } else {

                return $list;
            }
        }
    }

    public function zohoGetAccessToken($refreshToken, $distributorId): string
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

    public function getPagination($pageId, $results, $distributorId)
    {
        $currentPage = (int)$pageId;
        $lastPage = $this->pageManager->lastPage($results);

        $pagination = '
        <!-- Pagination -->
        <div class="row mt-3">
            <div class="col-12">';

        if ($lastPage > 1) {

            $previousPageNo = $currentPage - 1;
            $url = '/distributors/users';
            $previousPage = $url . $previousPageNo;

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
                <a 
                    class="user-pagination" 
                    aria-disabled="' . $dataDisabled . '" 
                    data-page-id="' . $currentPage - 1 . '" 
                    data-distributor-id="' . $distributorId . '"
                    href="' . $previousPage . '"
                >
                    <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline">Previous</span>
                </a>
            </li>';

            for ($i = 1; $i <= $lastPage; $i++) {

                $active = '';

                if ($i == (int)$currentPage) {

                    $active = 'active';
                    $pageId = '<input type="hidden" id="page_no" value="' . $currentPage . '">';
                }

                $pagination .= '
                <li class="page-item ' . $active . '">
                    <a 
                        class="user-pagination" 
                        data-page-id="' . $i . '" 
                        href="' . $url . '"
                        data-distributor-id="' . $distributorId . '"
                    >' . $i . '</a>
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
                <a 
                    class="user-pagination" 
                    aria-disabled="' . $dataDisabled . '" 
                    data-page-id="' . $currentPage + 1 . '" 
                    href="' . $url . '"
                    data-distributor-id="' . $distributorId . '"
                >
                    <span class="d-none d-sm-inline">Next</span> <span aria-hidden="true">&raquo;</span>
                </a>
            </li>';

            $pagination .= '
                    </ul>
                </nav>';
        }

        $pagination .= '
            </div>
        </div>';

        return $pagination;
    }
}
