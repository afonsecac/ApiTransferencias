<?php

namespace App\Service;

use App\Entity\NavigationItem;

class NavigationService extends CommonService
{
    /**
     * @return NavigationItem[]
     */
    public function getNavigationItems(): array
    {
        return $this->em->getRepository(NavigationItem::class)->getNavigationItems();
    }
}