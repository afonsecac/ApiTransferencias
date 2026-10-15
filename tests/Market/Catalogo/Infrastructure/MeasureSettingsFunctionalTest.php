<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Infrastructure\Http\ChangeMeasureSettingsDto;
use App\Market\Catalogo\Infrastructure\Http\MeasureSettingsController;
use App\Market\Catalogo\Infrastructure\Persistence\SysConfigMeasureSettings;
use App\Repository\SysConfigRepository;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-281 contra Postgres real: la pantalla de medidas lee y guarda las unidades base y el divisor en la configuración del
 * sistema, y el cambio de base se bloquea mientras haya productos con cantidades.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Http\MeasureSettingsController
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\SysConfigMeasureSettingsStore
 */
final class MeasureSettingsFunctionalTest extends ProviderFunctionalTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        self::getContainer()->get(SysConfigRepository::class)->invalidateCache();
    }

    private function controller(): MeasureSettingsController
    {
        return self::getContainer()->get(MeasureSettingsController::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function seedSettings(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $this->setSysConfig(SysConfigMeasureSettings::WEIGHT_BASE_KEY, 'kg');
        $this->setSysConfig(SysConfigMeasureSettings::VOLUME_BASE_KEY, 'l');
        $this->setSysConfig(SysConfigMeasureSettings::DIVISOR_KEY, '6000');
    }

    private function withoutProducts(): void
    {
        $this->em->getConnection()->executeStatement('UPDATE communication_package SET market_product_id = NULL WHERE market_product_id IS NOT NULL');
        $this->em->getConnection()->executeStatement('DELETE FROM market_product');
    }

    private function withOneProduct(): void
    {
        $this->withoutProducts();
        $sellerId = (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, created_at, updated_at)
             VALUES ('MED-1', ?, 'draft', 'Gift Cards', 'Retail', '{}', 1, 1, 'kg', 'weight', 'kg', NOW(), NOW())",
            [$sellerId],
        );
    }

    public function testItReadsTheSettings(): void
    {
        $this->seedSettings();
        $this->withoutProducts();

        $response = $this->controller()->get();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(['weightBase' => 'kg', 'volumeBase' => 'l', 'volumetricDivisor' => 6000, 'baseLocked' => false], $this->body($response));
    }

    public function testItSavesAndReadsBackTheNewSettingsWhenThereAreNoProducts(): void
    {
        $this->seedSettings();
        $this->withoutProducts();

        $response = $this->controller()->change(new ChangeMeasureSettingsDto('g', 'ml', 5000));
        $again = $this->body($this->controller()->get());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertEquals(['weightBase' => 'g', 'volumeBase' => 'ml', 'volumetricDivisor' => 5000, 'baseLocked' => false], $again);
    }

    public function testWithProductsTheBaseIsLockedAndTheChangeAnswers409(): void
    {
        $this->seedSettings();
        $this->withOneProduct();

        $locked = $this->body($this->controller()->get());
        $response = $this->controller()->change(new ChangeMeasureSettingsDto('g', 'l', 6000));

        $this->assertTrue($locked['baseLocked']);
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('MEASURE_SETTINGS_LOCKED', $this->body($response)['error']['code']);
        $this->assertSame('kg', $this->body($this->controller()->get())['weightBase'], 'No se guardó nada.');
    }

    public function testWithProductsTheDivisorStillChanges(): void
    {
        $this->seedSettings();
        $this->withOneProduct();

        $response = $this->controller()->change(new ChangeMeasureSettingsDto('kg', 'l', 5000));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(5000, $this->body($response)['volumetricDivisor']);
    }

    public function testAnInvalidSettingAnswers422(): void
    {
        $this->seedSettings();
        $this->withoutProducts();

        $response = $this->controller()->change(new ChangeMeasureSettingsDto('l', 'l', 6000));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('INVALID_MEASURE_SETTINGS', $this->body($response)['error']['code']);
    }
}
