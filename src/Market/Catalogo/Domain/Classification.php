<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La clasificación de un producto: servicio, subservicio y, si aplica, área (T1, T4). Es lo que dice de qué tipo es, igual
 * que en `ServiceCategoryKey` (`service.name`, `service.subservice.name` y `area`); no hay un campo `kind` aparte.
 *
 * Vocabulario controlado: todo el mercado vive bajo el servicio «Gift Cards»; los subservicios son las categorías de DTOne;
 * y el área es una subdivisión de un subservicio («Supermarket» cuelga de «Retail», que es un subservicio, no un área). El
 * negocio aporta más servicios, subservicios y áreas cuando los tenga.
 */
final readonly class Classification
{
    /** @var list<string> */
    public const SERVICES = ['Gift Cards'];

    /** @var list<string> */
    public const SUBSERVICES = ['Retail', 'Gaming', 'Cash Cards', 'Food', 'Entertainment', 'Travel & Transport'];

    /** @var array<string, string> área => subservicio del que cuelga */
    public const AREAS = ['Supermarket' => 'Retail'];

    private function __construct(
        private string $service,
        private string $subservice,
        private ?string $area,
    ) {
    }

    /**
     * @throws InvalidProduct si algo no está en el vocabulario o el área no corresponde a su subservicio
     */
    public static function of(string $service, string $subservice, ?string $area = null): self
    {
        $service = trim($service);
        $subservice = trim($subservice);
        $area = $area === null || trim($area) === '' ? null : trim($area);

        if (!in_array($service, self::SERVICES, true)) {
            throw new InvalidProduct(sprintf('El servicio "%s" no es válido (%s).', $service, implode(', ', self::SERVICES)));
        }
        if (!in_array($subservice, self::SUBSERVICES, true)) {
            throw new InvalidProduct(sprintf('El subservicio "%s" no es válido (%s).', $subservice, implode(', ', self::SUBSERVICES)));
        }
        if ($area !== null) {
            if (!isset(self::AREAS[$area])) {
                throw new InvalidProduct(sprintf('El área "%s" no es válida (%s).', $area, implode(', ', array_keys(self::AREAS))));
            }
            if (self::AREAS[$area] !== $subservice) {
                throw new InvalidProduct(sprintf('El área "%s" solo existe bajo el subservicio "%s".', $area, self::AREAS[$area]));
            }
        }

        return new self($service, $subservice, $area);
    }

    public function service(): string
    {
        return $this->service;
    }

    public function subservice(): string
    {
        return $this->subservice;
    }

    public function area(): ?string
    {
        return $this->area;
    }
}
