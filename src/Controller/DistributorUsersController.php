<?php

namespace App\Controller;

use App\Entity\ClinicUsers;
use App\Entity\DistributorUserPermissions;
use App\Entity\DistributorUsers;
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

class DistributorUsersController extends AbstractController
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

    #[Route('/distributors/get-user', name: 'distributor_get_user')]
    public function distributorGetUserAction(Request $request): Response
    {
        $user = $this->em->getRepository(DistributorUsers::class)->find($request->request->get('id'));

        $userPermissions = [];

        foreach($user->getDistributorUserPermissions() as $permission){

            $userPermissions[] = $permission->getPermission()->getId();
        }

        $response = [

            'id' => $user->getId(),
            'first_name' => $this->encryptor->decrypt($user->getFirstName()),
            'last_name' => $this->encryptor->decrypt($user->getLastName()),
            'email' => $this->encryptor->decrypt($user->getEmail()),
            'telephone' => $this->encryptor->decrypt($user->getTelephone()),
            'position' => $this->encryptor->decrypt($user->getPosition()),
            'iso_code' => $this->encryptor->decrypt($user->getIsoCode()),
            'intl_code' => $this->encryptor->decrypt($user->getIntlCode()),
            'permissions' => $user->getDistributorUserPermissions(),
            'user_permissions' => $userPermissions,
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/manage-users', name: 'distributor_users')]
    public function distributorUsersAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $data = $request->request->get('distributor_users_form');
        $distributor = $this->get('security.token_storage')->getToken()->getUser()->getDistributor();
        $user = $this->em->getRepository(DistributorUsers::class)->findBy(['email' => $data['email']]);
        $userId = (int) $data['user_id'];

        if(count($user) > 0 && $userId == 0){

            $response = [
                'response' => false
            ];

            return new JsonResponse($response);
        }

        if($userId == 0){

            $distributorUser = new DistributorUsers();

            $plainTextPwd = $this->generatePassword();
            $distributorUser->setIsPrimary(0);

            if (!empty($plainTextPwd)) {

                $hashedPwd = $passwordHasher->hashPassword($distributorUser, $plainTextPwd);

                $distributorUser->setRoles(['ROLE_DISTRIBUTOR']);
                $distributorUser->setPassword($hashedPwd);

                // Send Email
                $body = '<table style="padding: 8px; border-collapse: collapse; border: none; font-family: arial">';
                $body .= '<tr><td colspan="2">Hi '. $data['firstName'] .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/distributors/login">https://'. $_SERVER['HTTP_HOST'] .'/distributors/login</a></td>';
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

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($data['email'])
                    ->subject('Fluid Login Credentials')
                    ->html($body);

                $mailer->send($email);
            }

            $message = '<b><i class="fas fa-check-circle"></i> User details successfully created.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $distributorUser = $this->em->getRepository(DistributorUsers::class)->find($userId);

            $distributorUser->setIsPrimary($distributorUser->getIsPrimary());

            $message = '<b><i class="fas fa-check-circle"></i> User successfully updated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        $distributorUser->setDistributor($distributor);
        $distributorUser->setFirstName($this->encryptor->encrypt($data['firstName']));
        $distributorUser->setLastName($this->encryptor->encrypt($data['lastName']));
        $distributorUser->setEmail($this->encryptor->encrypt($data['email']));
        $distributorUser->setHashedEmail(md5($data['email']));
        $distributorUser->setTelephone($this->encryptor->encrypt($data['telephone']));
        $distributorUser->setIsoCode($this->encryptor->encrypt($data['isoCode']));
        $distributorUser->setIntlCode($this->encryptor->encrypt($data['intlCode']));
        $distributorUser->setPosition($this->encryptor->encrypt($data['position']));

        $this->em->persist($distributorUser);
        $this->em->flush();

        // Update user permissions
        // Primary account can't update permissions
        if(!$distributorUser->getIsPrimary()) {

            // Remove previous entries
            $permissions = $this->em->getRepository(DistributorUserPermissions::class)->findBy(['user' => $distributorUser->getId()]);

            if (count($permissions) > 0) {

                foreach ($permissions as $permission) {

                    $permissionRepo = $this->em->getRepository(DistributorUserPermissions::class)->find($permission->getId());

                    $this->em->remove($permissionRepo);
                }

                $this->em->flush();
            }

            // Save new permissions
            if (array_key_exists('permission', $data)) {

                foreach ($data['permission'] as $permission) {

                    $distributorUserPermission = new DistributorUserPermissions();
                    $user = $this->em->getRepository(DistributorUsers::class)->find($distributorUser->getId());
                    $userPermission = $this->em->getRepository(UserPermissions::class)->find($permission);

                    $distributorUserPermission->setDistributor($distributor);
                    $distributorUserPermission->setUser($user);
                    $distributorUserPermission->setPermission($userPermission);

                    $this->em->persist($distributorUserPermission);
                }

                $this->em->flush();
            }
        }

        $response = [

            'response' => true,
            'message' => $message
        ];

        return new JsonResponse($response);
    }

    #[Route('/distributors/users-refresh', name: 'distributor_refresh_users')]
    public function distributorRefreshUsersAction(Request $request): Response
    {
        $distributorId = $this->get('security.token_storage')->getToken()->getUser()->getDistributor()->getId();
        $users = $this->em->getRepository(DistributorUsers::class)->findDistributorUsers($distributorId);
        $userResults = $this->pageManager->paginate($users[0], $request, self::ITEMS_PER_PAGE);
        $pageId = $request->request->get('page_id');
        $html = '';

        foreach($userResults as $user){

            $html .= '
            <div class="row">
                <div 
                    class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom border-left text-primary text-truncate border-list pt-3 pb-3"
                >
                    First Name:
                </div>
                <div 
                    class="col-7 col-md-10 col-xl-2 border-list pt-3 pb-3 t-cell text-truncate bg-light border-right border-bottom" 
                    id="string_user_first_name_'. $user->getId() .'"
                >
                    '. $this->encryptor->decrypt($user->getFirstName()) .'
                </div>
                <div 
                    class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom text-primary text-truncate border-list pt-3 pb-3"
                >
                    Last Name:
                </div>
                <div 
                    class="col-7 col-md-10 col-xl-2 pt-3 pb-3 bg-light border-list border-bottom t-cell text-truncate"
                    >
                    '. $this->encryptor->decrypt($user->getLastName()) .'
                </div>
                <div 
                    class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom border-left text-primary text-truncate border-list pt-3 pb-3"
                >
                    Username:
                </div>
                <div 
                    class="col-7 col-md-10 col-xl-2 pt-3 pb-3 bg-light border-list border-bottom t-cell text-truncate"
                >
                    '. $this->encryptor->decrypt($user->getEmail()) .'
                </div>
                <div 
                    class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom border-left text-primary text-truncate border-list pt-3 pb-3"
                >
                    Telephone:
                </div>
                <div 
                    class="col-7 col-md-10 col-xl-2 pt-3 pb-3 bg-light border-list border-bottom t-cell text-truncate"
                >
                    '. $this->encryptor->decrypt($user->getTelephone()) .'
                </div>
                <div 
                    class="col-5 col-md-2 d-xl-none t-cell fw-bold bg-light border-bottom border-left text-primary text-truncate border-list pt-3 pb-3"
                >
                    Position:
                </div>
                <div 
                    class="col-7 col-md-10 col-xl-2 pt-3 pb-3 bg-light border-list border-bottom t-cell text-truncate"
                >
                    '. $this->encryptor->decrypt($user->getPosition()) .'
                </div>
                <div class="col-md-2 t-cell bg-light border-list border-right">
                    <a href="" class="float-end update-user" data-bs-toggle="modal" data-bs-target="#modal_user" data-user-id="'. $user->getId() .'">
                        <i class="fa-solid fa-pen-to-square edit-icon"></i>
                    </a>';

                    if($user->getIsPrimary() != 1) {

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

        $pagination = $this->getPagination($pageId, $userResults, $distributorId);

        $html .= $pagination;

        return new JsonResponse($html);
    }

    #[Route('/distributors/user/delete', name: 'distributor_user_delete')]
    public function distributorDeleteUser(Request $request): Response
    {
        $userId = (int) $request->request->get('id');
        $user = $this->em->getRepository(DistributorUsers::class)->find($userId);
        $distributorUserPermissions = $this->em->getRepository(DistributorUserPermissions::class)->findBy([
            'user' => $userId,
        ]);

        if(count($distributorUserPermissions) > 0){

            foreach ($distributorUserPermissions as $distributorUserPermission){

                $this->em->remove($distributorUserPermission);
            }
        }

        $this->em->remove($user);
        $this->em->flush();

        $response = '<b><i class="fas fa-check-circle"></i> User successfully deleted.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('/distributors/forgot-password', name: 'distributors_forgot_password_request')]
    public function clinicForgotPasswordAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $distributorUser = $this->em->getRepository(DistributorUsers::class)->findOneBy(
                [
                    'hashedEmail' => md5($request->request->get('reset_password_request_form')['email'])
                ]
            );

            if($distributorUser != null){

                $resetToken = uniqid();

                $distributorUser->setResetKey($resetToken);

                $this->em->persist($distributorUser);
                $this->em->flush();

                $html = '
                <p>To reset your password, please visit the following link</p>
                <p>
                    <a
                        href="https://'. $_SERVER['HTTP_HOST'] .'/distributors/reset/'. $resetToken .'"
                    >https://'. $_SERVER['HTTP_HOST'] .'/distributors/reset/'. $resetToken .'</a>
                </p>';

                $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                    'html'  => $html,
                ]);

                $email = (new Email())
                    ->from($this->getParameter('app.email_from'))
                    ->addTo($this->encryptor->decrypt($distributorUser->getEmail()))
                    ->subject('Fluid Password Reset')
                    ->html($html->getContent());

                $this->mailer->send($email);

                return $this->render('reset_password/distributors_check_email.html.twig');
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/distributors/reset/{token}', name: 'distributors_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null, MailerInterface $mailer): Response
    {
        $plainTextPwd = $this->generatePassword();
        $distributorUser = $this->em->getRepository(DistributorUsers::class)->findOneBy([
            'resetKey' => $request->get('token')
        ]);

        if (!empty($plainTextPwd)) {

            $hashedPwd = $passwordHasher->hashPassword($distributorUser, $plainTextPwd);

            $distributorUser->setPassword($hashedPwd);

            $this->em->persist($distributorUser);
            $this->em->flush();

            // Send Email
            $body  = '<p style="margin-bottom: 0">Hi '. $this->encryptor->decrypt($distributorUser->getFirstName()) .',</p>';
            $body .= '<br>';
            $body .= '<p style="margin-bottom: 0">Please use the credentials below login to the Fluid Backend.</p>';
            $body .= '<br>';
            $body .= '<table style="border: none; font-family: Arial, Helvetica, sans-serif">';
            $body .= '<tr>';
            $body .= '    <td><b>URL: </b></td>';
            $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/distributors/login">https://'. $_SERVER['HTTP_HOST'] .'/distributors/login</a></td>';
            $body .= '</tr>';
            $body .= '<tr>';
            $body .= '    <td><b>Username: </b></td>';
            $body .= '    <td>'. $this->encryptor->decrypt($distributorUser->getEmail()) .'</td>';
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
                ->addTo($this->encryptor->decrypt($distributorUser->getEmail()))
                ->subject('Fluid Login Credentials')
                ->html($html->getContent());

            $mailer->send($email);
        }

        return $this->redirectToRoute('distributors_password_reset');
    }

    #[Route('/distributors/password/reset', name: 'distributors_password_reset')]
    public function distributorPasswordReset(Request $request): Response
    {
        return $this->render('reset_password/distributors_password_reset.html.twig');
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

    public function getPagination($pageId, $results, $distributorId)
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
                    data-distributor-id="'. $distributorId .'"
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
                        data-distributor-id="'. $distributorId .'"
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
                    data-distributor-id="'. $distributorId .'"
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
