<?php

namespace App\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Service\CommonService;

class BalanceService extends CommonService
{
    public function balance(int $userId): AccountBalanceDto
    {
        $balance = $this->em->getRepository(BalanceOperation::class)->getBalanceOutput($userId);
        $account = $this->em->getRepository(Account::class)->find($userId);
    }
}