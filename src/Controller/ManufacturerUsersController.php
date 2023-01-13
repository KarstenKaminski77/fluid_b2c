<?php

namespace App\Controller;

use App\Entity\DistributorUsers;
use App\Entity\ManufacturerUsers;
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

class ManufacturerUsersController extends AbstractController
{
    private $em;
    private $pageManager;
    private $mailer;
    private $plainPassword;
    private $encryptor;

    const ITEMS_PER_PAGE = 10;

    public function __construct(EntityManagerInterface $em, PaginationManager $pagination, MailerInterface $mailer, Encryptor $encryptor)
    {
        $this->em = $em;
        $this->pageManager = $pagination;
        $this->mailer = $mailer;
        $this->encryptor = $encryptor;
    }

    #[Route('/manufacturers/get-user', name: 'manufacturer_get_user_data')]
    public function manufacturerGetUserDataAction(Request $request): Response
    {
        $user = $this->em->getRepository(ManufacturerUsers::class)->find($request->request->get('id'));

        $response = [

            'id' => $user->getId(),
            'firstName' => $this->encryptor->decrypt($user->getFirstName()),
            'lastName' => $this->encryptor->decrypt($user->getLastName()),
            'email' => $this->encryptor->decrypt($user->getEmail()),
            'mobile' => $this->encryptor->decrypt($user->getTelephone()),
            'telephone' => $this->encryptor->decrypt($user->getIntlCode()) . substr($this->encryptor->decrypt($user->getTelephone()), 1),
            'isoCode' => $this->encryptor->decrypt($user->getIsoCode()),
            'intlCode' => $this->encryptor->decrypt($user->getIntlCode()),
        ];

        return new JsonResponse($response);
    }

    #[Route('/manufacturers/manage-users', name: 'manufacturer_manage_user')]
    public function manufacturerUsersAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $data = $request->request;
        $manufacturer = $this->getUser()->getManufacturer();
        $user = $this->em->getRepository(ManufacturerUsers::class)->findOneBy([
            'hashedEmail' => md5($data->get('user-email'))
        ]);
        $userId = (int) $data->get('user-id');

        if($user == null && $userId > 0)
        {
            $response = [
                'response' => false,
                'message' => '<b><i class="fas fa-check-circle"></i> User details already exist.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>',
            ];

            return new JsonResponse($response);
        }

