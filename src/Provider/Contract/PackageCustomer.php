<?php

namespace App\Provider\Contract;

/**
 * Datos del cliente/beneficiario para una venta de paquete. Reemplaza el
 * array 'client' que hoy arma CommunicationSaleService para EtecsaGatewayClient::sellPackage().
 */
final readonly class PackageCustomer
{
    public function __construct(
        public ?string $identificationNumber = null,
        public ?string $name = null,
        public ?int $identificationType = null,
        public ?\DateTimeImmutable $arrivalDate = null,
        public ?int $nationalityExternalId = null,
    ) {
    }
}
