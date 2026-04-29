<?php

namespace App\Controller;

use App\DTO\CreateUserDto;
use App\DTO\UpdateUserDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ToggleOutDto;
use App\DTO\Out\UserOutDto;
use App\Entity\Client;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\UserManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users')]
class DashboardUserController extends AbstractController
{
    private const ROLE_WEIGHT = [
        'ROLE_SUPER_ADMIN'    => 100,
        'ROLE_ADMIN'          => 90,
        'ROLE_API_ADMIN'      => 80,
        'ROLE_COM_API_ADMIN'  => 70,
        'ROLE_REM_API_ADMIN'  => 70,
        'ROLE_SYSTEM_ADMIN'   => 60,
        'ROLE_API_EDITOR'     => 50,
        'ROLE_SYSTEM_EDITOR'  => 40,
        'ROLE_COM_API_USER'   => 30,
        'ROLE_REM_API_USER'   => 30,
        'ROLE_SYSTEM_SHOW'    => 20,
        'ROLE_API_USER'       => 15,
        'ROLE_SYSTEM_USER'    => 10,
        'ROLE_USER'           => 0,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly ValidatorInterface $validator,
        private readonly UserManagementService $userManagementService,
    ) {
    }

    #[Route('', name: 'dashboard_users_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar usuarios', tag: 'Users', responseDto: PaginatedListOutDto::class, itemDto: UserOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $qb = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.company', 'c')
            ->orderBy('u.firstName', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $clientId = $currentUser->getCompany()?->getId();
            if ($clientId === null) {
                return $this->json([]);
            }
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $clientId);
        }

        $search = $request->query->get('search');
        if (!empty($search)) {
            $qb->andWhere('u.email LIKE :s OR u.firstName LIKE :s OR u.lastName LIKE :s')
                ->setParameter('s', '%' . $search . '%');
        }

        $clientFilter = $request->query->get('clientId');
        if (!empty($clientFilter) && $this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('c.id = :filterClient')->setParameter('filterClient', $clientFilter);
        }

        $users = $qb->getQuery()->getResult();

        return $this->json(array_map(fn(User $u) => $this->serializeUser($u), $users));
    }

    #[Route('/{id}', name: 'dashboard_users_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener usuario', tag: 'Users', responseDto: UserOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if ($user === null) {
            return $this->json(['error' => ['message' => 'User not found']], Response::HTTP_NOT_FOUND);
        }

        $access = $this->checkClientAccess($currentUser, $user);
        if ($access !== null) {
            return $access;
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('', name: 'dashboard_users_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear usuario', tag: 'Users', requestDto: CreateUserDto::class, responseDto: UserOutDto::class, responseStatusCode: 201)]
    public function create(CreateUserDto $dto): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        $targetRole = $dto->getRole() ?? 'ROLE_SYSTEM_USER';
        if (!$this->canAssignRole($currentUser, $targetRole)) {
            return $this->json(['error' => ['message' => 'Cannot assign a role higher than your own.']], Response::HTTP_FORBIDDEN);
        }

        $companyId = $dto->getCompanyId() ?? $currentUser->getCompany()?->getId();
        if (!$this->isGranted('ROLE_ADMIN') && $companyId != $currentUser->getCompany()?->getId()) {
            return $this->json(['error' => ['message' => 'Cannot create users for another client.']], Response::HTTP_FORBIDDEN);
        }

        $dto->setRole($targetRole);

        try {
            $user = $this->userManagementService->create($dto, (int) $companyId);
        } catch (MyCurrentException $e) {
            $status = $e->getCode() === 409 ? Response::HTTP_CONFLICT : $e->getCode();
            return $this->json(['error' => ['message' => $e->getMessage()]], $status);
        }

        return $this->json($this->serializeUser($user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'dashboard_users_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar usuario', tag: 'Users', requestDto: UpdateUserDto::class, responseDto: UserOutDto::class)]
    public function update(int $id, UpdateUserDto $dto): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if ($user === null) {
            return $this->json(['error' => ['message' => 'User not found']], Response::HTTP_NOT_FOUND);
        }

        $access = $this->checkClientAccess($currentUser, $user);
        if ($access !== null) {
            return $access;
        }

        if (!$this->canManageUser($currentUser, $user)) {
            return $this->json(['error' => ['message' => 'Cannot edit a user with higher or equal role.']], Response::HTTP_FORBIDDEN);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        if ($dto->getRole() !== null && !$this->canAssignRole($currentUser, $dto->getRole())) {
            return $this->json(['error' => ['message' => 'Cannot assign a role higher than your own.']], Response::HTTP_FORBIDDEN);
        }

        try {
            $user = $this->userManagementService->update($user, $dto, $this->isGranted('ROLE_ADMIN'));
        } catch (MyCurrentException $e) {
            $status = $e->getCode() === 409 ? Response::HTTP_CONFLICT : $e->getCode();
            return $this->json(['error' => ['message' => $e->getMessage()]], $status);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/{id}/toggle', name: 'dashboard_users_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar usuario', tag: 'Users', responseDto: ToggleOutDto::class)]
    public function toggle(int $id): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if ($user === null) {
            return $this->json(['error' => ['message' => 'User not found']], Response::HTTP_NOT_FOUND);
        }

        $access = $this->checkClientAccess($currentUser, $user);
        if ($access !== null) {
            return $access;
        }

        if (!$this->canManageUser($currentUser, $user)) {
            return $this->json(['error' => ['message' => 'Cannot toggle a user with higher or equal role.']], Response::HTTP_FORBIDDEN);
        }

        if ($currentUser->getId() === $user->getId()) {
            return $this->json(['error' => ['message' => 'Cannot deactivate your own account.']], Response::HTTP_BAD_REQUEST);
        }

        $this->userManagementService->toggle($user);

        return $this->json(['id' => $user->getId(), 'isActive' => $user->isActive()]);
    }

    #[Route('/{id}', name: 'dashboard_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar usuario', tag: 'Users', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if ($user === null) {
            return $this->json(['error' => ['message' => 'User not found']], Response::HTTP_NOT_FOUND);
        }

        $access = $this->checkClientAccess($currentUser, $user);
        if ($access !== null) {
            return $access;
        }

        if (!$this->canManageUser($currentUser, $user)) {
            return $this->json(['error' => ['message' => 'Cannot delete a user with higher or equal role.']], Response::HTTP_FORBIDDEN);
        }

        if ($currentUser->getId() === $user->getId()) {
            return $this->json(['error' => ['message' => 'Cannot delete your own account.']], Response::HTTP_BAD_REQUEST);
        }

        $this->userManagementService->delete($user);

        return $this->json(['deleted' => true]);
    }

    // ─── Helpers ────────────────────────────────

    private function getAuthUser(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }

    private function unauthorized(): JsonResponse
    {
        return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
    }

    private function checkClientAccess(User $currentUser, User $targetUser): ?JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if ($this->isGranted('ROLE_COM_API_ADMIN') || $this->isGranted('ROLE_API_ADMIN')) {
            if ($currentUser->getCompany()?->getId() === $targetUser->getCompany()?->getId()) {
                return null;
            }
        }

        return $this->json(['error' => ['message' => 'Access denied']], Response::HTTP_FORBIDDEN);
    }

    private function getMaxRoleWeight(User $user): int
    {
        $reachable = $this->roleHierarchy->getReachableRoleNames($user->getRoles());
        $max = 0;
        foreach ($reachable as $role) {
            $weight = self::ROLE_WEIGHT[$role] ?? 0;
            if ($weight > $max) {
                $max = $weight;
            }
        }
        return $max;
    }

    private function canManageUser(User $currentUser, User $targetUser): bool
    {
        return $this->getMaxRoleWeight($currentUser) > $this->getMaxRoleWeight($targetUser);
    }

    private function canAssignRole(User $currentUser, string $role): bool
    {
        return $this->getMaxRoleWeight($currentUser) > (self::ROLE_WEIGHT[$role] ?? 0);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'middleName' => $user->getMiddleName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'jobTitle' => $user->getJobTitle(),
            'phoneNumber' => $user->getPhoneNumber(),
            'isActive' => $user->isActive(),
            'isCheckValidation' => $user->isCheckValidation(),
            'company' => $user->getCompany() ? [
                'id' => $user->getCompany()->getId(),
                'companyName' => $user->getCompany()->getCompanyName(),
            ] : null,
            'createdAt' => $user->getCreatedAt()?->format('c'),
            'removedAt' => $user->getRemovedAt()?->format('c'),
        ];
    }
}
