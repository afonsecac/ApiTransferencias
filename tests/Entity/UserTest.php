<?php

namespace App\Tests\Entity;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserPassword;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\User
 */
class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testSetId(): void
    {
        $this->user->setId(42);
        $this->assertSame(42, $this->user->getId());
    }

    public function testGetSetEmail(): void
    {
        $result = $this->user->setEmail('test@example.com');
        $this->assertSame($this->user, $result);
        $this->assertSame('test@example.com', $this->user->getEmail());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $this->user->setEmail('user@test.com');
        $this->assertSame('user@test.com', $this->user->getUserIdentifier());
    }

    public function testGetUserIdentifierReturnsEmptyStringWhenEmailIsNull(): void
    {
        $this->assertSame('', $this->user->getUserIdentifier());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesWithCustomRoles(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesDeduplicatesRoleUser(): void
    {
        $this->user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $roles = $this->user->getRoles();
        $this->assertCount(2, $roles);
    }

    public function testSetRolesReturnsSelf(): void
    {
        $result = $this->user->setRoles(['ROLE_ADMIN']);
        $this->assertSame($this->user, $result);
    }

    public function testGetSetPassword(): void
    {
        $result = $this->user->setPassword('hashed_password_123');
        $this->assertSame($this->user, $result);
        $this->assertSame('hashed_password_123', $this->user->getPassword());
    }

    public function testEraseCredentials(): void
    {
        // eraseCredentials should not throw and should be callable
        $this->user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testGetSetCompany(): void
    {
        $client = new Client();
        $result = $this->user->setCompany($client);
        $this->assertSame($this->user, $result);
        $this->assertSame($client, $this->user->getCompany());
    }

    public function testSetCompanyNullable(): void
    {
        $this->user->setCompany(null);
        $this->assertNull($this->user->getCompany());
    }

    public function testGetSetPermission(): void
    {
        $permission = ['read', 'write'];
        $result = $this->user->setPermission($permission);
        $this->assertSame($this->user, $result);
        $this->assertSame($permission, $this->user->getPermission());
    }

    public function testGetSetFirstName(): void
    {
        $result = $this->user->setFirstName('John');
        $this->assertSame($this->user, $result);
        $this->assertSame('John', $this->user->getFirstName());
    }

    public function testGetSetMiddleName(): void
    {
        $result = $this->user->setMiddleName('Robert');
        $this->assertSame($this->user, $result);
        $this->assertSame('Robert', $this->user->getMiddleName());
    }

    public function testSetMiddleNameNullable(): void
    {
        $this->user->setMiddleName(null);
        $this->assertNull($this->user->getMiddleName());
    }

    public function testGetSetLastName(): void
    {
        $result = $this->user->setLastName('Doe');
        $this->assertSame($this->user, $result);
        $this->assertSame('Doe', $this->user->getLastName());
    }

    public function testGetSetJobTitle(): void
    {
        $result = $this->user->setJobTitle('Developer');
        $this->assertSame($this->user, $result);
        $this->assertSame('Developer', $this->user->getJobTitle());
    }

    public function testSetJobTitleNullable(): void
    {
        $this->user->setJobTitle(null);
        $this->assertNull($this->user->getJobTitle());
    }

    public function testGetSetIsActive(): void
    {
        $result = $this->user->setIsActive(true);
        $this->assertSame($this->user, $result);
        $this->assertTrue($this->user->isIsActive());
    }

    public function testIsActiveAlias(): void
    {
        $this->user->setActive(true);
        $this->assertTrue($this->user->isActive());
    }

    public function testGetSetIsCheckValidation(): void
    {
        $result = $this->user->setIsCheckValidation(true);
        $this->assertSame($this->user, $result);
        $this->assertTrue($this->user->isIsCheckValidation());
    }

    public function testCheckValidationAlias(): void
    {
        $this->user->setCheckValidation(false);
        $this->assertFalse($this->user->isCheckValidation());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->user->setCreatedAt($date);
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getCreatedAt());
    }

    public function testGetSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->user->setUpdatedAt($date);
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getUpdatedAt());
    }

    public function testSetCreatedAtNowLifecycleCallback(): void
    {
        $this->user->setCreatedAtNow();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->user->getCreatedAt());
    }

    public function testSetUpdatedAtNowLifecycleCallback(): void
    {
        $this->user->setUpdatedAtNow();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->user->getUpdatedAt());
    }

    public function testGetSetIsActiveAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $result = $this->user->setIsActiveAt($date);
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getIsActiveAt());
    }

    public function testGetSetIsCheckValidationAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $result = $this->user->setIsCheckValidationAt($date);
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getIsCheckValidationAt());
    }

    public function testGetSetRemovedAt(): void
    {
        $date = new \DateTimeImmutable('2024-12-31');
        $result = $this->user->setRemovedAt($date);
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getRemovedAt());
    }

    public function testGetSetPhoneNumber(): void
    {
        $result = $this->user->setPhoneNumber('+1234567890');
        $this->assertSame($this->user, $result);
        $this->assertSame('+1234567890', $this->user->getPhoneNumber());
    }

    public function testGetSetCurrentIp(): void
    {
        $this->user->setCurrentIp('192.168.1.1');
        $this->assertSame('192.168.1.1', $this->user->getCurrentIp());
    }

    public function testGetSetStatus(): void
    {
        $result = $this->user->setStatus('online');
        $this->assertSame($this->user, $result);
        $this->assertSame('online', $this->user->getStatus());
    }

    public function testGetSetLangPreference(): void
    {
        $result = $this->user->setLangPreference('es');
        $this->assertSame($this->user, $result);
        $this->assertSame('es', $this->user->getLangPreference());
    }

    public function testGetHistoricPasswordsReturnsCollection(): void
    {
        $this->assertInstanceOf(Collection::class, $this->user->getHistoricPasswords());
        $this->assertCount(0, $this->user->getHistoricPasswords());
    }

    public function testAddHistoricPassword(): void
    {
        $password = $this->createMock(UserPassword::class);
        $password->expects($this->once())
            ->method('setUserHistoric')
            ->with($this->user);

        $result = $this->user->addHistoricPassword($password);
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getHistoricPasswords());
    }

    public function testAddHistoricPasswordDoesNotDuplicate(): void
    {
        $password = $this->createMock(UserPassword::class);
        $password->expects($this->once())
            ->method('setUserHistoric');

        $this->user->addHistoricPassword($password);
        $this->user->addHistoricPassword($password);
        $this->assertCount(1, $this->user->getHistoricPasswords());
    }

    public function testRemoveHistoricPassword(): void
    {
        $password = $this->createMock(UserPassword::class);
        $password->method('setUserHistoric');
        $password->method('getUserHistoric')->willReturn($this->user);

        $this->user->addHistoricPassword($password);
        $result = $this->user->removeHistoricPassword($password);
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getHistoricPasswords());
    }

    public function testPermissionDefaultsToEmptyArray(): void
    {
        $this->assertSame([], $this->user->getPermission());
    }

    public function testRolesDefaultsToOnlyRoleUser(): void
    {
        $roles = $this->user->getRoles();
        $this->assertSame(['ROLE_USER'], $roles);
    }
}
