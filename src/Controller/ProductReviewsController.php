<?php

namespace App\Controller;

use App\Entity\Clinics;
use App\Entity\ClinicUsers;
use App\Entity\ProductReviewComments;
use App\Entity\ProductReviewLikes;
use App\Entity\ProductReviews;
use App\Entity\Products;
use Doctrine\ORM\EntityManagerInterface;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ProductReviewsController extends AbstractController
{
    private $em;
    private $encryptor;
    private $mailer;

    public function __construct(EntityManagerInterface $em, Encryptor $encryptor, MailerInterface $mailer) {
        $this->em = $em;
        $this->encryptor = $encryptor;
        $this->mailer = $mailer;
    }

    #[Route('clinics/create-review', name: 'create_review')]
    public function createReviewAction(Request $request): Response
    {
        $data = $request->request;
        $product = $this->em->getRepository(Products::class)->find((int) $data->get('review_product_id'));
        $user = $this->em->getRepository(ClinicUsers::class)->find($this->getUser()->getId());
        $clinic = $this->em->getRepository(Clinics::class)->find($this->getUser()->getClinic()->getId());
        $review = new ProductReviews();

        $review->setClinicUser($user);
        $review->setClinic($clinic->getClinicName());
        $review->setProduct($product);
        $review->setReview($data->get('review'));
        $review->setRating($data->get('rating'));
        $review->setIsApproved(0);

        $this->em->persist($review);
        $this->em->flush();

        $url = $this->getParameter('app.base_url') .'/admin/review/'. $review->getId();

        // Approval Email
        $body = '
        <table style="border-collapse: collapse; padding: 10px; font-family: Arial; font-size: 14px; width: 700px;">
            <tr>
                <td colspan="2">
                    <p>Please <a href="'. $url .'">click here</a> to approve or reject the review.</p>
                </td>
            </tr>
        </table>
        <br>';

        $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
            'html'  => $body,
        ]);

        $email = (new Email())
            ->from($this->getParameter('app.email_from'))
            ->addTo($this->getParameter('app.email_from'))
            ->subject('Fluid - New Review')
            ->html($html->getContent());

        $this->mailer->send($email);

        $response = '<b><i class="fa-solid fa-circle-check"></i></i></b> Review Submitted For Approval.<div class="flash-close"><i class="fa-solid fa-xmark"></i></div>';

        return new JsonResponse($response);
    }

    #[Route('clinics/get-reviews/{product_id}', name: 'get_reviews')]
    public function getReviewsAction(Request $request): Response
    {
        $productId = $request->get('product_id');

        $review = $this->em->getRepository(ProductReviews::class)->getAverageRating($productId);

        $response = [
            'review_count' => $review[0][2],
            'review_average' => number_format($review[0][1],1)
        ];

        return new JsonResponse($response);
    }

    #[Route('clinics/get-reviews-on-load/{product_id}', name: 'get_reviews_on_load')]
    public function getReviewsOnLoadAction(Request $request): Response
    {
        $productId = $request->get('product_id');

        $review = $this->em->getRepository(ProductReviews::class)->getAverageRating($productId);

        $response = '<div id="review_count_'. $productId .'" class="d-inline-block">'. $review[0][2] .' Reviews</div>';
        $response .= "<script>rateStyle('". number_format($review[0][1],1) ."', 'parent_". $productId ."');</script>";

        return new Response($response);
    }

    #[Route('clinics/get-review-details/{product_id}', name: 'get_review_details')]
    public function getReviewDetailsAction(Request $request): Response
    {
        $viewAll = false;

        if($request->request->get('product_id') == null) {

            $productId = $request->get('product_id');
            $limit = 3;

        } else {

            $productId = $request->get('product_id');
            $limit = 100;
            $viewAll = true;
        }
        $productReview = $this->em->getRepository(ProductReviews::class)->findBy([
            'product' => $productId,
            'clinicUser' => $this->getUser()->getId(),
            'isApproved' => 1,
        ]);
        $product = $this->em->getRepository(Products::class)->find($productId);
        $reviews = $this->em->getRepository(ProductReviews::class)->findBy([
            'product' => $product,
            'isApproved' => 1,
        ],[
            'id' => 'DESC'
        ], $limit);
        $rating1 = $this->em->getRepository(ProductReviews::class)->getProductRating($product->getId(),1);
        $rating2 = $this->em->getRepository(ProductReviews::class)->getProductRating($product->getId(),2);
        $rating3 = $this->em->getRepository(ProductReviews::class)->getProductRating($product->getId(),3);
        $rating4 = $this->em->getRepository(ProductReviews::class)->getProductRating($product->getId(),4);
        $rating5 = $this->em->getRepository(ProductReviews::class)->getProductRating($product->getId(),5);
        $response = '
        <div class="row">
            <div class="col-12" id="review_details_container">
                <h5 class="pb-3 pt-3">Reviews</h5><h6 class="pb-4 recent-reviews">Showing the 3 most recent reviews</h6>
        ';
        $writeReview = '';

        if($productReview != null){

            $writeReview = 'btn-secondary disabled';
        }

        if(empty($rating1)){

            $rating1[0]['total'] = 0;
        }

        if(empty($rating2)){

            $rating2[0]['total'] = 0;
        }

        if(empty($rating3)){

            $rating3[0]['total'] = 0;
        }

        if(empty($rating4)){

            $rating4[0]['total'] = 0;
        }

        if(empty($rating5)){

            $rating5[0]['total'] = 0;
        }

        $total = $rating1[0]['total'] + $rating2[0]['total'] + $rating3[0]['total'] + $rating4[0]['total'] + $rating5[0]['total'];

        $star1 = 0;
        $star2 = 0;
        $star3 = 0;
        $star4 = 0;
        $star5 = 0;

        if($rating1[0]['total'] > 0){

            $star1 = round($rating1[0]['total'] / $total * 100);
        }

        if($rating2[0]['total'] > 0){

            $star2 = round($rating2[0]['total'] / $total * 100);
        }

        if($rating3[0]['total'] > 0){

            $star3 = round($rating3[0]['total'] / $total * 100);
        }

        if($rating4[0]['total'] > 0){

            $star4 = round($rating4[0]['total'] / $total * 100);
        }

        if($rating5[0]['total'] > 0){

            $star5 = round($rating5[0]['total'] / $total * 100);
        }

        if($reviews != null) {

            $response .= '
            <div class="row">
                <div class="col-12 col-sm-6 text-center">
                    <div class="star-rating-container">
                        <div class="star-rating-col-sm info">
                            5 <i class="fa-light fa-star"></i>
                        </div>
                        <div class="star-rating-col-lg info">
                            <div class="progress-outer">
                                <div class="progress-inner" style="width: '. $star5 .'%;"></div>
                            </div>
                        </div>
                        <div class="star-rating-col-sm info text-start">
                            '. $star5 .'%
                        </div>
                    </div>
                    <div class="star-rating-container">
                        <div class="star-rating-col-sm info">
                            4  <i class="fa-light fa-star"></i>
                        </div>
                        <div class="star-rating-col-lg info">
                            <div class="progress-outer">
                                <div class="progress-inner" style="width: '. $star4 .'%;"></div>
                            </div>
                        </div>
                        <div class="star-rating-col-sm info text-start">
                            '. $star4 .'%
                        </div>
                    </div>
                    <div class="star-rating-container">
                        <div class="star-rating-col-sm info">
                            3  <i class="fa-light fa-star"></i>
                        </div>
                        <div class="star-rating-col-lg info">
                            <div class="progress-outer">
                                <div class="progress-inner" style="width: '. $star3 .'%;"></div>
                            </div>
                        </div>
                        <div class="star-rating-col-sm info text-start">
                            '. $star3 .'%
                        </div>
                    </div>
                    <div class="star-rating-container">
                        <div class="star-rating-col-sm info">
                            2  <i class="fa-light fa-star"></i>
                        </div>
                        <div class="star-rating-col-lg info">
                            <div class="progress-outer">
                                <div class="progress-inner" style="width: '. $star2 .'%;"></div>
                            </div>
                        </div>
                        <div class="star-rating-col-sm info text-start">
                            '. $star2 .'%
                        </div>
                    </div>
                    <div class="star-rating-container">
                        <div class="star-rating-col-sm info">
                            1  <i class="fa-light fa-star"></i>
                        </div>
                        <div class="star-rating-col-lg info">
                            <div class="progress-outer">
                                <div class="progress-inner" style="width: '. $star1 .'%;"></div>
                            </div>
                        </div>
                        <div class="star-rating-col-sm info text-start">
                            '. $star1 .'%
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 text-center pt-4 pb-4 pt-sm-0 pb-sm-0">
                    <h6>Help other Fluid clinics</h6>
                    <p>Let thousands of veterinary purchasers know about<br> your experience with this product</p>
                    <a 
                        href="" 
                        class="btn btn-primary btn_create_review w-sm-100 '. $writeReview .'" 
                        data-bs-toggle="modal" data-product-id="'. $productId .'" 
                        data-bs-target="#modal_review">
                        WRITE A REVIEW
                    </a>
                </div>
            </div>';

            $c = 0;

            foreach ($reviews as $review) {

                if ($review->getIsApproved() == 1) {

                    $c++;

                    $productReviewComments = $this->em->getRepository(ProductReviewComments::class)->findBy([
                        'review' => $review->getId()
                    ]);
                    $productReviewLikes = $this->em->getRepository(ProductReviewLikes::class)->findBy([
                        'productReview' => $review->getId(),
                        'clinicUser' => $this->getUser()->getId(),
                    ]);

                    if (count($productReviewLikes) == 1) {

                        $likeIcon = 'text-secondary';

                    } else {

                        $likeIcon = 'list-icon-unchecked';
                    }

                    $likeCount = $this->em->getRepository(ProductReviewLikes::class)->findBy([
                        'productReview' => $review->getId()
                    ]);

                    $response .= '
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3 mt-2 d-inline-block">
                    ';

                    for ($i = 0; $i < $review->getRating(); $i++) {

                        $response .= '<i class="star star-over fa fa-star star-visible position-relative start-sm-over"></i>';
                    }

                    for ($i = 0; $i < (5 - $review->getRating()); $i++) {

                        $response .= '<i class="star star-under fa fa-star"></i>';
                    }

                    $commentCount = '';

                    if (count($productReviewComments) > 0) {

                        $commentCount = ' (' . count($productReviewComments) . ')';
                    }

                    $viewAllReviews = '';
                    $firstName = $this->encryptor->decrypt($review->getClinicUser()->getFirstName());
                    $lastName = $this->encryptor->decrypt($review->getClinicUser()->getLastName());
                    $position = $this->encryptor->decrypt($review->getClinicUser()->getPosition());

                    if (count($reviews) == $c) {

                        $viewAllReviews = '
                        <button 
                            class="btn btn-sm btn-white float-end info btn-view-all-reviews"
                            data-product-id="' . $productId . '"
                        >
                            View All Reviews
                        </button>';
                    }

                    $response .= '    
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-2">
                            Written on ' . $review->getCreated()->format('d M Y') . ' by <b>' . $firstName . ' ' . $lastName . ', ' . $position . '</b>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p>' . $review->getReview() . '</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 review-comments-row">
                            <button 
                                class="btn btn-sm btn-white review-like me-3 ' . $likeIcon . '" 
                                id="like_' . $review->getId() . '" 
                                data-review-id="' . $review->getId() . '"
                            >
                                <i class="fa-solid fa-thumbs-up review-icon me-2 ' . $likeIcon . '"></i> ' . count($likeCount) . '
                            </button>';

                            if (count($productReviewComments) > 0) {

                                $displayComment = false;

                                foreach($productReviewComments as $comment)
                                {
                                    if($comment->getIsApproved() == 1)
                                    {
                                        $displayComment = true;
                                        break;
                                    }
                                }

                                if($displayComment) {

                                    $response .= '
                                    <button 
                                        class="btn btn-sm btn-white btn-comment me-3" 
                                        data-review-id="' . $review->getId() . '"
                                        id="btn_comment_' . $review->getId() . '"
                                    >
                                        <i 
                                            class="fa-solid fa-comment review-icon me-2 list-icon-unchecked"
                                             id="comment_icon_' . $review->getId() . '"
                                        ></i> 
                                        <span class="list-icon-unchecked" id="comment_span_' . $review->getId() . '">
                                            <span class="d-none d-sm-inline">View Comments </span>' . $commentCount . '
                                        </span>
                                    </button>';
                                }
                            }

                            $response .= '
                            <button 
                                class="btn btn-sm btn-white btn-leave-comment me-3" 
                                data-review-id="' . $review->getId() . '"
                                id="btn_leave_comment_' . $review->getId() . '"
                            >
                                <i 
                                    class="fa-solid fa-comment-plus review-icon leave-comment me-2 list-icon-unchecked"
                                     id="leave_comment_' . $review->getId() . '"
                                ></i> 
                                <span class="list-icon-unchecked" id="leave_comment_span_' . $review->getId() . '">
                                    <span class="d-none d-sm-inline leave-comment">Leave Comment</span>
                                </span>
                            </button>
                            ' . $viewAllReviews . '
                        </div>
                    </div>
                    
                    <!-- View Comments -->
                    <div class="row comment-container hidden" id="comment_container_' . $review->getId() . '">
                        <div class="col-12">
                            <div class="mb-5">
                                <div class="row">
                                    <div class="col-12" id="review_comments_' . $review->getId() . '">';

                                    // Comments
                                    if (count($productReviewComments) > 0) {

                                        foreach ($productReviewComments as $comment) {

                                            if ($comment->getIsApproved() == 1) {

                                                $firstName = $this->encryptor->decrypt($comment->getClinicUser()->getFirstName());
                                                $lastName = $this->encryptor->decrypt($comment->getClinicUser()->getLastName());
                                                $position = $this->encryptor->decrypt($comment->getClinic()->getClinicUsers()[0]->getPosition());

                                                $response .= '
                                                <div class="row mt-4">
                                                    <div class="col-12">
                                                        <b>' . $firstName . ' ' . $lastName . '</b> 
                                                        ' . $position . ' ' . $comment->getCreated()->format('dS M Y H:i') . '
                                                    </div>
                                                    <div class="col-12">
                                                        ' . $comment->getComment() . '
                                                    </div>
                                                </div>';
                                            }
                                        }
                                    }

                                    $response .= '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Leave Comment -->
                    <div class="row comment-container hidden" id="leave_comment_container_' . $review->getId() . '">
                        <div class="col-12">
                            <div class="mb-5">
                                <form name="form-comment" class="form-comment" data-review-id="' . $review->getId() . '" method="post">
                                    <input type="hidden" name="review_id" value="' . $review->getId() . '">
                                    <div class="row">
                                        <div class="col-12 col-sm-10">
                                            <textarea 
                                                name="comment"
                                                id="comment_' . $review->getId() . '"
                                                class="form-control d-inline-block" 
                                                placeholder="Leave a comment on this review..."
                                                rows="3"
                                            ></textarea>
                                            <div class="hidden_msg" id="error_comment_' . $review->getId() . '">
                                                Required Field
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <button 
                                                type="submit" 
                                                class="btn btn-primary d-inline-block w-sm-100 mt-3 mt-sm-0" 
                                                data-review-id="' . $review->getId() . '">
                                                COMMENT
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';
                }
            }

        } else {

            $response = '
            <div class="row">
                <div class="col-12 text-center pt-4 pb-4">
                    <h6>Help other Fluid clinics</h6>
                    <p>Let thousands of veterinary purchasers know about<br> your experience with this product</p>
                    <a 
                        href="" 
                        class="btn btn-primary btn_create_review w-sm-100 '. $writeReview .'" 
                        data-bs-toggle="modal" data-product-id="'. $productId .'" 
                        data-bs-target="#modal_review">
                        WRITE A REVIEW
                    </a>
                </div>
            </div>';
        }

        $response .='
            </div>
        </div>';

        if($viewAll)
        {
            $response .= '
            <!-- Modal Review -->
            <div class="modal fade" id="modal_review_all" tabindex="-1" aria-labelledby="review_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header basket-modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form name="form_review" id="form_review" method="post">
                            <input type="hidden" name="review_product_id" id="review_product_id" value="0">
                            <input type="hidden" name="rating" id="rating" value="0">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12 col-sm-4 mb-0">
                                        <h6>Review guidelines</h6>
                                        <br>
                                        <ul>
                                            <li>
                                                <b>Be Helpful and Relevant</b> - Reviews are intended to provide helpful,
                                                meaningful content to customers.
                                                <br>
                                                <br>
                                            </li>
                                            <li>
                                                <b>Be Honest</b> - In order to preserve the integrity of our reviews, content
                                                should be an accurate representation of your experience with this item.
                                                Fluid strictly forbids commercial solicitations or compensation in exchange
                                                for positive reviews.
                                                <br>
                                                <br>
                                            </li>
                                            <li>
                                                <b>Stay Relevant</b> - Reviews should focus on the pros and cons of the item.
                                                Reviews focusing on the supplier or manufacturer directly will not be
                                                approved.
                                                <br>
                                                <br>
                                            </li>
                                            <li>
                                                <b>Acknowledge</b> - Please note that by submitting you acknowledge that
                                                your review may be used by the manufacturer in future marketing materials
                                                for this brand.
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-12 col-sm-8 mb-0 ps-3 ps-sm-5">
                                        <h5>Write a review for:</h5>
                                        <h6 class="text-primary">Terbinafine Tablets: 250mg, 100 Count</h6>
                                        <br>
                                        RATE THIS ITEM
                                        <div id="review_rating" class="mb-3 mt-2">
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-1"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-1"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-2"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-2"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-3"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-3"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-4"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-4"></i>
                                            </div>
                                            <div style="position: relative; display: inline-block">
                                                <i class="star star-under fa fa-star star-lg" id="star-under-5"></i>
                                                <i class="star star-over fa fa-star star-lg" id="star-over-5"></i>
                                            </div>
                                            <div class="hidden_msg" id="error_rating">
                                                Please click to rate this item
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <label>Review</label>
                                                <textarea rows="4" name="review" id="review" class="form-control"></textarea>
                                                <div class="hidden_msg" id="error_review">
                                                    Required Field
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger btn-sm w-sm-100" data-bs-dismiss="modal">CANCEL</button>
                                <button type="submit" class="btn btn-primary btn-sm w-sm-100">CREATE REVIEW</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        }

        if($product->getForm() == 'Each'){

            $dosage = $product->getSize() . $product->getUnit();

        } else {

            $dosage = $product->getDosage() . $product->getUnit();
        }

        $json = [
            'response' => $response,
            'product_name' => $product->getName() .' '. $dosage,
        ];

        return new JsonResponse($json);
    }

    #[Route('clinics/like-review', name: 'like_review')]
    public function likeReviewAction(Request $request): Response
    {
        $data = $request->request;
        $reviewId = $data->get('review_id');

        $user = $this->getUser()->getId();
        $productReview = $this->em->getRepository(ProductReviews::class)->find($reviewId);
        $productReviewLikes = $this->em->getRepository(ProductReviewLikes::class)->findBy([
            'productReview' => $reviewId,
            'clinicUser' => $user
        ]);
        $prc = $productReviewLikes;

        if(count($productReviewLikes) == 0){

            $productReviewLikes = new ProductReviewLikes();

            $productReviewLikes->setClinicUser($this->getUser());
            $productReviewLikes->setProductReview($productReview);

            $this->em->persist($productReviewLikes);

            $response = '<i class="fa-solid fa-thumbs-up text-secondary review-icon me-2"></i>';

        } else {

            $productReviewLikes = $this->em->getRepository(ProductReviewLikes::class)->find($productReviewLikes[0]->getId());
            $this->em->remove($productReviewLikes);

            $response = '<i class="fa-solid fa-thumbs-up list-icon-unchecked review-icon me-2"></i>';
        }

        $this->em->flush();

        $likeCount = $this->em->getRepository(ProductReviewLikes::class)->findBy([
            'productReview' => $reviewId
        ]);

        if(count($prc) == 0){

            $response .= '<span class="text-secondary">'. (int) count($likeCount) .'</span>';

        } else {

            $response .= '<span class="list-icon-unchecked">'. (int) count($likeCount) .'</span>';
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/manage-comment', name: 'inventory_manage_comment')]
    public function clinicsManageCommentAction(Request $request): Response
    {
        $data = $request->request;
        $reviewId = $data->get('review_id');

        if($reviewId > 0) {

            $review = $this->em->getRepository(ProductReviews::class)->find($reviewId);
            $reviewComment = new ProductReviewComments();

            $reviewComment->setClinicUser($this->getUser());
            $reviewComment->setClinic($this->getUser()->getClinic());
            $reviewComment->setReview($review);
            $reviewComment->setComment($data->get('comment'));
            $reviewComment->setIsApproved(0);

            $this->em->persist($reviewComment);
            $this->em->flush();

            // Approval Email
            $url = $this->getParameter('app.base_url') .'/admin/comment/'. $reviewComment->getId();
            $body = '
            <table style="border-collapse: collapse; padding: 10px; font-family: Arial; font-size: 14px; width: 700px;">
                <tr>
                    <td colspan="2">
                        <p>Please <a href="'. $url .'">click here</a> to approve or reject the comment.</p>
                    </td>
                </tr>
            </table>
            <br>';

            $html = $this->forward('App\Controller\ResetPasswordController::emailFooter', [
                'html'  => $body,
            ]);

            $email = (new Email())
                ->from($this->getParameter('app.email_from'))
                ->addTo($this->getParameter('app.email_from'))
                ->subject('Fluid - New Comment')
                ->html($html->getContent());

            $this->mailer->send($email);
        }

        $reviewComments = $this->em->getRepository(ProductReviewComments::class)->findBy([
            'review' => $reviewId
        ]);

        $response = '';

        if(count($reviewComments) > 0) {

            foreach ($reviewComments as $comment) {

                $firstName = $this->encryptor->decrypt($comment->getClinicUser()->getFirstName());
                $lastName = $this->encryptor->decrypt($comment->getClinicUser()->getLastName());
                $position = $this->encryptor->decrypt($comment->getClinicUser()->getPosition());
                $created = $comment->getCreated()->format('dS M Y H:i');
                $reviewComment = $comment->getComment();

                $response .= '
                <div class="row mt-4">
                    <div class="col-12">
                        <b>' . $firstName .' '. $lastName . '</b> 
                        ' . $position . ' '. $created .'
                    </div>
                    <div class="col-12">
                        ' . $reviewComment . '
                    </div>
                </div>';
            }
        }

        return new JsonResponse($response);
    }

    #[Route('/clinics/get-comment-count', name: 'get_comment_count')]
    public function clinicsGetCommentCountAction(Request $request): Response
    {
        $data = $request->request;
        $reviewId = $data->get('review_id');
        $response = '';

        if($reviewId > 0) {

            $reviewComments = $this->em->getRepository(ProductReviewComments::class)->findBy([
                'review' => $reviewId
            ]);

            if(count($reviewComments) > 0) {

                $response = ' ('. count($reviewComments) .')';
            }
        }

        return new JsonResponse($response);
    }
}
