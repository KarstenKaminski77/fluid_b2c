<?php

namespace App\Controller;

use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\Distributors;
use App\Entity\DistributorUsers;
use App\Entity\Manufacturers;
use App\Entity\ManufacturerUsers;
use App\Entity\RestrictedDomains;
use App\Form\ResetPasswordRequestFormType;
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

class ManufacturersController extends AbstractController
{
    private $encryptor;
    private $em;
    private $mailer;

    public function __construct(Encryptor $encryptor, EntityManagerInterface $em, MailerInterface $mailer)
    {
        $this->encryptor = $encryptor;
        $this->em = $em;
        $this->mailer = $mailer;
    }

    #[Route('/manufacturers/analytics', name: 'manufacturer_analytics')]
    #[Route('/manufacturers/users/1', name: 'manufacturer_users')]
    #[Route('/manufacturers/company-information', name: 'manufacturer_company_information')]
    public function manufacturerDashboardAction(Request $request): Response
    {
        $manufacturer = '';

        if($this->getUser() != null)
        {
            $manufacturerId = $this->getUser()->getManufacturer()->getId();
            $manufacturer = $this->em->getRepository(ManufacturerUsers::class)->find($manufacturerId);

        }
        else
        {
            return $this->redirectToRoute('manufacturer_login');
        }


        return $this->render('frontend/manufacturers/index.html.twig',[
            'manufacturer' => $manufacturer,
        ]);
    }

    #[Route('/manufacturer/register', name: 'manufacturer_reg')]
    public function manufacturerReg(Request $request): Response
    {

        return $this->render('frontend/manufacturers/register.html.twig');
    }

