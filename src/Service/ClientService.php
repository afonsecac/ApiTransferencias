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
        if (isset($params['orderBy'])) {
            $orderBy = [
                'orderBy' => $params['orderBy'],
                'direction' => $params['direction']
            ];
        }
        /** @var \App\Repository\ClientRepository $clientRepo */
        $clientRepo = $this->em->getRepository(Client::class);
        return $clientRepo->getAllClients($params, $orderBy);
    }
}