        if($userId == 0)
        {
            $manufacturerUser = new ManufacturerUsers();

            $plainTextPwd = $this->generatePassword();
            $manufacturerUser->setIsPrimary(0);

            if (!empty($plainTextPwd))
            {
                $hashedPwd = $passwordHasher->hashPassword($manufacturerUser, $plainTextPwd);

                $manufacturerUser->setRoles(['ROLE_MANUFACTURER']);
                $manufacturerUser->setPassword($hashedPwd);

                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                $body .= '<tr><td colspan="2">Hi '. $data->get('firstName') .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/login">https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/login</a></td>';
                $body .= '</tr>';
                $body .= '<tr>';
                $body .= '    <td><b>Username: </b></td>';
                $body .= '    <td>'. $data->get('user-email') .'</td>';
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
                    ->addTo($data->get('user-email'))
                    ->subject('Fluid Login Credentials')
                    ->html($html);

                $mailer->send($email);
            }

            $message = '<b><i class="fas fa-check-circle"></i> User details successfully created.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        }
        else
        {
            $manufacturerUser = $this->em->getRepository(ManufacturerUsers::class)->find($userId);

            $manufacturerUser->setIsPrimary($manufacturerUser->getIsPrimary());

            $message = '<b><i class="fas fa-check-circle"></i> User successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $manufacturerUser->setManufacturer($manufacturer);
        $manufacturerUser->setFirstName($this->encryptor->encrypt($data->get('user-first-name')));
        $manufacturerUser->setLastName($this->encryptor->encrypt($data->get('user-last-name')));
        $manufacturerUser->setEmail($this->encryptor->encrypt($data->get('user-email')));
        $manufacturerUser->setHashedEmail(md5($data->get('user-email')));
        $manufacturerUser->setTelephone($this->encryptor->encrypt($data->get('user-mobile')));
        $manufacturerUser->setIsoCode($this->encryptor->encrypt($data->get('user-iso-code')));
        $manufacturerUser->setIntlCode($this->encryptor->encrypt($data->get('user-intl-code')));

        $this->em->persist($manufacturerUser);
        $this->em->flush();

        // Get Users List
        $usersList = $this->forward('App\Controller\ManufacturerUsersController::manufacturerGetUsersAction')->getContent();

        $response = [

            'response' => true,
            'message' => $message,
            'usersList' => json_decode($usersList)
        ];

        return new JsonResponse($response);
    }

    #[Route('/manufacturers/get-users', name: 'manufacturer_get_users')]
    public function manufacturerGetUsersAction(Request $request): Response
    {
        $manufacturerId = $this->getUser()->getManufacturer()->getId();
        $users = $this->em->getRepository(ManufacturerUsers::class)->findManufacturerUsers($manufacturerId);
        $userResults = $this->pageManager->paginate($users[0], $request, self::ITEMS_PER_PAGE);
        $pageId = $request->request->get('page_id');

        $html = '
        <div class="row" id="users">
            <div class="col-12 mb-3 d-flex d-md-none">
                <button type="button" class="btn btn-secondary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#modal_user" id="user_new">
                    <i class="fa-solid fa-circle-plus"></i>
                    ADD COLLEAGUE
                </button>
            </div>
            <div class="col-12 text-center pt-3 pb-3 mt-1">
                <h3 class="text-primary text-truncate">Manage User Accounts</h3>
                <span class="d-none d-sm-inline mb-5 mt-2 text-center text-primary text-sm-start">
                    Fluid supports having several users under a single clinic. Each user will have their own login, can
                    independently participate in the Fluid discussions. You have full control over editing the permissions
                    of each user in your clinic. Use the table below to view the available permission levels.
                </span>
            </div>
            <div class="col-12 d-none d-xl-block">
                <div class="row">
                    <div class="col-md-3 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-left border-top">
                        First Name
                    </div>
                    <div class="col-md-3 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Last Name
                    </div>
                    <div class="col-md-2 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Username
                    </div>
                    <div class="col-md-2 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-top">
                        Telephone
                    </div>
                    <div class="col-md-2 pt-3 pb-3 text-primary fw-bold bg-light border-bottom border-right border-top">
                        <button type="button" class="bg-transparent float-end border-0 p-0 m-0" data-bs-toggle="modal" data-bs-target="#modal_user" id="user_new">
                        <i class="fa-regular fa-square-plus float-end edit-icon"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-12" id="users_list">';

            $i = 0;
            $borderBottom = 'border-bottom-dashed';
            
            foreach($userResults as $user)
            {
                $i++;
                
                if(count($userResults) == $i)
                {
                    $borderBottom = 'border-bottom';
                }

                $html .= '
                <div class="row">
                    <div 
                        class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-left text-primary text-truncate '. $borderBottom .' pt-3 pb-3"
                    >
                        First Name:
                    </div>
                    <div 
                        class="col-7 col-md-10 col-xl-3 '. $borderBottom .' pt-3 pb-3 t-cell text-truncate bg-light border-left" 
                        id="string_user_first_name_'. $user->getId() .'"
                    >
                        '. $this->encryptor->decrypt($user->getFirstName()) .'
                    </div>
                    <div 
                        class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom text-primary text-truncate '. $borderBottom .' pt-3 pb-3"
                    >
                        Last Name:
                    </div>
                    <div 
                        class="col-7 col-md-10 col-xl-3 pt-3 pb-3 bg-light '. $borderBottom .' border-bottom t-cell text-truncate"
                        >
                        '. $this->encryptor->decrypt($user->getLastName()) .'
                    </div>
                    <div 
                        class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom border-left text-primary text-truncate '. $borderBottom .' pt-3 pb-3"
                    >
                        Username:
                    </div>
                    <div 
                        class="col-7 col-md-10 col-xl-2 pt-3 pb-3 bg-light '. $borderBottom .' border-bottom t-cell text-truncate"
                    >
                        '. $this->encryptor->decrypt($user->getEmail()) .'
                    </div>
                    <div 
                        class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom border-left text-primary text-truncate '. $borderBottom .' pt-3 pb-3"
                    >
                        Telephone:
                    </div>
                    <div 
                        class="col-7 col-md-10 col-xl-2 pt-3 pb-3 bg-light '. $borderBottom .' border-bottom t-cell text-truncate"
                    >
                        '. $this->encryptor->decrypt($user->getTelephone()) .'
                    </div>
                    <div class="col-md-2 t-cell bg-light '. $borderBottom .' border-right">
                        <a href="" class="float-end update-user" data-bs-toggle="modal" data-bs-target="#modal_user" data-user-id="'. $user->getId() .'">
                            <i class="fa-solid fa-pen-to-square edit-icon"></i>
                        </a>';

                if($user->getIsPrimary() != 1)
                {
                    $html .= '
                            <a href="" class="delete-icon float-end delete-user" data-bs-toggle="modal"
                                data-value="' . $user->getId() . '" data-bs-target="#modal_user_delete" data-user-id="' . $user->getId() . '">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>';
                }

                $html .= '
                    </div>
                </div>';
            }

            $html .= '
            </div>

            <!-- Modal Manage Users -->
            <div class="modal fade" id="modal_user" tabindex="-1" aria-labelledby="modal_user" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <form name="form_users" id="form_users" method="post">
                            <input type="hidden" name="user-id" id="user_id" value="0">
                            <div class="modal-header">
                                <h5 class="modal-title" id="user_modal_label"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">

                                    <!-- First Name -->
                                    <div class="col-12 col-sm-6">
                                        <label>First Name <span class="text-danger">*</span></label>
                                        <input type="hidden" value="" name="distributor_users_form[user_id]" id="user_id">
                                        <input
                                            type="text"
                                            name="user-first-name"
                                            id="user_first_name"
                                            class="form-control"
                                            placeholder="First Name"
                                        >
                                        <div class="hidden_msg" id="error_user_first_name">
                                            Required Field
                                        </div>
                                    </div>

                                    <!-- Last Name -->
                                    <div class="col-12 col-sm-6">
                                        <label>Last Name <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="user-last-name"
                                            id="user_last_name"
                                            class="form-control"
                                            placeholder="last Name"
                                        >
                                        <div class="hidden_msg" id="error_user_last_name">
                                            Required Field
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">

                                    <!-- Email -->
                                    <div class="col-12 col-sm-6">
                                        <label>Email <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="user-email"
                                            id="user_email"
                                            class="form-control"
                                            placeholder="Email Address"
                                        >
                                        <div class="hidden_msg" id="error_user_email">
                                            Required Field
                                        </div>
                                    </div>

                                    <!-- Telephone Number -->
                                    <div class="col-12 col-sm-6">
                                        <label>Telephone <span class="text-danger">*</span></label>
                                        <span id="telephone_container">
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="user-mobile"
                                                id="user_mobile"
                                                placeholder="(123) 456-7890*"
                                            >
                                        </span>

                                        <div class="hidden_msg" id="error_user_telephone">
                                            Required Field
                                        </div>
                                        <input type="hidden" name="user-telephone" id="user_telephone">
                                        <input type="hidden" name="user-iso-code" id="user_iso_code">
                                        <input type="hidden" name="user-intl-code" id="user_intl_code">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">CANCEL</button>
                                <button type="submit" class="btn btn-primary" id="create_user">SAVE</button>
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
        </div>';

        $pagination = $this->getPagination($pageId ?? 1, $userResults, $manufacturerId);

        $html .= $pagination;

        return new JsonResponse($html);
    }

    #[Route('/manufacturers/user/delete', name: 'manufacturer_user_delete')]
    public function manufacturerDeleteUser(Request $request): Response
    {
        $userId = (int) $request->request->get('id');
        $user = $this->em->getRepository(ManufacturerUsers::class)->find($userId);

        $this->em->remove($user);
        $this->em->flush();

        // Get Users List
        $response['html'] = json_decode($this->forward('App\Controller\ManufacturerUsersController::manufacturerGetUsersAction')->getContent());
        $response['flash'] = '<b><i class="fas fa-check-circle"></i> User successfully deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/manufacturer/forgot-password', name: 'manufacturers_forgot_password_request')]
    public function clinicForgotPasswordAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $manufacturerUser = $this->em->getRepository(ManufacturerUsers::class)->findOneBy(
                [
                    'hashedEmail' => md5($request->request->get('reset_password_request_form')['email'])
                ]
            );

