<?php

namespace App\Service;

use App\DTO\CreateUserDto;
use App\DTO\UpdateUserDto;
use App\Entity\Client;
use App\Entity\User;
use App\Exception\MyCurrentException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * @throws MyCurrentException
     */
    public function create(CreateUserDto $dto, int $companyId): User
    {
        $client = $this->em->getRepository(Client::class)->find($companyId);
        if ($client === null) {
            throw new MyCurrentException('CLIENT_NOT_FOUND', 'Client not found', 404);
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $dto->getEmail()]);
        if ($existing !== null) {
            throw new MyCurrentException('EMAIL_ALREADY_IN_USE', 'Email already in use.', 409);
        }

        $user = new User();
        $user->setEmail($dto->getEmail());
        $user->setFirstName(mb_substr($dto->getFirstName(), 0, 60));
        $user->setLastName(mb_substr($dto->getLastName(), 0, 120));
        $user->setRoles([$dto->getRole() ?? 'ROLE_SYSTEM_USER']);
        $user->setCompany($client);
        $user->setIsActive(true);
        $user->setIsCheckValidation(false);
        $user->setPermission([]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->getPassword()));

        if ($dto->getMiddleName() !== null) {
            $user->setMiddleName(mb_substr($dto->getMiddleName(), 0, 60));
        }
        if ($dto->getJobTitle() !== null) {
            $user->setJobTitle(mb_substr($dto->getJobTitle(), 0, 255));
        }
        if ($dto->getPhoneNumber() !== null) {
            $user->setPhoneNumber(mb_substr($dto->getPhoneNumber(), 0, 20));
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @throws MyCurrentException
     */
    public function update(User $user, UpdateUserDto $dto, bool $isAdmin): User
    {
        if ($dto->getFirstName() !== null) {
            $user->setFirstName(mb_substr($dto->getFirstName(), 0, 60));
        }
        if ($dto->getLastName() !== null) {
            $user->setLastName(mb_substr($dto->getLastName(), 0, 120));
        }
        if ($dto->getMiddleName() !== null) {
            $user->setMiddleName(mb_substr($dto->getMiddleName(), 0, 60));
        }
        if ($dto->getJobTitle() !== null) {
            $user->setJobTitle(mb_substr($dto->getJobTitle(), 0, 255));
        }
        if ($dto->getPhoneNumber() !== null) {
            $user->setPhoneNumber(mb_substr($dto->getPhoneNumber(), 0, 20));
        }
        if ($dto->getEmail() !== null && $dto->getEmail() !== $user->getEmail()) {
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $dto->getEmail()]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                throw new MyCurrentException('EMAIL_ALREADY_IN_USE', 'Email already in use.', 409);
            }
            $user->setEmail($dto->getEmail());
        }
        if ($dto->getRole() !== null) {
            $user->setRoles([$dto->getRole()]);
        }
        if ($dto->getPassword() !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $dto->getPassword()));
        }
        if ($dto->getCompanyId() !== null && $isAdmin) {
            $client = $this->em->getRepository(Client::class)->find($dto->getCompanyId());
            if ($client !== null) {
                $user->setCompany($client);
            }
        }

        $this->em->flush();

        return $user;
    }

    public function toggle(User $user): User
    {
        $user->setIsActive(!$user->isActive());
        $this->em->flush();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->setRemovedAt(new \DateTimeImmutable('now'));
        $user->setIsActive(false);
        $this->em->flush();
    }
}
