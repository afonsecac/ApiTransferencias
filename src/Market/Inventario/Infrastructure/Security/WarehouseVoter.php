<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Security;

use App\Entity\User;
use App\Market\Inventario\Application\WarehouseAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Aislamiento entre almacenes (COMR-288). El sujeto es el id del almacén y el atributo, lo que se quiere hacer en él
 * (`WAREHOUSE_VIEW`, `WAREHOUSE_STOCK`, `WAREHOUSE_ASSEMBLY`, `WAREHOUSE_TRANSFERS`, `WAREHOUSE_DELIVERIES`). Se concede solo con un
 * vínculo activo del usuario con ESE almacén y el permiso correspondiente; todo lo demás se deniega. Los controladores del socio y
 * los casos de uso que reciben un id de almacén lo consultan antes de leer o escribir nada.
 *
 * @extends Voter<string, int>
 */
final class WarehouseVoter extends Voter
{
    public const VIEW = 'WAREHOUSE_VIEW';
    public const STOCK = 'WAREHOUSE_STOCK';
    public const ASSEMBLY = 'WAREHOUSE_ASSEMBLY';
    public const TRANSFERS = 'WAREHOUSE_TRANSFERS';
    public const DELIVERIES = 'WAREHOUSE_DELIVERIES';

    private const PERMISSION_OF = [
        self::STOCK => 'stock',
        self::ASSEMBLY => 'assembly',
        self::TRANSFERS => 'transfers',
        self::DELIVERIES => 'deliveries',
    ];

    public function __construct(private readonly WarehouseAccess $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return ($attribute === self::VIEW || isset(self::PERMISSION_OF[$attribute])) && is_int($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return false;
        }

        if ($attribute === self::VIEW) {
            return $this->access->canSee((int) $user->getId(), $subject);
        }

        return $this->access->allows((int) $user->getId(), $subject, self::PERMISSION_OF[$attribute]);
    }
}
