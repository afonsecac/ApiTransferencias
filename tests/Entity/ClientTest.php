<?php

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\Client;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Client
 */
class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client();
    }

    public function testConstructorDefaults(): void
    {
        $client = new Client();
        $this->assertFalse($client->isIsActive());
        $this->assertInstanceOf(Collection::class, $client->getAccounts());
        $this->assertCount(0, $client->getAccounts());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->client->getId());
    }

    public function testGetSetCompanyName(): void
    {
        $result = $this->client->setCompanyName('Acme Corp');
        $this->assertSame($this->client, $result);
        $this->assertSame('Acme Corp', $this->client->getCompanyName());
    }

    public function testGetSetCompanyAddress(): void
    {
        $result = $this->client->setCompanyAddress('123 Main St');
        $this->assertSame($this->client, $result);
        $this->assertSame('123 Main St', $this->client->getCompanyAddress());
    }

    public function testSetCompanyAddressNullable(): void
    {
        $this->client->setCompanyAddress('Some address');
        $this->client->setCompanyAddress(null);
        $this->assertNull($this->client->getCompanyAddress());
    }

    public function testGetSetCompanyCountry(): void
    {
        $result = $this->client->setCompanyCountry('USA');
        $this->assertSame($this->client, $result);
        $this->assertSame('USA', $this->client->getCompanyCountry());
    }

    public function testGetSetCompanyZipCode(): void
    {
        $result = $this->client->setCompanyZipCode('12345');
        $this->assertSame($this->client, $result);
        $this->assertSame('12345', $this->client->getCompanyZipCode());
    }

    public function testSetCompanyZipCodeNullable(): void
    {
        $this->client->setCompanyZipCode(null);
        $this->assertNull($this->client->getCompanyZipCode());
    }

    public function testGetSetCompanyEmail(): void
    {
        $result = $this->client->setCompanyEmail('info@acme.com');
        $this->assertSame($this->client, $result);
        $this->assertSame('info@acme.com', $this->client->getCompanyEmail());
    }

    public function testGetSetCompanyPhoneNumber(): void
    {
        $result = $this->client->setCompanyPhoneNumber('+1234567890');
        $this->assertSame($this->client, $result);
        $this->assertSame('+1234567890', $this->client->getCompanyPhoneNumber());
    }

    public function testGetSetIsActive(): void
    {
        $result = $this->client->setIsActive(true);
        $this->assertSame($this->client, $result);
        $this->assertTrue($this->client->isIsActive());
    }

    public function testIsActiveAlias(): void
    {
        $this->client->setActive(true);
        $this->assertTrue($this->client->isActive());
    }

    public function testSetActiveNullable(): void
    {
        $this->client->setIsActive(null);
        $this->assertNull($this->client->isIsActive());
    }

    public function testGetSetCompanyIdentification(): void
    {
        $result = $this->client->setCompanyIdentification('B10583565');
        $this->assertSame($this->client, $result);
        $this->assertSame('B10583565', $this->client->getCompanyIdentification());
    }

    public function testGetSetCompanyIdentificationType(): void
    {
        $result = $this->client->setCompanyIdentificationType('CIF');
        $this->assertSame($this->client, $result);
        $this->assertSame('CIF', $this->client->getCompanyIdentificationType());
    }

    public function testGetSetCurrency(): void
    {
        $result = $this->client->setCurrency('USD');
        $this->assertSame($this->client, $result);
        $this->assertSame('USD', $this->client->getCurrency());
    }

    public function testSetCurrencyNullable(): void
    {
        $this->client->setCurrency(null);
        $this->assertNull($this->client->getCurrency());
    }

    public function testGetSetDiscountOfClient(): void
    {
        $result = $this->client->setDiscountOfClient(10.5);
        $this->assertSame($this->client, $result);
        $this->assertSame(10.5, $this->client->getDiscountOfClient());
    }

    public function testGetSetContractWith(): void
    {
        $result = $this->client->setContractWith('PartnerCorp');
        $this->assertSame($this->client, $result);
        $this->assertSame('PartnerCorp', $this->client->getContractWith());
    }

    public function testSetContractWithNullable(): void
    {
        $this->client->setContractWith(null);
        $this->assertNull($this->client->getContractWith());
    }

    public function testGetSetMinBalance(): void
    {
        $result = $this->client->setMinBalance(100.0);
        $this->assertSame($this->client, $result);
        $this->assertSame(100.0, $this->client->getMinBalance());
    }

    public function testGetSetCriticalBalance(): void
    {
        $result = $this->client->setCriticalBalance(50.0);
        $this->assertSame($this->client, $result);
        $this->assertSame(50.0, $this->client->getCriticalBalance());
    }

    public function testGetSetAlert(): void
    {
        $result = $this->client->setAlert(true);
        $this->assertSame($this->client, $result);
        $this->assertTrue($this->client->isAlert());
    }

    public function testGetAccountsReturnsCollection(): void
    {
        $this->assertInstanceOf(Collection::class, $this->client->getAccounts());
    }

    public function testAddAccount(): void
    {
        $account = $this->createMock(Account::class);
        $account->expects($this->once())
            ->method('setClient')
            ->with($this->client);

        $result = $this->client->addAccount($account);
        $this->assertSame($this->client, $result);
        $this->assertCount(1, $this->client->getAccounts());
        $this->assertTrue($this->client->getAccounts()->contains($account));
    }

    public function testAddAccountDoesNotDuplicate(): void
    {
        $account = $this->createMock(Account::class);
        $account->expects($this->once())
            ->method('setClient');

        $this->client->addAccount($account);
        $this->client->addAccount($account);
        $this->assertCount(1, $this->client->getAccounts());
    }

    public function testRemoveAccount(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('setClient');
        $account->method('getClient')->willReturn($this->client);

        $this->client->addAccount($account);
        $result = $this->client->removeAccount($account);
        $this->assertSame($this->client, $result);
        $this->assertCount(0, $this->client->getAccounts());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->client->setCreatedAt($date);
        $this->assertSame($this->client, $result);
        $this->assertSame($date, $this->client->getCreatedAt());
    }

    public function testGetSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->client->setUpdatedAt($date);
        $this->assertSame($this->client, $result);
        $this->assertSame($date, $this->client->getUpdatedAt());
    }

    public function testSetCreatedLifecycleCallback(): void
    {
        $this->client->setCreated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->client->getCreatedAt());
    }

    public function testSetUpdatedLifecycleCallback(): void
    {
        $this->client->setUpdated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->client->getUpdatedAt());
    }

    public function testGetSetIsActiveAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $result = $this->client->setIsActiveAt($date);
        $this->assertSame($this->client, $result);
        $this->assertSame($date, $this->client->getIsActiveAt());
    }

    public function testGetSetRemoveAt(): void
    {
        $date = new \DateTimeImmutable('2024-12-31');
        $result = $this->client->setRemoveAt($date);
        $this->assertSame($this->client, $result);
        $this->assertSame($date, $this->client->getRemoveAt());
    }
}