            if($manufacturerUser != null)
            {
                $resetToken = uniqid();

                $manufacturerUser->setResetKey($resetToken);

                $this->em->persist($manufacturerUser);
                $this->em->flush();

                $html = '
                <p>To reset your password, please visit the following link</p>
                <p>
                    <a
                        href="https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/reset/'. $resetToken .'"
                    >https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/reset/'. $resetToken .'</a>
                </p>';

                $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $html,
                ]);

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($this->encryptor->decrypt($manufacturerUser->getEmail()))
                    ->subject('Fluid Password Reset')
                    ->html($html->getContent());

                $this->mailer->send($email);

                return $this->render('reset_password/manufacturers_check_email.html.twig');
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/manufacturers/reset/{token}', name: 'manufacturers_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null, MailerInterface $mailer): Response
    {
        $plainTextPwd = $this->generatePassword();
        $manufacturerUser = $this->em->getRepository(DistributorUsers::class)->findOneBy([
            'resetKey' => $request->get('token')
        ]);

        if (!empty($plainTextPwd))
        {
            $hashedPwd = $passwordHasher->hashPassword($manufacturerUser, $plainTextPwd);

            $manufacturerUser->setPassword($hashedPwd);

            $this->em->persist($manufacturerUser);
            $this->em->flush();

            // Send Email
            $body  = '<p style="margin-bottom: 0">Hi '. $this->encryptor->decrypt($manufacturerUser->getFirstName()) .',</p>';
            $body .= '<br>';
            $body .= '<p style="margin-bottom: 0">Please use the credentials below login to the Fluid Backend.</p>';
            $body .= '<br>';
            $body .= '<table style="border: none; font-family: Arial, Helvetica, sans-serif">';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/login">https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/login</a></td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Username: </b></td>';
            $body .= '    <td>'. $this->encryptor->decrypt($manufacturerUser->getEmail()) .'</td>';
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
                ->addTo($this->encryptor->decrypt($manufacturerUser->getEmail()))
                ->subject('Fluid Login Credentials')
                ->html($html->getContent());

            $mailer->send($email);
        }

        return $this->redirectToRoute('manufacturers_password_reset');
    }

    #[Route('/manufacturers/password/reset', name: 'manufacturers_password_reset')]
    public function manufacturerPasswordReset(Request $request): Response
    {
        return $this->render('reset_password/manufacturers_password_reset.html.twig');
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

        foreach ($sets as $set)
        {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);

        for ($i = 0; $i < 16 - count($sets); $i++)
        {
            $password .= $all[array_rand($all)];
        }

        $this->plainPassword = str_shuffle($password);

        return $this->plainPassword;
    }

    private function sendLoginCredentials($clinic_user, $plainTextPwd, $data)
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

    public function getPagination($pageId, $results, $manufacturerId)
    {
        $currentPage = (int) $pageId;
        $lastPage = $this->pageManager->lastPage($results);

        $pagination = '
        <!-- Pagination -->
        <div class="row mt-3">
            <div class="col-12">';

        if($lastPage > 1) {

            $previousPageNo = $currentPage - 1;
            $url = '/manufacturers/users';
            $previousPage = $url . $previousPageNo;

            $pagination .= '
            <nav class="custom-pagination">
                <ul class="pagination justify-content-center">
            ';

            $disabled = 'disabled';
            $dataDisabled = 'true';

            // Previous Link
            if ($currentPage > 1)
            {
                $disabled = '';
                $dataDisabled = 'false';
            }

            $pagination .= '
            <li class="page-item ' . $disabled . '">
                <a 
                    class="user-pagination" 
                    aria-disabled="' . $dataDisabled . '" 
                    data-page-id="' . $currentPage - 1 . '" 
                    data-manufacturer-id="' . $manufacturerId . '"
                    href="' . $previousPage . '"
                >
                    <span aria-hidden="true">&laquo;</span> <span class="d-none d-sm-inline">Previous</span>
                </a>
            </li>';

            for ($i = 1; $i <= $lastPage; $i++)
            {
                $active = '';

                if ($i == (int)$currentPage) {

                    $active = 'active';
                }

                $pagination .= '
                <li class="page-item ' . $active . '">
                    <a 
                        class="user-pagination" 
                        data-page-id="' . $i . '" 
                        href="' . $url . '"
                        data-manufacturer-id="' . $manufacturerId . '"
                    >' . $i . '</a>
                </li>';
            }

            $disabled = 'disabled';
            $dataDisabled = 'true';

            if ($currentPage < $lastPage)
            {
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
                    data-manufacturer-id="' . $manufacturerId . '"
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
