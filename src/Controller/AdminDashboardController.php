<?php

namespace App\Controller;

use App\Entity\ActiveIngredients;
use App\Entity\Api;
use App\Entity\ApiDetails;
use App\Entity\ArticleDetails;
use App\Entity\Articles;
use App\Entity\Banners;
use App\Entity\Categories;
use App\Entity\Categories1;
use App\Entity\Categories2;
use App\Entity\Categories3;
use App\Entity\Clinics;
use App\Entity\ClinicUserPermissions;
use App\Entity\ClinicUsers;
use App\Entity\Countries;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\DistributorUserPermissions;
use App\Entity\DistributorUsers;
use App\Entity\ListItems;
use App\Entity\Manufacturers;
use App\Entity\ManufacturerUsers;
use App\Entity\Pages;
use App\Entity\ProductForms;
use App\Entity\ProductImages;
use App\Entity\ProductManufacturers;
use App\Entity\ProductReviewComments;
use App\Entity\ProductReviews;
use App\Entity\Products;
use App\Entity\ProductsSpecies;
use App\Entity\RestrictedDomains;
use App\Entity\Species;
use App\Entity\SubCategories;
use App\Entity\Tags;
use App\Entity\User;
use App\Entity\UserPermissions;
use App\Entity\CommunicationMethods;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AdminDashboardController extends AbstractController
{
    private EntityManagerInterface $em;
    private PaginationManager $page_manager;
    private UserPasswordHasherInterface $passwordHasher;
    private MailerInterface $mailer;
    private $children;
    private Encryptor $encryptor;
    const ITEMS_PER_PAGE = 7;

    public function __construct(
        EntityManagerInterface $em, PaginationManager $page_manager, Encryptor $encryptor,
        UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer
    )
    {
        $this->em = $em;
        $this->page_manager = $page_manager;
        $this->passwordHasher = $passwordHasher;
        $this->mailer = $mailer;
        $this->encryptor = $encryptor;
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->render('Admin/dashboard.html.twig');
    }

    #[Route('/admin', name: 'admin')]
    #[Route('/admin/products/{page_id}', name: 'products_list')]
    public function productsList(Request $request): Response
    {
        $products = $this->em->getRepository(Products::class)->adminFindAll();
        $results = $this->page_manager->paginate($products[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/products/');

        return $this->render('Admin/products_list.html.twig',[
            'products' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/product/crud', name: 'product_crud')]
    public function productCrudAction(Request $request): Response
    {
        $productId = $request->get('product_id') ?? $request->request->get('delete');
        $product = $this->em->getRepository(Products::class)->find($productId);

        if($request->request->get('delete') != null){

            $product->setIsActive(0);

            $this->em->persist($product);
            $this->em->flush();

            $flash = '<b><i class="fas fa-check-circle"></i> Product Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        if($product == null){

            $product = new Products();
        }

        $response = [];

        if(!empty($request->request)) {

            $data = $request->request;
            $productId = $request->get('product_id');
            $manufacturers = $this->em->getRepository(ProductManufacturers::class)->findBy([
                'products' => $productId,
            ]);
            $productSpecies = $this->em->getRepository(ProductsSpecies::class)->findBy([
                'products' => $productId,
            ]);
            $category1 = $this->em->getRepository(Categories1::class)->find($data->get('category-id'));
            $category2 = $this->em->getRepository(Categories2::class)->find($data->get('sub-category-id') ?? null);
            $category3 = $this->em->getRepository(Categories3::class)->find($data->get('category3-id') ?? null);
            $productImages = $this->em->getRepository(ProductImages::class)->findBy([
                'product' => $productId,
            ]);

            // Clear many-to-many tables
            foreach($manufacturers as $manufacturer){

                $this->em->remove($manufacturer);
            }

            foreach($productSpecies as $species){

                $this->em->remove($species);
            }

            $this->em->flush();

            $product->setIsPublished($data->get('is-published') ?? 0);
            $product->setIsActive(1);
            $product->setExpiryDateRequired($data->get('expiry-date') ?? 0);

            $manufacturerIds = [];

            foreach($data->get('manufacturers') as $manufacturer){

                $productManufacturer = new ProductManufacturers();
                $manu = $this->em->getRepository(Manufacturers::class)->find($manufacturer);

                $productManufacturer->setProducts($product);
                $productManufacturer->setManufacturers($manu);

                $manufacturerIds[] = $manufacturer;

                $this->em->persist($productManufacturer);
            }

            $product->setName($data->get('name'));
            $productSpeciesSlug = '';

            foreach($data->get('species') as $species){

                $productSpecies = new ProductsSpecies();
                $specie = $this->em->getRepository(Species::class)->find($species);
                $productSpeciesSlug .= ' '. $specie->getName();

                $productSpecies->setProducts($product);
                $productSpecies->setSpecies($specie);

                $this->em->persist($productSpecies);
            }

            // Tags
            $slug = '';

            if($data->get('tag') != null){

                $selectedTags = [];

                foreach($data->get('tag') as $tag){

                    $tagRepo = $this->em->getRepository(Tags::class)->find($tag);

                    if($tagRepo != null) {

                        $selectedTags[$tagRepo->getName()] = (int)$tag;
                        $slug .= $tagRepo->getName() . ' ';

                        $product->setTags($selectedTags);
                    }
                }
            }

            // Active ingredients
            $activeIngredients = '';
            $ingredients = $data->get('ingredient');

            if(is_array($ingredients) && count($ingredients) > 0){

                foreach($ingredients as $ingredient){

                    $activeIngredients .= $ingredient . ',';
                }
            }

            $product->setCategory($category1);
            $product->setCategory2($category2);
            $product->setCategory3($category3);
            $product->setSku($data->get('serial_no'));
            $product->setActiveIngredient(trim($activeIngredients, ','));
            $product->setDosage($data->get('dosage'));
            $product->setDosageUnit($data->get('dosage-unit'));
            $product->setSize($data->get('size'));
            $product->setUnit($data->get('unit'));
            $product->setUnitPrice($data->get('price'));
            $product->setStockCount($data->get('stock'));
            $product->setForm($data->get('form'));
            $product->setSlug(trim($slug .' '. $productSpeciesSlug));
            $product->setManufacturerIds($manufacturerIds);

            // Image
            // File Types
            // Image = 1
            // PDF = 2
            // Video = 3
            $productImage = new ProductImages();

            if(!empty($_FILES['image']['name'])) {

                $fileName = $_FILES['image'];
                $extension = pathinfo($fileName['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('fluid_'. $product->getId() .'_', true) . '.' . $extension;
                $filePath = __DIR__ . '/../../public/images/products/';

                if(move_uploaded_file($fileName['tmp_name'], $filePath . $newFileName)){

                    if($extension == 'jpg' || $extension == 'jpeg' || $extension == 'png' || $extension == 'gif' || $extension == 'webp'){

                        $productImage->setFileType(1);

                    } elseif($extension == 'pdf'){

                        $productImage->setFileType(2);
                    }

                    $productImage->setIsDefault(0);

                    if(count($productImages) == 0){

                        $productImage->setIsDefault(1);
                    }

                    $productImage->setProduct($product);
                    $productImage->setImage($newFileName);

                    $this->em->persist($productImage);
                }
            }

            // Video
            if(!empty($data->get('video'))){

                $productImage->setIsDefault(0);

                if(count($productImages) == 0){

                    $productImage->setIsDefault(1);
                }

                $productImage->setProduct($product);
                $productImage->setImage($data->get('video'));
                $productImage->setFileType(3);

                $this->em->persist($productImage);
            }

            $product->setDescription($data->get('details'));

            $this->em->persist($product);
            $this->em->flush();

            // Category product counts
            // Categories1
            $productCount = $this->em->getRepository(Products::class)->findBy([
                'category' => $product->getCategory(),
            ]);
            $category1 = $this->em->getRepository(Categories1::class)->find($product->getCategory());
            $count = 0;

            if(is_array($productCount)){

                $count = count($productCount);
            }

            $category1->setProductCount($count);

            $this->em->persist($category1);
            $this->em->flush();

            // Categories2
            if($product->getCategory2() != null) {

                $productCount = $this->em->getRepository(Products::class)->findBy([
                    'category2' => $product->getCategory2(),
                ]);
                $category2 = $this->em->getRepository(Categories2::class)->find($product->getCategory2()->getId());
                $count = 0;

                if (is_array($productCount)) {

                    $count = count($productCount);
                }

                $category2->setProductCount($count);

                $this->em->persist($category2);
                $this->em->flush();
            }

            // Categories3
            if($product->getCategory3() != null) {

                $productCount = $this->em->getRepository(Products::class)->findBy([
                    'category3' => $product->getCategory3(),
                ]);
                $category3 = $this->em->getRepository(Categories3::class)->find($product->getCategory3()->getId());
                $count = 0;

                if (is_array($productCount)) {

                    $count = count($productCount);
                }

                $category3->setProductCount($count);

                $this->em->persist($category2);
                $this->em->flush();
            }

            $productImages = $this->em->getRepository(ProductImages::class)->findBy([
                'product' => $productId
            ]);

            $response['images'] = '';

            if(count($productImages) > 0){

                $response['images'] = '<label class="w-100">&nbsp;</label>';

                foreach($productImages as $image){

                    $imageId = $image->getId();
                    $modaBody = '';
                    $modalSize = 'modal-lg';
                    $class = '';
                    
                    if($image->getIsDefault() == 1){
                        
                        $class = 'text-success';
                    }

                    $response['images'] .= '
                    <div class="row" id="image_'. $imageId .'">
                        <div class="col-10">';

                            if($image->getFileType() == 1 || $image->getFileType() == 3) {

                                $response['images'] .= '
                                <a
                                    href=""
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal_image_' . $imageId . '"
                                >
                                    ' . $image->getImage() . '
                                </a>';

                            } elseif($image->getFileType() == 2){

                                $response['images'] .= '
                                <a
                                    href="'. $this->generateUrl('product_download', ['fileId' => $image->getId()]) .'"
                                >
                                    '. $image->getImage() .'
                                </a>';
                            }

                        $response['images'] .= '
                        </div>
                        <div class="col-2">
                            <span
                                class="delete-image float-end"
                                data-image-id="'. $imageId .'"
                                role="button"
                            >
                                <i class="fa-solid fa-xmark text-danger fw-bold"></i>
                            </span>';

                            if($image->getFileType() == 1) {

                                $modalSize = '';
                                $modaBody = '<img src="../../images/products/'. $image->getImage() .'" class="img-fluid">';
                                $response['images'] .= '
                                <span
                                    class="float-end me-3 is-default-image ' . $class . '"
                                    data-image-id="' . $imageId . '"
                                    data-product-id="'. $product->getId() .'"
                                    id="is_default_image_' . $imageId . '"
                                    role="button"
                                >
                                    <i class="fa-solid fa-check-double"></i>
                                </span>';

                            } elseif($image->getFileType() == 3){

                                $modaBody = '
                                <div class="ratio ratio-16x9">
                                    <iframe class="embed-responsive-item"
                                        src="'. $image->getImage() .'"
                                        id="video"
                                        allowscriptaccess="always"
                                        allow="autoplay">
                                    </iframe>
                                </div>';
                            }

                        $response['images'] .= '
                        </div>
                    </div>
    
                    <!-- Modal Image -->
                    <div class="modal fade" id="modal_image_'. $imageId .'" tabindex="-1" aria-labelledby="image_label" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered '. $modalSize .'">
                            <div class="modal-content">
                                <button type="button" class="btn-close flash-close" data-bs-dismiss="modal" aria-label="Close" style="z-index: 999999999"></button>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-12 mb-0 text-center">
                                            '. $modaBody .'
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
            }

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Product updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['product'] = $product->getName();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/category/crud', name: 'category_crud')]
    public function categoryCrudAction(Request $request): Response
    {
        if($request->request->get('delete') > 0){

            $categoryId = (int) $request->request->get('delete');

        } else {

            $categoryId = (int) $request->get('category_id');
        }

        $category = $this->em->getRepository(Categories1::class)->find($categoryId);
        $response['flash'] = '';
        $isNew = false;

        // Delete category
        if($request->request->get('delete') > 0){

            $products = $this->em->getRepository(Products::class)->findBy([
                'category' => $categoryId
            ]);

            if(count($products) > 0){

                foreach($products as $product){

                    $categories2 = $this->em->getRepository(Categories2::class)->findBy([
                        'category1' => $categoryId
                    ]);

                    if(count($categories2) > 0){

                        foreach($categories2 as $category2){

                            $categories3 = $this->em->getRepository(Categories3::class)->findBy([
                                'category2' => $category2->getId()
                            ]);

                            if(count($categories3) > 0){

                                foreach($categories3 as $category3){

                                    $this->em->remove($category3);
                                }

                                $this->em->flush();
                            }

                            $this->em->remove($category2);
                        }

                        $this->em->flush();
                    }

                    $product->setCategory(null);
                    $product->setCategory2(null);
                    $product->setCategory3(null);

                    $this->em->persist($product);
                }

                $this->em->flush();

            } else {

                $categories2 = $this->em->getRepository(Categories2::class)->findBy([
                    'category1' => $categoryId
                ]);

                foreach($categories2 as $category2){

                    $categories3 = $this->em->getRepository(Categories3::class)->findBy([
                        'category2' => $category2->getId(),
                    ]);

                    foreach($categories3 as $category3){

                        $this->em->remove($category3);
                    }

                    $this->em->flush();

                    $this->em->remove($category2);
                }

                $this->em->flush();
            }

            // Delete Category
            $this->em->remove($category);
            $this->em->flush();

            $flash = '<b><i class="fas fa-check-circle"></i> Category Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        if($category == null){

            $category = new Categories1();
            $isNew = true;
        }

        if(!empty($request->request)) {

            $category->setTags([]);
            $category->setSlug(null);
            $category->setName($request->request->get('category'));

            $this->em->persist($category);
            $this->em->flush();

            if($request->request->get('tag') != null){

                if($isNew){

                    $tagArray = [
                        1 => [
                            $category->getId() => $request->request->get('tag')[1][0]
                        ]
                    ];

                } else {

                    $tagArray = $request->request->get('tag');
                }

                foreach($tagArray as $key => $tag){


                    if($key == 1){

                        foreach($tag as $index => $value) {

                            $cat = $this->em->getRepository(Categories1::class)->find($index);
                            $selectedTags = [];
                            $slug = '';

                            foreach($value as $item){

                                $tagRepo = $this->em->getRepository(Tags::class)->find($item);

                                $selectedTags[$tagRepo->getName()] = (int) $item;
                                $slug .= $tagRepo->getName() .' ';
                            }

                            $cat->setTags($selectedTags);
                            $cat->setSlug(trim($slug));

                            $this->em->persist($cat);
                        }

                    } elseif($key == 2){

                        foreach($tag as $index => $value) {

                            $categoryId = $index ?? $category->getId();
                            $cat = $this->em->getRepository(Categories2::class)->find($categoryId);
                            $selectedTags = [];
                            $slug = '';

                            foreach($value as $item){

                                $tagRepo = $this->em->getRepository(Tags::class)->find($item);

                                $selectedTags[$tagRepo->getName()] = (int) $item;
                                $slug .= $tagRepo->getName() .' ';
                            }

                            $cat->setTags($selectedTags);
                            $cat->setSlug(trim($slug));

                            $this->em->persist($cat);
                        }
                    }
                }

                $this->em->flush();
            }

            // Level Two
            if($request->request->get('level2_category') != null) {

                if (count($request->request->get('level2_category')) > 0) {

                    $secondLevelCategories = [];

                    foreach ($request->request->get('level2_category') as $key => $value) {

                        $secondLevelCategories[] = $value[0];

                        $category2 = $this->em->getRepository(Categories2::class)->find($key);
                        $category2->setName($value[0]);

                        $this->em->persist($category2);
                    }

                    $this->em->flush();
                }
            }

            // Level Three Categories
            if($request->request->get('level3_category') != null && count($request->request->get('level3_category')) > 0){

                $thirdLevelCategories = [];

                foreach($request->request->get('level3_category') as $key => $value){

                    $thirdLevelCategories[] = $value[0];

                    $category3 = $this->em->getRepository(Categories3::class)->find($key);
                    $category3->setName($value[0]);

                    $this->em->persist($category3);
                }

                $this->em->flush();
            }

            // Level Three Tags
            if(is_array($request->request->get('tag')) && array_key_exists(3, $request->request->get('tag')) && count($request->request->get('tag')[3]) > 0){

                foreach($request->request->get('tag')[3] as $key => $value){

                    $categoryId = $key;
                    $tagsArray = [];
                    $slug = '';

                    $category = $this->em->getRepository(Categories3::class)->find($categoryId);

                    if(count($value) > 0){

                        foreach($value as $tag){

                            $tagRepo = $this->em->getRepository(Tags::class)->find($tag);
                            $slug .= $tagRepo->getName() .' ';
                            $tagsArray[$tagRepo->getName()] = (int) $tag;

                            $this->em->persist($tagRepo);
                        }
                    }

                    $category->setTags(json_encode($tagsArray));
                    $category->setSlug($slug);

                    $this->em->persist($category);
                    $this->em->flush();
                }
            }

            $response['level2'] = [];

            if(!empty($secondLevelCategories)){

                $response['level2'] = $secondLevelCategories;
            }

            $response['level3'] = [];

            if(!empty($thirdLevelCategories)){

                $response['level3'] = $thirdLevelCategories;
            }
            $response['isNew'] = $isNew;
            $response['categoryId'] = $category->getId();
            $response['category'] = $request->request->get('category');
            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Category updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/clinic/crud', name: 'clinic_crud')]
    public function clinicCrudAction(Request $request): Response
    {
        $data = $request->request;
        $clinicId = $request->get('clinic_id') ?? $data->get('delete');
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $response['clinicUsers'] = $this->em->getRepository(ClinicUsers::class)->findBy([
            'clinic' => $clinicId,
        ]);

        if($data->get('delete') != null){

            $flash = '<b><i class="fas fa-check-circle"></i> Clinic Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        $response['flash'] = '';

        if(!empty($data)) {

            // Clinic Details
            $clinic->setClinicName($this->encryptor->encrypt($data->get('clinic_name')));
            $clinic->setEmail($this->encryptor->encrypt($data->get('email')));
            $clinic->setTelephone($this->encryptor->encrypt($data->get('telephone')));

            $this->em->persist($clinic);

            // Clinic Users
            if(count($data->get('user_id')) > 0){

                for($i = 0; $i < count($data->get('user_id')); $i++){

                    $userId = $data->get('user_id')[$i];
                    $firstName = $data->get('user_first_name')[$i];
                    $lastName = $data->get('user_last_name')[$i];
                    $userEmail = $data->get('user_email')[$i];
                    $userTelephone = $data->get('user_telephone')[$i];

                    $clinicUsers = $this->em->getRepository(ClinicUsers::class)->find($userId);

                    $clinicUsers->setFirstName($this->encryptor->encrypt($firstName));
                    $clinicUsers->setLastName($this->encryptor->encrypt($lastName));
                    $clinicUsers->setEmail($this->encryptor->encrypt($userEmail));
                    $clinicUsers->setTelephone($this->encryptor->encrypt($userTelephone));

                    $this->em->persist($clinicUsers);

                    // User Permissions
                    $userPermissions = $this->em->getRepository(ClinicUserPermissions::class)->findBy([
                        'user' => $userId
                    ]);

                    // Remove currently saved
                    foreach($userPermissions as $userPermission){

                        $this->em->remove($userPermission);
                    }

                    // Save new permissions
                    if($data->get('user_permissions') != null) {

                        foreach ($data->get('user_permissions') as $permissionId) {

                            $pieces = explode('_', $permissionId);

                            if ($pieces[1] == $clinicUsers->getId()) {

                                $userPermission = new ClinicUserPermissions();
                                $permission = $this->em->getRepository(UserPermissions::class)->find($permissionId);

                                $userPermission->setPermission($permission);
                                $userPermission->setClinic($clinic);
                                $userPermission->setUser($clinicUsers);

                                $this->em->persist($userPermission);
                            }
                        }
                    }
                }
            }

            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Cliinic Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['clinicName'] = $data->get('clinic_name');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/get/api-detail', name: 'api_details')]
    public function apiDetailsAction(Request $request): Response
    {
        $apiDetailsId = $request->request->get('api-details-id');
        $apiDetails = $this->em->getRepository(ApiDetails::class)->find($apiDetailsId);
        $response = '';

        if($apiDetails != null){

            $response = [
                'distributorId' => $apiDetails->getDistributor()->getId(),
                'clientSecret' => $this->encryptor->decrypt($apiDetails->getClientSecret()),
                'clientId' => $this->encryptor->decrypt($apiDetails->getClientId()),
                'organizationId' => $this->encryptor->decrypt($apiDetails->getOrganizationId()),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/distributor/crud', name: 'distributor_crud')]
    public function distributorCrudAction(Request $request): Response
    {
        $data = $request->request;
        $distributorId = $request->get('distributor_id') ?? $data->get('delete');
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $response['distributorUsers'] = $this->em->getRepository(DistributorUsers::class)->findBy([
            'distributor' => $distributorId,
        ]);

        if($data->get('delete') != null){

            $flash = '<b><i class="fas fa-check-circle"></i> Distributor Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        $response['flash'] = '';

        if(!empty($data)) {

            // Distributor Details
            $distributor->setDistributorName($this->encryptor->encrypt($data->get('distributor-name')));
            $distributor->setEmail($this->encryptor->encrypt($data->get('email')));
            $distributor->setTelephone($this->encryptor->encrypt($data->get('telephone')));

            $this->em->persist($distributor);

            // API
            $api = $this->em->getRepository(Api::class)->find($data->get('api'));
            $apiDetails = $this->em->getRepository(ApiDetails::class)->findOneBy([
                'distributor' => $distributorId,
            ]);

            if($apiDetails == null){

                $apiDetails = new ApiDetails();
            }

            $apiDetails->setApi($api);
            $apiDetails->setDistributor($distributor);
            $apiDetails->setClientId($this->encryptor->encrypt($data->get('client-id')));
            $apiDetails->setClientSecret($this->encryptor->encrypt($data->get('client-secret')));
            $apiDetails->setOrganizationId($this->encryptor->encrypt($data->get('organization-id')));

            $this->em->persist($apiDetails);

            // Distributor Users
            if(count($data->get('user_id')) > 0){

                for($i = 0; $i < count($data->get('user_id')); $i++){

                    $userId = $data->get('user_id')[$i];
                    $firstName = $this->encryptor->encrypt($data->get('user_first_name')[$i]);
                    $lastName = $this->encryptor->encrypt($data->get('user_last_name')[$i]);
                    $userEmail = $this->encryptor->encrypt($data->get('user_email')[$i]);
                    $userTelephone = $this->encryptor->encrypt($data->get('user_telephone')[$i]);

                    $distributorUsers = $this->em->getRepository(DistributorUsers::class)->find($userId);

                    $distributorUsers->setFirstName($firstName);
                    $distributorUsers->setLastName($lastName);
                    $distributorUsers->setEmail($userEmail);
                    $distributorUsers->setTelephone($userTelephone);

                    $this->em->persist($distributorUsers);

                    // User Permissions
                    $userPermissions = $this->em->getRepository(DistributorUserPermissions::class)->findBy([
                        'user' => $userId
                    ]);

                    // Remove currently saved
                    foreach($userPermissions as $userPermission){

                        $this->em->remove($userPermission);
                    }

                    // Save new permissions
                    if($data->get('user_permissions') != null){

                        foreach($data->get('user_permissions') as $permissionId){

                            $pieces = explode('_', $permissionId);

                            if($pieces[1] == $distributorUsers->getId()) {

                                $userPermission = new DistributorUserPermissions();
                                $permission = $this->em->getRepository(UserPermissions::class)->find($permissionId);

                                $userPermission->setPermission($permission);
                                $userPermission->setDistributor($distributor);
                                $userPermission->setUser($distributorUsers);

                                $this->em->persist($userPermission);
                            }
                        }
                    }
                }
            }

            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Distributor Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['distributorName'] = $data->get('distributor_name');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/communication-method/crud', name: 'communication_method_crud')]
    public function communicationMethodCrudAction(Request $request): Response
    {
        $data = $request->request;
        $communicationMethodId = $data->get('communicationMethodId');
        $communicationMethod = $this->em->getRepository(CommunicationMethods::class)->find($communicationMethodId);

        $response = [];

        if(!empty($data)) {

            // Clinic Details
            $communicationMethod->setMethod($data->get('communication_method'));

            $this->em->persist($communicationMethod);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Communication Method Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['method'] = $data->get('communication_method');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/banner/crud', name: 'banner_crud')]
    public function BannerCrudAction(Request $request): Response
    {
        $data = $request->request;
        $bannerId = $data->get('banner-id') ?? $data->get('delete');
        $banner = $this->em->getRepository(Banners::class)->find($bannerId);
        $page = $this->em->getRepository(Pages::class)->find($data->get('page-id') ?? 0);
        $file = '';

        $response = [];

        if($data->get('delete') != null){

            $this->em->remove($banner);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Banner Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        if(!empty($data) && $data->get('delete') == null) {

            if($banner == null){

                $banner = new Banners();
            }

            if(!empty($_FILES['banner']['name'])) {

                $extension = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
                $file = uniqid() . '.' . $extension;
                $targetFile = __DIR__ . '/../../public/images/banners/' . $file;

                if (move_uploaded_file($_FILES['banner']['tmp_name'], $targetFile)) {

                    $banner->setName($file);
                }
            }

            $banner->setCaption($data->get('caption'));
            $banner->setIsDefault($data->get('is-default') ?? 0);
            $banner->setIsPublished($data->get('is-published') ?? 0);
            $banner->setOrderBy($data->get('order-by'));
            $banner->setPage($page);
            $banner->setAlt($data->get('alt'));

            $this->em->persist($banner);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Banner Successfully Saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['banner'] = $file;
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/manufacturer/crud', name: 'manufacturer_crud')]
    #[Route('/manufacturers/crud', name: 'update_manufacturer')]
    public function manufacturerCrudAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface  $mailer): Response
    {
        $data = $request->request;
        $manufacturerId = $data->get('manufacturer-id') ?? 0;
        $manufacturer = $this->em->getRepository(Manufacturers::class)->find($manufacturerId);
        $response['manufacturerLogo'] = '';

        if($manufacturerId == 0 && $manufacturer == null)
        {
            $manufacturer = new Manufacturers();
            $plainTextPwd = $this->generatePassword();
        }

        $domainName = explode('@', $data->get('email'));

        $manufacturer->setName($this->encryptor->encrypt($data->get('manufacturer-name')));
        $manufacturer->setEmail($this->encryptor->encrypt($data->get('email')));
        $manufacturer->setHashedEmail(md5($data->get('email')));
        $manufacturer->setDomainName(md5($domainName[1]));
        $manufacturer->setTelephone($this->encryptor->encrypt($data->get('telephone')));
        $manufacturer->setIntlCode($this->encryptor->encrypt($data->get('intl-code')));
        $manufacturer->setIsoCode($this->encryptor->encrypt($data->get('iso-code')));
        $manufacturer->setFirstName($this->encryptor->encrypt($data->get('first-name')));
        $manufacturer->setLastName($this->encryptor->encrypt($data->get('last-name')));

        if(!empty($_FILES['logo']['name'])) {

            $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $file = $manufacturer->getId() . '-' . uniqid() . '.' . $extension;
            $targetFile = __DIR__ . '/../../public/images/logos/' . $file;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {

                $manufacturer->setLogo($file);

                $response['manufacturerLogo'] = $this->getParameter('app.base_url') .'/images/logos/'. $manufacturer->getLogo();
            }
        }

        $this->em->persist($manufacturer);
        $this->em->flush();

        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Manufacturer Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        if($data->get('manufacturer-id') == 0 && $manufacturer == null)
        {
            // Create user
            $manufacturerUsers = new ManufacturerUsers();

            $hashed_pwd = $passwordHasher->hashPassword($manufacturerUsers, $plainTextPwd);

            $manufacturerUsers->setManufacturer($manufacturer);
            $manufacturerUsers->setFirstName($this->encryptor->encrypt($data->get('first-name')));
            $manufacturerUsers->setLastName($this->encryptor->encrypt($data->get('last-name')));
            $manufacturerUsers->setEmail($this->encryptor->encrypt($data->get('email')));
            $manufacturerUsers->setHashedEmail(md5($data->get('email')));
            $manufacturerUsers->setTelephone($this->encryptor->encrypt($data->get('telephone')));
            $manufacturerUsers->setIsoCode($this->encryptor->encrypt($data->get('iso-code')));
            $manufacturerUsers->setIsoCode($this->encryptor->encrypt($data->get('intl-code')));
            $manufacturerUsers->setRoles(['ROLE_MANUFACTURER']);
            $manufacturerUsers->setPassword($hashed_pwd);
            $manufacturerUsers->setIsPrimary(1);

            $this->em->persist($manufacturerUsers);
            $this->em->flush();


            // Send Email
            $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
            $body .= '<tr><td colspan="2">Hi ' . $data->get('first_name') . ',</td></tr>';
            $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
            $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
            $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://' . $_SERVER['HTTP_HOST'] . '/manufacturers/login">https://' . $_SERVER['HTTP_HOST'] . '/manufacturers/login</a></td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Username: </b></td>';
            $body .= '    <td>' . $data->get('email') . '</td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Password: </b></td>';
            $body .= '    <td>' . $plainTextPwd . '</td>';
            $body .= '</tr>';
            $body .= '</table>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html' => $body,
            ])->getContent();

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($data->get('email'))
                ->subject('Fluid Login Credentials')
                ->html($html);

            $mailer->send($email);

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Manufacturer Successfully Created.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $response['manufacturer'] = $data->get('manufacturer-name');

        return new JsonResponse($response);
    }

    #[Route('/admin/domain/crud', name: 'domain_crud')]
    public function domainCrudAction(Request $request): Response
    {
        $data = $request->request;
        $domainId = $data->get('domain-id') ?? $request->request->get('delete');
        $restrictedDomain = $this->em->getRepository(RestrictedDomains::class)->find($domainId);

        $response = [];

        if($request->request->get('delete') != null){

            $restrictedDomain = $this->em->getRepository(RestrictedDomains::class)->find($domainId);

            if($restrictedDomain != null)
            {
                $this->em->remove($restrictedDomain);
                $this->em->flush();
            }

            $flash = '<b><i class="fas fa-check-circle"></i> Domain Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        if(!empty($data)) {

            if($restrictedDomain == null){

                $restrictedDomain = new RestrictedDomains();
            }

            // Domain Details
            $restrictedDomain->setName($data->get('domain-name'));

            $this->em->persist($restrictedDomain);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Domain Successfully saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['domain'] = $data->get('domain-name');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/tag/crud', name: 'tag_crud')]
    public function tagCrudAction(Request $request): Response
    {
        $data = $request->request;
        $tagId = $data->get('tagId') ?? $data->get('delete');
        $tag = $this->em->getRepository(Tags::class)->find($tagId);
        $response = [];

        if($data->get('delete') != null){

            $this->em->remove($tag);
            $this->em->flush();

            $response = '<b><i class="fas fa-check-circle"></i> Tag Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($response);
        }

        if(!empty($data)) {

            if($tag == null){

                $tag = new Tags();
            }

            // Clinic Details
            $tag->setName($data->get('tag_name'));

            $this->em->persist($tag);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Tag Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['tag'] = $data->get('tag_name');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/review/crud', name: 'review_crud')]
    public function reviewCrudAction(Request $request): Response
    {
        $data = $request->request;
        $reviewId = $data->get('review-id') ?? 0;
        $review = $this->em->getRepository(ProductReviews::class)->find($reviewId);
        $response = [];

        if(!empty($data)) {

            $review->setIsApproved($data->get('is-approved'));

            $this->em->persist($review);
            $this->em->flush();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/clinics/approve', name: 'clinic_is_approved')]
    public function isClinicApprovedAction(Request $request, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $clinicId = $data->get('clinic-id') ?? 0;
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $response = [];

        if(!empty($data)) {

            $clinic->setIsApproved($data->get('is-approved'));

            $this->em->persist($clinic);
            $this->em->flush();
        }

        if($data->get('is-approved'))
        {
            // Send Email
            $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
            $body .= '<tr><td colspan="2">Hi ' . $this->encryptor->decrypt($clinic->getManagerFirstName()) . ',</td></tr>';
            $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
            $body .= '<tr><td colspan="2">Please your Fluid account has been approved.</td></tr>';
            $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://' . $_SERVER['HTTP_HOST'] . '/clinics/login">https://' . $_SERVER['HTTP_HOST'] . '/clinics/login</a></td>';
            $body .= '</tr>';;
            $body .= '</table>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html' => $body,
            ])->getContent();

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($this->encryptor->decrypt($clinic->getEmail()))
                ->subject('Fluid Account Approval')
                ->html($html);

            $mailer->send($email);
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/distributors/approve', name: 'distributor_is_approved')]
    public function isDistributorApprovedAction(Request $request, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $distributorId = $data->get('distributor-id') ?? 0;
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $response = [];

        if(!empty($data)) {

            $distributor->setIsApproved($data->get('is-approved'));

            $this->em->persist($distributor);
            $this->em->flush();
        }

        if($data->get('is-approved'))
        {
            // Send Email
            $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
            $body .= '<tr><td colspan="2">Hi ' . $this->encryptor->decrypt($distributor->getManagerFirstName()) . ',</td></tr>';
            $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
            $body .= '<tr><td colspan="2">Please your Fluid account has been approved.</td></tr>';
            $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://' . $_SERVER['HTTP_HOST'] . '/distributors/login">https://' . $_SERVER['HTTP_HOST'] . '/distributors/login</a></td>';
            $body .= '</tr>';;
            $body .= '</table>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html' => $body,
            ])->getContent();

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($this->encryptor->decrypt($distributor->getEmail()))
                ->subject('Fluid Account Approval')
                ->html($html);

            $mailer->send($email);
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/comment/crud', name: 'comment_crud')]
    public function commentCrudAction(Request $request): Response
    {
        $data = $request->request;
        $commentId = $data->get('comment-id') ?? 0;
        $comment = $this->em->getRepository(ProductReviewComments::class)->find($commentId);
        $response = [];

        if(!empty($data)) {

            $comment->setIsApproved($data->get('is-approved'));

            $this->em->persist($comment);
            $this->em->flush();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/active-ingredient/crud', name: 'active_ingredient_crud')]
    public function activeIngredientCrudAction(Request $request): Response
    {
        $data = $request->request;
        $ingredientId = $data->get('ingredientId') ?? $data->get('delete');
        $ingredient = $this->em->getRepository(ActiveIngredients::class)->find($ingredientId);
        $response = [];

        if($data->get('delete') != null){

            $this->em->remove($ingredient);
            $this->em->flush();

            $response = '<b><i class="fas fa-check-circle"></i> Active Ingredient Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($response);
        }

        if(!empty($data)) {

            if($ingredient == null){

                $ingredient = new ActiveIngredients();
            }

            $ingredient->setName($data->get('ingredient-name'));

            $this->em->persist($ingredient);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Active Ingredient Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['ingredient'] = $data->get('ingredient-name');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/api/crud', name: 'api_crud')]
    public function apiCrudAction(Request $request): Response
    {
        $data = $request->request;
        $apiId = $data->get('apiId') ?? $data->get('delete');
        $api = $this->em->getRepository(Api::class)->find($apiId);
        $response = [];

        if($data->get('delete') != null){

            $this->em->remove($api);
            $this->em->flush();

            $response = '<b><i class="fas fa-check-circle"></i> API Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($response);
        }

        if(!empty($data)) {

            if($api == null){

                $api = new Api();
            }

            $api->setName($data->get('api-name'));

            $this->em->persist($api);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> API Successfully Saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['api'] = $data->get('api-name');
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/api-details/crud', name: 'api_details_crud')]
    public function apiDetailsCrudAction(Request $request): Response
    {
        $data = $request->request;
        $apiId = (int) $data->get('api-id');
        $apiDetailsId = $data->get('api-details-id') ?? $data->get('delete');
        $apiDetails = $this->em->getRepository(ApiDetails::class)->find((int) $apiDetailsId);
        $response = [];

        if($data->get('delete') != null){

            $this->em->remove($apiDetails);
            $this->em->flush();

            $apiDetails = $this->em->getRepository(ApiDetails::class)->findBy([
                'api' => $apiId,
            ]);

            $response['html'] = $this->getApiDetails($apiDetails);;
            $response = '<b><i class="fas fa-check-circle"></i> API Detail Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($response);
            die();
        }

        if(!empty($data)) {

            $api = $this->em->getRepository(Api::class)->find($apiId);
            $distributor = $this->em->getRepository(Distributors::class)->find($data->get('distributor-id'));

            if($apiDetailsId == 0){

                $apiDetails = new ApiDetails();
            }

            $apiDetails->setApi($api);
            $apiDetails->setDistributor($distributor);
            $apiDetails->setOrganizationId($this->encryptor->encrypt($data->get('organization-id')));
            $apiDetails->setClientId($this->encryptor->encrypt($data->get('client-id')));
            $apiDetails->setClientSecret($this->encryptor->encrypt($data->get('client-secret')));

            $this->em->persist($apiDetails);
            $this->em->flush();

            $apiDetails = $this->em->getRepository(ApiDetails::class)->findBy([
                'api' => $apiId,
            ]);

            $html = $this->getApiDetails($apiDetails);

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> API Details Successfully Saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['html'] = $html;
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/article/crud', name: 'article_crud')]
    public function articleCrudAction(Request $request): Response
    {
        $data = $request->request;
        $articleId = $data->get('article-id') ?? $data->get('delete');
        $article = $this->em->getRepository(Articles::class)->find($articleId);
        $response = [];

        if($data->get('delete') != null){

            foreach($article->getArticleDetails() as $articleDetail){

                $this->em->remove($articleDetail);
            }

            $this->em->remove($article);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Article Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($response);
        }

        if(!empty($data)) {

            if($article == null){

                $article = new Articles();
            }

            $page = $this->em->getRepository(Pages::class)->find($data->get('page-id'));
            $articleCount = count($article->getArticleDetails());

            $article->setIsMulti($data->get('is-multi') ?? 0);
            $article->setPage($page);
            $article->setName($data->get('name'));
            $article->setIcon($data->get('icon'));
            $article->setDescription($data->get('description'));
            $article->setArticleCount($articleCount);

            $this->em->persist($article);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Article Successfully Saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['name'] = $article->getName();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/articles-details/crud', name: 'article_details_crud')]
    public function articleDetailsCrudAction(Request $request): Response
    {
        $data = $request->request;
        $articleId = $data->get('article-id') ?? 0;
        $articleDetailId = $data->get('article-detail-id') ?? $data->get('delete');
        $article = $this->em->getRepository(Articles::class)->find($articleId);
        $articleDetails = $this->em->getRepository(ArticleDetails::class)->find((int) $articleDetailId);
        $user = $this->em->getRepository(User::class)->find($this->getUser()->getId() ?? 0);
        $response = [];

        if($data->get('delete') != null){

            $this->em->remove($articleDetails);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Article Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['articleCount'] = count($article->getArticleDetails()->toArray()) - 1;

            return new JsonResponse($response);
            die();
        }

        if(!empty($data)) {

            if($articleDetailId == 0){

                $articleDetails = new ArticleDetails();

                $articleDetails->setUser($user);
            }

            $articleDetails->setArticle($article);
            $articleDetails->setName($data->get('article-name'));
            $articleDetails->setDescription($data->get('article-description'));
            $articleDetails->setCopy($data->get('article-copy'));

            $this->em->persist($articleDetails);
            $this->em->flush();

            $articleDetails = $this->em->getRepository(ArticleDetails::class)->findBy([
                'article' => $articleDetails->getArticle()->getId(),
            ]);

            $article->setArticleCount(count($articleDetails));

            $this->em->persist($article);
            $this->em->flush();

            // Generate html
            $i = 0;
            $html = '';

            foreach($article->getArticleDetails()->toArray() as $articleDetail){

                    $i++;
                    $borderBottom = '';
                    $firstName = $this->encryptor->decrypt($articleDetail->getUser()->getFirstName());
                    $lastName = $this->encryptor->decrypt($articleDetail->getUser()->getLastName());

                    if(count($article->getArticleDetails()) == $i){

                        $borderBottom = 'border-bottom';
                    }

                    $html .= '
                    <div class="row" id="row_'. $articleDetail->getId() .'">
                        <div class="col-4 fw-bold d-block d-md-none text-truncate">
                            #ID
                        </div>
                        <div class="col-8 col-md-1 text-truncate pe-0">
                            <div class="border-left border-top ps-2 py-2 bg-white '. $borderBottom .'">
                                #'. $articleDetail->getId() .'
                            </div>
                        </div>
                        <div class="col-4 d-block d-md-none fw-bold text-truncate">
                            Name
                        </div>
                        <div class="col-8 col-md-4 text-truncate px-0">
                            <div class="border-top ps-2 py-2 bg-white '. $borderBottom .'">
                                '. $articleDetail->getName() .'
                            </div>
                        </div>
                        <div class="col-4 d-block d-md-none fw-bold text-truncate">
                            User
                        </div>
                        <div class="col-8 col-md-2 text-truncate px-0">
                            <div class="border-top ps-2 py-2 bg-white '. $borderBottom .'">
                                '. $firstName .' '. $lastName .'
                            </div>
                        </div>
                        <div class="col-4 d-block d-md-none fw-bold text-truncate">
                            Modified
                        </div>
                        <div class="col-8 col-md-2 text-truncate px-0">
                            <div class="border-top ps-2 py-2 bg-white '. $borderBottom .'">
                                '. $articleDetail->getModified()->format('Y-m-d H:i:s') .'
                            </div>
                        </div>
                        <div class="col-4 d-block d-md-none fw-bold text-truncate">
                            Created
                        </div>
                        <div class="col-8 col-md-2 text-truncate px-0">
                            <div class="border-top ps-2 py-2 bg-white '. $borderBottom .'">
                                '. $articleDetail->getModified()->format('Y-m-d H:i:s') .'
                            </div>
                        </div>
                        <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0 ps-0">
                            <div class="border-top border-right ps-2 py-2 bg-white h-100 '. $borderBottom .'">
                                <a
                                        href="#"
                                        data-bs-toggle="modal"
                                        data-bs-target="#article_modal"
                                        class="float-end open-edit-modal"
                                        data-article-id="'. $articleDetail->getId() .'"
                                >
                                    <i class="fa-solid fa-pen-to-square edit-icon"></i>
                                </a>
                                <a
                                        href=""
                                        class="delete-icon float-start float-sm-end ms-5 ms-md-0 open-delete-ingredient-modal"
                                        data-bs-toggle="modal"
                                        data-ingredient-id="'. $articleDetail->getId() .'"
                                        data-bs-target="#modal_delete_article"
                                >
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                    </div>';
                }

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Article Successfully Saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['html'] = $html;
            $response['articleCount'] = count($article->getArticleDetails()->toArray());

        }

        return new JsonResponse($response);
    }

    #[Route('/admin/species/crud', name: 'species_crud')]
    public function speciesCrudAction(Request $request): Response
    {
        $data = $request->request;
        $speciesId = $data->get('speciesId') ?? $request->request->get('delete');
        $species = $this->em->getRepository(Species::class)->find($speciesId);

        $response = [];

        if($request->request->get('delete') != null){

            $productSpecies = $this->em->getRepository(ProductsSpecies::class)->findBy([
                'species' => $speciesId,
            ]);

            foreach ($productSpecies as $productSpecie) {

                $this->em->remove($productSpecie);
            }

            $this->em->flush();

            $this->em->remove($species);
            $this->em->flush();

            $flash = '<b><i class="fas fa-check-circle"></i> Species Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        if(!empty($data)) {

            if($species == null){

                $species = new Species();
            }

            $species->setName($data->get('species-name'));
            $species->setIcon($data->get('species-icon'));

            $this->em->persist($species);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Species Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['species'] = $species->getName();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/country/crud', name: 'country_crud')]
    public function countryCrudAction(Request $request): Response
    {
        $data = $request->request;
        $countryId = $data->get('country-id');
        $isActive = $data->get('is-active') ?? 0;
        $country = $this->em->getRepository(Countries::class)->find($countryId);
        $response = [];

        if(!empty($data)) {

            $country->setName($data->get('country-name'));
            $country->setCurrency($data->get('currency'));
            $country->setIsActive($isActive);

            $this->em->persist($country);
            $this->em->flush();

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> Country Successfully Updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['country'] = $country->getName();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/user/crud', name: 'user_crud')]
    public function userCrudAction(Request $request): Response
    {
        $data = $request->request;
        $userId = $data->get('userId') ?? $request->request->get('delete');
        $user = $this->em->getRepository(User::class)->find($userId);

        $response = [];

        if($request->request->get('delete') != null){

            $this->em->remove($user);
            $this->em->flush();

            $flash = '<b><i class="fas fa-check-circle"></i> User Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        if(!empty($data)) {

            $action = 'Updated';
            $newUser = false;

            if($user == null){

                $validUser = $this->em->getRepository(User::class)->findBy([
                    'email' => $data->get('email'),
                ]);

                if(count($validUser) > 0){

                    $response['flash'] = '<b><i class="fas fa-check-circle"></i> User account already exists and was not created!.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

                    return new JsonResponse($response);
                }

                $newUser = true;
                $action = 'Created';
                $user = new User();
            }

            $user->setFirstName($this->encryptor->encrypt($data->get('first_name')));
            $user->setLastName($this->encryptor->encrypt($data->get('last_name')));
            $user->setEmail($this->encryptor->encrypt($data->get('email')));
            $user->setHashedEmail(md5($data->get('email')));

            // User Roles
            if(count($data->get('roles')) > 0){

                $roles = [];

                foreach($data->get('roles') as $role){

                    $roles[] = $role;
                }

                $user->setRoles($roles);
            }

            $this->em->persist($user);
            $this->em->flush();

            if($newUser || $data->get('resetPassword') == 'true'){

                $getPassword = $this->setUserPassword($user->getId());

                $plainPwd = $getPassword['plainPassword'];
                $hashedPwd = $getPassword['hashedPassword'];

                $user->setPassword($hashedPwd);

                $this->em->persist($user);
                $this->em->flush();

                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial, serif">';
                $body .= '<tr><td colspan="2">Hi '. $user->getFirstName() .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/admin">https://'. $_SERVER['HTTP_HOST'] .'/admin</a></td>';
                $body .= '</tr>';
                $body .= '<tr>';
                $body .= '    <td><b>Username: </b></td>';
                $body .= '    <td>'. $user->getEmail() .'</td>';
                $body .= '</tr>';
                $body .= '<tr>';
                $body .= '    <td><b>Password: </b></td>';
                $body .= '    <td>'. $plainPwd .'</td>';
                $body .= '</tr>';
                $body .= '</table>';

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($data->get('email'))
                    ->subject('Fluid Login Credentials')
                    ->html($body);

                $this->mailer->send($email);
            }

            $response['flash'] = '<b><i class="fas fa-check-circle"></i> User account successfully '. $action .'!.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            $response['user'] = $user->getFirstName() .' '. $user->getLastName();
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/user-permissions/crud', name: 'user_permission_crud')]
    public function userPermissionCrudAction(Request $request): Response
    {
        $data = $request->request;
        $userPermissionId = $data->get('permissionId') ?? $request->request->get('delete');
        $userPermission = $this->em->getRepository(UserPermissions::class)->find($userPermissionId);
        $action = 'Updated';

        $response = [];

        if($request->request->get('delete') != null){

            $this->em->remove($userPermission);
            $this->em->flush();

            $flash = '<b><i class="fas fa-check-circle"></i> User Permission Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            return new JsonResponse($flash);
        }

        if(!empty($data)) {

            if($userPermission == null){

                $userPermission = new UserPermissions();
                $action = 'Created';
            }

            $isClinic = $data->get('isClinic') ?? 0;
            $isDistributor = $data->get('isDistributor') ?? 0;

            $userPermission->setIsClinic($isClinic);
            $userPermission->setIsDistributor($isDistributor);
            $userPermission->setPermission($data->get('permission'));
            $userPermission->setInfo($data->get('description'));

            $this->em->persist($userPermission);
            $this->em->flush();
        }

        $response['flash'] = '<b><i class="fas fa-check-circle"></i> User Successfully '. $action .'.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        $response['permission'] = $data->get('permission');

        return new JsonResponse($response);
    }

    #[Route('/admin/product/manufacturer/save', name: 'products_manufacturer_save')]
    public function productsSaveManufacturer(Request $request): Response
    {
        $data = $request->request;
        $manufacturerId = $data->get('manufacturerId');
        $manufacturer_name = $data->get('manufacturer');
        $manufacturer = $this->em->getRepository(Manufacturers::class)->find($manufacturerId);
        $response = false;

        if($manufacturer != null && $manufacturerId > 0){

            $manufacturer->setName($manufacturer_name);

            $this->em->persist($manufacturer);
            $this->em->flush();

            $response = true;

        } elseif($manufacturerId == 0){

            $manufacturer = new Manufacturers();

            $manufacturer->setName($manufacturer_name);

            $this->em->persist($manufacturer);
            $this->em->flush();

            $manufacturers = $this->em->getRepository(Manufacturers::class)->findAll();

            $response = $this->getMultiDropdownList(
                $manufacturers, 'manufacturer', ProductsSpecies::class, 'getName',
                'products', $request->get('product_id'), 'getSpecies'
            );
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/product/category/save', name: 'products_category_save')]
    public function productsSaveCategory(Request $request): Response
    {
        $data = $request->request;
        $categoryId = $data->get('category_id') ?? 0;
        $categoryName = $data->get('category');
        $category = $this->em->getRepository(Categories1::class)->find($categoryId);
        $response = false;

        if($category != null && $categoryId > 0){

            $category->setName($categoryName);

            $this->em->persist($category);
            $this->em->flush();

            $response = true;

        } elseif($categoryId == null){

            $category = new Categories1();

            $category->setName($categoryName);

            $this->em->persist($category);
            $this->em->flush();

            $categories = $this->em->getRepository(Categories1::class)->findAll();

            $response = $this->individualDropdownList(
                $categories, 'category', 'category', 'getName',
            );
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/product/category2/save', name: 'products_category2_save')]
    public function productsSaveCategory2(Request $request): Response
    {
        $data = $request->request;
        $category1Id = $data->get('category1-id');
        $category2Id = $data->get('category2-id');
        $category = $data->get('sub-category');
        $category1 = $this->em->getRepository(Categories1::class)->find($category1Id);
        $category2 = $this->em->getRepository(Categories2::class)->find($category2Id);
        $response = false;

        if($category2 != null && $category2Id > 0){

            $category2->setName($category);
            $category2->setCategory1($category1);

            $this->em->persist($category2);
            $this->em->flush();

            $response = true;

        } elseif($category2Id == 0){

            $category2 = new Categories2();

            $category2->setName($category);
            $category2->setCategory1($category1);

            $this->em->persist($category2);
            $this->em->flush();

            $categories = $this->em->getRepository(Categories2::class)->findBy([
                'category1' => $category1Id,
            ]);

            $response = $this->individualDropdownList(
                $categories, 'sub-category', 'sub_category', 'getName',
            );
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/product/sub-category/save', name: 'products_sub_category_save')]
    public function productsSaveSubCategory(Request $request): Response
    {
        $data = $request->request;
        $subCategoryId = $data->get('sub_category_id');
        $subCategoryName = $data->get('sub_category');
        $subCategory = $this->em->getRepository(Categories2::class)->find($subCategoryId);
        $response = false;

        if($subCategory != null && $subCategoryId > 0){

            $subCategory->setName($subCategoryName);

            $this->em->persist($subCategory);
            $this->em->flush();

            $response = true;

        } elseif($subCategoryId == 0){

            $subCategory = new Categories2();

            $subCategory->setName($subCategoryName);

            $this->em->persist($subCategory);
            $this->em->flush();

            $subCategories = $this->em->getRepository(Categories2::class)->findAll();

            $response = $this->individualDropdownList(
                $subCategories, 'sub-category', 'sub_category', 'getName',
            );
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/product/species/save', name: 'products_species_save')]
    public function productsSaveSpecies(Request $request): Response
    {
        $data = $request->request;
        $speciesId = $data->get('species_id');
        $speciesName = $data->get('species');
        $species = $this->em->getRepository(Species::class)->find($speciesId);
        $response = false;

        if($species != null && $speciesId > 0){

            $species->setName($speciesName);

            $this->em->persist($species);
            $this->em->flush();

            $response = true;

        } elseif($speciesId == 0){

            $species = new Species();

            $species->setName($speciesName);

            $this->em->persist($species);
            $this->em->flush();

            $species = $this->em->getRepository(Species::class)->findAll();

            $response = $this->getMultiDropdownList(
                $species, 'species', ProductsSpecies::class, 'getName',
                'products', $request->get('product_id'), 'getSpecies'
            );
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/categories/sub-categories/save', name: 'sub_categories_save')]
    public function categorySaveSubCategory(Request $request): Response
    {
        $data = $request->request;
        $parentId = $data->get('parent-id');
        $rootId = $data->get('root-id');
        $category = $data->get('category');
        $tags = $data->get('tag');
        $response = [];
        $response['list'] = '';
        $level = (int) $data->get('level');

        if($parentId == 0){

            return new JsonResponse('');
        }

        if($tags != null){

            foreach($tags as $key => $tag){

                $level = $key;

                foreach($tag as $index => $value) {

                    if($key == 2) {

                        $cat = new Categories2();
                        $parentCategory = $this->em->getRepository(Categories1::class)->find($index);
                        $cat->setCategory1($parentCategory);

                    } elseif($key == 3){

                        $cat = new Categories3();
                        $parentCategory = $this->em->getRepository(Categories2::class)->find($index);
                        $cat->setCategory2($parentCategory);

                    } else {

                        return new JsonResponse('');
                    }

                    $selectedTags = [];
                    $slug = '';

                    foreach($value as $item){

                        $tagRepo = $this->em->getRepository(Tags::class)->find($item);

                        $selectedTags[$tagRepo->getName()] = (int) $item;
                        $slug .= $tagRepo->getName() .' ';
                    }

                    $cat->setName($category);
                    $cat->setTags($selectedTags);
                    $cat->setSlug(trim($slug));

                    $this->em->persist($cat);
                }
            }

            $this->em->flush();

        } else {

            if($level == 2) {

                $category2 = new Categories2();
                $category1 = $this->em->getRepository(Categories1::class)->find($parentId);

                $category2->setName($category);
                $category2->setCategory1($category1);

                $this->em->persist($category2);
                $this->em->flush();

            } else {

                $category3 = new Categories3();
                $category2 = $this->em->getRepository(Categories2::class)->find($parentId);

                $category3->setName($category);
                $category3->setCategory2($category2);

                $this->em->persist($category3);
                $this->em->flush();
            }
        }

        $parentId = $rootId ?? $parentId;

        $categories = $this->em->getRepository(Categories2::class)->findBy([
            'category1' => $parentId,
        ]);

        if(count($categories) > 0){

            foreach($categories as $category){

                $response['list'] .= '
                <div class="row" data-category2-row="'. $category->getId() .'">
                    <div class="col-9 col-sm-11 pe-0">
                        <div class="border-left border-top ps-2 py-2 bg-white">
                            '. $category->getName() .'
                        </div>
                    </div>
                    <div class="col-3 col-sm-1 ps-0 d-table">
                        <div class="border-right border-top py-2 d-table-cell bg-white" style="display: table-cell !important;">
                            <a href="" class="float-end level2-edit me-3 text-primary" data-category-id="'. $category->getId() .'">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a href="" 
                                class="float-end level2-delete me-3 text-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#modal_delete_level"
                                data-category-id="'. $category->getId() .'"
                                data-level="2"
                            >
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row level2-panel" id="level2_row_'. $category->getId() .'" data-user-id="'. $category->getId() .'" style="display: none">
                    <div class="col-12 d-table bg-white border-top-dashed">
                        <div class="border-left border-right d-table-cell px-2 pt-0">
                            <div class="row">

                                <!-- Category -->
                                <div class="col-12 col-md-6">
                                    <label class="text-primary mb-2">
                                        Name <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        name="level2_category[]"
                                        data-category-id="'. $category->getId() .'"
                                        type="text"
                                        class="form-control"
                                        placeholder="Category"
                                        value="'. $category->getName() .'"
                                    >
                                    <div class="hidden_msg" id="error_level2_category_'. $category->getId() .'">
                                        Required Field
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div class="col-12 col-md-6">
                                    <label class="text-primary mb-2">
                                        Tags
                                    </label>
                                    <div class="position-relative">
                                        <div
                                            class="form-control cursor-text text-placeholder multi-select tag"
                                            data-level="2"
                                            data-category-id="'. $category->getId() .'"
                                        >';

                                        $response['list'] .= $this->forward(
                                            'App\Controller\AdminDashboardController::getSelectedTags',
                                            [
                                                'tags'  => $category->getTags(),
                                            ]
                                        )->getContent();

                                        $response['list'] .= '
                                        </div>
                                        <div id="tag_list" class="row tag_list" style="display: none">';

                                            $tagsArray = [];
                                            $tags = $category->getTags();

                                            foreach($tags as $tag){

                                                $tagRepo = $this->em->getRepository(Tags::class)->find($tag);

                                                $tagsArray[$tagRepo->getName()] = $tag;
                                            }

                                            if(count($tagsArray) > 0){

                                                foreach($tagsArray as $key => $value){

                                                    $response['list'] .= '
                                                    <input
                                                        type="hidden"
                                                        name="tag['. $level .']['. $category->getId() .'][]"
                                                        class="tag_hidden"
                                                        data-name="'. $key .'"
                                                        value="'. $value .'"
                                                        data-tag-hidden-id="'. $value .'"
                                                    >';
                                                }
                                            }

                                            $response['list'] .= '
                                            <div
                                                class="tag-list-container"
                                                data-category-id="'. $category->getId() .'"
                                            >';

                                            $response['list'] .= $this->categoryTagDropdownList('tag', [], $level);

                                            $response['list'] .= '
                                            </div>
                                        </div>
                                        <div class="hidden_msg" id="error_ctag">
                                            Required Field
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Third Level -->
                            <div class="row">
                                <div class="col-12 pb-3 mt-3 border-top-dashed pt-3">
                                    <h6 class="text-primary mb-2 d-inline">Third Level Category</h6>
                                    <a href="#"
                                       class="float-end category3-modal-link"
                                       data-bs-toggle="modal"
                                       data-bs-target="#third_level_modal"
                                       data-category-id="'. $category->getId() .'"
                                    >
                                        <i class="fa-solid fa-square-plus me-1"></i>
                                        Create New
                                    </a>
                                </div>
                                <div class="col-12" id="level3_container">';

                            $subCategories = $category->getCategories3();

                            if(count($subCategories) > 0){

                                foreach($subCategories as $subCategory){

                                    $response['list'] .= '
                                    <div class="row" data-category3-row="'. $subCategory->getId() .'">
                                        <div class="col-9 col-sm-11 ps-1 pe-0">
                                            <div class="border-top ps-2 py-2 bg-secondary">
                                                '. $subCategory->getName() .'
                                            </div>
                                        </div>
                                        <div class="col-3 col-sm-1 ps-0 pe-1 d-table">
                                            <div class="border-top py-2 d-table-cell bg-secondary" style="display: table-cell !important;">
                                                <a href="" class="float-end level3-edit me-3 text-primary" data-category-id="'. $subCategory->getId() .'">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <a 
                                                    href="" 
                                                    class="float-end level3-delete me-3 text-danger" 
                                                    data-level="3" 
                                                    data-category-id="'. $subCategory->getId() .'"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modal_delete_level"
                                                >
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Level Three Panel -->
                                    <div 
                                        class="row level3-panel" 
                                        id="level3_row_'. $subCategory->getId() .'" 
                                        data-user-id="'. $subCategory->getId() .'" 
                                        style="display: none"
                                    >
                                        <div class="col-12 d-table">
                                            <div class="row mb-3">
                                                <!-- Level Two Category -->
                                                <div class="col-12 col-md-6">
                                                    <label class="text-primary mb-2">
                                                        Name <span class="text-danger">*</span>
                                                    </label>
                                                    <input
                                                        name="level3_category['. $subCategory->getId() .'][]"
                                                        data-category-id="'. $subCategory->getId() .'"
                                                        type="text"
                                                        class="form-control"
                                                        placeholder="Category"
                                                        value="'. $subCategory->getName() .'"
                                                    >
                                                    <div class="hidden_msg level3-msg">
                                                        Required Field
                                                    </div>
                                                </div>

                                                <!-- Tags -->
                                                <div class="col-12 col-md-6">
                                                    <label class="text-primary mb-2">
                                                        Tags
                                                    </label>
                                                    <div class="position-relative">
                                                        <div
                                                            class="form-control cursor-text text-placeholder multi-select tag"
                                                            data-level="2"
                                                            data-category-id="'. $subCategory->getId() .'"
                                                        >';

                                                            $response['list'] .= $this->forward(
                                                                'App\Controller\AdminDashboardController::getSelectedTags',
                                                                [
                                                                    'tags'  => $subCategory->getTags(),
                                                                ]
                                                            )->getContent();

                                                        $response['list'] .= '
                                                        </div>
                                                        <div id="tag_list" class="row tag_list" style="display: none">';

                                                            $tagsArray2 = [];
                                                            $tags2 = $category->getTags();

                                                            foreach($tags2 as $tag2){

                                                                $tagRepo2 = $this->em->getRepository(Tags::class)->find($tag2);

                                                                $tagsArray2[$tagRepo2->getName()] = $tag2;
                                                            }

                                                            if($tagsArray2 > 0){

                                                                foreach($tagsArray2 as $key => $value){

                                                                    $response['list'] .= '
                                                                    <input
                                                                        type="hidden"
                                                                        name="tag[2]['. $subCategory->getId() .'][]"
                                                                        class="tag_hidden"
                                                                        data-name="'. $key .'"
                                                                        value="'. $value .'"
                                                                        data-tag-hidden-id="'. $value .'"
                                                                    >';
                                                                }
                                                            }

                                                            $response['list'] .= '
                                                            <div
                                                                    class="tag-list-container"
                                                                    data-category-id="'. $subCategory->getId() .'"
                                                            >
                                                                {{ tagList2|raw }}
                                                            </div>
                                                        </div>
                                                        <div class="hidden_msg" id="error_ctag">
                                                            Required Field
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                                }

                            } else {


                            }

                        $response['list'] .= '
                                </div> 
                            </div>
                        </div>
                    </div>
                </div>';
            }
        }

        $response['flash'] = '<b><i class="fas fa-check-circle"></i> Category updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/admin/product/tag/save', name: 'product_save_tag')]
    public function productSaveTag(Request $request): Response
    {
        $data = $request->request;
        $tagId = (int) $data->get('tag-id');
        $productId = (int) $data->get('product-id');
        $tag = $data->get('tag');
        $tagRepo = $this->em->getRepository(Tags::class)->find($tagId);
        $product = $this->em->getRepository(Products::class)->find($productId);
        $selectedTags = '';

        if($tagRepo == null){

            $tagRepo = new Tags();
        }

        $tagRepo->setName($tag);

        $this->em->persist($tagRepo);
        $this->em->flush();

        if($product != null){

            $selectedTags = $product->getTags();
        }

        $response = $this->categoryTagDropdownList('tag', $selectedTags, 1);

        return new JsonResponse($response);
    }

    #[Route('/admin/categories/tag/save', name: 'categories_save_tag')]
    public function categorySaveTag(Request $request): Response
    {
        $data = $request->request;
        $tagId = $data->get('tagId');
        $tag = $data->get('tag');
        $tagRepo = $this->em->getRepository(Tags::class)->find($tagId);

        if($tagRepo == null){

            $tagRepo = new Tags();
        }

        $tagRepo->setName($tag);

        $this->em->persist($tagRepo);
        $this->em->flush();

        $response = $this->categoryTagDropdownList('tag', '', 1);

        return new JsonResponse($response);
    }

    #[Route('/admin/products/active-ingredient/save', name: 'products_save_active_ingredient')]
    public function productsSaveActiveIingredient(Request $request): Response
    {
        $data = $request->request;
        $productId = (int) $data->get('product-id');
        $ingredientId = (int) $data->get('ingredient-id') ?? 0;
        $ingredient = $data->get('ingredient');
        $ingredientRepo = $this->em->getRepository(ActiveIngredients::class)->find($ingredientId);

        if($ingredientRepo == null){

            $ingredientRepo = new ActiveIngredients();
        }

        $ingredientRepo->setName($ingredient);

        $this->em->persist($ingredientRepo);
        $this->em->flush();

        $response = $this->getActiveIngredientDropdownList($productId);

        return new JsonResponse($response);
    }

    #[Route('/admin/form/save', name: 'form_save')]
    public function productsSaveForm(Request $request): Response
    {
        $data = $request->request;
        $formId = $data->get('formId') ?? 0;
        $form = $data->get('form');
        $formRepo = $this->em->getRepository(ProductForms::class)->find($formId);

        if($formRepo == null){

            $formRepo = new ProductForms();
        }

        $formRepo->setName($form);

        $this->em->persist($formRepo);
        $this->em->flush();

        $response = $this->productFormDropdownList('forms', $form);

        return new JsonResponse($response);
    }

    #[Route('/admin/product/is-published', name: 'product_is_published')]
    public function productIsPublished(Request $request): Response
    {
        $isPublished = $request->request->get('is_published') ?? 0;
        $productId = $request->request->get('product_id');

        $product = $this->em->getRepository(Products::class)->find($productId);

        if($product != null){

            $product->setIsPublished($isPublished);

            $this->em->persist($product);
            $this->em->flush();
        }

        return new JsonResponse($isPublished);
    }

    #[Route('/admin/banner/is-published', name: 'banner_is_published')]
    public function bannerIsPublished(Request $request): Response
    {
        $isPublished = $request->request->get('is-published') ?? 0;
        $bannerId = $request->request->get('banner-id');

        $banner = $this->em->getRepository(Banners::class)->find($bannerId);

        if($banner != null){

            $banner->setIsPublished($isPublished);

            $this->em->persist($banner);
            $this->em->flush();
        }

        return new JsonResponse($isPublished);
    }

    #[Route('/admin/user-permission/is-clinic', name: 'permission_is_clinic')]
    public function permissionIsClinic(Request $request): Response
    {
        $isClinic = $request->request->get('is_clinic') ?? 0;
        $permissionId = $request->request->get('permission_id');

        $userPermission = $this->em->getRepository(UserPermissions::class)->find($permissionId);

        if($userPermission != null){

            $userPermission->setIsClinic($isClinic);

            $this->em->persist($userPermission);
            $this->em->flush();
        }

        return new JsonResponse($isClinic);
    }

    #[Route('/admin/user-permission/is-distributor', name: 'permission_is_distributor')]
    public function permissionIsDistributor(Request $request): Response
    {
        $isDistributor = $request->request->get('is_distributor') ?? 0;
        $permissionId = $request->request->get('permission_id');

        $userPermission = $this->em->getRepository(UserPermissions::class)->find($permissionId);

        if($userPermission != null){

            $userPermission->setIsDistributor($isDistributor);

            $this->em->persist($userPermission);
            $this->em->flush();
        }

        return new JsonResponse($isDistributor);
    }

    #[Route('/admin/product/{productId}', name: 'products', requirements: ['productId' => '\d+'])]
    public function productsCrud(Request $request, $product_id = 0): Response
    {
        $productId = $request->get('productId') ?? 0;
        $product = $this->em->getRepository(Products::class)->find($productId);
        $category1Id = 0;
        $category2Id = 0;
        $arr = '[]';
        $arr_species = '[]';

        if($product != null){

            if($product->getCategory() != null) {

                $category1Id = $product->getCategory()->getId();
            }
        }

        if($product != null){

            if($product->getCategory2() != null) {

                $category2Id = $product->getCategory2()->getId();
            }
        }

        if($product != null && $product->getCategory3() != null){

            $category3Id = $product->getCategory3()->getId();
        }

        $manufacturers = $this->em->getRepository(Manufacturers::class)->findAll();
        $species = $this->em->getRepository(Species::class)->findAll();
        $categories = $this->em->getRepository(Categories1::class)->findAll();
        $categories2 = $this->em->getRepository(Categories2::class)->findBy([
            'category1' => $category1Id
        ]);
        $categories3 = $this->em->getRepository(Categories3::class)->findBy([
            'category2' => $category2Id
        ]);

        $productManufacturers = $this->em->getRepository(ProductManufacturers::class)->findBy([
            'products' => $request->get('productId'),
        ]);
        $productSpecies = $this->em->getRepository(ProductsSpecies::class)->findBy([
            'products' => $request->get('productId'),
        ]);

        if($product == null){

            $product = new Products();
        }

        // Manufacturers dropdown
        $manufacturersList = '';

        if($manufacturers != null){

            $manufacturersList = $this->getMultiDropdownList(
                $manufacturers, 'manufacturer', ProductManufacturers::class, 'getName',
                'products', $request->get('productId'), 'getProducts', true
            );
            $array = '';
            $arr = '[';

            foreach($productManufacturers as $productManufacturer){

                $array .= $productManufacturer->getManufacturers()->getId().',';
            }

            $arr .= trim($array,',') . ']';
        }

        // Species dropdown
        $speciesList = '';

        if($species != null){

            $speciesList = $this->getMultiDropdownList(
                $species, 'species', ProductManufacturers::class, 'getName',
                'products', $request->get('productId'), 'getProducts'
            );
            $array = '';
            $arr_species = '[';

            foreach($productSpecies as $productSpecie){

                $array .= $productSpecie->getSpecies()->getId().',';
            }

            $arr_species .= trim($array,',') . ']';
        }

        // Tags dropdown
        $arrTags = [];

        if($product->getTags() != null){

            foreach($product->getTags() as $key => $tag){

                $arrTags[] = $tag;
            }
        }

        $selectedTags = $product->getTags();
        $tagsList = $this->categoryTagDropdownList('tag', $selectedTags, 1);

        // Categories dropdown
        if($product->getCategory() != null){

            $categoryId = $product->getCategory()->getId();

        } else {

            $categoryId = 0;
        }

        $categoriesList = $this->individualDropdownList(
            $categories, 'category', 'category', 'getName'
        );

        // Categories2 dropdown
        $subCategoriesList = $this->individualDropdownList(
            $categories2, 'sub-category', 'sub_category', 'getName'
        );

        // Categories3 dropdown
        $categories3List = $this->individualDropdownList(
            $categories3, 'category3', 'category3', 'getName'
        );

        // Form dropdown
        $formList = $this->productFormDropdownList('forms', $product->getForm());

        // Active ingredient dropdown
        $activeIngredientList = $this->getActiveIngredientDropdownList($productId);

        return $this->render('Admin/products.html.twig',[
            'product' => $product,
            'manufacturers' => $manufacturers,
            'species' => $species,
            'categoriesList' => $categoriesList,
            'subCategoriesList' => $subCategoriesList,
            'categories3List' => $categories3List,
            'product_id' => $request->get('productId'),
            'manufacturersList' => $manufacturersList,
            'productManufacturers' => $productManufacturers,
            'speciesList' => $speciesList,
            'productSpecies' => $productSpecies,
            'arr' => $arr,
            'arr_species' => $arr_species,
            'tagList' => $tagsList,
            'selectedTags' => $selectedTags,
            'arrTags' => json_encode($arrTags),
            'formList' => $formList,
            'activeIngredientList' => $activeIngredientList['list'],
            'selectedIngredients' => $activeIngredientList['selected'],
            'selectedIngredientsTwig' => $activeIngredientList['twigArray'],
        ]);
    }

    #[Route('/admin/user-permission/{permission_id}', name: 'user_permissions', requirements: ['permission_id' => '\d+'])]
    public function userPermissionsCrud(Request $request, $permission_id = 0): Response
    {
        $userPermission = $this->em->getRepository(UserPermissions::class)->find($request->get('permission_id'));

        if($userPermission == null){

            $userPermission = new UserPermissions();
        }

        return $this->render('Admin/user_permissions.html.twig',[
            'permission' => $userPermission,
        ]);
    }

    #[Route('/admin/categories/{page_id}', name: 'categories_list')]
    public function categoriesList(Request $request): Response
    {
        $categories = $this->em->getRepository(Categories1::class)->findList();
        $results = $this->page_manager->paginate($categories[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/categories/');

        return $this->render('Admin/categories_list.html.twig',[
            'categories' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/categories/get-parent/{categoryId}', name: 'category_get_parent')]
    public function categoryGetParent(Request $request): Response
    {
        $categoryId = $request->get('categoryId');
        $category = $this->em->getRepository(Categories::class)->find($categoryId);

        if($category->getParent() != null) {

            $parent = $this->em->getRepository(Categories::class)->findParent($category->getParent()->getId());
            $parentName = $parent['category'];

        } elseif($category->getIsRoot() == 1){

            $parentName = 'Root';

        } else {

            $parentName = '';
        }

        return new Response($parentName);
    }

    #[Route('/admin/category/{categoryId}', name: 'categories', requirements: ['categoryId' => '\d+'])]
    public function categoriesCrud(Request $request, $categoryId = 0): Response
    {
        $categoryId = (int) $request->get('categoryId') ?? 0;
        $category = $this->em->getRepository(Categories1::class)->find($categoryId);
        $selectedTags = [];

        if($category != null){

            // Selected First Level
            $tagIds = $category->getTags();

            if(count($tagIds) > 0){

                $selectedTags = $this->em->getRepository(Tags::class)->findByArray($tagIds);
            }

            for($i = 0; $i < count($category->getCategories2()); $i++){

                if(count($category->getCategories2()[$i]->getTags()) > 0) {

                    $tagsArray = [];
                    $tags = $category->getCategories2()[$i]->getTags();

                    foreach ($tags as $tag) {

                        $tagRepo = $this->em->getRepository(Tags::class)->find($tag);

                        $tagsArray[$tagRepo->getName()] = $tag;
                    }

                    $category->getCategories2()[$i]->setTagsArray($tagsArray);
                }
            }
        }

        if($category == null){

            $category = new Categories1();
        }

        // Level One Tag dropdown
        $tagList = $this->categoryTagDropdownList('tag', $category->getTags(), 1);

        $array = '';
        $arr = '[';

        foreach($category->getTags() as $tag){

            $array .= $tag .',';
        }

        $arr .= trim($array,',') . ']';

        // Level Two Tag Dropdown
        $tagList2 = $this->categoryTagDropdownList('tag', [], 2);

        // Level Three Tag Dropdown
        $tagList3 = $this->categoryTagDropdownList('tag', [], 3);

        return $this->render('Admin/categories.html.twig',[
            'category' => $category,
            'category_id' => $categoryId,
            'tagList' => $tagList,
            'selectedTags' => $selectedTags,
            'tagList2' => $tagList2,
            'tagList3' => $tagList3,
            'arr' => $arr,
        ]);
    }

    #[Route('/admin/clinics/{page_id}', name: 'clinics_list')]
    public function clinicsList(Request $request): Response
    {
        $isApproved = $request->request->get('is-approved') ?? 0;
        $clinics = $this->em->getRepository(Clinics::class)->adminFindAll($isApproved);
        $results = $this->page_manager->paginate($clinics[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/clinics/');
        $isStatusChange = $request->request->get('is-status-change') ?? 0;
        $response = '';

        if($isStatusChange == 0)
        {
            return $this->render('Admin/clinics_list.html.twig',[
                'clinics' => $results,
                'pagination' => $pagination
            ]);
        }

        $i = 0;

        foreach($results as $clinic)
        {
            $i++;
            $status = '';
            $border = 'border-bottom-dashed';

            if($i == count($results))
            {
                $border = 'border-bottom';
            }

            if($clinic->getIsApproved() == 0)
            {
                $status = 'Awaiting Approval';
            }
            elseif($clinic->getIsApproved() == 1)
            {
                $status = 'Approved';
            }
            elseif($clinic->getIsApproved() == 2)
            {
                $status = 'Declined';
            }

            $response .= '
            <div class="col-12">
                <div class="row py-3 '. $border .'" id="row_'. $clinic->getId() .'">
                    <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                        #ID
                    </div>
                    <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $clinic->getId() .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate"> 
                        Clinic Name
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $this->encryptor->decrypt($clinic->getClinicName()) .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Email
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $this->encryptor->decrypt($clinic->getEmail()) .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Telephone
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $this->encryptor->decrypt($clinic->getTelephone()) .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Status
                    </div>
                    <div class="col-8 col-md-1 text-truncate">
                        '. $status .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Modified
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $clinic->getModified()->format('Y-m-d H:i:s') .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Created
                    </div>
                    <div class="col-8 col-md-1 text-truncate">
                        '. $clinic->getCreated()->format('Y-m-d') .'
                    </div>
                    <div class="col-12 col-md-1 mt-3 mt-md-0 text-truncate">
                        <a
                            href="'. $this->generateUrl('clinics', ['clinic_id' => $clinic->getId()]) .'"
                            class="float-end open-user-modal"
                        >
                            <i class="fa-solid fa-pen-to-square edit-icon"></i>
                        </a>
                    </div>
                </div>
            </div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/distributors/{page_id}', name: 'distributors_list')]
    public function ditributorsList(Request $request): Response
    {
        $isApproved = $request->request->get('is-approved') ?? 0;
        $distributors = $this->em->getRepository(Distributors::class)->adminFindAll($isApproved);
        $results = $this->page_manager->paginate($distributors[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/distributors/');
        $isStatusChange = $request->request->get('is-status-change') ?? 0;
        $response = '';

        if($isStatusChange == 0)
        {
            return $this->render('Admin/distributors_list.html.twig',[
                'distributors' => $results,
                'pagination' => $pagination
            ]);
        }

        $i = 0;

        foreach($results as $distributor)
        {
            $i++;
            $status = '';
            $border = 'border-bottom-dashed';

            if($i == count($results))
            {
                $border = 'border-bottom';
            }

            if($distributor->getIsApproved() == 0)
            {
                $status = 'Awaiting Approval';
            }
            elseif($distributor->getIsApproved() == 1)
            {
                $status = 'Approved';
            }
            elseif($distributor->getIsApproved() == 2)
            {
                $status = 'Declined';
            }

            $response .= '
            <div class="col-12">
                <div class="row py-3 '. $border .'" id="row_'. $distributor->getId() .'">
                    <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                        #ID
                    </div>
                    <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $distributor->getId() .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate"> 
                        Clinic Name
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $this->encryptor->decrypt($distributor->getDistributorName()) .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Email
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $this->encryptor->decrypt($distributor->getEmail()) .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Telephone
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $this->encryptor->decrypt($distributor->getTelephone()) .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Status
                    </div>
                    <div class="col-8 col-md-1 text-truncate">
                        '. $status .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Modified
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $distributor->getModified()->format('Y-m-d H:i:s') .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Created
                    </div>
                    <div class="col-8 col-md-1 text-truncate">
                        '. $distributor->getCreated()->format('Y-m-d') .'
                    </div>
                    <div class="col-12 col-md-1 mt-3 mt-md-0 text-truncate">
                        <a
                            href="'. $this->generateUrl('distributor_admin', ['distributorId' => $distributor->getId()]) .'"
                            class="float-end open-user-modal"
                        >
                            <i class="fa-solid fa-pen-to-square edit-icon"></i>
                        </a>
                    </div>
                </div>
            </div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/communication-methods/{page_id}', name: 'communication_methods_list')]
    public function communicationMethodsList(Request $request): Response
    {
        $communicationMethods = $this->em->getRepository(CommunicationMethods::class)->adminFindAll();
        $results = $this->page_manager->paginate($communicationMethods[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/communication-methods/');

        return $this->render('Admin/communication_methods_list.html.twig',[
            'communicationMethods' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/banners/{page_id}', name: 'banners_list')]
    public function bannersList(Request $request): Response
    {
        $banners = $this->em->getRepository(Banners::class)->adminFindAll();
        $results = $this->page_manager->paginate($banners[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/banners/');

        return $this->render('Admin/banners_list.html.twig',[
            'banners' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/manufacturers/{page_id}', name: 'manufacturers_list')]
    public function manufacturersList(Request $request): Response
    {
        $manufacturers = $this->em->getRepository(Manufacturers::class)->adminFindAll();
        $results = $this->page_manager->paginate($manufacturers[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/manufacturers/');

        return $this->render('Admin/manufacturers_list.html.twig',[
            'manufacturers' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/tags/{page_id}', name: 'tags_list')]
    public function tagsList(Request $request): Response
    {
        $tags = $this->em->getRepository(Tags::class)->adminFindAll();
        $results = $this->page_manager->paginate($tags[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/tags/');

        return $this->render('Admin/tags_list.html.twig',[
            'tags' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/reviews/{page_id}', name: 'reviews_list')]
    public function ReviewsList(Request $request): Response
    {
        $isApproved = $request->request->get('is-approved') ?? 0;
        $reviews = $this->em->getRepository(ProductReviews::class)->adminFindByApproval($isApproved);
        $results = $this->page_manager->paginate($reviews[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/reviews/');
        $isStatusChange = $request->request->get('is-status-change') ?? 0;
        $response = '';

        if($isStatusChange == 0)
        {
            return $this->render('Admin/reviews_list.html.twig',[
                'reviews' => $results,
                'pagination' => $pagination
            ]);
        }

        foreach($results as $review)
        {
            $firstName = $this->encryptor->decrypt($review->getClinicUser()->getFirstName());
            $lastName = $this->encryptor->decrypt($review->getClinicUser()->getLastName());
            $response .= '
            <div class="col-12">
                <div class="row py-3 border-bottom-dashed" id="row_'. $review->getId() .'">
                    <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                        #ID
                    </div>
                    <div class="col-8 col-md-1 ps-4 text-truncate">
                        #'. $review->getId() .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Product
                    </div>
                    <div class="col-8 col-md-4 text-truncate">
                        '. $review->getProduct()->getName() .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Reviewed By
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $firstName .' '. $lastName .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Modified
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $review->getModified()->format('Y-m-d vH:i:s') .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Created
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $review->getCreated()->format('Y-m-d') .'
                    </div>
                    <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                        <a
                            href="'. $this->getParameter('app.base_url') . '/admin/review/'. $review->getId() .'"
                            class="float-end open-review-modal"
                        >
                            <i class="fa-solid fa-pen-to-square edit-icon"></i>
                        </a>
                    </div>
                </div>
            </div>';
        }

        return new JsonResponse($response);

    }

    #[Route('/admin/comments/{page_id}', name: 'comments_list')]
    public function CommentsList(Request $request): Response
    {
        $isApproved = $request->request->get('is-approved') ?? 0;
        $comments = $this->em->getRepository(ProductReviewComments::class)->adminFindByApproval($isApproved);
        $results = $this->page_manager->paginate($comments[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/comments/');
        $isStatusChange = $request->request->get('is-status-change') ?? 0;
        $response = '';

        if($isStatusChange == 0)
        {
            return $this->render('Admin/comments_list.html.twig',[
                'comments' => $results,
                'pagination' => $pagination
            ]);
        }

        foreach($results as $comment)
        {
            $firstName = $this->encryptor->decrypt($comment->getClinicUser()->getFirstName());
            $lastName = $this->encryptor->decrypt($comment->getClinicUser()->getLastName());
            $response .= '
            <div class="col-12">
                <div class="row py-3 border-bottom-dashed" id="row_'. $comment->getId() .'">
                    <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                        #ID
                    </div>
                    <div class="col-8 col-md-1 ps-4 text-truncate">
                        #'. $comment->getId() .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Product
                    </div>
                    <div class="col-8 col-md-5 text-truncate">
                        '. $comment->getReview()->getProduct()->getName() .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Reviewed By
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $firstName .' '. $lastName .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Modified
                    </div>
                    <div class="col-8 col-md-2 text-truncate">
                        '. $comment->getModified()->format('Y-m-d vH:i:s') .'
                    </div>
                    <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                        Created
                    </div>
                    <div class="col-8 col-md-1 text-truncate">
                        '. $comment->getCreated()->format('Y-m-d') .'
                    </div>
                    <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                        <a
                            href="'. $this->getParameter('app.base_url') . '/admin/comment/'. $comment->getId() .'"
                            class="float-end open-review-modal"
                        >
                            <i class="fa-solid fa-pen-to-square edit-icon"></i>
                        </a>
                    </div>
                </div>
            </div>';
        }

        return new JsonResponse($response);

    }

    #[Route('/admin/active-ingredients/{page_id}', name: 'active_ingredients_list')]
    public function activeIngredientsList(Request $request): Response
    {
        $activeIngredients = $this->em->getRepository(ActiveIngredients::class)->adminFindAll();
        $results = $this->page_manager->paginate($activeIngredients[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/active-ingredients/');

        return $this->render('Admin/active_ingredients_list.html.twig',[
            'activeIngredients' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/apis/{page_id}', name: 'api_list')]
    public function apiList(Request $request): Response
    {
        $api = $this->em->getRepository(Api::class)->adminFindAll();
        $results = $this->page_manager->paginate($api[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/apis/');

        return $this->render('Admin/api_list.html.twig',[
            'apis' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/restricted-domains/{page_id}', name: 'restricted_domains_list')]
    public function restrictedDomainsList(Request $request): Response
    {
        $restrictedDomains = $this->em->getRepository(RestrictedDomains::class)->adminFindAll();
        $results = $this->page_manager->paginate($restrictedDomains[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/restricted-domains/');

        return $this->render('Admin/restricted_domains_list.html.twig',[
            'domains' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/articles/{page_id}', name: 'articles_list')]
    public function articlesList(Request $request): Response
    {
        $articles = $this->em->getRepository(Articles::class)->adminFindAll();
        $results = $this->page_manager->paginate($articles[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/articles/');

        return $this->render('Admin/articles_list.html.twig',[
            'articles' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/users/{page_id}', name: 'users_list')]
    public function usersList(Request $request): Response
    {
        $users = $this->em->getRepository(User::class)->adminFindAll();
        $results = $this->page_manager->paginate($users[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/users/');

        return $this->render('Admin/users_list.html.twig',[
            'users' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/species/{page_id}', name: 'species_list')]
    public function speciesList(Request $request): Response
    {
        $species = $this->em->getRepository(Species::class)->adminFindAll();
        $results = $this->page_manager->paginate($species[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/species/');

        return $this->render('Admin/species_list.html.twig',[
            'species' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/countries/{page_id}', name: 'countries_list')]
    public function countriesList(Request $request): Response
    {
        $countries = $this->em->getRepository(Countries::class)->adminFindAll();
        $results = $this->page_manager->paginate($countries[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/countries/');

        return $this->render('Admin/countries_list.html.twig',[
            'countries' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/countries-search', name: 'countries_search')]
    public function countriesSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $country = $this->em->getRepository(Countries::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($country[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/countries/');
        $html = '';

        foreach($results as $result){

            $isActive = $result->getIsActive() ?? 0;

            $html .= '
            <div class="row py-3 border-bottom-dashed" id="row_'. $result->getId() .'">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Country
                </div>
                <div class="col-8 col-md-4 text-truncate">
                    '. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Currency
                </div>
                <div class="col-8 col-md-1 text-truncate">
                    '. $result->getCurrency() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Active
                </div>
                <div class="col-8 col-md-1 text-truncate">
                    '. $isActive .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                    <a
                        href="'. $this->generateUrl('country', ['countryId' => $result->getId()]) .'"
                        class="float-start float-md-end open-country-modal ms-5 ms-md-0"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/product-search', name: 'product_search')]
    public function productSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $products = $this->em->getRepository(Products::class)->findBySearchAdmin($searchString);
        $results = $this->page_manager->paginate($products[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/products/');
        $html = '';

        foreach($results as $result){

            $checked = '';
            $category1 = '';
            $category2 = '';

            if($result->getIsActive() == 1){

                $checked = 'checked';
            }

            if($result->getCategory() != null){

                $category1 = $result->getCategory()->getName();
            }

            if($result->getCategory2() != null){

                $category2 = $result->getCategory2()->getName();
            }

            $html .= '
            <div class="row py-3 border-bottom-dashed" id="row_{{ product.id }}">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Published
                </div>
                <div class="col-8 col-md-1 text-truncate">
                    <div class="form-check form-switch">
                        <input
                            name="is_published"
                            class="form-check-input is-published"
                            type="checkbox"
                            role="switch"
                            data-product-id="{{ product.id }}"
                            value="{{ product.isPublished }}"
                            '. $checked .'
                        >
                    </div>
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Name
                </div>
                <div class="col-8 col-md-3 text-truncate">
                    '. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Category
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $category1 .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Sub Category
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $category2 .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Stock
                </div>
                <div class="col-8 col-md-1 text-truncate">
                    '. $result->getStockCount() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Price
                </div>
                <div class="col-8 col-md-1 text-truncate">
                    '. $result->getUnitPrice() .'
                </div>
                <div class="col-12 col-md-1 mt-3 mt-md-0 text-truncate">
                    <a
                        href="'. $this->generateUrl('products',['productId' => $result->getId()]) .'"
                        class="float-start float-md-end ms-5 ms-md-0 open-user-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-end open-delete-user-modal"
                        data-bs-toggle="modal"
                        data-product-id="{{ product.id }}"
                        data-bs-target="#modal_delete_product"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/active-ingredient-search', name: 'active_ingredient_search')]
    public function activeIngredientSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $activeIngredients = $this->em->getRepository(ActiveIngredients::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($activeIngredients[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/active-ingredients/');
        $html = '';

        foreach($results as $result){

            $html .= '
            <div class="row py-3 border-bottom-dashed" id="row_{{ activeIngredient.id }}">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Clinic Name
                </div>
                <div class="col-8 col-md-6 text-truncate">
                    '. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                    <a
                        href="'. $this->generateUrl('active_ingredients', ['ingredientId' => $result->getId()]) .'"
                        class="float-end open-tag-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0 open-delete-ingredient-modal"
                        data-bs-toggle="modal"
                        data-ingredient-id="{{ activeIngredient.id }}"
                        data-bs-target="#modal_delete_ingredient"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/restricted-domain-search', name: 'restricted_domain_search')]
    public function restrictedDomainSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $restrictedDomains = $this->em->getRepository(RestrictedDomains::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($restrictedDomains[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/active-ingredients/');
        $html = '';

        foreach($results as $result){

            $html .= '
            <div class="row py-3 border-bottom-dashed" id="row_{{ activeIngredient.id }}">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Domain Name
                </div>
                <div class="col-8 col-md-6 text-truncate">
                    '. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                    <a
                        href="'. $this->generateUrl('restricted_domain', ['domainId' => $result->getId()]) .'"
                        class="float-end open-tag-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0 open-delete-domain-modal"
                        data-bs-toggle="modal"
                        data-domain-id="{{ activeIngredient.id }}"
                        data-bs-target="#modal_delete_domain"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/clinic-search', name: 'clinic_search')]
    public function clinicSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $clinics = $this->em->getRepository(Clinics::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($clinics[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/clinics/');
        $html = '';
        $i = 0;

        foreach($results as $result){

            $border = 'border-bottom-dashed py-3';
            $i++;
            if(count($results) == $i) {

                $border = 'pt-3';
            }

            $html .= '
            <div class="row '. $border .'" id="row_'. $result->getId() .'">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Clinic Name
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getClinicName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Email
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getEmail() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Telephone
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getTelephone() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 mt-3 mt-md-0 text-truncate">
                    <a
                        href="'. $this->generateUrl('clinics', ['clinic_id' => $result->getId()]) .'"
                        class="float-end open-user-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0"
                        data-bs-toggle="modal"
                        data-clinic-id="'. $result->getId() .'"
                        data-bs-target="#modal_delete_clinic"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/distributor-search', name: 'distributor_search')]
    public function distributorSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $distributors = $this->em->getRepository(Distributors::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($distributors[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/distributors/');
        $html = '';
        $i = 0;

        foreach($results as $result){

            $border = 'border-bottom-dashed py-3';
            $i++;
            if(count($results) == $i) {

                $border = 'pt-3';
            }

            $html .= '
            <div class="row '. $border .'" id="row_'. $result->getId() .'">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Distributor Name
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getDistributorName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Email
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getEmail() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Telephone
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getTelephone() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 mt-3 mt-md-0 text-truncate">
                    <a
                        href="'. $this->generateUrl('distributor_admin', ['distributorId' => $result->getId()]) .'"
                        class="float-end open-user-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0"
                        data-bs-toggle="modal"
                        data-clinic-id="'. $result->getId() .'"
                        data-bs-target="#modal_delete_clinic"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/manufacturer-search', name: 'manufacturer_search')]
    public function manufacturerSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $manufacturers = $this->em->getRepository(Manufacturers::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($manufacturers[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/manufacturers/');
        $html = '';
        $i = 0;

        foreach($results as $result){

            $html .= '
            <div class="row py-3 border-bottom-dashed" id="row_'. $result->getId() .'">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Clinic Name
                </div>
                <div class="col-8 col-md-6 text-truncate">
                    '. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                    <a
                        href="'. $this->generateUrl('manufacturers', ['manufacturerId' => $result->getId()]) .'"
                        class="float-end open-user-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0 open-delete-manufacturer-modal"
                        data-bs-toggle="modal"
                        data-manufacturer-id="'. $result->getId() .'"
                        data-bs-target="#modal_delete_manufacturer"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/banner-search', name: 'banner_search')]
    public function bannerSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $banners = $this->em->getRepository(Banners::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($banners[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/banners/');
        $html = '';
        $i = 0;

        foreach($results as $result){

            $i++;
            $class = 'border-bottom-dashed py-3';
            $isPublished = '';

            if($i == count($results)){

                $class = 'pt-3';
            }

            if($result->getIsPublished() == 1){

                $isPublished = 'checked';
            }

            $html .= '
            <div class="row '. $class .'" id="row_'. $result->getId() .'">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    '. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Published
                </div>
                <div class="col-8 col-md-1 text-truncate">
                    <div class="form-check form-switch">
                        <input
                            name="is-published"
                            class="form-check-input is-published"
                            type="checkbox"
                            role="switch"
                            data-banner-id="'. $result->getId() .'"
                            value="{{ banner.isPublished }}"
                            '. $isPublished .'
                        >
                    </div>
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Name
                </div>
                <div class="col-8 col-md-3 text-truncate">
                    '. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Alt
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getAlt() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    '. $result->getModified()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 mt-3 mt-md-0 text-truncate">
                    <a
                        href="'. $this->generateUrl('banners', ['bannerId' => $result->getId()]) .'"
                        class="float-end"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0 open-delete-banner-modal"
                        data-bs-toggle="modal"
                        data-banner-id="'. $result->getId() .'"
                        data-bs-target="#modal_delete_banner"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/tag-search', name: 'tag_search')]
    public function tagSearch(Request $request): Response
    {
        $searchString = $request->request->get('search-string');
        $tags = $this->em->getRepository(Tags::class)->findBySearch($searchString);
        $results = $this->page_manager->paginate($tags[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/tags/');
        $html = '';

        foreach($results as $result){

            $html .= '
            <div class="row py-3 border-bottom-dashed" id="row_{{ tag.id }}">
                <div class="col-4 fw-bold ps-4 d-block d-md-none text-truncate">
                    #ID
                </div>
                <div class="col-8 col-md-1 ps-4 text-truncate">
                    #'. $result->getId() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Clinic Name
                </div>
                <div class="col-8 col-md-6 text-truncate">
                    #'. $result->getName() .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Modified
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    #'. $result->getModified()->format('Y-m-d H:i:s') .'
                </div>
                <div class="col-4 ps-4 d-block d-md-none fw-bold text-truncate">
                    Created
                </div>
                <div class="col-8 col-md-2 text-truncate">
                    #'. $result->getCreated()->format('Y-m-d') .'
                </div>
                <div class="col-12 col-md-1 text-truncate mt-3 mt-md-0">
                    <a
                        href="'. $this->generateUrl('tags', ['tagId' => $result->getId()]) .'"
                        class="float-end open-tag-modal"
                    >
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a
                        href=""
                        class="delete-icon float-start float-sm-end ms-5 ms-md-0 open-delete-tag-modal"
                        data-bs-toggle="modal"
                        data-tag-id="'. $result->getId() .'"
                        data-bs-target="#modal_delete_tag"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        $response = [
            'html' => $html,
            'pagination' => $pagination,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/user-permissions/{page_id}', name: 'user_permissions_list')]
    public function userPermissionsList(Request $request): Response
    {
        $userPermissions = $this->em->getRepository(UserPermissions::class)->adminFindAll();
        $results = $this->page_manager->paginate($userPermissions[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->get('page_id'), $results, '/admin/user-permissions/');

        return $this->render('Admin/user_permissions_list.html.twig',[
            'permissions' => $results,
            'pagination' => $pagination
        ]);
    }

    #[Route('/admin/clinic/{clinic_id}', name: 'clinics', requirements: ['clinic_id' => '\d+'])]
    public function clinicsCrud(Request $request, $clinic_id = 0): Response
    {
        $clinicId = $request->get('clinic_id') ?? 0;
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $clinicUsers = $this->em->getRepository(ClinicUsers::class)->findBy([
            'clinic' => $clinicId
        ]);
        $userPermissions = $this->em->getRepository(UserPermissions::class)->findBy([
            'isClinic' => 1
        ]);

        if($clinic == null){

            $clinic = new Clinics();
        }

        return $this->render('Admin/clinics.html.twig',[
            'clinic' => $clinic,
            'clinicUsers' => $clinicUsers,
            'userPermissions' => $userPermissions
        ]);
    }

    #[Route('/admin/distributor/{distributorId}', name: 'distributor_admin', requirements: ['distributorId' => '\d+'])]
    public function distributorsCrud(Request $request, $distributorId = 0): Response
    {
        $distributorId = $request->get('distributorId') ?? 0;
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $distributorUsers = $this->em->getRepository(DistributorUsers::class)->findBy([
            'distributor' => $distributorId
        ]);
        $userPermissions = $this->em->getRepository(UserPermissions::class)->findBy([
            'isDistributor' => 1
        ]);
        $api = $this->em->getRepository(Api::class)->findAll();

        if($distributor == null){

            $distributor = new Distributors();
        }

        return $this->render('Admin/distributors.html.twig',[
            'distributor' => $distributor,
            'distributorUsers' => $distributorUsers,
            'userPermissions' => $userPermissions,
            'api' => $api,
        ]);
    }

    #[Route('/admin/communication-method/{communicationMethodId}', name: 'communication_methods', requirements: ['communicationMethodId' => '\d+'])]
    public function communicationMethodsCrud(Request $request, $communicationMethodId = 0): Response
    {
        $communicationMethodId = $request->get('communicationMethodId') ?? 0;
        $communicationMethod = $this->em->getRepository(CommunicationMethods::class)->find($communicationMethodId);

        if($communicationMethod == null){

            $clinic = new CommunicationMethods();
        }

        return $this->render('Admin/communication_methods.html.twig',[
            'communicationMethod' => $communicationMethod,
        ]);
    }

    #[Route('/admin/banner/{bannerId}', name: 'banners', requirements: ['bannerId' => '\d+'])]
    public function bannersCrud(Request $request, $bannerId = 0): Response
    {
        $bannerId = $request->get('bannerId') ?? 0;
        $banner = $this->em->getRepository(Banners::class)->find($bannerId);
        $pages = $this->em->getRepository(Pages::class)->findAll();

        if($bannerId == null){

            $banner = new Banners();
        }

        return $this->render('Admin/banners.html.twig',[
            'banner' => $banner,
            'pages' => $pages,
        ]);
    }

    #[Route('/admin/manufacturer/{manufacturerId}', name: 'manufacturers', requirements: ['manufacturerId' => '\d+'])]
    public function manufacturersCrud(Request $request, $manufacturerId = 0): Response
    {
        $manufacturerId = $request->get('manufacturerId') ?? 0;
        $manufacturer = $this->em->getRepository(Manufacturers::class)->find($manufacturerId);

        if($manufacturer == null){

            $manufacturer = new Manufacturers();
        }

        return $this->render('Admin/manufacturers.html.twig',[
            'manufacturer' => $manufacturer,
        ]);
    }

    #[Route('/admin/tag/{tagId}', name: 'tags', requirements: ['tagId' => '\d+'])]
    public function tagCrud(Request $request, $tagId = 0): Response
    {
        $tagId = $request->get('tagId') ?? 0;
        $tag = $this->em->getRepository(Tags::class)->find($tagId);

        if($tag == null){

            $tag = new Tags();
        }

        return $this->render('Admin/tags.html.twig',[
            'tag' => $tag,
        ]);
    }

    #[Route('/admin/review/{reviewId}', name: 'reviews', requirements: ['reviewId' => '\d+'])]
    public function reviewCrud(Request $request, $reviewId = 0): Response
    {
        $reviewId = $request->get('reviewId') ?? 0;
        $review = $this->em->getRepository(ProductReviews::class)->find($reviewId);

        if($review == null){

            $review = new ProductReviews();
        }

        return $this->render('Admin/reviews.html.twig',[
            'review' => $review,
        ]);
    }

    #[Route('/admin/comment/{commentId}', name: 'comments', requirements: ['commentId' => '\d+'])]
    public function commentCrud(Request $request, $commentId = 0): Response
    {
        $commentId = $request->get('commentId') ?? 0;
        $comment = $this->em->getRepository(ProductReviewComments::class)->find($commentId);

        if($comment == null){

            $comment = new ProductReviewComments();
        }

        return $this->render('Admin/comments.html.twig',[
            'comment' => $comment,
        ]);
    }

    #[Route('/admin/active-ingredient/{ingredientId}', name: 'active_ingredients', requirements: ['ingredientId' => '\d+'])]
    public function activIngredientCrud(Request $request, $ingredientId = 0): Response
    {
        $ingredientId = $request->get('ingredientId') ?? 0;
        $ingredient = $this->em->getRepository(ActiveIngredients::class)->find($ingredientId);

        if($ingredient == null){

            $ingredient = new ActiveIngredients();
        }

        return $this->render('Admin/active_ingredients.html.twig',[
            'ingredient' => $ingredient,
        ]);
    }

    #[Route('/admin/api/{apiId}', name: 'apis', requirements: ['apiId' => '\d+'])]
    public function apiCrud(Request $request, $apiId = 0): Response
    {
        $apiId = $request->get('apiId') ?? 0;
        $api = $this->em->getRepository(Api::class)->find($apiId);
        $distributors = $this->em->getRepository(Distributors::class)->findAll();

        if($api == null){

            $api = new Api();
        }

        return $this->render('Admin/api.html.twig',[
            'api' => $api,
            'distributors' => $distributors,
        ]);
    }

    #[Route('/admin/restricted-domain/{domainId}', name: 'restricted_domain', requirements: ['domainId' => '\d+'])]
    public function domainCrud(Request $request, $domainId = 0): Response
    {
        $domainId = $request->get('domainId') ?? 0;
        $domain = $this->em->getRepository(RestrictedDomains::class)->find($domainId);

        if($domain == null){

            $domain = new RestrictedDomains();
        }

        return $this->render('Admin/restricted_domain.html.twig',[
            'domain' => $domain,
        ]);
    }

    #[Route('/admin/article/{articleId}', name: 'articles', requirements: ['articleId' => '\d+'])]
    public function articlesCrud(Request $request, $articleId = 0): Response
    {
        $articleId = $request->get('articleId') ?? 0;
        $article = $this->em->getRepository(Articles::class)->find($articleId);
        $pages = $this->em->getRepository(Pages::class)->findAll();

        if($article == null){

            $article = new Articles();
        }

        return $this->render('Admin/articles.html.twig',[
            'article' => $article,
            'pages' => $pages,
        ]);
    }

    #[Route('/admin/specie/{speciesId}', name: 'species', requirements: ['speciesId' => '\d+'])]
    public function speciesCrud(Request $request, $speciesId = 0): Response
    {
        $speciesId = $request->get('speciesId') ?? 0;
        $species = $this->em->getRepository(Species::class)->find($speciesId);

        if($species == null){

            $species = new Species();
        }

        return $this->render('Admin/species.html.twig',[
            'species' => $species,
        ]);
    }

    #[Route('/admin/country/{countryId}', name: 'country', requirements: ['countryId' => '\d+'])]
    public function countryCrud(Request $request, $countryId = 0): Response
    {
        $countryId = $request->get('countryId') ?? 0;
        $country = $this->em->getRepository(Countries::class)->find($countryId);

        if($country == null){

            $country = new Countries();
        }

        return $this->render('Admin/countries.html.twig',[
            'country' => $country,
        ]);
    }

    #[Route('/admin/user/{userId}', name: 'users', requirements: ['userId' => '\d+'])]
    public function usersCrud(Request $request, $userId = 0): Response
    {
        $usersId = $request->get('userId') ?? 0;
        $users = $this->em->getRepository(User::class)->find($usersId);

        if($users == null){

            $users = new User();
        }

        $rolesList = $this->getRolesDropdownList($users->getId());

        $array = '';
        $arr = '[';

        foreach($users->getRoles() as $role){

            $array .= '"'. $role .'",';
        }

        $arr .= trim($array,',') . ']';

        return $this->render('Admin/users.html.twig',[
            'users' => $users,
            'rolesList' => $rolesList,
            'arr' => $arr,
        ]);
    }

    #[Route('/admin/side-bar/{return}', name: 'side-bar')]
    public function sideBar(Request $request): Response
    {
        $userId = $this->getUser()->getId();
        $user = $this->em->getRepository(User::class)->find($userId);
        $roles = $this->getUser()->getRoles();

        if($request->get('return') == 'sideBar') {

            $response = '
            <div class="col-2 col-sm-2 col-md-3 col-xl-2 px-sm-2 px-md-0 bg-light border-right distributor-left-col overflow-scroll">
                <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white min-vh-100 text-truncate">
                    <ul 
                        class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start w-100 mt-4" 
                        id="menu"
                    >';

                if (in_array('ROLE_PRODUCT', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('products_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="nav-item py-2 w-100 admin-nav ' . $disabled . '">
                    <' . $tag . ' ' . $href . '" class="align-middle px-0 nav-icon text-truncate ' . $textPrimary . '">
                        <i class="menu-icon fa-regular fa-house fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Home</span>
                    </' . $tag . '>
                </li>';

            if (in_array('ROLE_ACTIVE_INGREDIENT', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('active_ingredients_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . '
                    ' . $href . '
                    class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                >
                    <i class="menu-icon fa-fw fa-regular fa-pills fa-fw"></i>
                    <span class="ms-1 d-none d-sm-inline">Active Ingreients</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_ARTICLE', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('articles_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . '
                    ' . $href . '
                    class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                >
                    <i class="menu-icon fa-fw fas fa-light fa-newspaper"></i>
                    <span class="ms-1 d-none d-sm-inline">Articles</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_API', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('api_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . '
                    ' . $href . '
                    class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                >
                    <i class="menu-icon fa-fw fa-regular fa-network-wired fa-fw"></i>
                    <span class="ms-1 d-none d-sm-inline">API</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_BANNER', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('banners_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . '
                    ' . $href . '
                    class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                >
                    <i class="fa-regular fa-images menu-icon fa-fw"></i>
                    <span class="ms-1 d-none d-sm-inline">Banners</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_CATEGORY', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('categories_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }


            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . '
                    ' . $href . '
                    class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                >
                    <i class="menu-icon fa-fw fas fa-bars fa-fw"></i>
                    <span class="ms-1 d-none d-sm-inline">Categories</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_CLINIC', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('clinics_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . '
                    ' . $href . '
                    class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                >
                    <i class="menu-icon fa-fw fa-regular fa-clinic-medical fa-fw"></i>
                    <span class="ms-1 d-none d-sm-inline">Clinics</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_COMMUNICATION_METHOD', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('communication_methods_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
            <li class="w-100 admin-nav ' . $disabled . '">
                <' . $tag . ' class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                   ' . $href . '
                >
                    <i class="menu-icon fa-fw fa-regular fa-broadcast-tower fa-fw"></i>
                    <span class="ms-1 d-none d-sm-inline">Communication Methods</span>
                </' . $tag . '>
            </li>';

            if (in_array('ROLE_COUNTRY', $roles)) {

                $tag = 'a';
                $href = 'href="' . $this->generateUrl('countries_list', ['page_id' => 1]) . '"';
                $disabled = '';
                $textPrimary = 'text-primary';

            } else {

                $tag = 'span';
                $href = '';
                $disabled = 'text-disabled admin-nav-disabled';
                $textPrimary = '';
            }

            $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . ' class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                       ' . $href . '
                    >
                        <i class="menu-icon fa-fw fas fa-solid fa-earth-africa fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Countries</span>
                    </' . $tag . '>
                </li>';

                if (in_array('ROLE_DISTRIBUTOR', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('distributors_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . ' class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                       ' . $href . '
                    >
                        <i class="menu-icon fa-fw fa-regular fa-truck-field fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Distributors</span>
                    </' . $tag . '>
                </li>';

                if (in_array('ROLE_MANUFACTURER', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('manufacturers_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . '
                        ' . $href . '
                        class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                    >
                        <i class="menu-icon fa-regular fa-industry-windows fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Manufacturers</span>
                    </' . $tag . '>
                </li>';

                if (in_array('ROLE_PRODUCT', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('products_list', ['page_id' => 1]) . '"';
                    $hrefReview = 'href="' . $this->generateUrl('reviews_list', ['page_id' => 1]) . '"';
                    $hrefComment = 'href="' . $this->generateUrl('comments_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $hrefReview = '';
                    $hrefComment = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . '
                      ' . $href . '
                       class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                    >
                        <i class="menu-icon fa-fw fa-regular fa-boxes-stacked fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Products</span>
                    </' . $tag . '>
                </li>';

                $response .= '
                <li class="w-100">
                    <a
                        href="#submenu2"
                        data-bs-toggle="collapse"
                        class="px-0 align-middle text-primary collapsed text-center text-sm-start nav-icon my-2 text-truncate admin-nav py-2"
                        aria-expanded="false"
                    >
                        <i class="fa-regular fa-cart-flatbed fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Product Feedback</span>
                    </a>
                    <ul class="nav flex-column collapse" id="submenu2" data-bs-parent="#menu" style="">
                        <li class="w-100 admin-nav ' . $disabled . '">
                            <' . $tag . '
                              ' . $hrefReview . '
                               class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                            >
                                <i class="menu-icon fa-fw fa-regular fa-comments fa-fw"></i>
                                <span class="ms-1 d-none d-sm-inline">Product Reviews</span>
                            </' . $tag . '>
                        </li>
                        <li class="w-100 admin-nav ' . $disabled . '">
                            <' . $tag . '
                              ' . $hrefComment . '
                               class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                            >
                                <i class="menu-icon fa-fw fa-regular fa-comments fa-fw"></i>
                                <span class="ms-1 d-none d-sm-inline">Review Comments</span>
                            </' . $tag . '>
                        </li>
                    </ul>
                </li>';

                if (in_array('ROLE_RESTRICTED_DOMAIN', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('restricted_domains_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                    <li class="w-100 admin-nav ' . $disabled . '">
                        <' . $tag . '
                          ' . $href . '
                           class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                        >
                            <i class="menu-icon fa-fw fa-regular fa-ban fa-fw"></i>
                            <span class="ms-1 d-none d-sm-inline">Restricted Domains</span>
                        </' . $tag . '>
                    </li>';

                if (in_array('ROLE_SPECIE', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('species_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . '
                        ' . $href . '
                        class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                    >
                        <i class="menu-icon fa-fw fa-regular fa-paw-simple fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Species</span>
                    </' . $tag . '>
                </li>';

                if (in_array('ROLE_TAG', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('tags_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . '
                        ' . $href . '
                        class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                    >
                        <i class="menu-icon fa-fw fa-regular fa-tags"></i>
                        <span class="ms-1 d-none d-sm-inline">Tags</span>
                    </' . $tag . '>
                </li>';

                if (in_array('ROLE_USER', $roles)) {

                    $tag = 'a';
                    $href = 'href="#"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                <li class="w-100 admin-nav ' . $disabled . '">
                    <' . $tag . '
                        href="' . $this->generateUrl('users_list', ['page_id' => 1]) . '"
                        class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                    >
                        <i class="menu-icon fa-fw fa-regular fa-user fa-fw"></i>
                        <span class="ms-1 d-none d-sm-inline">Users</span>
                    </' . $tag . '>
                </li>';

                if (in_array('ROLE_USER', $roles)) {

                    $tag = 'a';
                    $href = 'href="' . $this->generateUrl('user_permissions_list', ['page_id' => 1]) . '"';
                    $disabled = '';
                    $textPrimary = 'text-primary';

                } else {

                    $tag = 'span';
                    $href = '';
                    $disabled = 'text-disabled admin-nav-disabled';
                    $textPrimary = '';
                }

                $response .= '
                        <li class="w-100 admin-nav ' . $disabled . '">
                            <' . $tag . '
                                ' . $href . '
                                class="px-0 align-middle nav-icon my-2 text-truncate ' . $textPrimary . '"
                            >
                                <i class="menu-icon fa-fw fa-regular fa-user-lock"></i>
                                <span class="ms-1 d-none d-sm-inline">User Permissions</span>
                            </' . $tag . '>
                        </li>
                    </ul>
                </div>
            </div>';

        } else {

            $response = $this->encryptor->decrypt($user->getFirstName()) .' '. $this->encryptor->decrypt($user->getLastName());
        }

        return new Response($response);
    }

    #[Route('/admin/category/get-sub-categories', name: 'get_sub_categories')]
    public function categoryGetSubCategories(Request $request): Response
    {
        $category1Id = $request->request->get('category1_id');
        $category2Id = $request->request->get('category2_id');
        $categories2 = $this->em->getRepository(Categories2::class)->findBy([
            'category1' => $category1Id
        ]);
        $removeCategory2 = true;
        $removeCategory3 = true;
        $categories3List = 'Select a Grandchild Category';

        // Remove previously saved second level category
        if(count($categories2) > 0) {

            foreach($categories2 as $category2){

                if($category2->getId() == $category2Id){

                    $removeCategory2 = false;

                    // Third level category
                    $categories3 = $this->em->getRepository(Categories3::class)->find($category2->getId());

                    if($categories3 != null){

                        $removeCategory3 = false;

                    } else {

                        $categories3List = $this->individualDropdownList(
                            $categories3, 'category3', 'category3', 'getName'
                        );
                    }
                }
            }

            $categories2List = $this->individualDropdownList(
                $categories2, 'sub-category', 'sub_category', 'getName'
            );

        } else {

            $categories2List = $this->individualDropdownList(
                [], 'sub-category', 'sub_category', 'getName'
            );
        }

        $response = [
            'removeCategory2' => $removeCategory2,
            'categories2List' => $categories2List,
            'removeCategory3' => $removeCategory3,
            'categories3List' => $categories3List,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/category/get-categories3', name: 'get_categories3')]
    public function getCategories3(Request $request): Response
    {
        $category2Id = $request->request->get('category2_id');
        $categories3 = $this->em->getRepository(Categories3::class)->findBy([
            'category2' => $category2Id
        ]);
        $displayCategories3List = false;
        $categories3List = '';

        // Remove previously saved sub category
        if(count($categories3) > 0) {

            $categories3List = $this->individualDropdownList(
                $categories3, 'category3', 'category3', 'getName'
            );

            $displayCategories3List = true;
        }

        $response = [
            'categories3List' => $categories3List,
            'displayCategories3List' => $displayCategories3List,
        ];

        return new JsonResponse($response);
    }

    #[Route('/admin/product/delete-image', name: 'product_delete_image')]
    public function productDeleteImage(Request $request): Response
    {
        $imageId = $request->request->get('image_id');
        $image = $this->em->getRepository(ProductImages::class)->find($imageId);

        $filesystem = new Filesystem();

        $filesystem->remove(__DIR__ . '/../../public/images/products/'. $image->getImage());

        $this->em->remove($image);
        $this->em->flush();

        $response = '<b><i class="fas fa-check-circle"></i> Image Successfully Deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/admin/product/default-image', name: 'product_default_image')]
    public function productDefaultImage(Request $request): Response
    {
        $productId = $request->request->get('product_id');
        $imageId = $request->request->get('image_id');
        $images = '';

        if($productId > 0 && $imageId > 0){

            $productImage = $this->em->getRepository(ProductImages::class)->find($imageId);
            $images = $this->em->getRepository(ProductImages::class)->findBy([
                'product' => $productId,
            ]);

            foreach($images as $image){

                $image->setIsDefault(0);

                $this->em->persist($image);
            }

            $this->em->flush();

            $productImage->setIsDefault(1);

            $this->em->persist($productImage);
            $this->em->flush();
        }

        return new JsonResponse($images);
    }

    #[Route('/admin/pcategories2/delete', name: 'category_delete')]
    public function categoryDelete(Request $request): Response
    {
        $categoryId = $request->request->get('category-id');
        $level = $request->request->get('level');
        $response = 'false';

        if($level == 2){

            $category2 = $this->em->getRepository(Categories2::class)->find($categoryId);
            $products = $this->em->getRepository(Products::class)->findBy([
                'category2' => $categoryId,
            ]);

            // Set foreign keys to null
            if(count($products) > 0){

                foreach($products as $product){

                    $product->setCategory2(null);
                    $product->setCategory3(null);

                    $this->em->persist($product);
                }

                $this->em->flush();
            }

            // Delete child categories so there are no orphans
            $categories3 = $this->em->getRepository(Categories3::class)->findBy([
                'category2' => $categoryId,
            ]);

            foreach($categories3 as $category3){

                $this->em->remove($category3);
            }

            $this->em->flush();

            // Delete second level category
            $this->em->remove($category2);
            $this->em->flush();

            $response = '<b><i class="fas fa-check-circle"></i> Category Successfully Deleted!.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } elseif($level == 3){

            $category3 = $this->em->getRepository(Categories3::class)->find($categoryId);
            $products = $this->em->getRepository(Products::class)->findBy([
                'category3' => $categoryId,
            ]);

            // Set foreign keys to null
            if(count($products) > 0){

                foreach($products as $product){

                    $product->setCategory3(null);

                    $this->em->persist($product);
                }

                $this->em->flush();
            }

            // Delete third level category
            $this->em->remove($category3);
            $this->em->flush();

            $response = '<b><i class="fas fa-check-circle"></i> Category Successfully Deleted!.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/product/duplicate', name: 'duplicate_product')]
    public function duplicateProduct(Request $request): Response
    {
        $productId = $request->request->get('product-id');
        $response = 0;

        if($productId > 0 || $productId != null)
        {
            $product = $this->em->getRepository(Products::class)->find($productId);

            // Duplicate record
            $duplicate = clone $product;

            $this->em->persist($duplicate);
            $this->em->flush();

            // Get Product Images
            $productId = $duplicate->getId();
            $productImages = $product->getProductImages();

            foreach($productImages as $productImage)
            {
                $productImageNew = new ProductImages();

                $productImageNew->setProduct($duplicate);
                $productImageNew->setImage($productImage->getImage());
                $productImageNew->setIsDefault($productImage->getIsDefault());
                $productImageNew->setFileType($productImage->getFileType());

                $this->em->persist($productImageNew);
            }

            $this->em->flush();

            $response = $productId;
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/error', name: 'admin_error_500')]
    public function admin500ErrorAction(Request $request): Response
    {
        $username = $this->getUser();
        $id = '';

        if($username != null) {

            $id = $this->getUser()->getId();
        }

        return $this->render('bundles/TwigBundle/Exception/error500.html.twig', [
            'type' => 'admin',
            'id' => $id,
        ]);
    }

    private function categoryTagDropdownList($label, $selectedTags, $level): string
    {
        $tags = $this->em->getRepository(Tags::class)->findAll();

        $list = '
        <div class="px-3 row">
            <div class="bg-dropdown px-0 col-12">';

        // Loop through selected dropdown options
        foreach($tags as $tag){

            // Get related records
            $select = $label .'-select';

            if(is_array($selectedTags) && in_array($tag->getId(), $selectedTags)) {

                $select = '';
            }


            $list .= '
            <div class="row">
                <div 
                    class="col-12 edit-'. $label .' d-table"
                    data-'. $label .'-id="'. $tag->getId() .'"
                    
                >
                    <div 
                        class="row '. $label .'-row d-table-row" data-'. $label .'-id="'. $tag->getId() .'">
                        <div 
                            class="col-10 py-2 d-table-cell align-middle '. $label .'-select-row '. $select .'"
                            data-'. $label .'-id="'. $tag->getId() .'"
                            data-'. $label .'="'. $tag->getName() .'"
                            data-level="'. $level .'"
                            data-category-id="0"
                            tabindex="0"
                        >
                                <span class="'. $label .'_string">
                                    '. $tag->getName() .'
                                </span>
                                <input 
                                    type="text" 
                                    class="form-control form-control-sm '. $label .'-form-ctrl"
                                    value="'. $tag->getName() .'"
                                    data-'. $label .'-field-'. $tag->getId() .'
                                    id="'. $label .'_edit_field_'. $tag->getId() .'"
                                    style="display: none"
                                >
                                <div class="hidden_msg" id="error_'. $label .'_'. $tag->getId() .'">
                                    Required Field
                                </div>
                            </div>
                            <div class="col-2 py-2 d-table-cell align-middle">
                                <a 
                                    href="" 
                                    class="float-end '. $label .'-edit-icon me-3" 
                                    id="'. $label .'_edit_'. $tag->getId() .'"
                                    data-'. $label .'-edit-id="'. $tag->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-pen-to-square"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end '. $label .'-remove-icon me-3" 
                                    id="'. $label .'_remove_'. $tag->getId() .'"
                                    data-'. $label .'-id="'. $tag->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-circle-minus"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end '. $label .'-cancel-icon me-3" 
                                    id="'. $label .'_cancel_'. $tag->getId() .'"
                                    data-'. $label .'-cancel-id="'. $tag->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-xmark"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end '. $label .'-save-icon me-3" 
                                    id="'. $label .'_save_'. $tag->getId() .'"
                                    data-'. $label .'-id="'. $tag->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-floppy-disk"></i>
                               </a>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        $list .= '
                <div class="col-12 d-table border-bottom-dashed bg-white">
                    <div class="row d-table-row '. $label .'-add">
                        <div class="col-10 py-2 d-table-cell align-middle text-info">
                            <span class="'. $label .'-create-string" role="button">
                                <i class="fa-regular fa-square-plus me-2"></i>
                                Add Tag
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm '. $label .'-create-field"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_'. $label .'_create">
                                Required Field
                            </div>
                        </div>
                        <div 
                            class="col-2 py-2 d-table-cell align-middle text-info"
                            role="button"
                        >
                            <a 
                                href="" 
                                class="float-end '. $label .'-create-cancel-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-create-save-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $list;
    }

    private function productFormDropdownList($label, $selectedForm): string
    {
        $forms = $this->em->getRepository(ProductForms::class)->findAll();

        $list = '
        <div class="row">
            <div class="bg-dropdown px-0 col-12">';

        // Loop through selected dropdown options
        foreach($forms as $form){

            // Get related records
            $select = $label .'-select';
            $protected = 0;

            if($form->getId() == 1){

                $protected = 1;
            }

            $list .= '
            <div class="row">
                <div 
                    class="col-12 edit-'. $label .' d-table"
                    data-'. $label .'-id="'. $form->getId() .'"
                    data-'. $label .'="'. $form->getName() .'"
                    data-protected="'. $protected .'"
                >
                    <div 
                        class="row '. $label .'-row d-table-row" data-'. $label .'-id="'. $form->getId() .'">
                        <div 
                            class="col-10 py-2 d-table-cell align-middle '. $label .'-select-row '. $select .'"
                            data-'. $label .'-id="'. $form->getId() .'"
                            data-'. $label .'="'. $form->getName() .'"
                            tabindex="-1"
                        >
                                <span class="'. $label .'_string">
                                    '. $form->getName() .'
                                </span>
                                <input 
                                    type="text" 
                                    class="form-control form-control-sm '. $label .'-form-ctrl"
                                    value="'. $form->getName() .'"
                                    data-'. $label .'-field-'. $form->getId() .'
                                    id="'. $label .'_edit_field_'. $form->getId() .'"
                                    style="display: none"
                                >
                                <div class="hidden_msg" id="error_'. $label .'_'. $form->getId() .'">
                                    Required Field
                                </div>
                            </div>
                            <div class="col-2 py-2 d-table-cell align-middle">
                                <a 
                                    href="" 
                                    class="float-end '. $label .'-edit-icon me-3" 
                                    id="'. $label .'_edit_'. $form->getId() .'"
                                    data-'. $label .'-edit-id="'. $form->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-pen-to-square"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end '. $label .'-remove-icon me-3" 
                                    id="'. $label .'_remove_'. $form->getId() .'"
                                    data-'. $label .'-id="'. $form->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-circle-minus"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end '. $label .'-cancel-icon me-3" 
                                    id="'. $label .'_cancel_'. $form->getId() .'"
                                    data-'. $label .'-cancel-id="'. $form->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-xmark"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end '. $label .'-save-icon me-3" 
                                    id="'. $label .'_save_'. $form->getId() .'"
                                    data-'. $label .'-id="'. $form->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-floppy-disk"></i>
                               </a>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        $list .= '
                <div class="col-12 d-table border-bottom-dashed bg-white">
                    <div class="row d-table-row '. $label .'-add">
                        <div class="col-10 py-2 d-table-cell align-middle text-info">
                            <span class="'. $label .'-create-string" role="button">
                                <i class="fa-regular fa-square-plus me-2"></i>
                                Add Tag
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm '. $label .'-create-field"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_'. $label .'_create">
                                Required Field
                            </div>
                        </div>
                        <div 
                            class="col-2 py-2 d-table-cell align-middle text-info"
                            role="button"
                        >
                            <a 
                                href="" 
                                class="float-end '. $label .'-create-cancel-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-create-save-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $list;
    }

    private function individualDropdownList($repository, $label, $elementId, $name): string
    {
        $list = '
        <div class="px-3 row">
            <div class="bg-dropdown px-0 col-12">';

        // Loop through all dropdown options and mark selected
        foreach($repository as $repo){

            $select = $label . '-select';

            $list .= '
            <div class="row">
                <div 
                    class="col-12 edit-'. $label .' d-table"
                    data-'. $label .'-id="'. $repo->getId() .'"
                    
                >
                    <div 
                        class="row '. $label .'-row d-table-row" data-'. $label .'-id="'. $repo->getId() .'">
                        <div 
                            class="col-10 py-2 d-table-cell align-middle '. $label .'-select-row '. $select .'"
                            data-'. $label .'-id="'. $repo->getId() .'"
                            data-'. $label .'="'. $repo->$name() .'"
                            id="'. $elementId .'_row_id_'. $repo->getId() .'"
                            tabindex="-1"
                        >
                            <span id="'. $elementId .'_string_'. $repo->getId() .'">
                                '. $repo->$name() .'
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm '. $label .'-form-ctrl"
                                value="'. $repo->$name() .'"
                                data-'. $label .'-field-'. $repo->getId() .'
                                id="'. $elementId .'_edit_field_'. $repo->getId() .'"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_'. $elementId .'_'. $repo->getId() .'">
                                Required Field
                            </div>
                        </div>
                        <div class="col-2 py-2 d-table-cell align-middle">
                            <a 
                                href="" 
                                class="float-end '. $label .'-edit-icon me-3" 
                                id="'. $elementId .'_edit_'. $repo->getId() .'"
                                data-'. $label .'-edit-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-pen-to-square"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-remove-icon me-3" 
                                id="'. $elementId .'_remove_'. $repo->getId() .'"
                                data-'. $label .'-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-circle-minus"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-cancel-icon me-3" 
                                id="'. $elementId .'_cancel_'. $repo->getId() .'"
                                data-'. $label .'-cancel-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-save-icon me-3" 
                                id="'. $elementId .'_save_'. $repo->getId() .'"
                                data-'. $label .'-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
                </div>
            </div>';
        }

        $pieces = explode('-', $label);
        $string = '';

        foreach($pieces as $piece){

            $string .= ucfirst($piece) . ' ';
        }

        $list .= '
                <div class="col-12 d-table">
                    <div class="row d-table-row" id="'. $elementId .'_add">
                        <div class="col-10 py-2 d-table-cell align-middle text-info">
                            <span id="'. $elementId .'_create_string" role="button">
                                <i class="fa-regular fa-square-plus me-2"></i>
                                Add '. $string .'
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm"
                                id="'. $elementId .'_create_field"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_'. $elementId .'_create">
                                Required Field
                            </div>
                        </div>
                        <div 
                            class="col-2 py-2 d-table-cell align-middle text-info"
                            role="button"
                        >
                            <a 
                                href="" 
                                class="float-end '. $label .'-create-cancel-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-create-save-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $list;
    }

    private function getMultiDropdownList($repository, $label, $entity, $name, $foreign_key, $entity_id, $method, $isEncrypted = false): string
    {
        $list = '
        <div class="px-3 row">
            <div class="bg-dropdown px-0 col-12">';

        // Loop through all dropdown options
        foreach($repository as $repo){

            $itemName = $repo->$name();

            if($isEncrypted)
            {
                $itemName = $this->encryptor->decrypt($repo->$name());
            }

            // Get related records
            $query = $this->em->getRepository($entity)->findBy([
                $foreign_key => $entity_id,
            ]);

            $select = $label . '-select';

            if($entity_id > 0) {

                foreach ($query as $qry) {

                    // Remove class identifier for adding
                    if ($qry->$method()->getId() == $repo->getId()) {

                        $select = '';

                        break;
                    }
                }

            // New product
            } else {

                $select = $label . '-select';
            }


            $list .= '
            <div class="row">
            <div 
                class="col-12 edit-'. $label .' d-table"
                data-'. $label .'-id="'. $repo->getId() .'"
                
            >
                <div 
                    class="row '. $label .'-row d-table-row" data-'. $label .'-id="'. $repo->getId() .'">
                    <div 
                        class="col-10 py-2 d-table-cell align-middle select-row '. $label .'-select-row '. $select .'"
                        data-'. $label .'-id="'. $repo->getId() .'"
                        data-'. $label .'="'. $itemName .'"
                        id="'. $label .'_row_id_'. $repo->getId() .'"
                        tabindex="-1"
                    >
                            <span id="'. $label .'_string_'. $repo->getId() .'">
                                '. $itemName .'
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm '. $label .'-form-ctrl"
                                value="'. $itemName .'"
                                data-'. $label .'-field-'. $repo->getId() .'
                                id="'. $label .'_edit_field_'. $repo->getId() .'"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_'. $label .'_'. $repo->getId() .'">
                                Required Field
                            </div>
                        </div>
                        <div class="col-2 py-2 d-table-cell align-middle">
                            <a 
                                href="" 
                                class="float-end '. $label .'-edit-icon me-3" 
                                id="'. $label .'_edit_'. $repo->getId() .'"
                                data-'. $label .'-edit-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-pen-to-square"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-remove-icon me-3" 
                                id="'. $label .'_remove_'. $repo->getId() .'"
                                data-'. $label .'-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-circle-minus"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-cancel-icon me-3" 
                                id="'. $label .'_cancel_'. $repo->getId() .'"
                                data-'. $label .'-cancel-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-save-icon me-3" 
                                id="'. $label .'_save_'. $repo->getId() .'"
                                data-'. $label .'-id="'. $repo->getId() .'"
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
            </div>
            </div>';
        }

        $list .= '
                <div class="col-12 d-table">
                    <div class="row d-table-row" id="'. $label .'_add">
                        <div class="col-10 py-2 d-table-cell align-middle text-info">
                            <span id="'. $label .'_create_string" role="button">
                                <i class="fa-regular fa-square-plus me-2"></i>
                                Add '. ucfirst($label) .'
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm"
                                id="'. $label .'_create_field"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_'. $label .'_create">
                                Required Field
                            </div>
                        </div>
                        <div 
                            class="col-2 py-2 d-table-cell align-middle text-info"
                            role="button"
                        >
                            <a 
                                href="" 
                                class="float-end '. $label .'-create-cancel-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end '. $label .'-create-save-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $list;
    }

    private function getRolesDropdownList($userId): string
    {
        $userId = $userId ?? 0;
        $repository = [
            "ROLE_USER",
            "ROLE_ACTIVE_INGREDIENT",
            "ROLE_ARTICLE",
            "ROLE_API",
            "ROLE_BANNER",
            "ROLE_ADMIN",
            "ROLE_CATEGORY",
            "ROLE_CLINIC",
            "ROLE_DISTRIBUTOR",
            "ROLE_COMMUNICATION_METHOD",
            "ROLE_COUNTRY",
            "ROLE_MANUFACTURER",
            "ROLE_PRODUCT",
            "ROLE_RESTRICTED_DOMAIN",
            "ROLE_SPECIE",
            "ROLE_SUB_CATEGORY",
            "ROLE_TAG",
        ];

        $list = '
        <div class="px-3 row">
            <div class="bg-dropdown px-0 col-12">';

        // Loop through all dropdown options
        foreach($repository as $repo){

            // Get related records
            $query = $this->em->getRepository(User::class)->find($userId);

            $select = 'role-select';

            if($query != null) {

                foreach ($query->getRoles() as $role) {

                    // Remove class identifier for adding
                    if ($role == $repo) {

                        $select = '';
                        break;
                    }
                }
            }


            $list .= '
            <div class="row">
                <div 
                    class="col-12 edit-role d-table"
                    data-role-id="'. $repo .'"
                    
                >
                    <div 
                        class="row role-row d-table-row" data-role-id="'. $repo .'">
                        <div 
                            class="col-10 py-2 d-table-cell align-middle '. $select .'"
                            data-role-id="'. $repo .'"
                            data-role="'. $repo .'"
                            id="role_row_id_'. $repo .'"
                        >
                                <span id="role_string_'. $repo .'">
                                    '. $repo .'
                                </span>
                                <input 
                                    type="text" 
                                    class="form-control form-control-sm role-form-ctrl"
                                    value="'. $repo .'"
                                    data-role-field-'. $repo .'
                                    id="role_edit_field_'. $repo .'"
                                    style="display: none"
                                >
                                <div class="hidden_msg" id="error_role_'. $repo .'">
                                    Required Field
                                </div>
                            </div>
                            <div class="col-2 py-2 d-table-cell align-middle">
                               <a 
                                    href="" 
                                    class="float-end role-remove-icon me-3" 
                                    id="role_remove_'. $repo .'"
                                    data-role-id="'. $repo .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-circle-minus"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end role-cancel-icon me-3" 
                                    id="role_cancel_'. $repo .'"
                                    data-role-cancel-id="'. $repo .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-xmark"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end role-save-icon me-3" 
                                    id="role_save_'. $repo .'"
                                    data-role-id="'. $repo .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-floppy-disk"></i>
                               </a>
                            </div>
                        </div>
                </div>
            </div>';
        }

        $list .= '
            </div>
        </div>';

        return $list;
    }

    private function getActiveIngredientDropdownList($productId): array
    {
        $product = $this->em->getRepository(Products::class)->find($productId);
        $activeIngredients = $this->em->getRepository(ActiveIngredients::class)->findAll();
        $list['selected'] = '';
        $list['selected'] = '';
        $list['twigArray'] = [];

        $list['list'] = '
        <div class="px-3 row">
            <div class="bg-dropdown px-0 col-12">';

        // Loop through all dropdown options
        foreach($activeIngredients as $activeIngredient){

            $select = 'ingredient-select';

            if($product != null) {

                if(!empty($product->getActiveIngredient())) {

                    $ingredients = explode(",", $product->getActiveIngredient());

                    $list['selected'] = $ingredients;

                    // Remove select class to disable adding item
                    if (strstr($product->getActiveIngredient(), $activeIngredient->getName())) {

                        $list['twigArray'][$activeIngredient->getId()] = $activeIngredient->getName();
                        $select = '';
                    }
                }
            }

            $list['list'] .= '
            <div class="row">
                <div 
                    class="col-12 edit-ingredient d-table"
                    data-ingredient-id="'. $activeIngredient->getId() .'"
                    data-ingredient="'. $activeIngredient->getName() .'"  
                >
                    <div 
                        class="row ingredient-row d-table-row" 
                        data-ingredient-id="'. $activeIngredient->getId() .'"
                    >
                        <div 
                            class="col-10 py-2 d-table-cell align-middle ingredient-select-row '. $select .'"
                            data-ingredient-id="'. $activeIngredient->getId() .'"
                            data-ingredient="'. $activeIngredient->getName() .'"
                            id="ingredient_row_id_'. $activeIngredient->getId() .'"
                            tabindex="-1"
                        >
                                <span class="ingredient_string" id="ingredient_string_'. $activeIngredient->getId() .'">
                                    '. $activeIngredient->getName() .'
                                </span>
                                <input 
                                    type="text" 
                                    class="form-control form-control-sm ingredient-form-ctrl"
                                    value="'. $activeIngredient->getName() .'"
                                    data-ingredient-field-'. $activeIngredient->getId() .'
                                    id="ingredient_edit_field_'. $activeIngredient->getId() .'"
                                    style="display: none"
                                >
                                <div class="hidden_msg" id="error_ingredient_'. $activeIngredient->getId() .'">
                                    Required Field
                                </div>
                            </div>
                            <div class="col-2 py-2 d-table-cell align-middle">
                                <a 
                                    href="" 
                                    class="float-end ingredient-edit-icon me-3" 
                                    id="ingredient_edit_'. $activeIngredient->getId() .'"
                                    data-ingredient-edit-id="'. $activeIngredient->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-pen-to-square"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end ingredient-remove-icon me-3" 
                                    id="ingredient_remove_'. $activeIngredient->getId() .'"
                                    data-ingredient-id="'. $activeIngredient->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-circle-minus"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end ingredient-cancel-icon me-3" 
                                    id="ingredient_cancel_'. $activeIngredient->getId() .'"
                                    data-ingredient-cancel-id="'. $activeIngredient->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-xmark"></i>
                               </a>
                               <a 
                                    href="" 
                                    class="float-end ingredient-save-icon me-3" 
                                    id="ingredient_save_'. $activeIngredient->getId() .'"
                                    data-ingredient-id="'. $activeIngredient->getId() .'"
                                    style="display: none"
                                >
                                   <i class="fa-solid fa-floppy-disk"></i>
                               </a>
                            </div>
                        </div>
                </div>
            </div>';
        }

        $list['list'] .= '
                <div class="col-12 d-table border-bottom-dashed bg-white">
                    <div class="row d-table-row ingredient-add">
                        <div class="col-10 py-2 d-table-cell align-middle text-info">
                            <span class="ingredient-create-string" role="button">
                                <i class="fa-regular fa-square-plus me-2"></i>
                                Add Tag
                            </span>
                            <input 
                                type="text" 
                                class="form-control form-control-sm ingredient-create-field"
                                style="display: none"
                            >
                            <div class="hidden_msg" id="error_ingredient_create">
                                Required Field
                            </div>
                        </div>
                        <div 
                            class="col-2 py-2 d-table-cell align-middle text-info"
                            role="button"
                        >
                            <a 
                                href="" 
                                class="float-end ingredient-create-cancel-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-xmark"></i>
                           </a>
                           <a 
                                href="" 
                                class="float-end ingredient-create-save-icon me-3" 
                                style="display: none"
                            >
                               <i class="fa-solid fa-floppy-disk"></i>
                           </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $list;
    }

    public function getPagination($pageId, $results, $url): string
    {
        $currentPage = $pageId;
        $lastPage = $this->page_manager->lastPage($results);
        $pagination = '';

        if(count($results) > 0) {

            $pagination .= '
            <!-- Pagination -->
            <div class="row">
                <div class="col-12">';

            if ($lastPage > 1) {

                $previousPageNo = $currentPage - 1;
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
                        class="address-pagination" 
                        href="' . $previousPage . '"
                    >
                        <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline">Previous</span>
                    </a>
                </li>';

                $is_active = false;
                $c = 0;
                $i = $currentPage;
                $pageCount = $currentPage + 9;

                // First 10 pages
                // If page count is less than 10
                if($currentPage < 10 && $lastPage < 10){

                    $i = 1;
                    $pageCount = $lastPage;
                }

                // If page count is greater than 10
                if($currentPage < 10 && $lastPage > 10){

                    $i = 1;
                    $pageCount = 10;
                }

                // Last 10 pages
                if($currentPage > 10 && $currentPage > $lastPage - 10){

                    $i = $currentPage - 10;
                    $pageCount = $currentPage;
                }

                for ($i; $i <= $pageCount; $i++) {

                    $active = '';
                    $c++;

                    if ($i == (int)$currentPage) {

                        $active = 'active';
                        $is_active = true;
                    }

                    // Go to previous page if all records for a page have been deleted
                    if(!$is_active && $i == count($results)){

                        $active = 'active';
                    }

                    $pagination .= '
                    <li class="page-item ' . $active . '">
                        <a class="address-pagination" href="' . $url . $i . '">' . $i . '</a>
                    </li>';

                    if($i == $lastPage){
                        break;
                    }
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
                        class="address-pagination" 
                        aria-disabled="' . $dataDisabled . '" 
                        href="' . $url . $currentPage + 1 . '">
                        <span class="d-none d-sm-inline">Next</span> <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>';

                if(count($results) < $currentPage){

                    $currentPage = count($results);
                }

                $pagination .= '
                        </ul>
                    </nav>
                </div>';
            }
        }

        return $pagination;
    }

    #[ArrayShape(['plainPassword' => "string", 'hashedPassword' => "string"])]
    private function setUserPassword($userId)
    {
        // ... e.g. get the user data from a registration form
        $user = $this->em->getRepository(User::class)->find($userId);

        $plaintextPassword = $this->generatePassword();

        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plaintextPassword
        );

        return [
            'plainPassword' => $plaintextPassword,
            'hashedPassword' => $hashedPassword
        ];
    }

    #[Route('/admin/category/get-tags/{tags}', name: 'get_selected_tags')]
    public function getSelectedTags(Request $request)
    {
        $response = '';
        $tags = $request->get('tags');

        if(is_array($tags) && count($tags) > 0){

            foreach($tags as $tag){

                $tagRepo = $this->em->getRepository(Tags::class)->find((int) $tag);

                if($tagRepo != null){

                    $response .= '
                        <span class="badge bg-disabled me-3 my-1" data-tag-id="'. $tagRepo->getId() .'">
                        '. $tagRepo->getName() .'
                    </span>';
                }

            }
        } else {

            $response .= 'Select Tags';
        }

        return new Response($response);
    }

    #[Route('/admin/category/get-tag-array/{categoryId}', name: 'get_selected_tags')]
    public function getSelectedTagsArray(Request $request)
    {
        $levelId = (int) $request->request->get('level_id');
        $categoryId = (int) $request->request->get('category_id');
        $array = [];

        if($levelId == 1){

            $repo = Categories1::class;

        } elseif($levelId == 2) {

            $repo = Categories2::class;

        } elseif($levelId == 3) {

            $repo = Categories3::class;
        }

        $category = $this->em->getRepository($repo)->find($categoryId);

        if($category != null){

            $array = json_encode($category->getTags());
        }

        return new JsonResponse($array);
    }

    #[Route('/admin/get-articles-details', name: 'get_article_details')]
    public function getArticleDetailsAction(Request $request): Response
    {
        $articleId = $request->request->get('article-id');
        $articleDetails = $this->em->getRepository(ArticleDetails::class)->find($articleId);
        $response = [
            'name' => $articleDetails->getName(),
            'description' => $articleDetails->getDescription(),
            'copy' => $articleDetails->getCopy(),
        ];

        return new JsonResponse($response);
    }

    private function generatePassword(): string
    {
        $sets = [];
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $sets[] = '23456789';
        $sets[] = '!@$%*?';

        $all = '';
        $password = '';

        foreach ($sets as $set) {

            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);

        for ($i = 0; $i < 16 - count($sets); $i++) {

            $password .= $all[array_rand($all)];
        }

        $this->plain_password = str_shuffle($password);

        return $this->plain_password;
    }

    private function getApiDetails($apiDetails):string
    {
        $html = '
        <div class="row">
            <div class="col-12 col-sm-6">
                <label class="text-primary mb-2">API Credentials</label>
            </div>
            <div class="col-12 col-sm-6">
                <a
                    href=""
                    class="float-end open-api-details"
                    data-api-details-id="0"
                    data-bs-toggle="modal"
                    data-bs-target="#modal_api_details"
                >
                    <i class="fa-regular fa-square-plus edit-icon me-2"></i>
                    Add New
                </a>
            </div>
        </div>
        ';

        if(is_array($apiDetails) && count($apiDetails) > 0){

            foreach($apiDetails as $apiDetail){

                $html .= '
                    <div class="row">
                        <div class="col-9 col-sm-11 pe-0">
                            <input
                                    type="hidden"
                                    value="'. $apiDetail->getId() .'"
                                    name="apiDetails-id[]"
                            >
                            <div class="border-left border-top ps-2 py-2 bg-white border-bottom">
                                '. $this->encryptor->decrypt($apiDetail->getDistributor()->getDistributorName()) .'
                            </div>
                        </div>
                        <div class="col-3 col-sm-1 ps-0 d-table">
                            <div class="border-right border-top py-2 d-table-cell bg-white border-bottom" style="display: table-cell !important;">
                                <a
                                    href=""
                                    class="float-end open-api-details"
                                    data-api-details-id="'. $apiDetail->getId() .'"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal_api_details"
                                >
                                    <i class="fa-solid fa-pen-to-square edit-icon"></i>
                                </a>
                                <a href="" class="float-end api-edit-icon me-3 text-danger" data-api-id="'. $apiDetail->getId() .'">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                    </div>';
            }
        }

        return $html;
    }
}