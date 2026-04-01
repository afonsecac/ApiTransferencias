<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

class DashboardStatisticsService extends CommonService
{
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        SerializerInterface $serializer,
        private readonly CommunicationSaleInfoRepository $saleRepo,
    ) {
        parent::__construct(
            $em, $security, $parameters, $mailer, $logger,
            $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer
        );
    }

    private function resolveClientId(?int $requestedClientId): ?int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $requestedClientId;
        }

        $ownClientId = $user->getCompany()?->getId();
        if ($requestedClientId !== null && $requestedClientId !== $ownClientId) {
            throw new AccessDeniedException();
        }

        return $ownClientId;
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'clientId'        => $this->resolveClientId($filters['clientId'] ?? null),
            'dateFrom'        => !empty($filters['dateFrom'])
                ? new \DateTimeImmutable($filters['dateFrom'])
                : new \DateTimeImmutable('-1 year'),
            'dateTo'          => !empty($filters['dateTo'])
                ? (new \DateTimeImmutable($filters['dateTo']))->modify('+1 day')
                : (new \DateTimeImmutable('now'))->modify('+1 day'),
            'environmentType' => $filters['environmentType'] ?? null,
            'type'            => $filters['type'] ?? null,
        ];
    }

    public function getSummary(array $filters): array
    {
        $f = $this->normalizeFilters($filters);
        return $this->saleRepo->getStatsSummary(
            $f['clientId'], $f['dateFrom'], $f['dateTo'], $f['environmentType'], $f['type']
        );
    }

    public function getOperationsByClient(array $filters): array
    {
        $f = $this->normalizeFilters($filters);
        return $this->saleRepo->getStatsOperationsByClient(
            $f['clientId'], $f['dateFrom'], $f['dateTo'], $f['environmentType'], $f['type']
        );
    }

    public function getOperationsOverTime(array $filters, string $groupBy): array
    {
        $f = $this->normalizeFilters($filters);
        $allowed = ['day', 'week', 'month'];
        if (!in_array($groupBy, $allowed, true)) {
            $groupBy = 'day';
        }
        return $this->saleRepo->getStatsOperationsOverTime(
            $f['clientId'], $f['dateFrom'], $f['dateTo'], $f['environmentType'], $f['type'], $groupBy
        );
    }

    public function getBusiestDays(array $filters): array
    {
        $f = $this->normalizeFilters($filters);
        return $this->saleRepo->getStatsBusiestDays(
            $f['clientId'], $f['dateFrom'], $f['dateTo'], $f['environmentType'], $f['type']
        );
    }

    public function getPeakHours(array $filters): array
    {
        $f = $this->normalizeFilters($filters);
        return $this->saleRepo->getStatsPeakHours(
            $f['clientId'], $f['dateFrom'], $f['dateTo'], $f['environmentType'], $f['type']
        );
    }

    public function getTopPackages(array $filters, int $limit): array
    {
        $f = $this->normalizeFilters($filters);
        return $this->saleRepo->getStatsTopPackages(
            $f['clientId'], $f['dateFrom'], $f['dateTo'], $f['environmentType'], $f['type'], $limit
        );
    }
}
