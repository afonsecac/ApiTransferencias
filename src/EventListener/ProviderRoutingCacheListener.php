<?php

namespace App\EventListener;

use App\Entity\ClientProviderRouting;
use App\Repository\ClientProviderRoutingRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class ProviderRoutingCacheListener
{
    public function __construct(private readonly ClientProviderRoutingRepository $routingRepo) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->maybeInvalidate($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->maybeInvalidate($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->maybeInvalidate($args->getObject());
    }

    private function maybeInvalidate(object $entity): void
    {
        if ($entity instanceof ClientProviderRouting) {
            $this->routingRepo->invalidateCache();
        }
    }
}
