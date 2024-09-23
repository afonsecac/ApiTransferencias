<?php

namespace App\Schedule\Task;

use App\Service\UserService;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("*/30 * * * *")]
class CloseSessionTask
{
    public function __construct(
        private readonly UserService $userService,
    )
    {

    }

    public function __invoke(): void
    {
        $this->userService->closeAllOpenSessions();
    }
}