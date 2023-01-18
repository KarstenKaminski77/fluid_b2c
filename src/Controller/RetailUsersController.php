<?php

namespace App\Controller;

use App\Entity\Addresses;
use App\Entity\Baskets;
use App\Entity\ClinicRetailUsers;
use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\Countries;
use App\Entity\Distributors;
use App\Entity\DistributorUsers;
use App\Entity\Manufacturers;
use App\Entity\ManufacturerUsers;
use App\Entity\RetailUsers;
use App\Form\ResetPasswordRequestFormType;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RetailUsersController extends AbstractController
{
    private $plainPassword;
    private $em;
    private $emRemote;
    private $mailer;
    private $encryptor;
    private $passwordHasher;
    private $pageManager;

    const ITEMS_PER_PAGE = 10;

    public function __construct(
        ManagerRegistry $em, MailerInterface $mailer, Encryptor $encryptor,
        UserPasswordHasherInterface $passwordHasher, PaginationManager $pageManager
    )
    {
        $this->em = $em->getManager('default');
        $this->emRemote = $em->getManager('remote');
        $this->mailer = $mailer;
        $this->encryptor = $encryptor;
        $this->passwordHasher = $passwordHasher;
        $this->pageManager = $pageManager;
    }
    
    #[Route('/retail/register', name: 'retail_reg')]
    public function retailRegister(): Response
    {
        $countries = $this->em->getRepository(Countries::class)->findBy([
            'isActive' => 1
        ],[
            'name' => 'ASC'
        ]);
        return $this->render('frontend/retail/register.html.twig', [
            'controller_name' => 'RetailUsersController',
            'countries' => $countries,
        ]);
    }

    #[Route('/retail/register/create', name: 'retail_create')]
    public function retailCreateAction(Request $request): Response
    {
        $data = $request->request;
        $hashedEmail = md5($data->get('email'));
        $retailUser = $this->em->getRepository(RetailUsers::class)->findOneBy([
            'hashedEmail' => $hashedEmail,
        ]);

        if($retailUser == null)
        {
            $retailUser = new RetailUsers();

            $plainTextPwd = $this->generatePassword();

            // Create user
            if (!empty($plainTextPwd))
            {
                $hashedPwd = $this->passwordHasher->hashPassword($retailUser, $plainTextPwd);
                $country = $this->em->getRepository(Countries::class)->find($data->get('country'));

                $retailUser->setFirstName($this->encryptor->encrypt($data->get('first-name')));
                $retailUser->setLastName($this->encryptor->encrypt($data->get('last-name')));
                $retailUser->setEmail($this->encryptor->encrypt($data->get('email')));
                $retailUser->setHashedEmail(md5($data->get('email')));
                $retailUser->setPassword($hashedPwd);
                $retailUser->setRoles(['ROLE_RETAIL']);
                $retailUser->setTelephone($this->encryptor->encrypt($data->get('telephone')));
                $retailUser->setIntlCode($this->encryptor->encrypt($data->get('intl-code')));
                $retailUser->setIsoCode($this->encryptor->encrypt($data->get('iso-code')));
                $retailUser->setCountry($country);

                $this->em->persist($retailUser);
                $this->em->flush();


                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                $body .= '<tr><td colspan="2">Hi '. $data->get('first-name') .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to Fluid.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/retail/login">https://'. $_SERVER['HTTP_HOST'] .'/retail/login</a></td>';
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

                $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $body,
                ])->getContent();

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($data->get('email'))
                    ->subject('Fluid Login Credentials')
                    ->html($html);

                $this->mailer->send($email);
            }

            $response = 'Your Fluid account was successfully created, an email with your login credentials has been sent to your inbox.';

        } else {

            $response = false;
        }

        return new JsonResponse($response);
    }

    #[Route('/retail/search', name: 'retail_search')]
    #[Route('/retail/basket', name: 'retail_basket')]
    #[Route('/retail/personal-information', name: 'retail_personal_information')]
    #[Route('/retail/addresses/{pageId}', name: 'retail_addresses')]
    #[Route('/retail/clinic/about', name: 'retail_clinic_about')]
    #[Route('/retail/clinic/operating-hours', name: 'retail_clinic_operating_hours')]
    #[Route('/retail/clinic/refund-policy', name: 'retail_clinic_refund_policy')]
    #[Route('/retail/clinic/sales-tax-policy', name: 'retail_clinic_sales_tax_policy')]
    #[Route('/retail/clinic/shipping-policy', name: 'retail_clinic_shipping_policy')]
    public function retailBaseAction(Request $request): Response
    {
        if($this->getUser() == null)
        {
            return $this->redirectToRoute('retail_login');
        }

        $retailUserId = $this->getUser()->getId();
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);
        $firstName = $this->encryptor->decrypt($retailUser->getFirstName());
        $lastName = $this->encryptor->decrypt($retailUser->getLastName());
        $retailClinicId = $retailUser->getClinicId() ?? 0;
        $retailClinic = $this->emRemote->getRepository(Clinics::class)->find($retailClinicId);
        $pageId = $request->request->get('page_id') ?? 1;
        $isAjax = $request->request->get('is-ajax') ?? false;
        $html = '';

        // Get basket
        $basket = $this->em->getRepository(Baskets::class)->findOneBy([
            'retailUser' => $retailUserId,
        ]);

        if($basket == null)
        {
            $basket = new Baskets();

            $basket->setRetailUser($retailUser);
            $basket->setName('Fluid Commerce');
            $basket->setStatus('active');
            $basket->setIsDefault(1);
            $basket->setTotal(0,00);
            $basket->setClinicId($retailClinicId);
            $basket->setSavedBy($this->encryptor->encrypt($firstName .' '. $lastName));

            $this->em->persist($basket);
            $this->em->flush();
        }

        $basketId = $basket->getId();

        if($retailUser->getClinicId() == null)
        {
            $retailClinics = $this->emRemote->getRepository(Clinics::class)->adminFindAll(1);
            $results = $this->pageManager->paginate($retailClinics[0], $request, self::ITEMS_PER_PAGE);
            $pagination = $this->getPagination($pageId, $results);
            $clinicLogo = $this->getParameter('app.base_url') .'/images/logos/image-not-found.jpg';

            $html = '
            <div class="row pt-3">
                <div class="col-12 text-center mt-1 pt-3 pb-3">
                    <h4 class="text-primary text-truncate">Select a Clinic</h4>
                    <span class="d-none d-sm-inline mb-5 mt-2 text-center text-primary text-sm-start">
                        Select a clinic in order to place orders with them online.
                    </span>
                </div>
            </div>
            <div class="row mb-3">';

            foreach($results as $clinic)
            {
                $address = $this->em->getRepository(Addresses::class)->findOneBy([
                    'clinic' => $clinic->getId(),
                    'isDefaultBilling' => 1,
                ]);
                $logo = $this->getParameter('app.base_url') .'/images/logos/image-not-found.jpg';

                if($address == null)
                {
                    $address = 'No address available.';
                }
                else
                {
                    $address = $this->encryptor->decrypt($address->getAddress());
                }

                if($clinic->getLogo() != null)
                {
                    $logo = $this->getParameter('app.base_url') .'/images/logos/'. $clinic->getLogo();
                }

                $html .= '
                <div class="col-12 mx-2">
                    <div class="row">
                        <div class="col-12 col-sm-3 bg-white border-top border-left border-right px-3 pt-3 text-center mx-auto">
                            <img src="'. $logo .'" class="img-fluid" style="max-height: 120px">
                            <h6 class="mt-3">'. $this->encryptor->decrypt($clinic->getClinicName()) .'</h6>
                            <p class="m-0">'. $address .'</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-sm-3 bg-white p-3 text-center mx-auto border-bottom border-left border-right px-3 pb-3">
                            <button
                                class="btn btn-primary w-100 btn-retail-connect"
                                data-clinic-id="'. $clinic->getId() .'"
                                data-clinic-logo="'. $logo .'"
                                data-clinic-name="'. $this->encryptor->decrypt($clinic->getClinicName()) .'"
                                data-action="click->retail#onClickBtnConnect"
                            >
                                CONNECT
                            </button>
                        </div>
                    </div>
                </div>';
            }

            $html .= '
            </div>
            <!-- Modal Connect -->
            <div class="modal fade" id="modal_connect" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header" style="border: none; padding-bottom: 0">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="padding: 0">
                            <div class="row">
                                <div class="col-12 mb-0 text-center">
                                    <img src="" id="logo_img" class="img-fluid">
                                    <p class="fw-bold">Request an account with <span id="modal_clinic_name"></span?</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary w-sm-100 mb-3 mb-sm-0 w-sm-100" data-bs-dismiss="modal">CANCEL</button>
                            <button 
                                type="submit" 
                                class="btn btn-primary w-sm-100 mb-sm-0 w-sm-100" 
                                id="btn_request_connection"
                                data-retail-user-id="'. $retailUserId .'"
                                data-action="click->retail#onClickRequestConnection"
                            >CONNECT</button>
                        </div>
                    </div>
                </div>
            </div>';
        }
        else
        {
            $clinicLogo = $this->getParameter('app.base_url') .'/images/logos/image-not-found.jpg';

            if($retailClinic->getLogo() != null)
            {
                $clinicLogo = $this->getParameter('app.base_url') .'/images/logos/'. $retailClinic->getLogo();
            }
        }

        if($isAjax)
        {
            return new JsonResponse($html);
        }

        return $this->render('frontend/retail/index.html.twig',[
            'retailUser' => $retailUser,
            'html' => $html,
            'clinic' => $retailClinic,
            'clinicLogo' => $clinicLogo,
            'basketId' => $basketId,
        ]);
    }

    #[Route('/retail/register/check-email', name: 'retail_check_email')]
    public function retailCheckEmailAction(Request $request): Response
    {
        $email = $request->request->get('email');
        $domainName = explode('@', $email);
        $response['duplicate'] = false;
        $response['inValid'] = false;
        $firstName = '';

        // Validate Email Address
        if(count($domainName) !== 2)
        {
            $response['inValid'] = true;

            return new JsonResponse($response);

            die();
        }

        // Duplicate Email Address & Domains
        $manufacturer = $this->em->getRepository(Manufacturers::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

        $manufacturerDomain = $this->em->getRepository(Manufacturers::class)->findOneBy([
            'domainName' => md5($domainName[1]),
        ]);

        $manufacturerUsers = $this->em->getRepository(Manufacturers::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

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

        $retailUsers = $this->em->getRepository(RetailUsers::class)->findOneBy([
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

        if($manufacturerDomain != null)
        {
            $user = $this->em->getRepository(ManufacturerUsers::class)->findOneBy([
                'manufacturer' => $manufacturerDomain->getId(),
                'isPrimary' => 1
            ]);
            $firstName = $this->encryptor->decrypt($user->getFirstName());
        }

        if($retailUsers != null)
        {
            $user = $this->em->getRepository(RetailUsers::class)->findOneBy([
                'hashedEmail' => md5($email),
            ]);
            $firstName = $this->encryptor->decrypt($user->getFirstName());
        }

        $response['firstName'] = $firstName;

        if(
            $manufacturer != null || $manufacturerDomain != null || $manufacturerUsers != null ||
            $clinic != null || $clinicUsers != null || $clinicDomain != null || $retailUsers != null ||
            $distributor != null || $distributorUsers != null || $distributorDomain != null
        )
        {
            $response['duplicate'] = true;
        }

        return new JsonResponse($response);
    }

    #[Route('/retail/forgot-password', name: 'retail_forgot_password_request')]
    public function retailForgotPasswordAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $retailUser = $this->em->getRepository(RetailUsers::class)->findOneBy(
                [
                    'hashedEmail' => md5($request->request->get('reset_password_request_form')['email'])
                ]
            );

            if($retailUser != null){

                $resetToken = uniqid();

                $retailUser->setResetKey($resetToken);

                $this->em->persist($retailUser);
                $this->em->flush();

                $html = '
                <p>To reset your password, please visit the following link</p>
                <p>
                    <a
                        href="https://'. $_SERVER['HTTP_HOST'] .'/retail/reset/'. $resetToken .'"
                    >https://'. $_SERVER['HTTP_HOST'] .'/retail/reset/'. $resetToken .'</a>
                </p>';

                $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $html,
                ]);

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($this->encryptor->decrypt($retailUser->getEmail()))
                    ->subject('Fluid Password Reset')
                    ->html($html->getContent());

                $this->mailer->send($email);

                return $this->render('reset_password/retail_check_email.html.twig');
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/retail/reset/{token}', name: 'retail_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null, MailerInterface $mailer): Response
    {
        $plainTextPwd = $this->generatePassword();
        $retailUser = $this->em->getRepository(RetailUsers::class)->findOneBy([
            'resetKey' => $request->get('token')
        ]);

        if (!empty($plainTextPwd)) {

            $hashedPwd = $passwordHasher->hashPassword($retailUser, $plainTextPwd);

            $retailUser->setPassword($hashedPwd);

            $this->em->persist($retailUser);
            $this->em->flush();

            // Send Email
            $body  = '<p style="margin-bottom: 0">Hi '. $this->encryptor->decrypt($retailUser->getFirstName()) .',</p>';
            $body .= '<br>';
            $body .= '<p style="margin-bottom: 0">Please use the credentials below login to the Fluid Backend.</p>';
            $body .= '<br>';
            $body .= '<table style="border: none; font-family: Arial, Helvetica, sans-serif">';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/retail/login">https://'. $_SERVER['HTTP_HOST'] .'/retail/login</a></td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Username: </b></td>';
            $body .= '    <td>'. $this->encryptor->decrypt($retailUser->getEmail()) .'</td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Password: </b></td>';
            $body .= '    <td>'. $plainTextPwd .'</td>';
            $body .= '</tr>';
            $body .= '</table>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html'  => $body,
            ]);

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($this->encryptor->decrypt($retailUser->getEmail()))
                ->subject('Fluid Login Credentials')
                ->html($html->getContent());

            $mailer->send($email);
        }

        return $this->redirectToRoute('retail_password_reset');
    }

    #[Route('/retail/password/reset', name: 'retail_password_reset')]
    public function retialPasswordReset(Request $request): Response
    {
        return $this->render('reset_password/retail_password_reset.html.twig');
    }

    #[Route('/retail/get-personal-information', name: 'retail_get_personal_information')]
    public function getRetailInformationAction(Request $request): Response
    {
        $retailUserId = $this->getUser()->getId();
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);
        $response = '<h4 class="w-100 text-center mt-5 pt-5"><i class="fa-light fa-face-frown me-3"></i>Something went wrong...</h4>';

        if($retailUser != null)
        {
            $response = '
            <form 
                name="retail-form" 
                id="retail_form" 
                method="post" 
                enctype="multipart/form-data"
                data-action="submit->retail#onPersonalInfoSubmit"
            >
                <input type="hidden" name="retail-user-id" id="retail_user_id" value="'. $retailUserId .'">
                <div class="row pt-3">
                    <div class="col-12 text-center mt-1 pt-3 pb-3" id="order_header">
                        <h4 class="text-primary">Personal Information</h4>
                    </div>
                </div>
        
                <div class="row pb-3 bg-light border-left border-right border-top pt-3">
                
                    <!-- First Name -->
                    <div class="col-12 col-sm-6">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="first-name" 
                            id="first_name" 
                            class="form-control" 
                            placeholder="First Name"
                            value="'. $this->encryptor->decrypt($retailUser->getFirstName()) .'"
                        >
                        <div class="hidden_msg" id="error_first_name">
                            Required Field
                        </div>
                    </div>
        
                    <!-- Last Name -->
                    <div class="col-12 col-sm-6">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="last-name" 
                            id="last_name" 
                            class="form-control" 
                            placeholder="Last Name"
                            value="'. $this->encryptor->decrypt($retailUser->getLastName()) .'"
                        >
                        <div class="hidden_msg" id="error_last_name">
                            Required Field
                        </div>
                    </div>
                </div>
        
                <div class="row pb-3 bg-light border-left border-right">
                
                    <!-- Email Address -->
                    <div class="col-12 col-sm-6">
                        <label>Email Address<span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="email" 
                            id="email" 
                            class="form-control" 
                            placeholder="Email"
                            value="'. $this->encryptor->decrypt($retailUser->getEmail()) .'"
                        >
                        <div class="hidden_msg" id="error_email">
                            Required Field
                        </div>
                    </div>
        
                    <!-- Telephone -->
                    <div class="col-12 col-sm-6">
                        <label>Business Telephone <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="telephone" 
                            id="telephone" 
                            class="form-control" 
                            placeholder="Telephone"
                            value="'. $this->encryptor->decrypt($retailUser->getTelephone()) .'"
                        >
                        <input 
                            type="hidden" 
                            value="'. $this->encryptor->decrypt($retailUser->getTelephone()) .'" 
                            name="mobile" 
                            id="mobile"
                        >
                        <input 
                            type="hidden" 
                            name="iso-code" 
                            id="iso_code" 
                            value="'. $this->encryptor->decrypt($retailUser->getIsoCode()) .'"
                        >
                        <input 
                            type="hidden" 
                            name="intl-code" 
                            id="intl_code" 
                            value="'. $this->encryptor->decrypt($retailUser->getIntlCode()) .'"
                        >
                        <div class="hidden_msg" id="error_telephone">
                            Required Field
                        </div>
                    </div>
                </div>
        
                <div class="row border-left border-right border-bottom">
                    <div class="col-12 pb-3">
                        <button id="form_save" type="submit" class="btn btn-primary float-end w-100">SAVE</button>
                    </div>
                </div>
            </form>';
        }

        return new JsonResponse($response);
    }

    #[Route('/retail/user/update', name: 'update_retail_user')]
    public function retailUserUpdateAction(Request $request): Response
    {
        $data = $request->request;
        $retailUserId = $data->get('retail-user-id') ?? 0;
        $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);
        $response = [];

        if($retailUser != null)
        {
            $retailUser->setEmail($this->encryptor->encrypt($data->get('email')));
            $retailUser->setHashedEmail(md5($data->get('email')));
            $retailUser->setTelephone($this->encryptor->encrypt($data->get('telephone')));
            $retailUser->setIntlCode($this->encryptor->encrypt($data->get('intl-code')));
            $retailUser->setIsoCode($this->encryptor->encrypt($data->get('iso-code')));
            $retailUser->setFirstName($this->encryptor->encrypt($data->get('first-name')));
            $retailUser->setLastName($this->encryptor->encrypt($data->get('last-name')));

            $this->em->persist($retailUser);
            $this->em->flush();

            $response['flash'] = $this->getFlash('Personal Information Successfully Saved.');
            $response['type'] = 'success';
        }
        else
        {
            $response['flash'] = $this->getFlash('An Error Occurred!');
            $response['type'] = 'danger';
        }

        return new JsonResponse($response);
    }

    #[Route('/retail/request-connection', name: 'retail_request_connection')]
    public function retailClinicRequestConnectionAction(Request $request): Response
    {
        $data = $request->request;
        $retailUserId = (int) $data->get('retail-user-id') ?? 0;
        $clinicId = (int) $data->get('clinic-id') ?? 0;
        $clinicRetailUser = $this->em->getRepository(ClinicRetailUsers::class)->findOneBy([
            'retailUser' => $retailUserId,
            'clinicId' => $clinicId,
        ]);
        $response = [];

        if($retailUserId > 0 && $clinicId > 0)
        {
            if($clinicRetailUser == null)
            {
                $clinicRetailUser = new ClinicRetailUsers();
            }

            $clinic = $this->emRemote->getRepository(Clinics::class)->find($clinicId);
            $clinicName = $this->encryptor->decrypt($clinic->getClinicName());

            if($clinicRetailUser->getIsIgnored() != 1)
            {
                $retailUser = $this->em->getRepository(RetailUsers::class)->find($retailUserId);

                $clinicRetailUser->setClinic($clinicId);
                $clinicRetailUser->setRetailUser($retailUser);
                $clinicRetailUser->setIsApproved(1);
                $clinicRetailUser->setIsIgnored(0);

                $this->em->persist($clinicRetailUser);

                $retailUser->setClinicId($clinicId);

                $this->em->flush();

                $response['flash'] = $this->getFlash('You have connected to '. $clinicName);
                $response['type'] = 'success';
            }
            else
            {
                $response['flash'] = $this->getFlash($clinicName .' has declined your request!');
                $response['type'] = 'danger';
            }
        }
        else
        {
            $response['flash'] = $this->getFlash('An Error Occurred!');
            $response['type'] = 'danger';
        }

        return new JsonResponse($response);
    }

    #[Route('/retail/get/clinic-copy', name: 'retail_get_clinic_copy')]
    public function retailGetClinicCopyAction(Request $request): Response
    {
        if($this->getUser() == null)
        {
            return new JsonResponse('Please login...');
        }

        $response = [];
        $clinicId = $this->getUser()->getClinicId();
        $clinic = $this->emRemote->getRepository(Clinics::class)->find($clinicId);
        $method = $request->request->get('method');
        $name = $request->request->get('name');
        $pieces = explode(' ', $name);
        $uri = '';

        foreach($pieces as $piece)
        {
            $uri .= strtolower($piece) .'-';
        }

        $html = '
        <div class="row mx-0 mt-0 mx-sm-3 mt-sm-3">
            <div class="col-12 text-center pt-3 pb-3" id="order_header">
                <h4 class="text-primary text-truncate">'. ucwords($name) .'</h4>
            </div>
        </div>
        <div class="row mx-0 mt-0 mx-sm-3 mt-sm-3">
            <div class="col-12 p-2 p-sm-5 bg-white border-xy">
                '. $clinic->$method() .'
            </div>
        </div>';

        $response['html'] = $html;
        $response['uri'] = trim($uri, '-');

        return new JsonResponse($response);
    }

    #[Route('/retail/error', name: 'retail_error_500')]
    public function retail500ErrorAction(Request $request): Response
    {
        $username = $this->getUser();
        $id = '';

        if($username != null) {

            $id = $this->getUser()->getId();
        }

        return $this->render('bundles/TwigBundle/Exception/error500.html.twig', [
            'type' => 'retail',
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

    private function sendLoginCredentials($retailUser, $plainTextPwd, $data)
    {

        // Send Email
        $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
        $body .= '<tr><td colspan="2">Hi '. $data['firstName'] .',</td></tr>';
        $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
        $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
        $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
        $body .= '<tr>';
        $body .= '    <td><b>URL: </b></td>';
        $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/retail/login">https://'. $_SERVER['HTTP_HOST'] .'/retail/login</a></td>';
        $body .= '</tr>';
        $body .= '<tr>';
        $body .= '    <td><b>Username: </b></td>';
        $body .= '    <td>'. $data['email'] .'</td>';
        $body .= '</tr>';
        $body .= '<tr>';
        $body .= '    <td><b>Password: </b></td>';
        $body .= '    <td>'. $plainTextPwd .'</td>';
        $body .= '</tr>';
        $body .= '</table>';

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($data['email'])
            ->subject('Fluid Login Credentials')
            ->html($body);

        $this->mailer->send($email);
    }

    public function getPagination($pageId, $results)
    {
        $currentPage = (int) $pageId;
        $lastPage = $this->pageManager->lastPage($results);

        $pagination = '
        <!-- Pagination -->
        <div class="row mt-3">
            <div class="col-12">';

        if($lastPage > 1) {

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
            if($currentPage > 1){

                $disabled = '';
                $dataDisabled = 'false';
            }

            $pagination .= '
            <li class="page-item '. $disabled .'">
                <a 
                    class="user-pagination" 
                    aria-disabled="'. $dataDisabled .'" 
                    data-page-id="'. $currentPage - 1 .'" 
                    href="'. $previousPage .'"
                >
                    <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline">Previous</span>
                </a>
            </li>';

            for($i = 1; $i <= $lastPage; $i++) {

                $active = '';

                if($i == (int) $currentPage){

                    $active = 'active';
                }

                $pagination .= '
                <li class="page-item '. $active .'">
                    <a 
                        class="user-pagination" 
                        data-page-id="'. $i .'" 
                        href="'. $url .'"
                    >'. $i .'</a>
                </li>';
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
                    class="user-pagination" 
                    aria-disabled="'. $dataDisabled .'" 
                    data-page-id="'. $currentPage + 1 .'" 
                    href="'. $url .'"
                >
                    <span class="d-none d-sm-inline">Next</span> <span aria-hidden="true">&raquo;</span>
                </a>
            </li>';

            $pagination .= '
                    </ul>
                </nav>';

            $pagination .= '
                </div>
            </div>';
        }

        return $pagination;
    }

    private function getFlash($message)
    {
        return '<b><i class="fas fa-check-circle"></i> '. $message .'<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
    }
}
