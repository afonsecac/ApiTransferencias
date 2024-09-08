<?php

namespace App\Service;

use App\Entity\CommunicationSaleHistory;
use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Service\CommonService;

class HistoricalSaleService extends CommonService
{
    /**
     * @param int $saleId
     * @param \App\Enums\CommunicationStateEnum $state
     * @param array $info
     * @return void
     */
    public function createHistoricalCommunication(
        int $saleId,
        CommunicationStateEnum $state,
        array $info = []
    ): void
    {
        $historicalInfo = new CommunicationSaleHistory();
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        $historicalInfo->setSale($sale);
        $historicalInfo->setState($state);
        $historicalInfo->setInfo($info);

        $this->em->persist($historicalInfo);
    }
}