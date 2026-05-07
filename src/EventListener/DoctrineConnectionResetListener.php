<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Closes the DBAL connection before each worker message so that a DB restart
 * (e.g. container recreate) does not leave a stale PDO handle that crashes the
 * next scheduled task or async handler.  Doctrine reconnects lazily on the
 * first query after close().
 */
#[AsEventListener]
class DoctrineConnectionResetListener
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(WorkerMessageReceivedEvent $event): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isConnected()) {
            $connection->close();
        }
        $this->em->clear();
    }
}
