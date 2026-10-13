<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Service\CommonService;
use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Reglas de capas de ADR-001 para el código nuevo (src/Market y src/Delivery).
 *
 * Se ejecutan dentro de PHPStan (extensión phpat): una violación hace fallar
 * `vendor/bin/phpstan analyse`. No afectan al código existente.
 */
final class MarketLayersTest
{
    private const DOMAIN = '/^App\\\\(Market|Delivery)\\\\[^\\\\]+\\\\Domain(\\\\|$)/';
    private const APPLICATION = '/^App\\\\(Market|Delivery)\\\\[^\\\\]+\\\\Application(\\\\|$)/';
    private const NEW_CODE = '/^App\\\\(Market|Delivery)(\\\\|$)/';

    #[TestRule]
    public function domainDoesNotDependOnFrameworkOrPersistence(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::DOMAIN, true))
            ->shouldNot()->dependOn()
            ->classes(
                Selector::inNamespace('Symfony'),
                Selector::inNamespace('Doctrine'),
                Selector::inNamespace('App\Service'),
            )
            // Los atributos de mapeo son metadatos inertes: PHP los ignora si Doctrine no está cargado.
            ->excluding(Selector::inNamespace('Doctrine\ORM\Mapping'))
            ->because('ADR-001: Domain no importa Symfony, Doctrine (salvo los atributos de Doctrine\ORM\Mapping) ni App\Service.');
    }

    #[TestRule]
    public function applicationDoesNotDependOnInfrastructure(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::APPLICATION, true))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace('/^App\\\\(Market|Delivery)\\\\[^\\\\]+\\\\Infrastructure(\\\\|$)/', true))
            ->because('ADR-001: Application no importa Infrastructure.');
    }

    /**
     * Una regla por contexto: nadie fuera de él puede depender de su Infrastructure.
     * Los contextos se descubren en disco (src y fixtures), así que uno nuevo queda cubierto sin tocar este archivo.
     *
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function contextsDoNotImportInfrastructureOfOtherContexts(): iterable
    {
        foreach ($this->contexts() as $context) {
            yield $context => PHPat::rule()
                ->classes(Selector::inNamespace(self::NEW_CODE, true))
                ->excluding(Selector::inNamespace('App\\' . $context))
                ->shouldNot()->dependOn()
                ->classes(Selector::inNamespace('App\\' . $context . '\\Infrastructure'))
                ->because('ADR-001: un contexto solo habla con otro por eventos o puertos, nunca por su Infrastructure.');
        }
    }

    #[TestRule]
    public function newCodeDoesNotExtendCommonService(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NEW_CODE, true))
            ->shouldNot()->extend()
            ->classes(Selector::classname(CommonService::class))
            ->because('ADR-001: CommonService está prohibido en src/Market y src/Delivery.');
    }

    /**
     * @return list<string> p. ej. "Market\\Skeleton", "Delivery\\Geo"
     */
    private function contexts(): array
    {
        $contexts = [];
        foreach ([dirname(__DIR__, 2) . '/src', __DIR__ . '/Fixtures'] as $root) {
            foreach (['Market', 'Delivery'] as $area) {
                foreach (glob($root . '/' . $area . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                    $contexts[$area . '\\' . basename($dir)] = true;
                }
            }
        }

        return array_keys($contexts);
    }
}
