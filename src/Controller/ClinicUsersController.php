<?php

namespace App\Controller;

use App\Entity\Clinics;
use App\Entity\ClinicUserPermissions;
use App\Entity\ClinicUsers;
use App\Entity\UserPermissions;
use App\Form\ResetPasswordRequestFormType;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ClinicUsersController extends AbstractController
{
    private $em;
    private $page_manager;
    private $mailer;
    private $encryptor;
    const ITEMS_PER_PAGE = 10;

    public function __construct(EntityManagerInterface $em, PaginationManager $page_manager, MailerInterface $mailer, Encryptor $encryptor)
    {
        $this->em = $em;
        $this->page_manager = $page_manager;
        $this->mailer = $mailer;
        $this->encryptor = $encryptor;
    }

    #[Route('/clinics/get-clinic-users', name: 'get-clinic-users')]
    public function getClinicUsersAction(Request $request): Response
    {
        $permissions = json_decode($request->request->get('permissions'), true);

        if(!in_array(6, $permissions)){

            $html = '
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

            $response = [
                'html' => $html,
                'pagination' => ''
            ];

            return new JsonResponse($response);
        }

        $clinic = $this->getUser()->getClinic();
        $clinic_users = $this->em->getRepository(ClinicUsers::class)->findClinicUsers($clinic->getId());
        $results = $this->page_manager->paginate($clinic_users[0], $request, self::ITEMS_PER_PAGE);
        $pagination = $this->getPagination($request->request->get('page_id'), $results);
        $user_permissions = $this->em->getRepository(UserPermissions::class)->findBy([
            'isClinic' => 1
        ]);
        $i = 0;
        
        $html = '
        <!-- Users -->
        <div class="row">
            <div class="col-12 text-center pt-3 pb-3 mt-3 mt-sm-4 mt-md-0 pt-md-0" id="users_header">
                <h4 class="text-primary text-truncate">Manage User Accounts</h4>
                <span class="d-none d-sm-inline mb-5 mt-2 text-center text-primary text-sm-start">
                    Fluid supports having several users under a single clinic. Each user will have their own login, can
                    independently participate in the Fluid discussions. You have full control over editing the permissions
                    of each user in your clinic. Use the table below to view the available permission levels.
                </span>
            </div>
            <div class="col-12 col-md-12 mt-0 ps-0 pe-0">
                <!-- Create New -->
                <a 
                    href="" 
                    class="float-end text-truncate mb-2" 
                    data-bs-toggle="modal" 
                    data-bs-target="#modal_user" 
                    id="user_new"
                >
                    <i class="fa-regular fa-square-plus fa-fw"></i> 
                    <span class="ms-1">Add Colleague</span>
                </a>
            </div>
        </div>

        <div class="row d-none d-xl-flex bg-light border-right border-left border-top">
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                First Name
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Last Name
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Username
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Telephone
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Position
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">

            </div>
        </div>';

        foreach($results as $user) {

            $i++;
            $borderTop = '';

            if($i == 1)
            {
                $borderTop = 'border-top';
            }

            $html .= '
           <div class="row border-bottom border-right border-left t-row '. $borderTop .'">
               <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">First Name</div>
               <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                   '. $this->encryptor->decrypt($user->getFirstName()) .'
               </div>
               <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Last Name</div>
               <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                   '. $this->encryptor->decrypt($user->getLastName()) .'
               </div>
               <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Email</div>
               <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                   '. $this->encryptor->decrypt($user->getEmail()) .'
               </div>
               <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Telephone</div>
               <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                   '. $this->encryptor->decrypt($user->getTelephone()) .'
               </div>
               <div class="col-4 col-md-2 d-xl-none t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3">Position</div>
               <div class="col-8 col-md-10 col-xl-2 t-cell text-truncate border-list pt-3 pb-3">
                   '. $this->encryptor->decrypt($user->getPosition()) .'
               </div>
               <div class="col-12 col-xl-2 t-cell text-truncate pt-3 pb-3">
                   <a 
                       href="" 
                       class="float-end open-user-modal" 
                       data-page-id="'. $request->request->get('page_id') .'"
                       data-bs-toggle="modal" 
                       data-bs-target="#modal_user" 
                       data-user-id="'. $user->getId() .'"
                   >
                       <i class="fa-solid fa-pen-to-square edit-icon"></i>
                   </a>';

                   if($user->getIsPrimary() != 1) {

                       $html .= '
                       <a href="" class="delete-icon float-start float-sm-end open-delete-user-modal" data-bs-toggle="modal"
                          data-value="' . $user->getId() . '" data-bs-target="#modal_user_delete" data-user-id="' . $user->getId() . '">
                           <i class="fa-solid fa-trash-can"></i>
                       </a>';
                   }

               $html .= '
               </div>
           </div>';
        }

        $html .= '

            <!-- Modal Manage Users -->
            <div class="modal fade" id="modal_user" tabindex="-1" aria-labelledby="modal_user" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <form name="form_users" id="form_users" method="post">
                            <div class="modal-header">
                                <h5 class="modal-title" id="user_modal_label"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">

                                    <!-- First Name -->
                                    <div class="col-12 col-sm-6">
                                        <label>First Name</label>
                                        <input type="hidden" value="" name="clinic_users_form[user_id]" id="user_id">
                                        <input 
                                            type="text" 
                                            name="clinic_users_form[firstName]" 
                                            id="user_first_name" 
                                            placeholder="First Name*"
                                            class="form-control"
                                            value=""
                                        >
                                        <div class="hidden_msg" id="error_user_first_name">
                                            Required Field
                                        </div>
                                    </div>

                                    <!-- Last Name -->
                                    <div class="col-12 col-sm-6">
                                        <label>Last Name</label>
                                        <input 
                                            type="text" 
                                            name="clinic_users_form[lastName]" 
                                            id="user_last_name" 
                                            placeholder="Last Name*"
                                            class="form-control"
                                            value=""
                                        >
                                        <div class="hidden_msg" id="error_user_last_name">
                                            Required Field
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">

                                    <!-- Email -->
                                    <div class="col-12 col-sm-6">
                                        <label>Email</label>
                                        <input 
                                            type="text" 
                                            name="clinic_users_form[email]" 
                                            id="user_email" 
                                            placeholder="Email Address*"
                                            class="form-control"
                                            value=""
                                        >
                                        <div class="hidden_msg" id="error_user_email">
                                            Required Field
                                        </div>
                                    </div>

                                    <!-- Telephone Number -->
                                    <div class="col-12 col-sm-6">
                                        <label>Telepgone</label>
                                        <input 
                                            type="text" 
                                            id="user_mobile" 
                                            placeholder="(123) 456-7890*"
                                            class="form-control"
                                            value=""
                                        >
                                        <input 
                                            type="hidden" 
                                            name="clinic_users_form[telephone]" 
                                            id="user_telephone"
                                            value=""
                                        >
                                        <input 
                                            type="hidden" 
                                            name="clinic_users_form[isoCode]" 
                                            id="user_iso_code"
                                            value=""
                                        >
                                        <input 
                                            type="hidden" 
                                            name="clinic_users_form[intlCode]" 
                                            id="user_intl_code"
                                            value=""
                                        >
                                        <div class="hidden_msg" id="error_user_telephone">
                                            Required Field
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">

                                    <!-- Position -->
                                    <div class="col-12">
                                        <label>Position</label>
                                        <input 
                                            type="text" 
                                            name="clinic_users_form[position]" 
                                            id="user_position" 
                                            placeholder="Position"
                                            class="form-control"
                                            value=""
                                        >
                                        <div class="hidden_msg" id="error_user_telephone">
                                            Required Field
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">';

                                    if(count($user_permissions) > 0){

                                        foreach($user_permissions as $permission){

                                            $html .= '
                                            <!-- User Permissions -->
                                            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                                                <div class="btn-sm btn-outline-light border-1 border p-2 my-2">
                                                    <input 
                                                        class="form-check-input me-2" 
                                                        type="checkbox" 
                                                        value="'. $permission->getId() .'" 
                                                        id="permission_'. $permission->getId() .'"
                                                        data-permission-id="'. $permission->getId() .'"
                                                        name="clinic_users_form[permission][]"
                                                    >
                                                    <label class="form-check-label info" for="permission_'. $permission->getId() .'">
                                                        '. $permission->getPermission() .'
                                                    </label>
                                                    <span 
                                                        class="ms-1 float-end text-primary"
                                                        data-bs-trigger="hover"
                                                        data-bs-container="body" 
                                                        data-bs-toggle="popover" 
                                                        data-bs-placement="top" 
                                                        data-bs-html="true"
                                                        data-bs-content="'. $permission->getInfo() .'"
                                                        role="button"
                                                    >
                                                        <i class="far fa-question-circle"></i>
                                                    </span>
                                                </div>
                                            </div>';
                                        }
                                    }

                                $html .= '
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary w-sm-100" data-bs-dismiss="modal">CANCEL</button>
                                <button type="submit" class="btn btn-primary w-sm-100" id="create_user">SAVE</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal Delete User -->
            <div class="modal fade" id="modal_user_delete" tabindex="-1" aria-labelledby="user_delete_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="user_delete_label">Delete User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-12 mb-0">
                                    Are you sure you would like to delete this user? This action cannot be undone.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">CANCEL</button>
                            <button type="submit" class="btn btn-danger btn-sm" id="delete_user">DELETE</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Users -->';

        $response = [
            'html' => $html,
            'pagination' => $pagination
        ];
        
        return new JsonResponse($response);
    }

    #[Route('/clinics/get-user', name: 'clinic_get_user')]
    public function clinicsGetUserAction(Request $request): Response
    {
        $user = $this->em->getRepository(ClinicUsers::class)->find($request->request->get('id'));
        $permissions = [];

        foreach($user->getClinicUserPermissions() as $permission){

            $permissions[] = $permission->getPermission()->getId();
        }

        $encryptedWhiteSpace = '5btuAewE-zQ3DwAsyXmH4A';

        $response = [
            'id' => $user->getId(),
            'first_name' => $this->encryptor->decrypt($user->getFirstName() ?? $encryptedWhiteSpace),
            'last_name' => $this->encryptor->decrypt($user->getLastName() ?? $encryptedWhiteSpace),
            'email' => $this->encryptor->decrypt($user->getEmail()),
            'telephone' => $this->encryptor->decrypt($user->getTelephone() ?? $encryptedWhiteSpace),
            'position' => $this->encryptor->decrypt($user->getPosition() ?? $encryptedWhiteSpace),
            'iso_code' => $this->encryptor->decrypt($user->getIsoCode() ?? $encryptedWhiteSpace),
            'intl_code' => $this->encryptor->decrypt($user->getIntlCode() ?? $encryptedWhiteSpace),
            'permissions' => $permissions,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/user/delete', name: 'clinic_user_delete')]
    public function clinicDeleteUser(Request $request): Response
    {
        $userId = $request->request->get('id');
        $userPermissions = $this->em->getRepository(ClinicUserPermissions::class)->findBy([
            'user' => $userId
        ]);
        $user = $this->em->getRepository(ClinicUsers::class)->find($userId);

        // Delete the users permissions
        foreach ($userPermissions as $userPermission){

            $this->em->remove($userPermission);
        }

        $this->em->remove($user);
        $this->em->flush();

        $response = '<b><i class="fas fa-check-circle"></i> User successfully deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/clinics/users-refresh', name: 'clinic_refresh_users')]
    public function clinicRefreshUsersAction(Request $request): Response
    {
        $clinic_id = $this->getUser()->getClinic()->getId();
        $users = $this->em->getRepository(Clinics::class)->getClinicUsers($clinic_id);

        $html = '
        <div class="row" id="users">
            <div class="col-12 col-md-12 mb-3 mt-0">
                <!-- Create New -->
                <button type="button" class="btn btn-primary float-end w-sm-100" data-bs-toggle="modal" data-bs-target="#modal_user" id="user_new">
                    <i class="fa-solid fa-circle-plus"></i> ADD COLLEAGUE
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-12 text-center pt-3 pb-3 mt-4" id="order_header">
                <h4 class="text-primary">Manage User Accounts</h4>
                <span class="mb-5 mt-2 text-center text-primary text-sm-start">
                    Fluid supports having several users under a single clinic. Each user will have their own login, 
                    can independently participate in the Fluid discussions. You have full control over editing the 
                    permissions of each user in your clinic. Use the table below to view the available permission levels.
                </span>
            </div>
        </div>
        <div class="row d-none d-xl-flex bg-light border-xy">
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                First Name
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Last Name
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Username
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Telephone
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">
                Position
            </div>
            <div class="col-md-2 pt-3 pb-3 text-primary fw-bold">

            </div>
        </div>';

        foreach($users[0]->getClinicUsers() as $user){

            $html .= '
            <div>
               <div class="row bg-light border-left border-bottom">
                   <div class="col-md-2 t-cell fw-bold text-primary text-truncate border-list pt-3 pb-3" id="string_user_first_name_'. $user->getId() .'">
                       '. $this->encryptor->decrypt($user->getFirstName()) .'
                   </div>
                   <div class="col-md-2 t-cell text-primary text-truncate border-list pt-3 pb-3" id="string_user_last_name_'. $user->getId() .'">
                       '. $this->encryptor->decrypt($user->getLastName()) .'
                   </div>
                   <div class="col-md-2 t-cell text-primary text-truncate border-list pt-3 pb-3" id="string_user_email_'. $user->getId() .'">
                       '. $this->encryptor->decrypt($user->getEmail()) .'
                   </div>
                   <div class="col-md-2 t-cell text-primary text-truncate border-list pt-3 pb-3" id="string_user_telephone_'. $user->getId() .'">
                       '. $this->encryptor->decrypt($user->getTelephone()) .'
                   </div>
                   <div class="col-md-2 t-cell text-primary text-truncate border-list pt-3 pb-3" id="string_user_position_'. $user->getId() .'">
                       '. $this->encryptor->decrypt($user->getPosition()) .'
                   </div>
                   <div class="col-md-2 t-cell text-primary border-list pt-3 pb-3">
                       <a href="" class="float-end" data-bs-toggle="modal" data-bs-target="#modal_user" id="user_update_'. $user->getId() .'">
                           <i class="fa-solid fa-pen-to-square edit-icon"></i>
                       </a>
                       <a href="" class="delete-icon float-end open-delete-user-modal" data-bs-toggle="modal"
                          data-user-id="'. $user->getId() .'" data-bs-target="#modal_user_delete">
                           <i class="fa-solid fa-trash-can"></i>
                       </a>
                   </div>
               </div>
           </div>';
        }

        return new JsonResponse($html);
    }

    #[Route('/clinics/get-users', name: 'clinic_get_users')]
    public function clinicUsersAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $data = $request->request->get('clinic_users_form');
        $clinic = $this->get('security.token_storage')->getToken()->getUser()->getClinic();
        $user = $this->em->getRepository(ClinicUsers::class)->find($data['email']);
        $userId = $data['user_id'];
        $pageId = $request->request->get('pageId') ?? 1;
        $sendEmail = false;

        if($userId == 0){

            if($user != null){

                $response = [
                    'response' => false
                ];

                return new JsonResponse($response);
            }

            $clinicUser = new ClinicUsers();

            $plainTextPwd = $this->generatePassword();
            $sendEmail = true;

            if (!empty($plainTextPwd)) {

                $hashedPwd = $passwordHasher->hashPassword($clinicUser, $plainTextPwd);

                $clinicUser->setRoles(['ROLE_CLINIC']);
                $clinicUser->setPassword($hashedPwd);

                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                $body .= '<tr><td colspan="2">Hi '. $data['firstName'] .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/clinics/login">https://'. $_SERVER['HTTP_HOST'] .'/clinics/login</a></td>';
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

                $body = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $body,
                ])->getContent();

                $send_email = true;
            }

            $message = '<b><i class="fas fa-check-circle"></i> User details successfully created.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $clinicUser = $this->em->getRepository(ClinicUsers::class)->find($userId);

            $message = '<b><i class="fas fa-check-circle"></i> User successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $isPrimary = 0;

        if($clinicUser->getIsPrimary() == 1){

            $isPrimary = 1;
        }

        $clinicUser->setClinic($clinic);
        $clinicUser->setFirstName($this->encryptor->encrypt($data['firstName']));
        $clinicUser->setLastName($this->encryptor->encrypt($data['lastName']));
        $clinicUser->setEmail($this->encryptor->encrypt($data['email']));
        $clinicUser->setHashedEmail(md5($data['email']));
        $clinicUser->setTelephone($this->encryptor->encrypt($data['telephone']));
        $clinicUser->setIsoCode($this->encryptor->encrypt($data['isoCode']));
        $clinicUser->setIntlCode($this->encryptor->encrypt($data['intlCode']));
        $clinicUser->setPosition($this->encryptor->encrypt($data['position']));
        $clinicUser->setIsPrimary($isPrimary);

        $this->em->persist($clinicUser);
        $this->em->flush();

        // Update user permissions
        if($clinicUser->getIsPrimary() != 1) {
            // Remove previous entries
            $permissions = $this->em->getRepository(ClinicUserPermissions::class)->findBy(['user' => $clinicUser->getId()]);

            if ($permissions > 0) {

                foreach ($permissions as $permission) {

                    $permissionRepo = $this->em->getRepository(ClinicUserPermissions::class)->find($permission->getId());

                    $this->em->remove($permissionRepo);
                }
            }

            // Save new permissions
            if (count($data['permission']) > 0) {

                foreach ($data['permission'] as $permission) {

                    $clinicUserPermission = new ClinicUserPermissions();
                    $user = $this->em->getRepository(ClinicUsers::class)->find($clinicUser->getId());
                    $userPermission = $this->em->getRepository(UserPermissions::class)->find($permission);

                    $clinicUserPermission->setClinic($clinic);
                    $clinicUserPermission->setUser($user);
                    $clinicUserPermission->setPermission($userPermission);

                    $this->em->persist($clinicUserPermission);
                }

                $this->em->flush();
            }
        }

        if($sendEmail){

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($data['email'])
                ->subject('Fluid Login Credentials')
                ->html($body);

            $mailer->send($email);
        }

        $response = [

            'response' => true,
            'message' => $message,
            'page_id' => $pageId,
        ];

        return new JsonResponse($response);
    }

    #[Route('/clinics/forgot-password', name: 'clinic_forgot_password_request')]
    public function clinicForgotPasswordAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $clinic_user = $this->em->getRepository(ClinicUsers::class)->findOneBy(
                [
                    'hashedEmail' => md5($request->request->get('reset_password_request_form')['email'])
                ]
            );

            if($clinic_user != null){

                $resetToken = uniqid();

                $clinic_user->setResetKey($resetToken);

                $this->em->persist($clinic_user);
                $this->em->flush();

                $html = '
                <p>To reset your password, please visit the following link</p>
                <p>
                    <a
                        href="https://'. $_SERVER['HTTP_HOST'] .'/clinics/reset/'. $resetToken .'"
                    >https://'. $_SERVER['HTTP_HOST'] .'/clinics/reset/'. $resetToken .'</a>
                </p>';

                $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $html,
                ]);

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($this->encryptor->decrypt($clinic_user->getEmail()))
                    ->subject('Fluid Password Reset')
                    ->html($html->getContent());

                $this->mailer->send($email);

                return $this->render('reset_password/clinics_check_email.html.twig');
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/clinics/reset/{token}', name: 'clinic_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null, MailerInterface $mailer): Response
    {
        $plain_text_pwd = $this->generatePassword();
        $clinic_user = $this->em->getRepository(ClinicUsers::class)->findOneBy([
            'resetKey' => $request->get('token')
        ]);

        if (!empty($plain_text_pwd)) {

            $hashed_pwd = $passwordHasher->hashPassword($clinic_user, $plain_text_pwd);

            $clinic_user->setPassword($hashed_pwd);

            $this->em->persist($clinic_user);
            $this->em->flush();

            // Send Email
            $body  = '<p style="margin-bottom: 0">Hi '. $this->encryptor->decrypt($clinic_user->getFirstName()) .',</p>';
            $body .= '<br>';
            $body .= '<p style="margin-bottom: 0">Please use the credentials below login to the Fluid Backend.</p>';
            $body .= '<br>';
            $body .= '<table style="border: none; font-family: Arial, Helvetica, sans-serif">';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/clinics/login">https://'. $_SERVER['HTTP_HOST'] .'/clinics/login</a></td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Username: </b></td>';
            $body .= '    <td>'. $this->encryptor->decrypt($clinic_user->getEmail()) .'</td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Password: </b></td>';
            $body .= '    <td>'. $plain_text_pwd .'</td>';
            $body .= '</tr>';
            $body .= '</table>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html'  => $body,
            ]);

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($this->encryptor->decrypt($clinic_user->getEmail()))
                ->subject('Fluid Login Credentials')
                ->html($html->getContent());

            $mailer->send($email);
        }

        return $this->redirectToRoute('clinics_password_reset');
    }

    #[Route('/clinics/password/reset', name: 'clinics_password_reset')]
    public function clinicPasswordReset(Request $request): Response
    {
        return $this->render('reset_password/clinics_password_reset.html.twig');
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

        $this->plain_password = str_shuffle($password);

        return $this->plain_password;
    }

    private function sendLoginCredentials($clinic_user, $plain_text_pwd, $data)
    {

        // Send Email
        $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
        $body .= '<tr><td colspan="2">Hi '. $data['firstName'] .',</td></tr>';
        $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
        $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
        $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
        $body .= '<tr>';
        $body .= '    <td><b>URL: </b></td>';
        $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/clinics/login">https://'. $_SERVER['HTTP_HOST'] .'/clinics/login</a></td>';
        $body .= '</tr>';
        $body .= '<tr>';
        $body .= '    <td><b>Username: </b></td>';
        $body .= '    <td>'. $data['email'] .'</td>';
        $body .= '</tr>';
        $body .= '<tr>';
        $body .= '    <td><b>Password: </b></td>';
        $body .= '    <td>'. $plain_text_pwd .'</td>';
        $body .= '</tr>';
        $body .= '</table>';

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($data['email'])
            ->subject('Fluid Login Credentials')
            ->html($body);

        $this->mailer->send($email);
    }

    public function getPagination($page_id, $results)
    {
        $current_page = (int) $page_id;
        $last_page = $this->page_manager->lastPage($results);

        $pagination = '
        <!-- Pagination -->
        <div class="row mt-3">
            <div class="col-12">';

        if($last_page > 1) {

            $previous_page_no = $current_page - 1;
            $url = '/distributors/users';
            $previous_page = $url . $previous_page_no;

            $pagination .= '
            <nav class="custom-pagination">
                <ul class="pagination justify-content-center">
            ';

            $disabled = 'disabled';
            $data_disabled = 'true';

            // Previous Link
            if($current_page > 1){

                $disabled = '';
                $data_disabled = 'false';
            }

            $pagination .= '
            <li class="page-item '. $disabled .'">
                <a 
                    class="user-pagination" 
                    aria-disabled="'. $data_disabled .'" 
                    data-page-id="'. $current_page - 1 .'" 
                    href="'. $previous_page .'"
                >
                    <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline">Previous</span>
                </a>
            </li>';

            for($i = 1; $i <= $last_page; $i++) {

                $active = '';

                if($i == (int) $current_page){

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
            $data_disabled = 'true';

            if($current_page < $last_page) {

                $disabled = '';
                $data_disabled = 'false';
            }

            $pagination .= '
            <li class="page-item '. $disabled .'">
                <a 
                    class="user-pagination" 
                    aria-disabled="'. $data_disabled .'" 
                    data-page-id="'. $current_page + 1 .'" 
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
}
