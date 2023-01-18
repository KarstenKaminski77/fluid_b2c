<?php

namespace App\Controller;

use App\Entity\BasketItems;
use App\Entity\Baskets;
use App\Entity\Countries;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\ListItems;
use App\Entity\Lists;
use App\Entity\ProductFavourites;
use App\Entity\ProductImages;
use App\Entity\ProductRetail;
use App\Entity\Products;
use App\Entity\RetailUsers;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ListsController extends AbstractController
{
    private $em;
    private $emRemote;
    private $encryptor;
    private $pageManager;
    const ITEMS_PER_PAGE = 4;

    public function __construct(ManagerRegistry $em, Encryptor $encryptor, PaginationManager $pageManager) {

        $this->em = $em->getManager('default');
        $this->emRemote = $em->getManager('remote');
        $this->encryptor = $encryptor;
        $this->pageManager = $pageManager;
    }

    #[Route('/retail/get/search', name: 'retail_get_search')]
    public function retailSearchAction(Request $request): Response
    {
        if($this->getUser() == null)
        {
            return new JsonResponse([
                'authenticated' => false,
            ]);
        }

        $response['html'] = '';
        $pageId = $request->request->get('page-id') ?? 1;
        $keywords = $request->request->get('keywords');
        $retailUserId = $this->getUser()->getId();
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);
        $clinicId = $this->getUser()->getClinicId();
        $list = $this->emRemote->getRepository(Lists::class)->findOneBy([
            'clinicId' => $clinicId,
            'listType' => 'retail',
        ]);
        $products = $this->emRemote->getRepository(ListItems::class)->findByKeyword($list->getId(), $keywords);
        $results = $this->pageManager->paginate($products[0], $request, self::ITEMS_PER_PAGE);
        $country = $this->emRemote->getRepository(Countries::class)->find($retailUser->getCountry()->getId());

        if(count($results) > 0)
        {
            $response['html'] .= '<div class="row">';
            $i = 0;

            foreach($results as $result)
            {
                $i++;
                $product = $result->getProduct();
                $from = '&nbsp;';
                $dosage = '&nbsp;';
                $firstImage = $this->emRemote->getRepository(ProductImages::class)->findOneBy([
                    'product' => $product->getId(),
                    'isDefault' => 1
                ]);

                if($firstImage == null){

                    $firstImage = 'image-not-found.jpg';

                } else {

                    $firstImage = $firstImage->getImage();
                }

                // Proce From
                if($product->getSize() != null && $product->getUnit())
                {
                    $price = $result->getUnitPrice() / $product->getSize();
                    $price = number_format($price, 2);
                    $from = 'From <b>'. $country->getCurrency() .' '. $price .' </b>/ '. $product->getUnit();
                }

                // Dosage
                if($product->getDosage() != null && $product->getDosageUnit() != null)
                {
                    $dosage = '<b>Dosage:</b> '. $product->getDosage();
                    $dosage .= $product->getDosageUnit() .' '. $product->getForm() .' / '. $product->getActiveIngredient();
                }

                $response['html'] .= '
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 px-4 pt-3  text-center">
                    <div class="row">
                        <span class="half-border">
                            <div class="row">
                                <div class=" col-12 bg-white border-top border-left border-right px-4 pb-3">
                                    <img src="'. $this->getParameter('app.base_url_remote') .'/images/products/'. $firstImage .'" style="max-height: 120px">
                                    <h6 class="mt-3">'. $product->getName() .'</h6>
                                    <p class="m-0 pb-3"><h5>'. $country->getCurrency() .' '. number_format($result->getUnitPrice(),2) .'</h5></p>
                                        <p>'. $from .'</p>
                                        <p>'. $dosage .'</p>
                                </div>
                                <div class="col-12 col-sm-6 bg-white border-left px-3 pb-3">
                                    <div class="input-group  ">
                                        <button 
                                            style="min-width: 2.5rem" 
                                            class="btn btn-decrement btn-outline-secondary btn-minus" 
                                            type="button" 
                                            data-action="click->retail-search#onMinusClick"
                                            disabled
                                        >
                                            <strong>−</strong>
                                        </button>
                                        <input type="text" inputmode="decimal" style="text-align: center" class="form-control prd-qty px-0" value="1">
                                        <button 
                                            style="min-width: 2.5rem" 
                                            class="btn btn-increment btn-outline-secondary btn-plus" 
                                            type="button"
                                            data-action="click->retail-search#onPlusClick"
                                        >
                                            <strong>+</strong>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 bg-white border-bottom border-right px-3 pb-3">
                                    <button
                                        class="btn btn-primary w-100 btn-basket-add"
                                        data-clinic-id="'. $clinicId .'"
                                        data-product-id="'. $product->getId() .'"
                                        data-list-item-id="'. $result->getId() .'"
                                        data-price="'. $result->getUnitPrice() .'"
                                        data-qty="1"
                                        data-action="click->retail-basket#onAddToClick"
                                    >
                                        <i class="fa-light fa-basket-shopping me-2"></i>
                                        ADD
                                    </button>
                                </div>
                            </div>
                        </span>
                    </div>
                </div>';
            }

            $response['html'] .= '</div>';
            $response['html'] .= $this->getPagination($pageId, $results);
        }
        else
        {
            $response['html'] .= '
            <div class="row">
                <div class="col-12 mt-5 text-center border-xy bg-light p-3">
                    No results found.
                </div>
            </div>';
        }

        return  new JsonResponse($response);
    }

    public function getPagination($pageId, $results): string
    {
        $currentPage = $pageId;
        $lastPage = $this->pageManager->lastPage($results);

        $html = '
        <!-- Pagination -->
        <div class="row">
            <div class="col-12 mt-3">';

        if($lastPage > 1) {

            $previousPageNo = $currentPage - 1;
            $url = '/retail/search/';
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
                <a 
                    class="page-link" 
                    aria-disabled="'. $dataDisabled .'" 
                    data-page-id="'. $currentPage - 1 .'" 
                    href="'. $previousPage .'"
                    data-action="click->retail-search#onPaginationClick"
                >
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
                        <a 
                            class="page-link" 
                            data-page-id="'. $i .'" 
                            href="'. $url . $i .'"
                            data-action="click->retail-search#onPaginationClick"
                        >'. $i .'</a>
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
                    <a 
                        class="page-link" 
                        aria-disabled="'. $dataDisabled .'" 
                        data-page-id="'. $currentPage + 1 .'" 
                        href="'. $url . $currentPage + 1 .'"
                        data-action="click->retail-search#onPaginationClick"
                    >
                        Next <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>';

            $html .= '
                    </ul>
                </nav>
            </div>';
        }

        return $html;
    }
}
