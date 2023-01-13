<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class countArrayExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('count', [$this, 'countArray']),
        ];
    }

    public function countArray($array)
    {
        if(is_array($array)) {

            return count($array);

        } else {

            return 0;
        }
    }
}
