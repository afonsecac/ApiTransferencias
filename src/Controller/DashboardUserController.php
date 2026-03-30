<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[Route('/users')]
class DashboardUserController extends AbstractController
{
    /**
     * Orden de jerarquía de mayor a menor. Se usa para comparar rangos.
     */
    private const ROLE_WEIGHT = [
        'ROLE_SUPER_ADMIN' => 100,
        'ROLE_ADMIN' => 90,
        'ROLE_API_ADMIN' => 80,
        'ROLE_COM_API_ADMIN' => 70,
        'ROLE_REM_API_ADMIN' => 70,
        'ROLE_SYSTEM_ADMIN' => 60,
        'ROLE_API_EDITOR' => 50,
        'ROLE_SYSTEM_EDITOR' => 40,
        'ROLE_COM_API_USER' => 30,
        'ROLE_REM_API_USER' => 30,
        'ROLE_SYSTEM_SHOW' => 20,
        'ROLE_API_USER' => 15,
        'ROLE_SYSTEM_USER' => 10,
        'ROLE_USER' => 0,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    #[Route('', name: 'dashboard_users_list', methods: ['GET'])]
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
    public function create(Request $request): JsonResponse
    {
        $currentUser = $this->getAuthUser();
        if ($currentUser === null) {
            return $this->unauthorized();
        }

        $data = $request->request->all();

        $errors = $this->validateUserData($data, true);
        if (!empty($errors)) {
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $errors]], Response::HTTP_BAD_REQUEST);
        }

        // Verificar que el rol asignado no sea superior al del usuario actual
        $targetRole = $data['role'] ?? 'ROLE_SYSTEM_USER';
        if (!$this->canAssignRole($currentUser, $targetRole)) {
            return $this->json([
                'error' => ['message' => 'Cannot assign a role higher than your own.'],
            ], Response::HTTP_FORBIDDEN);
        }

        // Verificar acceso al cliente destino
        $clientId = $data['companyId'] ?? $currentUser->getCompany()?->getId();
        if (!$this->isGranted('ROLE_ADMIN') && $clientId != $currentUser->getCompany()?->getId()) {
            return $this->json(['error' => ['message' => 'Cannot create users for another client.']], Response::HTTP_FORBIDDEN);
        }

        $client = $this->em->getRepository(Client::class)->find($clientId);
        if ($client === null) {
            return $this->json(['error' => ['message' => 'Client not found']], Response::HTTP_NOT_FOUND);
        }

        // Verificar email único
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing !== null) {
            return $this->json(['error' => ['message' => 'Email already in use.']], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName(mb_substr($data['firstName'], 0, 60));
        $user->setLastName(mb_substr($data['lastName'], 0, 120));
        $user->setRoles([$targetRole]);
        $user->setCompany($client);
        $user->setIsActive(true);
        $user->setIsCheckValidation(false);
        $user->setPermission([]);

        if (!empty($data['middleName'])) {
            $user->setMiddleName(mb_substr($data['middleName'], 0, 60));
        }
        if (!empty($data['jobTitle'])) {
            $user->setJobTitle(mb_substr($data['jobTitle'], 0, 255));
        }
        if (!empty($data['phoneNumber'])) {
            $user->setPhoneNumber(mb_substr($data['phoneNumber'], 0, 20));
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json($this->serializeUser($user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'dashboard_users_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
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
            return $this->json([
                'error' => ['message' => 'Cannot edit a user with higher or equal role.'],
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->request->all();

        if (isset($data['firstName'])) {
            $user->setFirstName(mb_substr($data['firstName'], 0, 60));
        }
        if (isset($data['lastName'])) {
            $user->setLastName(mb_substr($data['lastName'], 0, 120));
        }
        if (isset($data['middleName'])) {
            $user->setMiddleName(mb_substr($data['middleName'], 0, 60));
        }
        if (isset($data['jobTitle'])) {
            $user->setJobTitle(mb_substr($data['jobTitle'], 0, 255));
        }
        if (isset($data['phoneNumber'])) {
            $user->setPhoneNumber(mb_substr($data['phoneNumber'], 0, 20));
        }
        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                return $this->json(['error' => ['message' => 'Email already in use.']], Response::HTTP_CONFLICT);
            }
            $user->setEmail($data['email']);
        }

        // Cambio de rol: solo si puede asignar ese rol
        if (isset($data['role'])) {
            if (!$this->canAssignRole($currentUser, $data['role'])) {
                return $this->json([
                    'error' => ['message' => 'Cannot assign a role higher than your own.'],
                ], Response::HTTP_FORBIDDEN);
            }
            $user->setRoles([$data['role']]);
        }

        // Cambio de password
        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        // Cambio de cliente (solo ROLE_ADMIN)
        if (isset($data['companyId']) && $this->isGranted('ROLE_ADMIN')) {
            $client = $this->em->getRepository(Client::class)->find($data['companyId']);
            if ($client !== null) {
                $user->setCompany($client);
            }
        }

        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/{id}/toggle', name: 'dashboard_users_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
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
            return $this->json([
                'error' => ['message' => 'Cannot toggle a user with higher or equal role.'],
            ], Response::HTTP_FORBIDDEN);
        }

        // No permitir desactivarse a sí mismo
        if ($currentUser->getId() === $user->getId()) {
            return $this->json([
                'error' => ['message' => 'Cannot deactivate your own account.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setIsActive(!$user->isActive());
        $this->em->flush();

        return $this->json(['id' => $user->getId(), 'isActive' => $user->isActive()]);
    }

    #[Route('/{id}', name: 'dashboard_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
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
            return $this->json([
                'error' => ['message' => 'Cannot delete a user with higher or equal role.'],
            ], Response::HTTP_FORBIDDEN);
        }

        if ($currentUser->getId() === $user->getId()) {
            return $this->json([
                'error' => ['message' => 'Cannot delete your own account.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Soft delete
        $user->setRemovedAt(new \DateTimeImmutable('now'));
        $user->setIsActive(false);
        $this->em->flush();

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

    /**
     * Verifica que el usuario actual tenga acceso al cliente del usuario objetivo.
     * ROLE_ADMIN: cualquier cliente.
     * ROLE_COM_API_ADMIN/ROLE_API_ADMIN: solo su propio cliente.
     */
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

    /**
     * El peso del rol más alto del usuario.
     */
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

    /**
     * ¿El usuario actual puede gestionar (editar/eliminar/toggle) al usuario objetivo?
     * Solo si tiene estrictamente mayor jerarquía.
     */
    private function canManageUser(User $currentUser, User $targetUser): bool
    {
        return $this->getMaxRoleWeight($currentUser) > $this->getMaxRoleWeight($targetUser);
    }

    /**
     * ¿El usuario actual puede asignar este rol?
     * Solo puede asignar roles estrictamente inferiores al suyo.
     */
    private function canAssignRole(User $currentUser, string $role): bool
    {
        $currentWeight = $this->getMaxRoleWeight($currentUser);
        $targetWeight = self::ROLE_WEIGHT[$role] ?? 0;
        return $currentWeight > $targetWeight;
    }

    private function validateUserData(array $data, bool $isCreate): array
    {
        $errors = [];
        if ($isCreate) {
            if (empty($data['email'])) {
                $errors[] = 'email is required';
            }
            if (empty($data['firstName'])) {
                $errors[] = 'firstName is required';
            }
            if (empty($data['lastName'])) {
                $errors[] = 'lastName is required';
            }
            if (empty($data['password']) || mb_strlen($data['password']) < 8) {
                $errors[] = 'password is required (min 8 characters)';
            }
        }
        return $errors;
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
