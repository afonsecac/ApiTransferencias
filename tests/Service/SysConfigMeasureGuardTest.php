<?php

namespace App\Tests\Service;

use App\Controller\DashboardSysConfigController;
use App\DTO\UpdateSysConfigDto;
use App\Entity\SysConfig;
use App\Exception\MyCurrentException;
use App\Repository\SysConfigRepository;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-281: la pantalla genérica de configuración no puede cambiar, desactivar ni borrar las unidades base de las medidas
 * mientras haya productos con cantidades: reinterpretaría todos sus datos. El resto de claves de medidas (el divisor) sí.
 *
 * @covers \App\Service\SysConfigAdminService
 */
class SysConfigMeasureGuardTest extends ProviderFunctionalTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        self::getContainer()->get(SysConfigRepository::class)->invalidateCache();
    }

    private function controller(): DashboardSysConfigController
    {
        return self::getContainer()->get(DashboardSysConfigController::class);
    }

    private function config(string $name, string $value): SysConfig
    {
        $this->setSysConfig($name, $value);

        return $this->em->getRepository(SysConfig::class)->findOneBy(['propertyName' => $name]);
    }

    private function withOneProduct(): void
    {
        $this->em->getConnection()->executeStatement('UPDATE communication_package SET market_product_id = NULL WHERE market_product_id IS NOT NULL');
        $this->em->getConnection()->executeStatement('DELETE FROM market_product');
        $sellerId = (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, created_at, updated_at)
             VALUES ('GUARD-1', ?, 'draft', 'Gift Cards', 'Retail', '{}', 1, 1, 'kg', 'weight', 'kg', NOW(), NOW())",
            [$sellerId],
        );
    }

    public function testTheBaseUnitCannotBeEditedWhileThereAreProducts(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $weight = $this->config('market.measure.weight_base', 'kg');
        $this->withOneProduct();

        $response = $this->controller()->update((int) $weight->getId(), new UpdateSysConfigDto(propertyValue: 'g'));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), (string) $response->getContent());
        $this->em->refresh($weight);
        $this->assertSame('kg', $weight->getPropertyValue());
    }

    public function testTheBaseUnitCannotBeToggledOrDeletedWhileThereAreProducts(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $volume = $this->config('market.measure.volume_base', 'l');
        $this->withOneProduct();

        foreach (['toggle', 'delete'] as $action) {
            try {
                $this->controller()->$action((int) $volume->getId());
                $this->fail("«$action» debía rechazarse.");
            } catch (MyCurrentException $e) {
                $this->assertSame(Response::HTTP_CONFLICT, $e->getCode());
            }
        }
    }

    public function testTheDivisorAndTheOtherKeysStayEditable(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $divisor = $this->config('market.measure.volumetric_divisor', '6000');
        $this->withOneProduct();

        $response = $this->controller()->update((int) $divisor->getId(), new UpdateSysConfigDto(propertyValue: '5000'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheBaseUnitIsEditableWhenThereAreNoProducts(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $weight = $this->config('market.measure.weight_base', 'kg');
        $this->em->getConnection()->executeStatement('UPDATE communication_package SET market_product_id = NULL WHERE market_product_id IS NOT NULL');
        $this->em->getConnection()->executeStatement('DELETE FROM market_product');

        $response = $this->controller()->update((int) $weight->getId(), new UpdateSysConfigDto(propertyValue: 'g'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }
}
