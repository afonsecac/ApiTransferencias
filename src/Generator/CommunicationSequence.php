<?php

namespace App\Generator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

class CommunicationSequence extends AbstractIdGenerator
{
    public function generate(EntityManager $em, $entity)
    {
        return $this->generateId($em, $entity);
    }

}
