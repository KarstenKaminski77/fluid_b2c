<?php

namespace App\Controller;

use App\Entity\ArticleDetails;
use App\Entity\Articles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ArticlesController extends AbstractController
{
    private $emRemote;
    private $encryptor;

    public function __construct(ManagerRegistry $entityManager, Encryptor $encryptor)
    {
        $this->emRemote = $entityManager->getManager('remote');
        $this->encryptor = $encryptor;
    }

    #[Route('/article/{pageId}', name: 'articles_page')]
    public function articlesAction(Request $request): Response
    {
        $article = $this->emRemote->getRepository(Articles::class)->findByPageId($request->get('pageId'));

        return $this->render('frontend/articles.html.twig', [
            'articles' => $article,
        ]);
    }

    #[Route('/support/authors/{articleId}', name: 'support_authors')]
    public function articleAuthorsAction(Request $request): Response
    {
        $authors = $this->emRemote->getRepository(ArticleDetails::class)->findUsersByld($request->get('articleId'));
        $response = '';
        $count = count($authors);

        if($count > 3){

            $remaining = $count - 3;
            $response = 'and ' . $remaining . ' others';
        }

        return new Response($response);
    }

    #[Route('/article-list/authors/{articleDetailId}/{showAuthors}', name: 'article_list_authors')]
    public function articleListAuthorsAction(Request $request): Response
    {
        $articleDetails = $this->emRemote->getRepository(ArticleDetails::class)->find($request->get('articleDetailId'));
        $lastUpdated = $this->emRemote->getRepository(ArticleDetails::class)->findByLastUpdated($request->get('articleDetailId'));
        $response = '';

        if($request->get('showAuthors') == 1) {

            $response = 'Written By ';
            $authors = [];

            foreach ($articleDetails as $articleDetail) {

                $authors[] = $this->encryptor->decrypt($articleDetail->getUser()->getFirstName()) . ' ' . $this->encryptor->decrypt($articleDetail->getUser()->getLastName());
            }

            $separator = ', ';

            if(count($authors) <= 2){

                $separator = ' & ';
            }

            $response .= implode($separator, $authors);
        }

        if(array_key_exists(0, $lastUpdated)) {

            $modified = $lastUpdated[0]->getModified()->format('Y-m-d H:i:s');
            $response .= '<br>Updated ' . $this->timeAgo($modified);
        }

        return new Response($response);
    }

    #[Route('/article/{pageId}/{articleId}', name: 'article_list_page')]
    public function articleListAction(Request $request): Response
    {
        $articleId = $request->get('articleId');
        $article = $this->emRemote->getRepository(Articles::class)->find($articleId);

        return $this->render('frontend/articles_list.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/article/{pageId}/{articleId}/{articleDetailId}', name: 'article_details_page')]
    public function articleDetailsAction(Request $request): Response
    {
        $articleDetailId = $request->get('articleDetailId');
        $articleDetails = $this->emRemote->getRepository(ArticleDetails::class)->find($articleDetailId);

        return $this->render('frontend/article_details.html.twig', [
            'articleDetails' => $articleDetails,
        ]);
    }

    public function timeAgo($date) {

        $timestamp = strtotime($date);

        $strTime = ["second", "minute", "hour", "day", "month", "year"];
        $length = ["60","60","24","30","12","10"];
        $currentTime = time();

        if($currentTime >= $timestamp) {

            $diff = time() - $timestamp;

            for($i = 0; $diff >= $length[$i] && $i < count($length) - 1; $i++) {

                $diff = $diff / $length[$i];
            }

            $diff = round($diff);
            return $diff . " " . $strTime[$i] . "s ago ";
        }

        return '';
    }
}
