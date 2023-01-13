<?php

namespace App\Controller;

use App\Entity\Categories3;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Categories2Controller extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('/categories/get-grandchildren', name: 'get_grandchildren_categories')]
    public function getCategoriesAction(Request $request): Response
    {
        $parentId = $request->request->get('category_id');
        $categories = $this->em->getRepository(Categories3::class)->findByParent($parentId);
        $response = '';

        foreach($categories as $category){

            $catId = $category->getId();

            $response .= '
            <li 
                class="pt-0 pb-2 pt-md-0 pb-md-0 category-select2"
                data-category-id="' . $catId . '"
            >
                <input 
                    class="form-check-input me-2" 
                    name="category[]" type="checkbox" 
                    value="' . $catId . '" 
                    id="cat_' . $catId . '" 
                    data-category-id="' . $catId . '"
                    data-level="2"
                >
                <label class="ms-1" for="cat_' . $catId . '">
                    ' . $category->getName() . '
                </label>
            </li>';
        }

        return new JsonResponse($response);
    }
}