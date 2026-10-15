<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Infrastructure\Http\AiStatusController;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-277: el interruptor «IA habilitada» del catálogo. Hoy solo se lee: la creación de productos con IA va aparte.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Http\AiStatusController
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\SysConfigAiSettings
 */
final class AiStatusFunctionalTest extends ProviderFunctionalTestCase
{
    private function status(): Response
    {
        return self::getContainer()->get(AiStatusController::class)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testItIsDisabledByDefault(): void
    {
        $this->setSysConfig('market.ai.enabled', '0');

        $response = $this->status();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['enabled' => false], $this->body($response));
    }

    public function testItIsEnabledOnlyWithTheValueOne(): void
    {
        $this->setSysConfig('market.ai.enabled', '1');
        $this->assertSame(['enabled' => true], $this->body($this->status()));

        $this->setSysConfig('market.ai.enabled', 'true');
        $this->assertSame(['enabled' => false], $this->body($this->status()), 'Solo «1» lo enciende, como el interruptor de mercado.');
    }
}
