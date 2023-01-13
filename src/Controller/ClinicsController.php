<?php

namespace App\Controller;

use App\Entity\Addresses;
use App\Entity\Baskets;
use App\Entity\ClinicCommunicationMethods;
use App\Entity\Clinics;
use App\Entity\ClinicUserPermissions;
use App\Entity\ClinicUsers;
use App\Entity\CommunicationMethods;
use App\Entity\Countries;
use App\Entity\Distributors;
use App\Entity\DistributorUsers;
use App\Entity\Lists;
use App\Entity\RestrictedDomains;
use App\Entity\Species;
use App\Entity\UserPermissions;
use App\Form\AddressesFormType;
use App\Form\ClinicCommunicationMethodsFormType;
use App\Form\ClinicFormType;
use App\Form\ClinicUsersFormType;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ClinicsController extends AbstractController
{
    const ITEMS_PER_PAGE = 12;
    private $em;
    private $plainPassword;
    private $encryptor;
    private $mailer;

    public function __construct(EntityManagerInterface $em, Encryptor $encryptor, MailerInterface $mailer) {

        $this->em = $em;
        $this->encryptor = $encryptor;
        $this->mailer = $mailer;
    }

    #[Route('/clinics/register', name: 'clinic_reg')]
    public function clinicReg(Request $request): Response
    {
        $clinics = new Clinics();
        $clinicUsers = new ClinicUsers();

        $clinics->getClinicUsers()->add($clinicUsers);

        $form = $this->createForm(ClinicFormType::class, $clinics)->createView();
        $countries = $this->em->getRepository(Countries::class)->findBy([
            'isActive' => 1,
        ]);

        return $this->render('frontend/clinics/register.html.twig', [
            'form' => $form,
            'countries' => $countries,
        ]);
    }

    protected function createClinicForm()
    {
        $clinics = new Clinics();

        return $this->createForm(ClinicFormType::class, $clinics);
    }

    public function createClinicsAddressesForm()
    {
        $methods = new Addresses();

        return $this->createForm(AddressesFormType::class, $methods);
    }

    public function createClinicCommunicationMethodsForm()
    {
        $communicationMethods = new ClinicCommunicationMethods();

        return $this->createForm(ClinicCommunicationMethodsFormType::class, $communicationMethods);
    }

    public function createClinicUserForm()
    {
        $clinicUsers = new ClinicUsers();

        return $this->createForm(ClinicUsersFormType::class, $clinicUsers);
    }

    #[Route('/clinics/register/create', name: 'clinic_create')]
    public function clinicsCreateAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $clinics = $this->em->getRepository(Clinics::class)->findOneBy(['hashedEmail' => md5($data->get('email'))]);

        if($clinics == null) {

            $clinics = new Clinics();

            $plainTextPwd = $this->generatePassword();

            if (!empty($plainTextPwd)) {

                $domainName = explode('@', $data->get('email'));
                $country = $this->em->getRepository(Countries::class)->find($data->get('country'));

                $clinics->setClinicName($this->encryptor->encrypt($data->get('clinicName')));
                $clinics->setEmail($this->encryptor->encrypt($data->get('email')));
                $clinics->setHashedEmail(md5($data->get('email')));
                $clinics->setDomainName(md5($domainName[1]));
                $clinics->setTelephone($this->encryptor->encrypt($data->get('clinic-telephone')));
                $clinics->setIntlCode($this->encryptor->encrypt($data->get('clinic-intl-code')));
                $clinics->setIsoCode($this->encryptor->encrypt($data->get('clinic-iso-code')));
                $clinics->setCountry($country);
                $clinics->setIsApproved(0);

                $this->em->persist($clinics);
                $this->em->flush();

                // Create user
                $clinic = $this->em->getRepository(Clinics::class)->findOneBy([
                    'hashedEmail' => md5($data->get('email')),
                ]);
                $clinicUsers = new ClinicUsers();

                $hashedPwd = $passwordHasher->hashPassword($clinicUsers, $plainTextPwd);

                $clinicUsers->setClinic($clinic);
                $clinicUsers->setFirstName($this->encryptor->encrypt($data->get('firstName')));
                $clinicUsers->setLastName($this->encryptor->encrypt($data->get('lastName')));
                $clinicUsers->setPosition($this->encryptor->encrypt($data->get('position')));
                $clinicUsers->setEmail($this->encryptor->encrypt($data->get('email')));
                $clinicUsers->setHashedEmail(md5($data->get('email')));
                $clinicUsers->setTelephone($this->encryptor->encrypt($data->get('user-telephone')));
                $clinicUsers->setIntlCode($this->encryptor->encrypt($data->get('clinic-intl-code')));
                $clinicUsers->setIsoCode($this->encryptor->encrypt($data->get('clinic-iso-code')));
                $clinicUsers->setRoles(['ROLE_CLINIC']);
                $clinicUsers->setPassword($hashedPwd);
                $clinicUsers->setIsPrimary(1);

                $this->em->persist($clinicUsers);

                // Assign Full Permissions
                $userPermissions = $this->em->getRepository(UserPermissions::class)->findBy([
                    'isClinic' => 1,
                ]);

                foreach($userPermissions as $userPermission){

                    $clinicUserPermissions = new ClinicUserPermissions();

                    $clinicUserPermissions->setClinic($clinic);
                    $clinicUserPermissions->setUser($clinicUsers);
                    $clinicUserPermissions->setPermission($userPermission);

                    $this->em->persist($clinicUserPermissions);
                }

                // Create Default Basket
                $basket = new Baskets();

                $firstName = $this->encryptor->decrypt($clinicUsers->getFirstName());
                $lastName = $this->encryptor->decrypt($clinicUsers->getLastName());

                $basket->setClinic($clinic);
                $basket->setName('Fluid Commerce');
                $basket->setTotal(0);
                $basket->setStatus('active');
                $basket->setIsDefault(1);
                $basket->setSavedBy($this->encryptor->encrypt($firstName .' '. $lastName));

                $this->em->persist($basket);

                // Create In App Communication Method
                $clinicCommunicationMethod = new ClinicCommunicationMethods();
                $communicationMethod = $this->em->getRepository(CommunicationMethods::class)->find(1);

                $clinicCommunicationMethod->setClinic($clinic);
                $clinicCommunicationMethod->setCommunicationMethod($communicationMethod);
                $clinicCommunicationMethod->setSendTo($this->encryptor->encrypt($data->get('email')));
                $clinicCommunicationMethod->setIsDefault(1);
                $clinicCommunicationMethod->setIsActive(1);

                $this->em->persist($clinicCommunicationMethod);

                // Create Favourites List
                $favourite = new Lists();

                $favourite->setClinic($clinic);
                $favourite->setListType('favourite');
                $favourite->setName('Favourite Items');
                $favourite->setItemCount(0);
                $favourite->setIsProtected(1);

                $this->em->persist($favourite);
                $this->em->flush();

                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                $body .= '<tr><td colspan="2">Hi '. $this->encryptor->decrypt($clinicUsers->getFirstName()) .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/clinics/login">https://'. $_SERVER['HTTP_HOST'] .'/clinics/login</a></td>';
                $body .= '</tr>';
                $body .= '<tr>';
                $body .= '    <td><b>Username: </b></td>';
                $body .= '    <td>'. $this->encryptor->decrypt($clinicUsers->getEmail()) .'</td>';
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

    #[Route('/clinics/register/check-email', name: 'clinic_check_email')]
    public function clinicsCheckEmailAction(Request $request): Response
    {
        $email = $request->request->get('email');
        $domainName = explode('@', $email);
        $response['response'] = true;
        $restrictedDomains = $this->em->getRepository(RestrictedDomains::class)->arrayFindAll();
        $firstName = '';

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

        if($distributor != null || $distributorUsers != null || $clinic != null || $clinicUsers != null || $clinicDomain != null || $distributorDomain != null){

            $response['response'] = false;
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/update/personal-information', name: 'clinic_update_personal_information')]
    public function clinicsUpdatePersonalInformationAction(Request $request): Response
    {
        $data = $request->request;
        $username = $this->get('security.token_storage')->getToken()->getUser()->getUserIdentifier();
        $clinics = $this->em->getRepository(Clinics::class)->findOneBy(['email' => $username]);

        if($clinics != null) {

            $clinics->setFirstName($data->get('first_name'));
            $clinics->setLastName($data->get('last_name'));
            $clinics->setTelephone($data->get('telephone'));
            $clinics->setPosition($data->get('position'));

            $this->em->persist($clinics);
            $this->em->flush();

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Personal details successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $response = '<b><i class="fas fa-check-circle"></i> Personal details successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/update/company-information', name: 'clinic_update_company_information')]
    public function clinicsUpdateCompanyInformationAction(Request $request): Response
    {
        $data = $request->request->get('clinic_form');
        $clinicId = $this->getUser()->getClinic()->getId();
        $clinics = $this->em->getRepository(Clinics::class)->find($clinicId);
        $isApproved = (bool) $clinics->getIsApproved() ?? false;
        $tradeLicense = $_FILES['clinic_form']['name']['trade-license-file'];
        $tradeLicenseNo = $data['trade-license-no'];
        $tradeLicenseExpDate = $data['trade-license-exp-date'];

        // Account approval required if reg docs change
        if(
            !empty($tradeLicense) || $tradeLicenseNo != $clinics->getTradeLicenseNo() ||
            $tradeLicenseExpDate != $clinics->getTradeLicenseExpDate()->format('Y-m-d')
        )
        {
            $clinics->setIsApproved(0);
            $isApproved = false;
        }

        if($clinics != null)
        {
            $domainName = explode('@', $data['email']);

            $clinics->setClinicName($this->encryptor->encrypt($data['clinic-name']));
            $clinics->setEmail($this->encryptor->encrypt($data['email']));
            $clinics->setDomainName(md5($domainName[1]));
            $clinics->setTelephone($this->encryptor->encrypt($data['telephone']));
            $clinics->setIsoCode($this->encryptor->encrypt($data['iso-code']));
            $clinics->setIntlCode($this->encryptor->encrypt($data['intl-code']));
            $clinics->setManagerFirstName($this->encryptor->encrypt($data['manager-first-name']));
            $clinics->setManagerLastName($this->encryptor->encrypt($data['manager-last-name']));
            $clinics->setManagerIdNo($this->encryptor->encrypt($data['manager-id-no']));
            $clinics->setManagerIdExpDate(new \DateTime($data['manager-id-exp-date']));
            $clinics->setTradeLicenseNo($this->encryptor->encrypt($data['trade-license-no']));
            $clinics->setTradeLicenseExpDate(new \DateTime($data['trade-license-exp-date']));

            // Trade License
            if(!empty($_FILES['clinic_form']['name']['trade-license-file']))
            {
                $extension = pathinfo($_FILES['clinic_form']['name']['trade-license-file'], PATHINFO_EXTENSION);
                $file = $clinics->getId() . '-' . uniqid() . '.' . $extension;
                $targetFile = __DIR__ . '/../../public/documents/' . $file;

                if(move_uploaded_file($_FILES['clinic_form']['tmp_name']['trade-license-file'], $targetFile)) {

                    $clinics->setTradeLicense($file);
                }
            }

            // Logo
            if(!empty($_FILES['clinic_form']['name']['logo']))
            {
                $extension = pathinfo($_FILES['clinic_form']['name']['logo'], PATHINFO_EXTENSION);
                $file = $clinics->getId() . '-' . uniqid() . '.' . $extension;
                $targetFile = __DIR__ . '/../../public/images/logos/' . $file;

                if(move_uploaded_file($_FILES['clinic_form']['tmp_name']['logo'], $targetFile)) {

                    $clinics->setLogo($file);
                }
            }

            $this->em->persist($clinics);
            $this->em->flush();

            // Send Approval Email
            if(!$isApproved)
            {
                $orderUrl = $this->getParameter('app.base_url') . '/admin/clinic/'. $clinics->getId();
                $html = '<p>Please <a href="'. $orderUrl .'">click here</a> the clinics details.</p><br>';

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

            $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Company details successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }
        else
        {
            $response = '<b><i class="fas fa-check-circle"></i> An error occurred.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/update/copy', name: 'clinic_update_copy')]
    public function clinicsUpdateCopyAction(Request $request): Response
    {
        $response = [];

        if($this->getUser() == null)
        {
            return new JsonResponse('Please login..');
        }

        $clinic = $this->getUser()->getClinic();
        $copy = $request->request->get('copy');
        $method = $request->request->get('method');

        $clinic->$method($copy);

        $this->em->persist($clinic);
        $this->em->flush();

        $response['flash'] = '<b><i class="fa-solid fa-circle-check"></i></i></b> Successfully saved.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/get-company-information', name: 'get_company_information')]
    public function clinicsGetCompanyInformationAction(Request $request): Response
    {
        $species = $this->em->getRepository(Species::class)->findByNameAsc();
        $permissions = json_decode($request->get('permissions'), true);
        $countries = $this->em->getRepository(Countries::class)->findBy([
            'isActive' => 1,
        ]);

        // Permissions
        if(!in_array(10, $permissions))
        {
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

            return new JsonResponse($response);
        }

        $clinic = $this->getUser()->getClinic();

        // Ensure trade license is uploaded
        $tradeLicenseAsterisc = '';
        if($clinic->getTradeLicense() != null)
        {
            $btnDownload = '
            <span class="input-group-text">
                <a href="' . $this->generateUrl('download_trade_license', ['trade-license' => $clinic->getTradeLicense()]) . '">
                    <i class="fa-regular fa-download"></i>
                </a>
            </span>';
        }
        else
        {
            $tradeLicenseAsterisc = ' <span class="text-danger">*</span>';
            $btnDownload = '';
        }

        $managerIdExpDate = '';
        $tradingLicenseExpDate = '';

        if($clinic->getManagerIdExpDate() != null)
        {
            $managerIdExpDate = $clinic->getManagerIdExpDate()->format('Y-m-d');
        }

        if($clinic->getTradeLicenseExpDate() != null)
        {
            $tradingLicenseExpDate = $clinic->getTradeLicenseExpDate()->format('Y-m-d');
        }

        $response = '
        <div class="row position-relative" id="account_settings">
            <div class="col-12 text-center pt-3 pb-3" id="order_header">
                <h4 class="text-primary text-truncate">Account & Settings</h4>
            </div>
            
            <!-- Tabs -->
            <div class="col-12 nav nav-tabs">
                <div class="nav-item" role="button" id="company_information_tab">
                    <span class="nav-link text-primary active">
                        <i class="fa-regular fa-circle-info fa-fw d-inline d-lg-none"></i>
                        <span class="d-none d-lg-block">Company Information</span>
                    </span>
                </div>
                <div class="nav-item" role="button" id="about_tab">
                    <span class="nav-link text-primary">
                        <i class="fa-regular fa-circle-question fa-fw d-inline d-lg-none"></i>
                        <span class="d-none d-lg-block">About</span>
                    </span>
                </div>
                <div class="nav-item" role="button" id="operating_hours_tab">
                    <span class="nav-link text-primary">
                        <i class="fa-regular fa-clock fa-fw d-inline d-lg-none"></i>
                        <span class="d-none d-lg-block">Operating Hours</span>
                    </span>
                </div>
                <div class="nav-item" role="button" id="refund_policy_tab">
                    <span class="nav-link text-primary">
                        <i class="fa-regular fa-rotate-left fa-fw d-inline d-lg-none"></i>
                        <span class="d-none d-lg-block">Refund Policy</span>
                    </span>
                </div>
                <div class="nav-item" role="button" id="sales_tax_policy_tab">
                    <span class="nav-link text-primary">
                        <i class="fa-regular fa-scale-unbalanced fa-fw d-inline d-lg-none"></i>
                        <span class="d-none d-lg-block">Sales Tax Policy</span>
                    </span>
                </div>
                <div class="nav-item" role="button" id="shipping_policy_tab">
                    <span class="nav-link text-primary">
                        <i class="fa-regular fa-truck-ramp-box fa-fw d-inline d-lg-none"></i>
                        <span class="d-none d-lg-block">Shipping Policy</span>
                    </span>
                </div>
            </div>
            
            <!-- Company Information -->
            <div class="col-12" id="company_information_panel">
                <form name="form_clinic_information" id="form_clinic_information" method="post">
                    <div class="row pt-0 pt-sm-3 border-left border-right bg-light border-top">
                        <!-- Clinic name -->
                        <div class="col-12 col-sm-6 pt-3 pt-sm-0">
                            <label>
                                Business Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="clinic_form[clinic-name]" 
                                id="clinic_name" 
                                class="form-control" 
                                placeholder="Business Name"
                                value="'. $this->encryptor->decrypt($clinic->getClinicName()) .'"
                            >
                            <div class="hidden_msg" id="error_first_name">
                                Required Field
                            </div>
                        </div>
                        
                        <!-- Logo -->
                        <div class="col-sm-6 col-12" id="logo_file">
                            <label>
                                Logo
                            </label>
                            <div class="input-group">
                                <input type="file" id="logo" name="clinic_form[logo]" class="form-control" placeholder="Logo" value="">';

                                if($clinic->getLogo() != null)
                                {
                                    $response .= '
                                    <span class="input-group-text">
                                        <a href="" data-bs-toggle="modal" data-bs-target="#modal_logo">
                                            <i class="fa-regular fa-image"></i>
                                        </a>
                                    </span>';
                            }

                            $response .= '
                            </div>
                        </div>
                    </div>';

                    $file = $clinic->getLogo() ?? 'image-not-found.jpg';
                    $logo = $this->getParameter('app.base_url') .'/images/logos/'. $file;

                    $response .= '
                    <!-- Modal Logo -->
                    <div class="modal fade" id="modal_logo" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="border: none; padding-bottom: 0">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" style="padding: 0">
                                    <div class="row">
                                        <div class="col-12 mb-0 text-center">
                                            <img src="'. $logo .'" id="logo_img" class="img-fluid">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manager Name -->
                    <div class="row pt-0 pt-sm-3 border-left border-right bg-light">
            
                        <!-- First Name -->
                        <div class="col-12 col-sm-6 pt-3 pt-sm-0">
                            <label>
                                Managers First Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="clinic_form[manager-first-name]" 
                                id="manager_first_name" 
                                class="form-control" 
                                placeholder="Managers First Name"
                                value="'. $this->encryptor->decrypt($clinic->getManagerFirstName()) .'"
                            >
                            <div class="hidden_msg" id="error_manager_first_name">
                                Required Field
                            </div>
                        </div>
            
                        <!-- Last Name -->
                        <div class="col-12 col-sm-6 pt-3 pt-sm-0">
                            <label>
                                Managers Last Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="clinic_form[manager-last-name]" 
                                id="manager_last_name" 
                                class="form-control" 
                                placeholder="Managers Last Name"
                                value="'. $this->encryptor->decrypt($clinic->getManagerLastName()) .'"
                            >
                            <div class="hidden_msg" id="error_manager_last_name">
                                Required Field
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manager ID -->
                    <div class="row pt-0 pt-sm-3 border-left border-right bg-light">
            
                        <!-- ID No -->
                        <div class="col-12 col-sm-6 pt-3 pt-sm-0">
                            <label>
                                Managers Emirates ID No. <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="clinic_form[manager-id-no]" 
                                id="manager_id_no" 
                                class="form-control" 
                                placeholder="Managers Emirates ID Number"
                                value="'. $this->encryptor->decrypt($clinic->getManagerIdNo()) .'"
                            >
                            <div class="hidden_msg" id="error_manager_id_no">
                                Required Field
                            </div>
                        </div>
            
                        <!-- ID Expiry Date -->
                        <div class="col-12 col-sm-6 pt-3 pt-sm-0">
                            <label>
                                Managers ID Exp. Date <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="clinic_form[manager-id-exp-date]" 
                                id="manager_id_exp_date" 
                                class="form-control" 
                                placeholder="Managers ID Expiry Date"
                                value="'. $managerIdExpDate .'"
                            >
                            <div class="hidden_msg" id="error_manager_id_exp_date">
                                Required Field
                            </div>
                        </div>
                    </div>
                    
                    <div class="row pt-0 pt-sm-3 border-left border-right bg-light">
            
                        <!-- Email -->
                        <div class="col-12 col-sm-4 pt-3 pt-sm-0">
                            <label>
                                Business Email Address <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="clinic_form[email]" 
                                id="clinic_email" 
                                class="form-control" 
                                placeholder="Business Email Address"
                                value="'. $this->encryptor->decrypt($clinic->getEmail()) .'"
                            >
                            <div class="hidden_msg" id="error_clinic_email">
                                Required Field
                            </div>
                        </div>
            
                        <!-- Telephone -->
                        <div class="col-12 col-sm-4 pt-3 pt-sm-0">
                            <label>Business Telephone  <span class="text-danger">*</span></label>
                            <input 
                                type="hidden"
                                name="iso-code" 
                                id="isocode" 
                                value="'. $this->encryptor->decrypt($clinic->getIsoCode()) .'"
                            >
                            <input 
                                type="hidden"
                                name="clinic_form[telephone]" 
                                id="clinic_telephone" 
                                value="'. $this->encryptor->decrypt($clinic->getTelephone()) .'"
                            >
                            <input 
                                type="text" 
                                name="mobile" 
                                id="mobile" 
                                name="mobile" 
                                class="form-control" 
                                placeholder="Telephone*"
                                value="'. $this->encryptor->decrypt($clinic->getTelephone()) .'"
                            >
                            <div class="hidden_msg" id="error_telephone">
                                Required Field
                            </div>
                        </div>
                        
                        <!-- Countries -->
                        <div class="col-12 col-sm-4 pt-3 pt-sm-0">
                            <label>Located In <span class="text-danger">*</span></label>
                            <select name="country" style="color:#b1abb0 !important" id="country" class="form-control">
                                <option value="">Country</option>';

                                foreach($countries as $country)
                                {
                                    $selected = '';

                                    if($country->getId() == $clinic->getCountry()->getId())
                                    {
                                        $selected = 'selected';
                                    }

                                    $response .= '
                                    <option value="'. $country->getId() .'" '. $selected .'>'. $country->getName() .'</option>';
                                }

                            $response .= '
                            </select>
                            <div class="hidden_msg" id="error_country">
                                Required Field
                            </div>
                        </div>
                    </div>
                    
                    <!-- Trading License -->
                    <div class="row pt-0 pt-sm-3 border-left border-right bg-light pb-0 pb-sm-5">
            
                        <!-- Upload License -->
                        <div class="col-12 col-sm-4 pt-3 pt-sm-0">
                            <label>
                                Trade License '. $tradeLicenseAsterisc .'
                            </label>
                            <div class="input-group">
                                <input 
                                    type="file" 
                                    name="clinic_form[trade-license-file]" 
                                    id="trade_license_file" 
                                    class="form-control"
                                >
                                '. $btnDownload .'
                            </div>
                            <div class="hidden_msg" id="error_trade_license_file">
                                Required Field
                            </div>
                        </div>
            
                        <!-- License No -->
                        <div class="col-12 col-sm-4 pt-3 pt-sm-0">
                            <label>
                                Trade License No. <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="clinic_form[trade-license-no]" 
                                id="trade_license_no" 
                                class="form-control" 
                                placeholder="Trade License Number"
                                value="'. $this->encryptor->decrypt($clinic->getTradeLicenseNo()) .'"
                            >
                            <div class="hidden_msg" id="error_trade_license_no">
                                Required Field
                            </div>
                        </div>
                        
                        <!-- License Exp Date -->
                        <div class="col-12 col-sm-4 pt-3 pt-sm-0">
                            <label>
                                Expiry Date <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="clinic_form[trade-license-exp-date]" 
                                id="trade_license_exp_date" 
                                class="form-control" 
                                placeholder="Trade License Expiry Date"
                                value="'. $tradingLicenseExpDate .'"
                            >
                            <div class="hidden_msg" id="error_trade_license_exp_date">
                                Required Field
                            </div>
                        </div>
                    </div>
            
                    <div class="row pt-3 pb-3 border-left border-right border-bottom bg-light justify-content-evenly">
            
                        <label class="mb-4 d-block">
                            Select All Species Treated By Your Practice
                        </label>';

                        foreach($species as $specie)
                        {
                            $response .= '
                            <!-- '. $specie->getName() .' -->
                            <div class="col-6 col-sm-4 col-md-2 text-center">
                                <div class="custom-control custom-checkbox image-checkbox" style="position: relative">
                                    <input type="checkbox" class="custom-control-input species-checkbox" id="species_'. strtolower($specie->getName()) .'">
                                    <label class="custom-control-label" for="species_'. strtolower($specie->getName()) .'">
                                        <i class="'. $specie->getIcon() .' species-icon" id="icon_'. strtolower($specie->getName()) .'"></i>
                                    </label>
                                </div>
                            </div>';
                        }

                    $response .= '
                    </div>
            
                    <div class="row mb-3">
                        <div class="col-12 mt-2 mb-5 ps-0 pe-0">
                            <button id="btn_personal_information" type="submit" class="btn btn-primary w-100">UPDATE CLINIC INFORMATION</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- About -->
            <div class="col-12 hidden" id="about_panel">
                <form name="form_about" id="form_about" method="post">
                    <input type="hidden" name="method" value="setAbout">
                    <div class="row pt-0 border-left border-right bg-light border-bottom">
                        <div class="col-12 py-3">
                            <textarea
                                class="form-control tinymce-selector"
                                name="copy"
                                id="about_copy"
                                placeholder="About Copy"
                            >'. $clinic->getAbout() .'</textarea>
                            <div class="hidden_msg" id="error_about_copy">
                                Required Field
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 mt-2 mb-5 ps-0 pe-0">
                            <button id="btn_about" type="submit" class="btn btn-primary w-100">UPDATE ABOUT</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Operating Hours -->
            <div class="col-12 hidden" id="operating_hours_panel">
                <form name="form_operating_hours" id="form_operating_hours" method="post">
                    <input type="hidden" name="method" value="setOperatingHours">
                    <div class="row pt-0 border-left border-right bg-light border-bottom">
                        <div class="col-12 py-3">
                            <textarea
                                class="form-control tinymce-selector"
                                name="copy"
                                id="operating_hours_copy"
                                placeholder="Operating Hours Copy"
                            >'. $clinic->getOperatingHours() .'</textarea>
                            <div class="hidden_msg" id="error_operating_hours_copy">
                                Required Field
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 mt-2 mb-5 ps-0 pe-0">
                            <button id="btn_about" type="submit" class="btn btn-primary w-100">UPDATE OPERATING HOURS</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Refund Policy -->
            <div class="col-12 hidden" id="refund_policy_panel">
                <form name="form_refund_policy" id="form_refund_policy" method="post">
                    <input type="hidden" name="method" value="setRefundPolicy">
                    <div class="row pt-0 border-left border-right bg-light border-bottom">
                        <div class="col-12 py-3">
                            <textarea
                                class="form-control tinymce-selector"
                                name="copy"
                                id="refund_policy_copy"
                                placeholder="Refund Policy Copy"
                            >'. $clinic->getRefundPolicy() .'</textarea>
                            <div class="hidden_msg" id="error_refund_policy_copy">
                                Required Field
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 mt-2 mb-5 ps-0 pe-0">
                            <button id="btn_about" type="submit" class="btn btn-primary w-100">UPDATE REFUND POLICY</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Sales Tax Policy -->
            <div class="col-12 hidden" id="sales_tax_policy_panel">
                <form name="form_sales_tax_policy" id="form_sales_tax_policy" method="post">
                    <input type="hidden" name="method" value="setSalesTaxPolicy">
                    <div class="row pt-0 border-left border-right bg-light border-bottom">
                        <div class="col-12 py-3">
                            <textarea
                                class="form-control tinymce-selector"
                                name="copy"
                                id="sales_tax_policy_copy"
                                placeholder="Sales Tax Policy Copy"
                            >'. $clinic->getSalesTaxPolicy() .'</textarea>
                            <div class="hidden_msg" id="error_sales_tax_policy_copy">
                                Required Field
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 mt-2 mb-5 ps-0 pe-0">
                            <button id="btn_about" type="submit" class="btn btn-primary w-100">UPDATE SALES TAX POLICY</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Shipping Policy -->
            <div class="col-12 hidden" id="shipping_policy_panel">
                <form name="form_shipping_policy" id="form_shipping_policy" method="post">
                    <input type="hidden" name="method" value="setShippingPolicy">
                    <div class="row pt-0 border-left border-right bg-light border-bottom">
                        <div class="col-12 py-3">
                            <textarea
                                class="form-control tinymce-selector"
                                name="copy"
                                id="shipping_policy_copy"
                                placeholder="Shipping Policy Copy"
                            >'. $clinic->getShippingPolicy() .'</textarea>
                            <div class="hidden_msg" id="error_shipping_policy_copy">
                                Required Field
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 mt-2 mb-5 ps-0 pe-0">
                            <button id="btn_about" type="submit" class="btn btn-primary w-100">UPDATE SHIPPING POLICY</button>
                        </div>
                    </div>
                </form>
            </div>
            
        </div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/addresse-refresh', name: 'clinic_refresh_addresses')]
    public function clinicRefreshAddressesAction(Request $request): Response
    {
        $clinicId = $this->get('security.token_storage')->getToken()->getUser()->getClinic()->getId();
        $methods = $this->em->getRepository(Clinics::class)->getClinicAddresses($clinicId);

        $html = '';

        foreach($methods[0]->getAddresses() as $address){

            $class = 'address-icon';

            if($address->getIsDefault() == 1){

                $class = 'is-default-address-icon';
            }

            $html .= '<div class="row t-row">
                    <div class="col-md-10" id="string_address_clinic_name_'. $address->getId() .'">
                        <div class="row">
                            <div class="col-md-2 t-cell text-truncate" id="string_address_clinic_name_'. $address->getId() .'">
                                '. $address->getClinicName() .'
                            </div>
                            <div class="col-md-2 t-cell text-truncate" id="string_address_telephone_'. $address->getId() .'">
                                '. $address->getTelephone() .'
                            </div>
                            <div class="col-md-4 t-cell text-truncate" id="string_address_address_'. $address->getId() .'">
                                '. $address->getAddress() .'
                            </div>
                            <div class="col-md-2 t-cell text-truncate" id="string_address_city_'. $address->getId() .'">
                                '. $address->getCity() .'
                            </div>
                            <div class="col-md-2 t-cell text-truncate" id="string_address_state_'. $address->getId() .'">
                                '. $address->getState() .'
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2" id="string_address_postal)_code'. $address->getId() .'">
                        <div class="row">
                            <div class="col-md-4 t-cell text-truncate" id="string_address_postal_code'. $address->getId() .'">
                                '. $address->getPostalCode() .'
                            </div>
                            <div class="col-md-8 t-cell">
                                <a href="" class="float-end" data-bs-toggle="modal" data-bs-target="#modal_address" id="address_update_'. $address->getId() .'">
                                    <i class="fa-solid fa-pen-to-square edit-icon"></i>
                                </a>
                                <a href="" class="delete-icon float-end" data-bs-toggle="modal" data-value="'. $address->getId() .'" data-bs-target="#modal_address_delete" id="address_delete_'. $address->getId() .'">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                                <a href="#" id="address_default_'. $address->getId() .'">
                                    <i class="fa-solid fa-star float-end '. $class.'"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        return new JsonResponse($html);
    }

    #[Route('/clinics/communication-refresh', name: 'clinic_refresh_communication')]
    public function clinicRefreshCommunicationMethodsAction(Request $request): Response
    {
        $clinicId = $this->get('security.token_storage')->getToken()->getUser()->getClinic()->getId();
        $methods = $this->em->getRepository(Clinics::class)->getClinicCommunicationMethods($clinicId);

        $html = '';
        //dd($methods[0]->getClinicCommunicationMethods());
        foreach($methods[0]->getClinicCommunicationMethods() as $method){

            $html .= '
            <div class="row t-row">
                <div class="col-md-4 t-cell text-truncate" id="">
                    '. $method->getCommunicationMethod()->getMethod() .'
                </div>
                <div class="col-md-4 t-cell text-truncate" id="">
                    '. $method->getSendTo() .'
                </div>
                <div class="col-md-4 t-cell text-truncate" id="">
                    <a href="" class="float-end" data-bs-toggle="modal" data-bs-target="#modal_communication_methods" id="communication_method_update_'. $method->getId() .'">
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>
                    <a href="" class="delete-icon float-end" data-bs-toggle="modal" data-value="'. $method->getId() .'" data-bs-target="#modal_method_delete" id="method_delete_'. $method->getId() .'">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            </div>';
        }

        return new JsonResponse($html);
    }

    #[Route('/download-trade-license/{trade-license}', name: 'download_trade_license')]
    public function downloadTradeLicenseAction(Request $request)
    {
        $path = __DIR__ . '/../../public/documents/';
        $tradeLicense = $path . $request->get('trade-license');
        $response = new BinaryFileResponse($tradeLicense);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($tradeLicense)
        );

        return $response;
    }

    #[Route('/clinics/error', name: 'clinic_error_500')]
    public function clinic500ErrorAction(Request $request): Response
    {
        $username = $this->getUser();
        $id = '';

        if($username != null) {

            $id = $this->getUser()->getClinic()->getId();
        }

        return $this->render('bundles/TwigBundle/Exception/error500.html.twig', [
            'type' => 'clinics',
            'id' => $id,
        ]);
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
}