    #[Route('/manufacturer/register/create', name: 'manufacturer_create')]
    public function manufacturerCreateAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface  $mailer): Response
    {
        $data = $request->request;
        $hashedEmail = md5($data->get('email'));
        $manufacturer = $this->em->getRepository(Manufacturers::class)->findOneBy([
            'hashedEmail' => $hashedEmail,
        ]);

        if($manufacturer == null)
        {
            $manufacturer = new Manufacturers();

            $plainTextPwd = $this->generatePassword();

            if (!empty($plainTextPwd)) {

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
                    }
                }

                $this->em->persist($manufacturer);
                $this->em->flush();

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
                $body .= '<tr><td colspan="2">Hi '. $data->get('first_name') .',</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr><td colspan="2">Please use the credentials below login to the Fluid Backend.</td></tr>';
                $body .= '<tr><td colspan="2">&nbsp;</td></tr>';
                $body .= '<tr>';
                $body .= '    <td><b>URL: </b></td>';
                $body .= '    <td><a href="https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/login">https://'. $_SERVER['HTTP_HOST'] .'/manufacturers/login</a></td>';
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

                $mailer->send($email);
            }

            $response = 'Your Fluid account was successfully created, an email with your login credentials has been sent to your inbox.';

        } else {

            $response = false;
        }

        return new JsonResponse($response);
    }

    #[Route('/manufacturer/register/check-email', name: 'manufacturer_check_email')]
    public function manufacturersCheckEmailAction(Request $request): Response
    {
        $email = $request->request->get('email');
        $domainName = explode('@', $email);
        $response['duplicate'] = false;
        $response['restricted'] = false;
        $response['inValid'] = false;
        $restrictedDomains = $this->em->getRepository(RestrictedDomains::class)->arrayFindAll();

        // Validate Email Address
        if(count($domainName) !== 2)
        {
            $response['inValid'] = true;

            return new JsonResponse($response);

            die();
        }

        // Restricted Domain Names
        foreach($restrictedDomains as $restrictedDomain)
        {
            if(md5($domainName[1]) == md5($restrictedDomain->getName()))
            {
                $response['restricted'] = true;

                return new JsonResponse($response);

                die();
            }
        }

        // Duplicate Email Address & Domains
        $manufacturer = $this->em->getRepository(Manufacturers::class)->findOneBy([
            'hashedEmail' => md5($email),
        ]);

        $manufacturerDomain = $this->em->getRepository(Manufacturers::class)->findOneBy([
            'domainName' => md5($domainName[1]),
        ]);

        $manufacturerUsers = $this->em->getRepository(ManufacturerUsers::class)->findOneBy([
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

        if(
            $manufacturer != null || $manufacturerDomain != null || $manufacturerUsers != null ||
            $clinic != null || $clinicUsers != null || $clinicDomain != null ||
            $distributor != null || $distributorUsers != null || $distributorDomain != null
        )
        {
            $response['duplicate'] = true;
        }

        return new JsonResponse($response);
    }

    #[Route('/manufacturers/forgot-password', name: 'manufacturer_forgot_password_request')]
    public function clinicForgotPasswordAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $manufacturerUser = $this->em->getRepository(ManufacturerUsers::class)->findOneBy(
                [
                    'hashedEmail' => md5($request->request->get('reset_password_request_form')['email'])
                ]
            );

            if($manufacturerUser != null){

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
        $manufacturerUser = $this->em->getRepository(ManufacturerUsers::class)->findOneBy([
            'resetKey' => $request->get('token')
        ]);

        if (!empty($plainTextPwd)) {

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

    #[Route('/manufacturers/error', name: 'manufacturer_error_500')]
    public function manufacturer500ErrorAction(Request $request): Response
    {
        $id = $this->getUser()->getManufacturer()->getId();

        if($id == null)
        {
            return $this->render('security/login.html.twig', [
                'last_username' => '',
                'error' => '',
                'csrf_token_intention' => 'authenticate',
                'user_type' => 'manufacturers',

            ]);
        }

        return $this->render('bundles/TwigBundle/Exception/error500.html.twig',[
            'type' => 'manufacturers',
            'id' => $id,

        ]);
    }

    #[Route('/manufacturers/get-company-information', name: 'manufacturer_get_company_information')]
    public function getManufacturerInformationAction(Request $request): Response
    {
        $manufacturerId = $this->getUser()->getManufacturer()->getId();
        $manufacturer = $this->em->getRepository(Manufacturers::class)->find($manufacturerId);
        $response = '<h4 class="w-100 text-center mt-5 pt-5"><i class="fa-light fa-face-frown me-3"></i>Something went wrong...</h4>';

        if($manufacturer != null)
        {
            $response = '
            <form name="manufacturers_form" id="manufacturers_form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="manufacturer-id" id="manufacturer_id" value="'. $manufacturerId .'">
                <div class="row pt-3">
                    <div class="col-12 text-center mt-1 pt-3 pb-3" id="order_header">
                        <h4 class="text-primary">Company Information</h4>
                    </div>
                </div>
        
                <div class="row pb-3 pt-3 bg-light border-left border-right border-top">
                    <div class="col-12 col-sm-6">
                        <label>
                            Business Name <span class="text-danger">*</span>
                        </label>
                        <input type="checkbox" name="contact_me_by_fax_only" value="1" tabindex="-1" class="hidden" autocomplete="off">
                        <input 
                            type="text" 
                            name="manufacturer-name" 
                            id="manufacturer_name" 
                            class="form-control" 
                            placeholder="Company Name"
                            value="'. $this->encryptor->decrypt($manufacturer->getName()) .'"
                        >
                        <div class="hidden_msg" id="error_manufacturer_name">
                            Required Field
                        </div>
                    </div>
        
                    <div class="col-12 col-sm-6">
                        <div class="row">
                            <div class="col-11">
                                <label>Logo <span class="text-danger">*</span></label>
                                <input type="file" name="logo" id="logo" class="form-control" placeholder="Business Logo*">
                            </div>
                            <div class="col-1">
                                <a href="" data-bs-toggle="modal" data-bs-target="#modal_logo">
                                    <i class="fa-light fa-image img-icon float-end"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
        
                <div class="row pb-3 bg-light border-left border-right">
                    <div class="col-12 col-sm-6">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="first-name" 
                            id="first_name" 
                            class="form-control" 
                            placeholder="First Name"
                            value="'. $this->encryptor->decrypt($manufacturer->getFirstName()) .'"
                        >
                        <div class="hidden_msg" id="error_first_name">
                            Required Field
                        </div>
                    </div>
        
                    <div class="col-12 col-sm-6">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="last-name" 
                            id="last_name" 
                            class="form-control" 
                            placeholder="Last Name"
                            value="'. $this->encryptor->decrypt($manufacturer->getLastName()) .'"
                        >
                        <div class="hidden_msg" id="error_last_name">
                            Required Field
                        </div>
                    </div>
                </div>
        
                <div class="row pb-3 bg-light border-left border-right">
                    <div class="col-12 col-sm-6">
                        <label>Business Email <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="email" 
                            id="email" 
                            class="form-control" 
                            placeholder="Email"
                            value="'. $this->encryptor->decrypt($manufacturer->getEmail()) .'"
                        >
                        <div class="hidden_msg" id="error_email">
                            Required Field
                        </div>
                    </div>
        
                    <div class="col-12 col-sm-6">
                        <label>Business Telephone <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="telephone" 
                            id="telephone" 
                            class="form-control" 
                            placeholder="Telephone"
                            value="'. $this->encryptor->decrypt($manufacturer->getTelephone()) .'"
                        >
                        <input 
                            type="hidden" 
                            value="'. $this->encryptor->decrypt($manufacturer->getTelephone()) .'" 
                            name="mobile-no" 
                            id="mobile_no"
                        >
                        <input 
                            type="hidden" 
                            name="iso-code" 
                            id="manufacturer_iso_code" 
                            value="'. $this->encryptor->decrypt($manufacturer->getIsoCode()) .'"
                        >
                        <input 
                            type="hidden" 
                            name="intl-code" 
                            id="manufacturer_intl_code" 
                            value="'. $this->encryptor->decrypt($manufacturer->getIntlCode()) .'"
                        >
                        <div class="hidden_msg" id="error_telephone">
                            Required Field
                        </div>
                    </div>
                </div>
        
                <div class="row">
                    <div class="col-12 ps-0 pe-0">
                        <button id="form_save" type="submit" class="btn btn-primary float-end w-100">SAVE</button>
                    </div>
                </div>
            </form>
            
            <!-- Modal Logo -->
            <div class="modal fade" id="modal_logo" tabindex="-1" aria-labelledby="product_delete_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header" style="border: none; padding-bottom: 0">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="padding: 0">
                            <div class="row">
                                <div class="col-12 mb-0 text-center">
                                    <img 
                                        src="'. $this->getParameter('app.base_url') .'/images/logos/'. $manufacturer->getLogo() .'" 
                                        id="logo_img" class="img-fluid"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

        return new JsonResponse($response);
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
}
