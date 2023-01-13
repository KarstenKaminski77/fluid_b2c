<?php

namespace App\Controller;

use App\Entity\Clinics;
use App\Entity\DistributorClinics;
use App\Entity\Distributors;
use App\Services\PaginationManager;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class DistributorClinicsController extends AbstractController
{
    private $em;
    private $pageManager;
    private $encryptor;
    const ITEMS_PER_PAGE = 10;

    public function __construct(EntityManagerInterface $em, PaginationManager $pageManager, Encryptor $encryptor)
    {
        $this->em = $em;
        $this->pageManager = $pageManager;
        $this->encryptor = $encryptor;
    }

    #[Route('/clinic/request-connection', name: 'clinic_request_connection')]
    public function clinicRequestConnectionAction(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = $request->request;
        $distributorId = $data->get('distributor-id');
        $clinicId = $data->get('clinic-id');
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $distributorClinics = $this->em->getRepository(DistributorClinics::class)->findOneBy([
            'distributor' => $distributorId,
            'clinic' => $clinic,
        ]);
        $url = $this->getParameter('app.base_url') . '/distributors/customers/1';
        $isIgnored = false;

        if($distributorClinics == null){

            $distributorClinics = new DistributorClinics();

        } else {

            if($distributorClinics->getIsIgnored() == 1){

                $isIgnored = true;
            }
        }

        if(!$isIgnored) {

            $distributorClinics->setClinic($clinic);
            $distributorClinics->setDistributor($distributor);
            $distributorClinics->setIsActive(0);
            $distributorClinics->setIsIgnored(0);

            $this->em->persist($distributorClinics);
            $this->em->flush();

            $html =
            '<b><i>' . $this->encryptor->decrypt($clinic->getClinicName()) . '</i></b> wants connect to Zoho Inventory.
            <br><br>
            <a href="' . $url . '">Connect</a><br>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html'  => $html,
            ]);

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($this->encryptor->decrypt($distributor->getEmail()))
                ->subject('New Fluid Connection Request')
                ->html($html->getContent());

            $mailer->send($email);

            $flash = '<b><i class="fas fa-check-circle"></i> Connection request successfully sent.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        } else {

            $flash = '<b><i class="fas fa-check-circle"></i> Connection request successfully declined.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
        }

        return new JsonResponse($flash);
    }

    #[Route('/distributors/customers', name: 'zoho_customers')]
    public function clinicConnectAction(Request $request, MailerInterface $mailer): Response
    {
        $distributorId = $this->getUser()->getDistributor()->getId();
        $distributorClinics = $this->em->getRepository(DistributorClinics::class)->adminFindAll($distributorId);
        $results = $this->pageManager->paginate($distributorClinics[0], $request, self::ITEMS_PER_PAGE);

        $html = '
        <div class="row">
            <div class="col-12 text-center mt-1 pt-3 pb-3" id="customer_header">
                <h4 class="text-primary text-truncate">Manage Fluid Customers</h4>
            </div>
        </div>';

        if(count($results) > 0){

            $html .= '
            <div class="row bg-white border-bottom border-left border-right border-top">
                <div class="col-12 col-sm-1 pt-3 pb-3 text-primary fw-bold">
                    #Id
                </div>
                <div class="col-12 col-sm-5 pt-3 pb-3 text-primary fw-bold">
                    Clinic
                </div>
                <div class="col-12 col-sm-5 pt-3 pb-3 text-primary fw-bold">
                    Customer Id
                </div>
                <div class="col-12 col-sm-1 pt-3 pb-3 text-primary fw-bold">
                    
                </div>
            </div>';

            foreach($results as $result){

                $ignoreIcon = '<i class="fa-regular fa-bell-slash text-disabled"></i>';

                if($result->getIsIgnored() == 1){

                    $ignoreIcon = '<i class="fa-solid fa-bell-slash"></i>';
                }

                $html .= '
                <div class="row  bg-white border-bottom border-left border-right">
                    <div class="col-12 col-sm-1 pt-3 pb-3 text-primary text-truncate">
                        '. $result->getId() .'
                    </div>
                    <div class="col-12 col-sm-5 pt-3 pb-3 text-primary text-truncate">
                        '. $this->encryptor->decrypt($result->getClinic()->getClinicName()) .'
                    </div>
                    <div class="col-12 col-sm-5 pt-3 pb-3 text-primary text-truncate">
                        '. $result->getClientId() .'
                    </div>
                    <div class="col-12 col-sm-1 pt-3 pb-3 text-primary text-truncate">
                        <a 
                            href="" 
                            class="float-end ms-3 modal-customer-connect"
                            data-clinic-id="'. $result->getClinic()->getId() .'"
                            data-distributor-id="'. $result->getDistributor()->getId() .'"
                            data-clinic="'. $this->encryptor->decrypt($result->getClinic()->getClinicName()) .'"
                            data-customer-id="'. $result->getClientId() .'"
                            data-bs-toggle="modal" 
                            data-bs-target="#modal_connect_customer"
                        >
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a 
                            href="#" 
                            class="float-end ignore-icon"
                            data-distributor-id="'. $result->getDistributor()->getId() .'"
                            data-clinic-id="'. $result->getClinic()->getId() .'"
                        >
                           '. $ignoreIcon .'
                        </a>
                    </div>
                </div>';
            }
            
            $html .= '
            <!-- Modal Connect Customer -->
            <div class="modal fade" id="modal_connect_customer" tabindex="-1" aria-labelledby="connect_customer_modal_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="connect_customer_modal_label">Connect <span id="connect_clinic_name"></span></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-12" id="col_communication_method">
                                    <label>Customer ID</label>
                                    <input type="text" class="form-control" placeholder="Customer ID" id="customer_id">
                                    <div class="hidden_msg" id="error_customer_id">
                                        Required Field
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">CANCEL</button>
                            <button 
                                type="submit" 
                                id="btn_save_customer_connect" 
                                class="btn btn-primary"
                            >
                                SAVE
                            </button>
                        </div>
                    </div>
                </div>
            </div>';

        } else {

            $html .= '
            <div class="row  bg-white border-bottom border-left border-right">
                <div class="col-12 pt-3 pb-3 text-primary text-truncate">
                    You haven\'t connected with any customers
                </div>
            </div>';
        }

        $currentPage = $request->request->get('page_id');
        $lastPage = $this->pageManager->lastPage($results);

        $html .= '
        <!-- Pagination -->
        <div class="row mt-3">
            <div class="col-12">';

        if($lastPage > 1) {

            $previousPageNo = $currentPage - 1;
            $url = '/distributors/customers/';
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
                    class="customer-link" 
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

                $html .= '
                <li class="page-item '. $active .'">
                    <a class="customer-link" data-page-id="'. $i .'" href="'. $url .'">'. $i .'</a>
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
                <a class="customer-link" aria-disabled="'. $dataDisabled .'" data-page-id="'. $currentPage + 1 .'" href="'. $url . $currentPage + 1 .'">
                    <span class="d-none d-sm-inline">Next</span> <span aria-hidden="true">&raquo;</span>
                </a>
            </li>';

            $html .= '
                    </ul>
                </nav>
            </div>';
        }

        return new JsonResponse($html);
    }

    #[Route('/distributors/activate/connection', name: 'distributors_activate_connection')]
    #[Route('/distributors/activate/connection/email/{clinicId}', name: 'distributors_activate_connection_email')]
    public function clinicActivateConnectionAction(Request $request, MailerInterface $mailer): JsonResponse
    {
        $clinicId = $request->request->get('clinic-id');

        if($clinicId == null){

            $clinicId = $request->get('clinicId');
        }

        $distributorId = $request->request->get('distributor-id') ?? $this->getUser()->getDistributor()->getId();
        $distributor = $this->em->getRepository(Distributors::class)->find($distributorId);
        $clinic = $this->em->getRepository(Clinics::class)->find($clinicId);
        $clientId = $request->request->get('customer-id');
        $distributorClinics = $this->em->getRepository(DistributorClinics::class)->findOneBy([
            'distributor' => $distributorId,
            'clinic' => $clinicId,
        ]);

        if($distributorClinics == null){

            $distributorClinics = new DistributorClinics();
        }

        if($clientId != null) {

            $distributorClinics->setClientId($clientId);
        }
        $distributorClinics->setDistributor($distributor);
        $distributorClinics->setClinic($clinic);
        $distributorClinics->setIsActive(1);
        $distributorClinics->setIsIgnored(0);

        $this->em->persist($distributorClinics);
        $this->em->flush();

        $html =
        '<b><i>' . $this->encryptor->decrypt($distributor->getDistributorName()) .'</i></b> has activated your account on Fluid.
        <br><br>
        <a href="'. $this->getParameter('app.base_url') .'/inventory">Back To Fluid</a>';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $html,
        ])->getContent();

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($this->encryptor->decrypt($clinic->getEmail()))
            ->subject('Fluid Connection Activated')
            ->html($html);

        $mailer->send($email);

        $flash = '<b><i class="fas fa-check-circle"></i> Connection successfully activated.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($flash);
    }

    #[Route('/distributors/ignore-connection', name: 'distributors_ignore_connection')]
    public function clinicIgnoreConnectionAction(Request $request): JsonResponse
    {
        $clinicId = $request->request->get('clinic-id');
        $distributorId = $request->request->get('distributor-id');
        $distributorClinics = $this->em->getRepository(DistributorClinics::class)->findOneBy([
            'distributor' => $distributorId,
            'clinic' => $clinicId,
        ]);
        $ignoreIcon = '';

        if($distributorClinics != null) {

            if($distributorClinics->getIsIgnored() == 0) {

                $distributorClinics->setIsIgnored(1);
                $ignoreIcon = '<i class="fa-solid fa-bell-slash"></i>';
                $response['flash'] = '<b><i class="fas fa-check-circle"></i> Connection ignored.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

            } else {

                $distributorClinics->setIsIgnored(0);
                $ignoreIcon = '<i class="fa-regular fa-bell-slash text-disabled"></i>';
                $response['flash'] = '<b><i class="fas fa-check-circle"></i> Connection not ignored.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';
            }

            $this->em->persist($distributorClinics);
            $this->em->flush();
        }

        $response['icon'] = $ignoreIcon;

        return new JsonResponse($response);
    }
}
