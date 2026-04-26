<?php

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\CommunicationSaleInfo;
use App\Entity\Transfer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\BalanceOperation
 */
class BalanceOperationTest extends TestCase
{
    private BalanceOperation $operation;

    protected function setUp(): void
    {
        $this->operation = new BalanceOperation();
    }

    public function testConstructorDefaults(): void
    {
        $op = new BalanceOperation();
        $this->assertSame(0.0, $op->getAmountTax());
        $this->assertSame(0.0, $op->getDiscount());
        $this->assertSame('USD', $op->getCurrencyTax());
        $this->assertSame('USD', $op->getCurrencyDiscount());
        $this->assertSame(0.0, $op->getTotalAmount());
        $this->assertSame('USD', $op->getTotalCurrency());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->operation->getId());
    }

    public function testGetSetAmount(): void
    {
        $result = $this->operation->setAmount(150.75);
        $this->assertSame($this->operation, $result);
        $this->assertSame(150.75, $this->operation->getAmount());
    }

    public function testGetSetCurrency(): void
    {
        $result = $this->operation->setCurrency('EUR');
        $this->assertSame($this->operation, $result);
        $this->assertSame('EUR', $this->operation->getCurrency());
    }

    public function testGetSetAmountTax(): void
    {
        $result = $this->operation->setAmountTax(12.5);
        $this->assertSame($this->operation, $result);
        $this->assertSame(12.5, $this->operation->getAmountTax());
    }

    public function testGetSetCurrencyTax(): void
    {
        $result = $this->operation->setCurrencyTax('EUR');
        $this->assertSame($this->operation, $result);
        $this->assertSame('EUR', $this->operation->getCurrencyTax());
    }

    public function testGetSetDiscount(): void
    {
        $result = $this->operation->setDiscount(5.0);
        $this->assertSame($this->operation, $result);
        $this->assertSame(5.0, $this->operation->getDiscount());
    }

    public function testGetSetCurrencyDiscount(): void
    {
        $result = $this->operation->setCurrencyDiscount('EUR');
        $this->assertSame($this->operation, $result);
        $this->assertSame('EUR', $this->operation->getCurrencyDiscount());
    }

    public function testGetSetTotalAmount(): void
    {
        $result = $this->operation->setTotalAmount(200.0);
        $this->assertSame($this->operation, $result);
        $this->assertSame(200.0, $this->operation->getTotalAmount());
    }

    public function testGetSetTotalCurrency(): void
    {
        $result = $this->operation->setTotalCurrency('EUR');
        $this->assertSame($this->operation, $result);
        $this->assertSame('EUR', $this->operation->getTotalCurrency());
    }

    public function testGetSetTenant(): void
    {
        $account = $this->createMock(Account::class);
        $result = $this->operation->setTenant($account);
        $this->assertSame($this->operation, $result);
        $this->assertSame($account, $this->operation->getTenant());
    }

    public function testGetSetTransfer(): void
    {
        $transfer = $this->createMock(Transfer::class);
        $result = $this->operation->setTransfer($transfer);
        $this->assertSame($this->operation, $result);
        $this->assertSame($transfer, $this->operation->getTransfer());
    }

    public function testGetSetTransferId(): void
    {
        $result = $this->operation->setTransferId(42);
        $this->assertSame($this->operation, $result);
        $this->assertSame(42, $this->operation->getTransferId());
    }

    public function testSetTransferIdNullable(): void
    {
        $this->operation->setTransferId(null);
        $this->assertNull($this->operation->getTransferId());
    }

    public function testGetSetState(): void
    {
        $result = $this->operation->setState('COMPLETED');
        $this->assertSame($this->operation, $result);
        $this->assertSame('COMPLETED', $this->operation->getState());
    }

    public function testGetSetOperationType(): void
    {
        $result = $this->operation->setOperationType('DEBIT');
        $this->assertSame($this->operation, $result);
        $this->assertSame('DEBIT', $this->operation->getOperationType());
    }

    public function testSetOperationTypeNullable(): void
    {
        $this->operation->setOperationType(null);
        $this->assertNull($this->operation->getOperationType());
    }

    public function testGetSetCommunicationSale(): void
    {
        $sale = $this->createMock(CommunicationSaleInfo::class);
        $result = $this->operation->setCommunicationSale($sale);
        $this->assertSame($this->operation, $result);
        $this->assertSame($sale, $this->operation->getCommunicationSale());
    }

    public function testGetSetDisabledAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $result = $this->operation->setDisabledAt($date);
        $this->assertSame($this->operation, $result);
        $this->assertSame($date, $this->operation->getDisabledAt());
    }

    public function testGetSetPreviousAmount(): void
    {
        $result = $this->operation->setPreviousAmount(true);
        $this->assertSame($this->operation, $result);
        $this->assertTrue($this->operation->isPreviousAmount());
    }

    public function testGetSetUserInfo(): void
    {
        $info = ['name' => 'John', 'id' => 1];
        $result = $this->operation->setUserInfo($info);
        $this->assertSame($this->operation, $result);
        $this->assertSame($info, $this->operation->getUserInfo());
    }

    public function testGetSetMarkAsReported(): void
    {
        $result = $this->operation->setMarkAsReported(true);
        $this->assertSame($this->operation, $result);
        $this->assertTrue($this->operation->isMarkAsReported());
    }

    public function testGetSetReportedDateAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $result = $this->operation->setReportedDateAt($date);
        $this->assertSame($this->operation, $result);
        $this->assertSame($date, $this->operation->getReportedDateAt());
    }

    public function testGetSetComment(): void
    {
        $result = $this->operation->setComment('Test comment');
        $this->assertSame($this->operation, $result);
        $this->assertSame('Test comment', $this->operation->getComment());
    }

    public function testGetSetCommentToImpugned(): void
    {
        $result = $this->operation->setCommentToImpugned('Impugned reason');
        $this->assertSame($this->operation, $result);
        $this->assertSame('Impugned reason', $this->operation->getCommentToImpugned());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->operation->setCreatedAt($date);
        $this->assertSame($this->operation, $result);
        $this->assertSame($date, $this->operation->getCreatedAt());
    }

    public function testGetSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $result = $this->operation->setUpdatedAt($date);
        $this->assertSame($this->operation, $result);
        $this->assertSame($date, $this->operation->getUpdatedAt());
    }

    public function testSetCreatedLifecycleCallback(): void
    {
        $this->operation->setCreated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->operation->getCreatedAt());
    }

    public function testSetUpdatedLifecycleCallback(): void
    {
        $this->operation->setUpdated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->operation->getUpdatedAt());
    }

    public function testGetCalculateTotal(): void
    {
        $this->operation->setAmount(100.0);
        $this->operation->setAmountTax(10.0);
        $this->operation->setDiscount(5.0);

        $this->operation->getCalculateTotal();

        $this->assertSame(105.0, $this->operation->getTotalAmount());
    }

    public function testGetCalculateTotalWithZeroValues(): void
    {
        $this->operation->setAmount(50.0);
        // amountTax and discount default to 0 from constructor

        $this->operation->getCalculateTotal();

        $this->assertSame(50.0, $this->operation->getTotalAmount());
    }
}
