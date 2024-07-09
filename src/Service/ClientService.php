<?php

namespace App\Service;

use App\Entity\Client;
use App\EntityPaginator\PaginatorResponse;
use App\Service\CommonService;

class ClientService extends CommonService
{
    /**
     * @param array $params
     * @return \App\EntityPaginator\PaginatorResponse
     */
    public function getAllClients(array $params): PaginatorResponse
    {
        $orderBy = [];
        if (isset($params['orderBy']) && !is_null($params['orderBy'])) {
            $orderBy = [
                'orderBy' => $params['orderBy'],
                'direction' => $params['direction']
            ];
        }
        return $this->em->getRepository(Client::class)->getAllClients($params, $orderBy);
    }
}
