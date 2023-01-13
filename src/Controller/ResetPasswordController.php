<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private $resetPasswordHelper;
    private $entityManager;
    private $encryptor;
    private $params;

    public function __construct(ResetPasswordHelperInterface $resetPasswordHelper, EntityManagerInterface $entityManager, Encryptor $encryptor, ParameterBagInterface $params)
    {
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->entityManager = $entityManager;
        $this->encryptor = $encryptor;
        $this->params = $params;
    }

    /**
     * Display & process form to request a password reset.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSendingPasswordResetEmail(
                $form->get('email')->getData(),
                $mailer
            );
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether or not a user was found with the given email address or not
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $userPasswordHasher, string $token = null): Response
    {
        if ($token) {
            // We inventory the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                'There was a problem validating your reset request - %s',
                $e->getReason()
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            // Encode(hash) the plain password, and set it.
            $encodedPassword = $userPasswordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $user->setEmail($this->encryptor->encrypt($user->getEmail()));
            $this->entityManager->flush();

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('admin');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'hashedEmail' => md5($emailFormData),
        ]);

        // Do not reveal whether a user account was found or not.
        if ($user == null) {
            return $this->redirectToRoute('app_check_email');
        }

        $emailTo = $this->encryptor->decrypt($user->getEmail());
        $user->setEmail($this->encryptor->decrypt($user->getEmail()));

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // If you want to tell the user why a reset email was not sent, uncomment
            // the lines below and change the redirect to 'app_forgot_password_request'.
            // Caution: This may reveal if a user is registered or not.
            //
             $this->addFlash('reset_password_error', sprintf(
                 'There was a problem handling your password reset request - %s',
                 $e->getReason()
             ));

           return $this->redirectToRoute('app_check_email');
        }

        $email = (new TemplatedEmail())
            ->from($this->getParameter('app.email_from'))
            ->to($emailTo)
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $mailer->send($email);

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }

    #[Route('/email/footer', name: 'email_footer')]
    public function emailFooter($html): Response
    {
        $response = '
        <table style="border: none; width: 100%; padding: 10px; font-family: Arial, Helvetica, sans-serif" cellpadding="10">
            <tr>
                <td colspan="2">
                    '. $html .'
                    <br>
                    <p>Kind Regards,</p>
                    <p style="margin-bottom: 0">The Fluid Team</p>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 0">&nbsp;</td>
            </tr>
            <tr>
                <td style="width: 170px">
                    <img src="'. $this->params->get('app.base_url') .'/images/logo.png" style="width: 150px; height: auto">
                </td>
                <td>
                    <table>
                        <tr>
                            <td style="width: 30px; text-align: center">
                                <img src="'. $this->params->get('app.base_url') .'/images/icons/telephone.png" style="width: 22px">
                            </td>
                            <td>
                                +971 6 5395443
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 30px; text-align: center">
                                <img src="'. $this->params->get('app.base_url') .'/images/icons/email.png" style="width: 20px">
                            </td>
                            <td>
                                info@fluid.vet
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 30px; text-align: center">
                                <img src="'. $this->params->get('app.base_url') .'/images/icons/web.png" style="width: 18px">
                            </td>
                            <td>
                                <a href="https://wwwfluid.vet">www.fluid.vet</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="border-top: solid 1px #ccc">
                    <p style="padding-top: 10px">
                        <a href="" style="margin-right: 5px; text-decoration: none;">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/facebook.png" style="width: 30px;">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/linkedin.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/tiktok.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/twitter.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/instagram.png" style="width: 30px">
                        </a>
                        <a href="" style="margin-right: 5px; text-decoration: none">
                            <img src="'. $this->params->get('app.base_url') .'/images/icons/youtube.png" style="width: 30px">
                        </a>
                    </p>
                </td>
            </tr>
        </table>';

        return new Response($response);
    }
}
