<?php

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\BankCard;
use App\Entity\Sender;
use App\Entity\Transfer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Transfer
 */
class TransferTest extends TestCase
{
    private Transfer $transfer;

    protected function setUp(): void
    {
        $this->transfer = new Transfer();
    }

    public function testConstructorDefaults(): void
    {
        $transfer = new Transfer();
        $this->assertSame('USD', $transfer->getCurrency());
        $this->assertSame('USD', $transfer->getCurrencyTotal());
        $this->assertSame('USD', $transfer->getCurrencyCommission());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->transfer->getId());
    }

    public function testGetSetAmountDeposit(): void
    {
        $result = $this->transfer->setAmountDeposit(500.0);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(500.0, $this->transfer->getAmountDeposit());
    }

    public function testGetSetCurrency(): void
    {
        $result = $this->transfer->setCurrency('EUR');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('EUR', $this->transfer->getCurrency());
    }

    public function testGetSetAmountCommission(): void
    {
        $result = $this->transfer->setAmountCommission(25.0);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(25.0, $this->transfer->getAmountCommission());
    }

    public function testGetSetCurrencyCommission(): void
    {
        $result = $this->transfer->setCurrencyCommission('EUR');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('EUR', $this->transfer->getCurrencyCommission());
    }

    public function testGetSetTotalAmount(): void
    {
        $result = $this->transfer->setTotalAmount(525.0);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(525.0, $this->transfer->getTotalAmount());
    }

    public function testGetSetCurrencyTotal(): void
    {
        $result = $this->transfer->setCurrencyTotal('EUR');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('EUR', $this->transfer->getCurrencyTotal());
    }

    public function testGetSetRateToChange(): void
    {
        $result = $this->transfer->setRateToChange(1.15);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(1.15, $this->transfer->getRateToChange());
    }

    public function testGetSetTransactionType(): void
    {
        $result = $this->transfer->setTransactionType('3');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('3', $this->transfer->getTransactionType());
    }

    public function testGetSetTenant(): void
    {
        $account = $this->createMock(Account::class);
        $result = $this->transfer->setTenant($account);
        $this->assertSame($this->transfer, $result);
        $this->assertSame($account, $this->transfer->getTenant());
    }

    public function testGetSetRebusPayId(): void
    {
        $result = $this->transfer->setRebusPayId(12345);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(12345, $this->transfer->getRebusPayId());
    }

    public function testGetSetStatusId(): void
    {
        $result = $this->transfer->setStatusId(1);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(1, $this->transfer->getStatusId());
    }

    public function testGetSetStatusName(): void
    {
        $result = $this->transfer->setStatusName('Completed');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('Completed', $this->transfer->getStatusName());
    }

    public function testGetSetReasonNote(): void
    {
        $result = $this->transfer->setReasonNote('Test note');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('Test note', $this->transfer->getReasonNote());
    }

    public function testSetReasonNoteNullable(): void
    {
        $this->transfer->setReasonNote(null);
        $this->assertNull($this->transfer->getReasonNote());
    }

    public function testGetSetSenderId(): void
    {
        $result = $this->transfer->setSenderId(10);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(10, $this->transfer->getSenderId());
    }

    public function testSetSenderIdNullable(): void
    {
        $this->transfer->setSenderId(null);
        $this->assertNull($this->transfer->getSenderId());
    }

    public function testGetSetSender(): void
    {
        $sender = $this->createMock(Sender::class);
        $result = $this->transfer->setSender($sender);
        $this->assertSame($this->transfer, $result);
        $this->assertSame($sender, $this->transfer->getSender());
    }

    public function testGetSetBeneficiaryId(): void
    {
        $result = $this->transfer->setBeneficiaryId(20);
        $this->assertSame($this->transfer, $result);
        $this->assertSame(20, $this->transfer->getBeneficiaryId());
    }

    public function testSetBeneficiaryIdNullable(): void
    {
        $this->transfer->setBeneficiaryId(null);
        $this->assertNull($this->transfer->getBeneficiaryId());
    }

    public function testGetSetBeneficiary(): void
    {
        $beneficiary = $this->createMock(BankCard::class);
        $result = $this->transfer->setBeneficiary($beneficiary);
        $this->assertSame($this->transfer, $result);
        $this->assertSame($beneficiary, $this->transfer->getBeneficiary());
    }

    public function testGetSetSenderName(): void
    {
        $result = $this->transfer->setSenderName('John Doe');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('John Doe', $this->transfer->getSenderName());
    }

    public function testGetSetBeneficiaryName(): void
    {
        $result = $this->transfer->setBeneficiaryName('Jane Smith');
        $this->assertSame($this->transfer, $result);
        $this->assertSame('Jane Smith', $this->transfer->getBeneficiaryName());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->transfer->setCreatedAt($date);
        $this->assertSame($this->transfer, $result);
        $this->assertSame($date, $this->transfer->getCreatedAt());
    }

    public function testGetSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->transfer->setUpdatedAt($date);
        $this->assertSame($this->transfer, $result);
        $this->assertSame($date, $this->transfer->getUpdatedAt());
    }

    public function testSetCreatedLifecycleCallback(): void
    {
        $this->transfer->setCreated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->transfer->getCreatedAt());
    }

    public function testSetUpdatedLifecycleCallback(): void
    {
        $this->transfer->setUpdated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->transfer->getUpdatedAt());
    }

    public function testValidationsTransactionTypeDebit(): void
    {
        $this->transfer->setTransactionType('3');
        $result = Transfer::validationsTransactionType($this->transfer);
        $this->assertSame(['debitTx'], $result);
    }

    public function testValidationsTransactionTypeCredit(): void
    {
        $this->transfer->setTransactionType('5');
        $result = Transfer::validationsTransactionType($this->transfer);
        $this->assertSame(['creditTx'], $result);
    }
}
