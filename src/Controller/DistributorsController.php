<?php

namespace App\Controller;

use App\Entity\Addresses;
use App\Entity\ApiDetails;
use App\Entity\AvailabilityTracker;
use App\Entity\ChatMessages;
use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\Countries;
use App\Entity\DistributorProducts;
use App\Entity\Distributors;
use App\Entity\DistributorUserPermissions;
use App\Entity\DistributorUsers;
use App\Entity\Notifications;
use App\Entity\OrderItems;
use App\Entity\Orders;
use App\Entity\ProductManufacturers;
use App\Entity\Products;
use App\Entity\ProductsSpecies;
use App\Entity\RefreshTokens;
use App\Entity\RestrictedDomains;
use App\Entity\Tracking;
use App\Entity\UserPermissions;
use App\Form\AddressesFormType;
use App\Form\DistributorFormType;
use App\Form\DistributorProductsFormType;
use App\Form\DistributorUsersFormType;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class DistributorsController extends AbstractController
{
    private $em;
    const ITEMS_PER_PAGE = 10;
    private $pageManager;
    private $requestStack;
    private $plainPassword;
    private $encryptor;
    private $mailer;

    public function __construct(EntityManagerInterface $em, PaginationManager $pagination, RequestStack $requestStack, Encryptor $encryptor, MailerInterface $mailer) {
        $this->em = $em;
        $this->pageManager = $pagination;
        $this->requestStack = $requestStack;
        $this->encryptor = $encryptor;
        $this->mailer = $mailer;
    }

    #[Route('/distributors', name: 'distributors')]
    public function index(): Response
    {
        return $this->render('frontend/distributors/index.html.twig', [
            'controller_name' => 'DistributorsController',
        ]);
    }

    #[Route('/distributors/register', name: 'distributor_reg')]
    public function distributorReg(Request $request): Response
    {
        $countries = $this->em->getRepository(Countries::class)->findBy([
            'isActive' => 1,
        ]);
        $form = $this->createRegisterForm();

        return $this->render('frontend/distributors/register.html.twig', [
            'form' => $form->createView(),
            'countries' => $countries
        ]);
    }

    #[Route('/distributors/register/check-email', name: 'distributor_check_email')]
    public function distributorsCheckEmailAction(Request $request): Response
    {
        $email = $request->request->get('email');
        $domainName = explode('@', $email);
        $response['response'] = true;
        $firstName = '';
        $restrictedDomains = $this->em->getRepository(RestrictedDomains::class)->arrayFindAll();

        foreach($restrictedDomains as $restrictedDomain)
        {
            if(md5($domainName[1]) == md5($restrictedDomain->getName()))
            {
                $response['response'] = false;
                $response['restricted'] = true;

                return new JsonResponse($response);
            }
        }

        $distributor = $this->em->getRepository(Distributors::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

        $distributorDomain = $this->em->getRepository(Distributors::class)->findOneBy([
            'domainName' => md5($domainName[1]),
        ]);

        $distributorUsers = $this->em->getRepository(DistributorUsers::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

        $clinic = $this->em->getRepository(Clinics::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

        $clinicDomain = $this->em->getRepository(Clinics::class)->findOneBy([
            'domainName' => md5($domainName[1]),
        ]);

        $clinicUsers = $this->em->getRepository(ClinicUsers::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

        if($clinicDomain != null)
        {
            $user = $this->em->getRepository(ClinicUsers::class)->findOneBy([
                'clinic' => $clinicDomain->getId(),
                'isPrimary' => 1
            ]);
            $firstName = $this->encryptor->decrypt($user->getFirstName());
        }

        if($distributorDomain != null)
        {
            $user = $this->em->getRepository(DistributorUsers::class)->findOneBy([
                'distributor' => $distributorDomain->getId(),
                'isPrimary' => 1
            ]);
            $firstName = $this->encryptor->decrypt($user->getFirstName());
        }

        $response['firstName'] = $firstName;

        if($distributor != null || $distributorUsers != null || $clinic != null || $clinicUsers != null || $clinicDomain != null || $distributorDomain != null)
        {
            $response['response'] = false;
            $response['restricted'] = false;
        }

        return new JsonResponse($response);
    }

    protected function createRegisterForm()
    {
        $distributors = new Distributors();

        return $this->createForm(DistributorFormType::class, $distributors);
    }

    #[Route('/distributor/inventory', name: 'distributor_inventory')]
    public function createDistributorInventoryForm()
    {
        $distributorProducts = new DistributorProducts();

        return $this->createForm(DistributorProductsFormType::class, $distributorProducts);
    }

    #[Route('/distributor/addresses', name: 'distributor_addresses')]
    public function createDistributorAddressesForm()
    {
        $addresses = new Addresses();

        return $this->createForm(AddressesFormType::class, $addresses);
    }

    #[Route('/distributor/register/create', name: 'distributor_create')]
    public function distributorCreateAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $distributor = $this->em->getRepository(Distributors::class)->findOneBy(['email' => $data->get('email')]);
        $countries = $this->em->getRepository(Countries::class)->find($data->get('country'));
        $tracking = $this->em->getRepository(Tracking::class)->find(3);

        if($distributor == null) {

            $distributors = new Distributors();

            $plainTextPwd = $this->generatePassword();

            if (!empty($plainTextPwd)) {

                $domainName = explode('@', $data->get('email'));

                $distributors->setDistributorName($this->encryptor->encrypt($data->get('distributor-name')));
                $distributors->setEmail($this->encryptor->encrypt($data->get('email')));
                $distributors->setHashedEmail(md5($data->get('email')));
                $distributors->setDomainName(md5($domainName[1]));
                $distributors->setTelephone($this->encryptor->encrypt($data->get('telephone')));
                $distributors->setIntlCode($this->encryptor->encrypt($data->get('intl-code')));
                $distributors->setIsoCode($this->encryptor->encrypt($data->get('iso-code')));
                $distributors->setAddressCountry($countries);
                $distributors->setTracking($tracking);
                $distributors->setIsApproved(0);

                $this->em->persist($distributors);
                $this->em->flush();

                // Create user
                $distributor = $this->em->getRepository(Distributors::class)->findOneBy([
                    'hashedEmail' => md5($data->get('email')),
                ]);
                $distributorUsers = new DistributorUsers();

                $hashed_pwd = $passwordHasher->hashPassword($distributorUsers, $plainTextPwd);

                $distributorUsers->setDistributor($distributor);
                $distributorUsers->setFirstName($this->encryptor->encrypt($data->get('first-name')));
                $distributorUsers->setLastName($this->encryptor->encrypt($data->get('last-name')));
                $distributorUsers->setPosition($this->encryptor->encrypt($data->get('position')));
                $distributorUsers->setEmail($this->encryptor->encrypt($data->get('email')));
                $distributorUsers->setHashedEmail(md5($data->get('email')));
                $distributorUsers->setTelephone($this->encryptor->encrypt($data->get('telephone')));
                $distributorUsers->setRoles(['ROLE_DISTRIBUTOR']);
                $distributorUsers->setPassword($hashed_pwd);
                $distributorUsers->setIsPrimary(1);

                $this->em->persist($distributorUsers);
                $this->em->flush();

                // Assign User Permissions
                $userPermissions = $this->em->getRepository(UserPermissions::class)->findBy([
                    'isDistributor' => 1,
                ]);

                foreach($userPermissions as $userPermission){

                    $distributorUserPermissions = new DistributorUserPermissions();

                    $distributorUserPermissions->setUser($distributorUsers);
                    $distributorUserPermissions->setDistributor($distributors);
                    $distributorUserPermissions->setPermission($userPermission);

                    $this->em->persist($distributorUserPermissions);
                }

                $this->em->flush();

                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                $body .= '<tr><td colspan="2">Hi '. $data->get('first_name') .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/distributors/login">https://'. $_SERVER['HTTP_HOST'] .'/distributor/login</a></td>';
                $body .= '</tr>';
                $body .= '<tr>';
                $body .= '    <td><b>Username: </b></td>';
                $body .= '    <td>'. $data->get('email') .'</td>';
                $body .= '</tr>';
                $body .= '<tr>';
                $body .= '    <td><b>Password: </b></td>';
                $body .= '    <td>'. $plainTextPwd .'</td>';
                $body .= '</tr>';
                $body .= '</table>';

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($data->get('email'))
                    ->subject('Fluid Login Credentials')
                    ->html($body);

                $mailer->send($email);
            }

            $response = 'Your Fluid account was successfully created, an email with your login credentials has been sent to your inbox.';

        } else {

            $response = false;
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/dashboard', name: 'distributor_dashboard')]
    #[Route('/distributors/account', name: 'distributor_account')]
    #[Route('/distributors/about', name: 'distributor_about')]
    #[Route('/distributors/operating-hours', name: 'distributor_operating_hours')]
    #[Route('/distributors/refund-policy', name: 'distributor_refund_policy')]
    #[Route('/distributors/sales-tax-policy', name: 'distributor_sales_tax_policy')]
    #[Route('/distributors/shipping-policy', name: 'distributor_shipping_policy')]
    #[Route('/distributors/manage-inventory', name: 'distributor_manage_inventory')]
    #[Route('/distributors/users', name: 'distributor_get_users')]
    #[Route('/distributors/order/{order_id}', name: 'distributor_order')]
    #[Route('/distributors/orders/{distributor_id}', name: 'distributor_order_list')]
    #[Route('/distributors/customers/1', name: 'distributor_customer_list')]
    #[Route('/distributors/inventory/list/1', name: 'distributor_inventory_list')]
    public function distributorDashboardAction(Request $request): Response
    {
        if($this->get('security.token_storage')->getToken() == null){

            $this->addFlash('danger', 'Your session expired due to inactivity, please login.');

            return $this->redirectToRoute('distributor_login');
        }

        $distributor = $this->getUser()->getDistributor();
        $distributorId = $distributor->getId();
        $user = $this->getUser();
        $username = $distributor->getDistributorName();
        $users = $this->em->getRepository(DistributorUsers::class)->findDistributorUsers($distributorId);
        $userPermissions = $this->em->getRepository(UserPermissions::class)->findBy(['isDistributor' => 1]);
        $userResults = $this->pageManager->paginate($users[0], $request, self::ITEMS_PER_PAGE);
        $usersPagination = $this->getPagination(1, $userResults, $distributorId);
        $distributorProductsRepo = $this->em->getRepository(Products::class)->findByManufacturer($distributorId,0,0);
        $distributorProducts = $this->pageManager->paginate($distributorProductsRepo[0], $request, self::ITEMS_PER_PAGE);
        $distributorProductsPagination = $this->getPagination(1, $distributorProducts, $distributorId);
        $manufacturers = $this->em->getRepository(ProductManufacturers::class)->findByDistributorManufacturer($distributorId);
        $species = $this->em->getRepository(ProductsSpecies::class)->findByDistributorProducts($distributorId);
        $form = $this->createRegisterForm();
        $inventoryForm = $this->createDistributorInventoryForm();
        $addressForm = $this->createDistributorAddressesForm();
        $userForm = $this->createDistributorUserForm()->createView();
        $traking = $this->em->getRepository(Tracking::class)->findAll();
        $clinicId = '';
        if($request->get('order_id') != null) {

            $order = $this->em->getRepository(Orders::class)->find($request->get('order_id'));
            $clinicId = $order->getClinic()->getId();
        }
        $orderList = false;
        $orderDetail = false;

        $permissions = [];

        foreach($user->getDistributorUserPermissions() as $permission){

            $permissions[] = $permission->getPermission()->getId();
        }

        if(substr($request->getPathInfo(),0,20) == '/distributors/orders'){

            $orderList = true;
        }

        if(substr($request->getPathInfo(),0,20) == '/distributors/order/'){

            $orderDetail = true;
        }

        return $this->render('frontend/distributors/index.html.twig',[
            'distributor' => $distributor,
            'users' => $userResults,
            'form' => $form->createView(),
            'inventory_form' => $inventoryForm->createView(),
            'address_form' => $addressForm->createView(),
            'user_form' => $userForm,
            'order_list' => $orderList,
            'order_detail' => $orderDetail,
            'clinic_id' => $clinicId,
            'users_pagination' => $usersPagination,
            'username' => $username,
            'permissions' => $permissions,
            'user_permissions' => $userPermissions,
            'tracking' => $traking,
            'distributorProducts' => $distributorProducts,
            'distributorProductsPagination' => $distributorProductsPagination,
            'manufacturers' => $manufacturers,
            'species' => $species,
        ]);
    }

    #[Route('/sellers', name: 'sellers_page')]
    public function sellersAction(Request $request): Response
    {
        $sellers = $this->em->getRepository(Distributors::class)->findAll();

        return $this->render('frontend/sellers.html.twig', [
            'sellers' => $sellers,
        ]);
    }

    #[Route('/distributor/update/personal-information', name: 'distributor_update_personal_information')]
    public function distributorUpdatePersonalInformationAction(Request $request): Response
    {
        $data = $request->request;
        $username = $this->get('security.token_storage')->getToken()->getUser()->getUserIdentifier();
        $distributor = $this->em->getRepository(Distributors::class)->findOneBy(['email' => $username]);

        if($distributor != null) {

            $distributor->setFirstName($data->get('first_name'));
            $distributor->setLastName($data->get('last_name'));
            $distributor->setTelephone($data->get('telephone'));
            $distributor->setPosition($data->get('position'));

            $this->em->persist($distributor);
            $this->em->flush();

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Personal details successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/update/company-information', name: 'distributor_update_company_information')]
    public function distributorUpdateCompanyInformationAction(Request $request): Response
    {
        $data = $request->request->get('distributor_form');

        $distributor = $this->getUser()->getDistributor();
        $countryId = (int) $data['addressCountry'];
        $logo = '';
        $country = $this->em->getRepository(Countries::class)->find($countryId);
        $isApproved = (bool) $distributor->getIsApproved() ?? false;
        $tradeLicense = $_FILES['distributor_form']['name']['trade-license-file'];
        $tradeLicenseNo = $data['trade-license-no'];
        $tradeLicenseExpDate = $data['trade-license-exp-date'];

        // Account approval required if reg docs change
        if(
            !empty($tradeLicense) || $tradeLicenseNo != $this->encryptor->decrypt($distributor->getTradeLicenseNo()) ||
            $tradeLicenseExpDate != $distributor->getTradeLicenseExpDate()->format('Y-m-d')
        )
        {
            $distributor->setIsApproved(0);
            $isApproved = false;
        }

        if($distributor != null) {

            $domainName = explode('@', $data['email']);

            $distributor->setDistributorName($this->encryptor->encrypt($data['distributor-name']));
            $distributor->setTelephone($this->encryptor->encrypt($data['telephone']));
            $distributor->setEmail($this->encryptor->encrypt($data['email']));
            $distributor->setWebsite($this->encryptor->encrypt($data['website']));
            $distributor->setDomainName(md5($domainName[1]));
            $distributor->setAddressCountry($country);
            $distributor->setAddressStreet($this->encryptor->encrypt($data['address-street']));
            $distributor->setAddressCity($this->encryptor->encrypt($data['address-city']));
            $distributor->setAddressPostalCode($this->encryptor->encrypt($data['address-postal-code']));
            $distributor->setAddressState($this->encryptor->encrypt($data['address-state']));
            $distributor->setIsoCode($this->encryptor->encrypt($data['iso-code']));
            $distributor->setIntlCode($this->encryptor->encrypt($data['intl-code']));
            $distributor->setManagerFirstName($this->encryptor->encrypt($data['manager-first-name']));
            $distributor->setManagerLastName($this->encryptor->encrypt($data['manager-last-name']));
            $distributor->setManagerIdNo($this->encryptor->encrypt($data['manager-id-no']));
            $distributor->setManagerIdExpDate(new \DateTime($data['manager-id-exp-date']));
            $distributor->setTradeLicenseNo($this->encryptor->encrypt($data['trade-license-no']));
            $distributor->setTradeLicenseExpDate(new \DateTime($data['trade-license-exp-date']));

            if(!empty($_FILES['distributor_form']['name']['trade-license-file']))
            {
                $extension = pathinfo($_FILES['distributor_form']['name']['trade-license-file'], PATHINFO_EXTENSION);
                $file = $distributor->getId() . '-' . uniqid() . '.' . $extension;
                $targetFile = __DIR__ . '/../../public/documents/' . $file;

                if(move_uploaded_file($_FILES['distributor_form']['tmp_name']['trade-license-file'], $targetFile)) {

                    $distributor->setTradeLicense($file);
                }
            }

            if(!empty($_FILES['distributor_form']['name']['logo'])) {

                $extension = pathinfo($_FILES['distributor_form']['name']['logo'], PATHINFO_EXTENSION);
                $file = $distributor->getId() . '-' . uniqid() . '.' . $extension;
                $targetFile = __DIR__ . '/../../public/images/logos/' . $file;

                if (move_uploaded_file($_FILES['distributor_form']['tmp_name']['logo'], $targetFile)) {

                    $distributor->setLogo($file);
                    $logo = $file;
                }
            }

            $this->em->persist($distributor);
            $this->em->flush();

            // Send Approval Email
            if(!$isApproved)
            {
                $orderUrl = $this->getParameter('app.base_url') . '/admin/distributor/'. $distributor->getId();
                $html = '<p>Please <a href="'. $orderUrl .'">click here</a> to view the distributors details.</p><br>';

                $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $html,
                ]);

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($this->getParameter('app.email_from'))
                    ->subject('Fluid - Account Approval Request')
                    ->html($html->getContent());

                $this->mailer->send($email);
            }

            $message = '<b><i class="fa-solid fa-circle-check"></i></i></b> Company details successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $message = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.';
        }

        $response = [
            'message' => $message,
            'logo' => $logo,
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/update/about_us', name: 'distributor_update_about_us')]
    public function distributorUpdateAboutUsAction(Request $request): Response
    {
        $data = $request->request;
        $distributor = $this->getUser()->getDistributor();

        if($distributor != null) {

            $about = $data->get('about_us');

            if(!empty($about)) {

                $distributor->setAbout($about);

                $this->em->persist($distributor);
                $this->em->flush();
            }

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> About us successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> An error occurred.';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/update/operating_hours', name: 'distributor_update_operating_hours')]
    public function distributorUpdateOperatingHoursAction(Request $request): Response
    {
        $data = $request->request;
        $distributor = $this->getUser()->getDistributor();

        if($distributor != null) {

            if(!empty($data->get('operating_hours'))) {

                $distributor->setOperatingHours($data->get('operating_hours'));
            }

            $this->em->persist($distributor);
            $this->em->flush();

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Operating hours successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/update/refund_policy', name: 'distributor_update_refund_policy')]
    public function distributorUpdateRefundPolicyAction(Request $request): Response
    {
        $data = $request->request;
        $distributor = $this->getUser()->getDistributor();

        if($distributor != null) {

            if(!empty($data->get('refund_policy'))) {

                $distributor->setRefundPolicy($data->get('refund_policy'));
            }

            $this->em->persist($distributor);
            $this->em->flush();

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Refund policy successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/update/sales_tax_policy', name: 'distributor_update_sales_tax_policy')]
    public function distributorUpdateSalesTaxPolicyAction(Request $request): Response
    {
        $data = $request->request;
        $distributor = $this->getUser()->getDistributor();

        if($distributor != null) {

            if(!empty($data->get('sales_tax_policy'))) {

                $distributor->setSalesTaxPolicy($data->get('sales_tax_policy'));
            }

            $this->em->persist($distributor);
            $this->em->flush();

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Sales tax policy successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/update/shipping_policy', name: 'distributor_update_shipping_policy')]
    public function distributorUpdateShippingPolicyAction(Request $request): Response
    {
        $data = $request->request;
        $distributor = $this->getUser()->getDistributor();

        if($distributor != null) {

            if(!empty($data->get('shipping_policy'))) {

                $distributor->setShippingPolicy($data->get('shipping_policy'));
            }

            $this->em->persist($distributor);
            $this->em->flush();

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Shipping policy successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/inventory-search', name: 'distributor_inventory_search')]
    public function distributorInventorySearchAction(Request $request): Response
    {
        $products = $this->em->getRepository(Products::class)->findBySearch($request->get('keyword'));
        $select = '<ul id="product_list">';

        foreach($products as $product){

            $id = $product->getId();
            $name = $product->getName();
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

            $select .= "<li onClick=\"selectProduct('$id', '$name');\" class='search-item'>$name$dosage$size</li>";
        }

        $select .= '</ul>';

        return new Response($select);
    }

    #[Route('/distributors/inventory-get', name: 'distributor_inventory_get')]
    public function distributorGetInventoryAction(Request $request,TokenStorageInterface $tokenStorage): Response
    {
        $productId = (int) $request->request->get('product_id');
        $products = $this->em->getRepository(Products::class)->find($productId);

        if($products != null){

            $distributorId = $this->getUser()->getDistributor()->getId();

            $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
            $response = [];

            $distributorProduct = $this->em->getRepository(Distributors::class)
                ->getDistributorProduct($distributor->getId(), $productId);

            if($distributorProduct != null){

                $response['distributor_id'] = $distributor->getId();
                $response['itemId'] = $distributorProduct[0]['distributorProducts'][0]['itemId'];
                $response['sku'] = $distributorProduct[0]['distributorProducts'][0]['sku'];
                $response['unit_price'] = $distributorProduct[0]['distributorProducts'][0]['unitPrice'];
                $response['stock_count'] = $distributorProduct[0]['distributorProducts'][0]['stockCount'];
                $response['expiry_date'] = '';
                $response['tax_exempt'] = $distributorProduct[0]['distributorProducts'][0]['taxExempt'];
                $response['product'] = $distributorProduct[0]['distributorProducts'][0]['product'];

                if($distributorProduct[0]['distributorProducts'][0]['expiryDate'] != null){

                    $response['expiry_date'] = $distributorProduct[0]['distributorProducts'][0]['expiryDate']->format('Y-m-d');
                }

            } else {

                $product = $this->em->getRepository(Products::class)->find($productId);

                $response['distributor_id'] = $distributor->getId();
                $response['sku'] = '';
                $response['distributor_no'] = '';
                $response['unit_price'] = '';
                $response['stock_count'] = '';
                $response['expiry_date'] = '';
                $response['tax_exempt'] = 0;
                $response['product'] = [
                    'dosage' => $product->getDosage(),
                    'size' => $product->getSize(),
                    'unit' => $product->getUnit(),
                    'activeIngredient' => $product->getActiveIngredient(),
                ];
            }

        } else {

            $response['message'] = 'Inventory item not found';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/inventory-update', name: 'distributor_inventory_update')]
    public function distributorUpdateInventoryAction(Request $request, MailerInterface $mailer): Response
    {
        $data = $request->request->get('distributor_products_form');
        $product = $this->em->getRepository(Products::class)->find($data['product']);
        $distributor = $this->em->getRepository(Distributors::class)->find($data['distributor']);
        $distributorProducts = $this->em->getRepository(DistributorProducts::class)->findOneBy(
            [
                'product' => $data['product'],
                'distributor' => $data['distributor']
            ]
        );
        $tracking = false;
        $response['unitPrice'] = 0.00;
        $response['stockLevel'] = 0;

        if($distributorProducts == null){

            $distributorProducts = new DistributorProducts();

        } else {

            if($distributorProducts->getStockCount() == 0){

                $tracking = true;
            }
        }

        if(!empty($data['product']) && !empty($data['distributor'])){

            $trackingId = $distributor->getTracking()->getId();

            $distributorProducts->setDistributor($distributor);
            $distributorProducts->setProduct($product);
            $distributorProducts->setSku($data['sku']);
            $distributorProducts->setItemId($data['itemId']);
            $distributorProducts->setTaxExempt($data['taxExempt']);
            $distributorProducts->setIsActive(1);

            $taxExempt = 0;

            if(!empty($data['taxExempt'])){

                $taxExempt = $data['taxExempt'];
            }

            $distributorProducts->setTaxExempt($taxExempt);

            if($trackingId == 3)
            {
                $distributorProducts->setUnitPrice($data['unitPrice']);
                $distributorProducts->setStockCount((int)$data['stockCount']);
            }

            // Get stock and price from API
            if($trackingId == 1){

                // Retrieve price & stock from api
                $distributorId = $distributor->getId();
                $priceStockLevels = json_decode($this->forward('App\Controller\ProductsController::zohoRetrieveItem',[
                    'distributorId' => $distributorId,
                    'itemId' => $data['itemId'],
                ])->getContent(), true);

                $response['unitPrice'] = $priceStockLevels['unitPrice'] ?? 0.00;
                $response['stockLevel'] = $priceStockLevels['stockLevel'] ?? 0;

                $distributorProducts->setUnitPrice($response['unitPrice']);
                $distributorProducts->setStockCount($response['stockLevel']);
            }

            $this->em->persist($distributorProducts);
            $this->em->flush();

            // Update parent stock level
            $stockCount = $this->em->getRepository(DistributorProducts::class)->getProductStockCount($product->getId());

            $product->setStockCount($stockCount[0][1]);

            // Get the lowest price
            $lowestPrice = $this->em->getRepository(DistributorProducts::class)->getLowestPrice($product->getId());

            $product->setUnitPrice($lowestPrice[0]['unitPrice'] ?? 0.00);

            $this->em->persist($product);
            $this->em->flush();

            // Availability Tracker
            $availabilityTracker = '';

            if($tracking){

                $availabilityTracker = $this->em->getRepository(AvailabilityTracker::class)->findBy([
                    'product' => $product->getId(),
                    'distributor' => $data['distributor'],
                    'isSent' => 0,
                ]);

                foreach($availabilityTracker as $tracker){

                    $methodId = $tracker->getCommunication()->getCommunicationMethod()->getId();
                    $sendTo = $tracker->getCommunication()->getSendTo();
                    $product = $tracker->getProduct();

                    // In app notifications
                    if($methodId == 1){

                        $notifications = new Notifications();

                        $notifications->setClinic($tracker->getClinic());
                        $notifications->setIsRead(0);
                        $notifications->setIsReadDistributor(0);
                        $notifications->setIsActive(1);
                        $notifications->setAvailabilityTracker($tracker);

                        $this->em->persist($notifications);
                        $this->em->flush();

                        // Get the newly created notification
                        $notification = '
                        <table class="w-100">
                            <tr>
                                <td><span class="badge bg-success me-3">New Stock</span></td>
                                <td>'. $product->getName() .' '. $product->getDosage() . $product->getUnit() .'</td>
                                <td>
                                    <a href="#" class="delete-notification" data-notification-id="'. $notifications->getId() .'">
                                        <i class="fa-solid fa-xmark text-black-25 ms-3 float-end"></i>
                                    </a>
                                </td>
                            </tr>
                        </table>';

                        $notifications = $this->em->getRepository(Notifications::class)->find($notifications->getId());

                        $notifications->setNotification($notification);

                        $this->em->persist($notifications);
                        $this->em->flush();

                    // Email notifications
                    } elseif($methodId == 2){

                        $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                        $body .= '<tr><td colspan="2">'. $product->getName() .' '. $product->getDosage() . $product->getUnit() .' is back in stock</td></tr>';
                        $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                        $body .= '<tr>';
                        $body .= '    <td><b>Distributor: </b></td>';
                        $body .= '    <td>'. $tracker->getDistributor()->getDistributorName() .'</td>';
                        $body .= '</tr>';
                        $body .= '<tr>';
                        $body .= '    <td><b>Stock Level: </b></td>';
                        $body .= '    <td>'. $tracker->getProduct()->getDistributorProducts()[0]->getStockCount() .'</td>';
                        $body .= '</tr>';
                        $body .= '</table>';

                        $email = (new Email())
                        ->from($this->getParameter('app.email_from'))
                        ->addTo($sendTo)
                        ->subject('Fluid Stock Level Update')
                        ->html($body);

                        $mailer->send($email);

                    // Text notifications
                    } elseif($methodId == 3){

                    }

                    $availabilityTracker = $this->em->getRepository(AvailabilityTracker::class)->find($tracker->getId());
                    $availabilityTracker->setIsSent(1);

                    $this->em->persist($availabilityTracker);
                    $this->em->flush();
                }
            }

            $response['flash'] = '<b><i class="fa-solid fa-circle-check"></i></i></b> '. $product->getName() .' successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response['flash'] = 'An error occurred';
        }

        return new JsonResponse($response);
    }

    #[Route('/distributors/error', name: 'distributor_error_500')]
    public function distributor500ErrorAction(Request $request): Response
    {
        $id = $this->getUser()->getDistributor()->getId();

        if($id == null){

            return $this->render('security/login.html.twig', [
                'last_username' => '',
                'error' => '',
                'csrf_token_intention' => 'authenticate',
                'user_type' => 'distributors',

            ]);
        }

        return $this->render('bundles/TwigBundle/Exception/error500.html.twig',[
            'type' => 'distributors',
            'id' => $id,

        ]);
    }

    #[Route('/distributors/zoho/set/refresh-token', name: 'zoho_set_refresh_token')]
    public function setZohoRefreshTokenAction(Request $request): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $apiDetails = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $distributorId,
        ]);
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if($code != null) {

            $curl = curl_init();
            $clientId = $this->encryptor->decrypt($apiDetails->getClientId());
            $clientSecret = $this->encryptor->decrypt($apiDetails->getClientSecret());
            $endpoint = 'https://accounts.zoho.com/oauth/v2/token?code=' . $code . '&client_id=' . $clientId . '&';
            $endpoint .= 'client_secret=' . $clientSecret . '&redirect_uri=https://fluid.vet/distributors/zoho/set/refresh-token&';
            $endpoint .= 'grant_type=authorization_code';

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

            if(array_key_exists('refresh_token', $response)){

                $session = $this->requestStack->getSession();
                $session->set('accessToken', $response['access_token']);
                $session->set('refreshToken', $response['refresh_token']);

                $refreshToken = new RefreshTokens();
                $api = $this->getUser()->getDistributor()->getApiDetails();

                $refreshToken->setToken($response['refresh_token']);
                $refreshToken->setApiDetails($api);

                $this->em->persist($refreshToken);
                $this->em->flush();

                return $this->redirectToRoute('distributor_manage_inventory');

            } elseif(array_key_exists('error', $response)){

                echo $response['error'];
            }

        } elseif($error != null){

            echo $error;

            file_put_contents(__DIR__ . '/../../public/zoho.log', date('Y-m-d H:i:s') .': '. $error . "\n", FILE_APPEND);
        }

        return new JsonResponse('');
    }

    #[Route('/distributors/get/refresh-token', name: 'distributor_get_access_token')]
    public function distributorGetRefreshTokenAction(Request $request): Response
    {
        $id = $this->getUser()->getDistributor()->getId();
        $button = false;
        $token = false;
        $error = false;

        if($id == null){

            return $this->render('security/login.html.twig', [
                'last_username' => '',
                'error' => '',
                'csrf_token_intention' => 'authenticate',
                'user_type' => 'distributors',

            ]);
        }

        $api = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $id,
        ]);

        // Check client id & secret exist
        if($api == null){

            $error = true;

        } else {

            $refreshTokens = $api->getRefreshTokens();

            if(count($refreshTokens) == 0){

                $button = true;

            } else {

                $token = true;
            }
        }

        return new JsonResponse([
            'token' => $token,
            'button' => $button,
            'error' => $error
        ]);
    }

    #[Route('/distributors/zoho/get/access-token', name: 'clinics_get_zoho_access_token')]
    public function clinicsGetAccessTokenAction(Request $request): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $apiDetails = $this->em->getRepository(ApiDetails::class)->findOneBy([
            'distributor' => $distributorId,
        ]);
        $code = $request->get('code');
        $error = $request->get('error');

        if($code != null) {

            $curl = curl_init();
            $clientId = $this->encryptor->decrypt($apiDetails->getClientId());
            $clientSecret = $this->encryptor->decrypt($apiDetails->getClientSecret());
            $endpoint = 'https://accounts.zoho.com/oauth/v2/token?code=' . $code . '&client_id=' . $clientId . '&';
            $endpoint .= 'client_secret=' . $clientSecret . '&redirect_uri=https://fluid.vet/clinics/zoho/access-token&';
            $endpoint .= 'grant_type=authorization_code';

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

            if(array_key_exists('refresh_token', $response)){

                $session = $this->requestStack->getSession();
                $session->set('accessToken', $response['access_token']);
                $session->set('refreshToken', $response['refresh_token']);

                $refreshToken = new RefreshTokens();
                $distributor = $this->getUser()->getDistributor();

                $refreshToken->setToken($response['refresh_token']);
                $refreshToken->setDistributor($distributor);

                $this->em->persist($refreshToken);
                $this->em->flush();

                $this->redirectToRoute('distributor_manage_inventory');

            } elseif(array_key_exists('error', $response)){

                echo $response['error'];
            }

        } elseif($error != null){

            echo $error;
        }

        return new JsonResponse('');
    }

    #[Route('/distributors/update-tracking-id', name: 'update_tracking_id')]
    public function updateTrackingIdAction(Request $request): Response
    {
        $distributor = $this->em->getRepository(Distributors::class)->find((int)$request->request->get('distributor-id'));
        $tracking = $this->em->getRepository(Tracking::class)->find((int)$request->request->get('tracking-id'));

        $distributor->setTracking($tracking);

        $this->em->persist($distributor);
        $this->em->flush();

        $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Stock Tracking Method successfully saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/distributors/download/inventory', name: 'download_inventory_csv')]
    public function downloadInventoryAction(Request $request): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $distributorProducts = $this->em->getRepository(DistributorProducts::class)->findBy([
            'distributor' => $distributorId,
        ]);
        $fileName = __DIR__ ."/../../public/csv/distributors/inventory-". $distributorId .".csv";

        $fp = fopen($fileName, "w+");
        $line = [
            'ID#',
            'Name',
            'Dosage',
            'Stock Level',
            'Unit Price',
        ];

        fputcsv($fp, $line, ',');

        foreach ($distributorProducts as $distributorProduct)
        {
            $line = [
                $distributorProduct->getId(),
                $distributorProduct->getProduct()->getName(),
                $distributorProduct->getProduct()->getDosage(),
                $distributorProduct->getStockCount(),
                $distributorProduct->getUnitPrice(),
            ];

            fputcsv($fp, $line, ',');
        }

        fclose($fp);

        return $this->file($fileName);
    }

    #[Route('/distributors/upload/inventory', name: 'upload_inventory_csv')]
    public function uploadInventoryAction(Request $request): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $filePath = __DIR__ .'/../../public/csv/distributors/';
        $fileName = 'inventory-'. $distributorId .'.csv';
        $uploadedFile = $_FILES['file'];
        $fileType = strtolower(pathinfo($filePath . $fileName,PATHINFO_EXTENSION));
        $i = 0;

        if($fileType == 'csv')
        {

            if(move_uploaded_file($uploadedFile['tmp_name'], $filePath . $fileName))
            {
                $process = new Process([
                    __DIR__ . '/../../bin/console',
                    'app:update-inventory',
                    14
                ]);
                $process->run();

                // executes after the command finishes
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            };
        }

        $response['flash'] = '<b><i class="fa-solid fa-circle-check"></i></i></b> Inventory Successfully saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    public function generateInventoryXl($orderId, $distributorId, $status)
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
        <table style="width: 100%; border: none; border-collapse: collapse; font-size: 12px">
            <tr>
                <td style=" line-height: 25px">
                    <img 
                        src="'. __DIR__ .'/../../public/images/logos/'. $distributor->getLogo() .'"
                        style="width:100%; max-width: 200px"
                    >
                    <br>
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

    private function generatePassword()
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

        $this->plainPassword = str_shuffle($password);

        return $this->plainPassword;
    }

    public function createDistributorUserForm()
    {
        $distributorUsers = new DistributorUsers();

        return $this->createForm(DistributorUsersFormType::class, $distributorUsers);
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
