<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ReserveRecharge
{
    public function __construct(
        #[ApiProperty(
            description: '<p>All transactions in the sandbox environment are simulated: no real transaction takes place. To simulate different responses use the following numbers below:</p><table>
	<tbody>
		<tr>
			<td>Mobile Number</td>
			<td>Response Type</td>
		</tr>
		<tr>
			<td>xxxx xx60</td>
			<td>Completed</td>
		</tr>
		<tr>
			<td>xxxx xx65</td>
			<td>Rejected</td>
		</tr>
	</tbody>
</table><p>Any other number used that is not contemplated in this document will be shown as rejected.</p>',
            required: true,
            default: '5350499847'
        )]
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 10)]
        #[Groups(['comSales:create'])]
        readonly string $phoneNumber,
        #[ApiProperty(
            description: 'The promotion id in current system, take the information from /communication/promotions',
            required: true
        )]
        #[Assert\NotNull]
        #[Assert\Positive]
        #[Groups(['comSales:create'])]
        readonly int    $promotionId,
        #[ApiProperty(
            description: 'The package id in current system, take the information from /communication/packages',
            required: true
        )]
        #[Assert\Positive]
        #[Assert\NotNull]
        #[Groups(['comSales:create'])]
        readonly int    $packageId,
        #[ApiProperty(
            description: 'The transaction id on system of client, this info is unique',
            required: true
        )]
        #[Groups(['comSales:read', 'comSales:create'])]
        #[Assert\NotBlank]
        readonly string $clientTransactionId
    )
    {

    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getPromotionId(): int
    {
        return $this->promotionId;
    }

    public function getPackageId(): int
    {
        return $this->packageId;
    }

    public function getClientTransactionId(): string
    {
        return $this->clientTransactionId;
    }
}