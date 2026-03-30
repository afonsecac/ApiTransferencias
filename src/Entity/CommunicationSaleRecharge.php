<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleRechargeRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationSaleRechargeRepository::class)]
#[ApiResource(
    operations: [],
    security: "is_granted('ROLE_COM_API_USER')",
)]
class CommunicationSaleRecharge extends CommunicationSaleInfo
{
    #[ORM\Column(length: 15, nullable: true)]
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
    #[Groups(['comSales:read', 'comSales:create', 'sale:list', 'sale:detail'])]
    private string $phoneNumber;


    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function addId(int $id): void
    {
        $this->id = $id;
    }
}
