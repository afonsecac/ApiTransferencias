<?php

namespace App\Service;

use App\DTO\PaginationResult;
use App\Entity\ReportMarked;
use App\Entity\User;
use Symfony\Component\Finder\Exception\AccessDeniedException;

class ReportService extends CommonService
{
    /**
     * @param int $page
     * @param int $limit
     * @return \App\DTO\PaginationResult
     */
    public function getAllReports(int $page = 0, int $limit = 40): PaginationResult
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $accountId = null;
        if (!$this->security->isGranted('ROLE_SYSTEM_SHOW')) {
            throw new AccessDeniedException();
        }
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $accountId = $user->getCompany()?->getId();
        }

        /** @var \App\Repository\ReportMarkedRepository $repo */
        $repo = $this->em->getRepository(ReportMarked::class);
        return $repo->list($accountId, $page, $limit);
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