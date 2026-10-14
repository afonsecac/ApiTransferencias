<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Los centros de costo de UNA unidad y las reglas entre ellos: al menos uno, exactamente un principal y
 * activo, códigos únicos, y un centro se desactiva, nunca se borra (el principal ni siquiera se desactiva:
 * antes hay que elegir otro). Cambia las entidades en sitio; guardarlas es cosa del repositorio.
 */
final class CostCenterSet
{
    /**
     * @param list<CostCenter> $centers
     */
    public function __construct(private readonly OrgUnit $unit, private array $centers)
    {
    }

    /**
     * Los centros iniciales de una unidad recién creada: al menos uno y exactamente un principal.
     *
     * @param list<CostCenterDraft> $drafts
     * @throws InvalidOrgUnit
     */
    public static function start(OrgUnit $unit, array $drafts): self
    {
        if ($drafts === []) {
            throw new InvalidOrgUnit('La unidad necesita al menos un centro de costo.');
        }

        $codes = [];
        $principals = 0;
        foreach ($drafts as $draft) {
            if (isset($codes[$draft->code])) {
                throw new InvalidOrgUnit(sprintf('El código de centro de costo "%s" está repetido.', $draft->code));
            }
            $codes[$draft->code] = true;
            $principals += $draft->principal ? 1 : 0;
        }
        if ($principals !== 1) {
            throw new InvalidOrgUnit('La unidad necesita exactamente un centro de costo principal.');
        }

        return new self($unit, array_map(static fn (CostCenterDraft $d) => new CostCenter($unit, $d, $d->principal), $drafts));
    }

    /**
     * @return list<CostCenter>
     */
    public function all(): array
    {
        return $this->centers;
    }

    public function get(string $code): CostCenter
    {
        $code = strtoupper(trim($code));
        foreach ($this->centers as $center) {
            if ($center->code() === $code) {
                return $center;
            }
        }

        throw new InvalidOrgUnit(sprintf('La unidad no tiene el centro de costo "%s".', $code));
    }

    public function principal(): CostCenter
    {
        foreach ($this->centers as $center) {
            if ($center->isPrincipal()) {
                return $center;
            }
        }

        throw new InvalidOrgUnit('La unidad no tiene centro de costo principal.');
    }

    /**
     * @return CostCenter el centro nuevo, que nunca es el principal; hay que guardarlo
     */
    public function add(string $code, string $name): CostCenter
    {
        $draft = new CostCenterDraft($code, $name);
        foreach ($this->centers as $center) {
            if ($center->code() === $draft->code) {
                throw new InvalidOrgUnit(sprintf('El centro de costo "%s" ya existe en la unidad.', $draft->code));
            }
        }

        $new = new CostCenter($this->unit, $draft, false);
        $this->centers[] = $new;

        return $new;
    }

    public function rename(string $code, string $name): CostCenter
    {
        $center = $this->get($code);
        $center->rename($name);

        return $center;
    }

    /**
     * @return list<CostCenter> los dos centros que cambian (el principal anterior y el nuevo)
     */
    public function makePrincipal(string $code): array
    {
        $target = $this->get($code);
        if (!$target->isActive()) {
            throw new InvalidOrgUnit(sprintf('El centro de costo "%s" está desactivado: no puede ser el principal.', $target->code()));
        }
        if ($target->isPrincipal()) {
            return [];
        }

        $previous = $this->principal();
        $previous->setPrincipal(false);
        $target->setPrincipal(true);

        return [$previous, $target];
    }

    public function deactivate(string $code): CostCenter
    {
        $center = $this->get($code);
        if ($center->isPrincipal()) {
            throw new InvalidOrgUnit('El centro de costo principal no se puede desactivar: antes hay que elegir otro principal.');
        }
        $center->setActive(false);

        return $center;
    }

    public function activate(string $code): CostCenter
    {
        $center = $this->get($code);
        $center->setActive(true);

        return $center;
    }
}
