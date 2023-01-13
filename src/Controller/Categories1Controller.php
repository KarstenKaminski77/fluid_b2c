<?php

namespace App\Controller;

use App\Entity\Categories1;
use App\Entity\Categories2;
use App\Entity\Categories3;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Categories1Controller extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('/categories/get', name: 'get_child_categories')]
    public function getCategoriesAction(Request $request): Response
    {
        $parentId = $request->request->get('category_id');
        $level = $request->request->get('level') ?? 0;

        if($level == 1){

            $categories = $this->em->getRepository(Categories2::class)->findByParent($parentId);
            $nextLevel = 2;

        } elseif($level == 2){

            $categories = $this->em->getRepository(Categories3::class)->findByParent($parentId);
            $nextLevel = 3;

        } elseif($level == 3){

            $categories = $this->em->getRepository(Categories3::class)->findByParent($parentId);
            $nextLevel = '';
        }

        $response = '';

        foreach($categories[1] as $category){

            $catId = $category->getId();

            $response .= '
            <li 
                class="pt-0 pb-2 pt-md-0 pb-md-0 category-select"
                data-category-id="' . $catId . '"
                data-level="'. $nextLevel .'"
            >
                <input 
                    class="form-check-input me-2" 
                    name="category[]" type="checkbox" 
                    value="' . $catId . '" 
                    id="cat_' . $catId . '" 
                    data-category-id="' . $catId . '"
                    data-level="'. $nextLevel .'"
                >
                <label class="ms-1" for="cat_' . $catId . '">
                    (' . $category->getProductCount() . ') ' . $category->getName() . '
                </label>
            </li>';
        }

        $response .= '
        <li
            class="pb-2 pt-4 pb-md-0 reset-categories"
        >
            <i class="fa-solid fa-rotate info me-1"></i>
            <span class="info">Reset</span>
        </li>';

        return new JsonResponse($response);
    }
}