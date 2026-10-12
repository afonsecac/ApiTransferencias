<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// ADR-001: los controladores de los contextos nuevos (src/Market/<Contexto>/Infrastructure/Http y
// src/Delivery/<Contexto>/Infrastructure/Http) se importan solos, bajo el mismo prefijo y el mismo
// firewall que el resto del dashboard. Se importan solo los directorios que existen: mientras no haya
// ningún contexto no hay nada que cargar.
return static function (RoutingConfigurator $routes): void {
    foreach (['Market', 'Delivery'] as $area) {
        $directories = glob(dirname(__DIR__, 2) . '/src/' . $area . '/*/Infrastructure/Http', GLOB_ONLYDIR) ?: [];
        sort($directories);

        foreach ($directories as $directory) {
            $routes->import($directory . '/', 'attribute')->prefix('/dashboard/api');
        }
    }
};
