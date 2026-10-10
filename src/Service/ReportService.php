<?php

namespace App\Service;

use App\DTO\PaginationResult;
use App\Entity\ReportMarked;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ReportService extends CommonService
{
    /**
     * @param int|null $clientId para ROLE_ADMIN, filtra por un cliente concreto (null = todos);
     *     para un usuario normal, se ignora y se usa siempre su propio cliente.
     * @param int $page
     * @param int $limit
     * @return \App\DTO\PaginationResult
     */
    public function getAllReports(?int $clientId = null, int $page = 0, int $limit = 40): PaginationResult
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        if (!$this->security->isGranted('ROLE_SYSTEM_SHOW')) {
            throw new AccessDeniedException();
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $resolvedClientId = $clientId;
        } else {
            $ownClientId = $user->getCompany()?->getId();
            if ($clientId !== null && $clientId !== $ownClientId) {
                throw new AccessDeniedException();
            }
            $resolvedClientId = $ownClientId;
        }

        /** @var \App\Repository\ReportMarkedRepository $repo */
        $repo = $this->em->getRepository(ReportMarked::class);
        return $repo->list($resolvedClientId, $page, $limit);
    }

    public function getReport(int $id): ReportMarked
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        if (!$this->security->isGranted('ROLE_SYSTEM_SHOW')) {
            throw new AccessDeniedException();
        }
        $report = $this->em->getRepository(ReportMarked::class)->find($id);
        if (!$this->security->isGranted('ROLE_ADMIN') && $report?->getClient()?->getId() !== $user->getCompany(
            )?->getId()) {
                throw new AccessDeniedException();
            }
        return $report;
    }
}