<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Infrastructure\Http\DescribeProductImageDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\ProductImageController;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ReorderProductImagesDto;
use App\Repository\SysConfigRepository;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-274 contra Postgres real y GD: subida, galería ordenada con una referencia, tamaños generados, límites configurables
 * y contrato HTTP.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Http\ProductImageController
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\DoctrineProductImageRepository
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\SysConfigImageSettings
 */
final class ProductImageFunctionalTest extends ProviderFunctionalTestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();
        // La caché de sys_config no se deshace con la transacción del test: se parte siempre de la base real.
        self::getContainer()->get(SysConfigRepository::class)->invalidateCache();
    }

    protected function tearDown(): void
    {
        self::getContainer()->get(SysConfigRepository::class)->invalidateCache();
        foreach ($this->temporary as $path) {
            @unlink($path);
        }
        $dir = (string) self::getContainer()->getParameter('market.images.dir');
        foreach (glob($dir . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function controller(): ProductImageController
    {
        return self::getContainer()->get(ProductImageController::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    private function productId(string $sku = 'ARR-001'): int
    {
        $response = self::getContainer()->get(ProductController::class)->create(new CreateProductDto(
            sku: $sku,
            texts: ['es' => ['name' => 'Arroz']],
            service: 'Gift Cards',
            subservice: 'Retail',
            contentValue: 1000,
            contentUnit: 'g',
            deliveryKind: 'weight',
            deliveryUnit: 'kg',
        ));
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        return $this->body($response)['id'];
    }

    private function upload(int $productId, int $width = 800, int $height = 600, string $name = 'foto.jpg', string $format = 'jpeg'): Response
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 120, 200));
        $path = sys_get_temp_dir() . '/func-' . bin2hex(random_bytes(4)) . '.' . $format;
        $format === 'png' ? imagepng($image, $path) : imagejpeg($image, $path);
        imagedestroy($image);
        $this->temporary[] = $path;

        return $this->controller()->upload($productId, new Request(files: ['file' => new UploadedFile($path, $name, null, null, true)]));
    }

    public function testItUploadsTheFirstImageAsTheReferenceAndAnswersItsUrls(): void
    {
        $this->admin();
        $id = $this->productId();

        $response = $this->upload($id, 2000, 1000);
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertTrue($data['isReference']);
        $this->assertSame(1, $data['position']);
        $this->assertSame('foto.jpg', $data['originalName']);
        $this->assertSame([2000, 1000], [$data['width'], $data['height']]);
        $this->assertSame(['thumb', 'list', 'detail'], array_keys($data['urls']));
        foreach ($data['urls'] as $url) {
            $this->assertStringStartsWith('/market-images/', $url);
        }
    }

    public function testTheGeneratedSizesExistOnDisk(): void
    {
        $this->admin();
        $id = $this->productId();
        $data = $this->body($this->upload($id, 2000, 1000));
        $dir = (string) self::getContainer()->getParameter('market.images.dir');

        foreach ($data['urls'] as $url) {
            $this->assertFileExists($dir . '/' . substr($url, strlen('/market-images/')));
        }
    }

    public function testTheGalleryKeepsTheOrderAndOnlyOneReference(): void
    {
        $this->admin();
        $id = $this->productId();
        $this->upload($id);
        $this->upload($id);
        $this->upload($id);

        $data = $this->body($this->controller()->list($id));

        $this->assertSame([1, 2, 3], array_column($data['items'], 'position'));
        $this->assertSame([true, false, false], array_column($data['items'], 'isReference'));
        $this->assertSame(4_000_000, $data['maxBytes']);
        $this->assertSame(8, $data['maxPerProduct']);
    }

    public function testItReordersAndChangesTheReference(): void
    {
        $this->admin();
        $id = $this->productId();
        $ids = array_map(fn () => $this->body($this->upload($id))['id'], [1, 2, 3]);

        $reordered = $this->body($this->controller()->reorder($id, new ReorderProductImagesDto([$ids[2], $ids[0], $ids[1]])));
        $this->assertSame([$ids[2], $ids[0], $ids[1]], array_column($reordered['items'], 'id'));

        $changed = $this->body($this->controller()->setReference($id, $ids[2]));
        $this->assertSame([true, false, false], array_column($changed['items'], 'isReference'));
        $this->em->clear();
        $stored = $this->body($this->controller()->list($id));
        $this->assertSame([$ids[2], $ids[0], $ids[1]], array_column($stored['items'], 'id'));
        $this->assertSame([true, false, false], array_column($stored['items'], 'isReference'));
    }

    public function testAnOrderThatIsNotAPermutationAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $id = $this->productId();
        $first = $this->body($this->upload($id))['id'];
        $this->upload($id);

        $response = $this->controller()->reorder($id, new ReorderProductImagesDto([$first]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_GALLERY_ORDER', $this->body($response)['error']['code']);
    }

    public function testItDescribesAnImage(): void
    {
        $this->admin();
        $id = $this->productId();
        $image = $this->body($this->upload($id))['id'];

        $response = $this->controller()->describe($id, $image, new DescribeProductImageDto('frente', 'Arroz, frente', 'Rice, front'));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(['frente', 'Arroz, frente', 'Rice, front'], [$data['kind'], $data['altEs'], $data['altEn']]);
    }

    public function testItDeletesAnImageWithItsFilesAndPromotesTheReference(): void
    {
        $this->admin();
        $id = $this->productId();
        $first = $this->body($this->upload($id));
        $second = $this->body($this->upload($id));
        $dir = (string) self::getContainer()->getParameter('market.images.dir');
        $firstFile = $dir . '/' . substr($first['urls']['list'], strlen('/market-images/'));

        $response = $this->controller()->delete($id, $first['id']);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFileDoesNotExist($firstFile);
        $data = $this->body($this->controller()->list($id));
        $this->assertSame([$second['id']], array_column($data['items'], 'id'));
        $this->assertSame([true], array_column($data['items'], 'isReference'));
        $this->assertSame([1], array_column($data['items'], 'position'));
    }

    public function testDeletingAProductDeletesItsGalleryRows(): void
    {
        $this->admin();
        $id = $this->productId();
        $this->upload($id);
        $connection = $this->em->getConnection();

        $connection->executeStatement('DELETE FROM market_product WHERE id = ?', [$id]);

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_product_image WHERE product_id = ?', [$id]));
    }

    public function testTheLimitOfImagesPerProductIsConfigurableAndAnswersConflict(): void
    {
        $this->admin();
        $id = $this->productId();
        $this->setConfig('market.images.max_per_product', '2');
        $this->upload($id);
        $this->upload($id);

        $response = $this->upload($id);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('PRODUCT_GALLERY_FULL', $this->body($response)['error']['code']);
    }

    public function testTheSizeLimitIsConfigurable(): void
    {
        $this->admin();
        $id = $this->productId();
        $this->setConfig('market.images.max_bytes', '1000');

        $response = $this->upload($id, 800, 600);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('PRODUCT_IMAGE_INVALID', $this->body($response)['error']['code']);
    }

    public function testAFileThatIsNotAnImageIsRejected(): void
    {
        $this->admin();
        $id = $this->productId();
        $path = sys_get_temp_dir() . '/falso-' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($path, '<?php echo "no soy una imagen";');
        $this->temporary[] = $path;

        $response = $this->controller()->upload($id, new Request(files: ['file' => new UploadedFile($path, 'falso.jpg', null, null, true)]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame([], $this->body($this->controller()->list($id))['items']);
    }

    public function testUploadingWithoutAFileAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $id = $this->productId();

        $response = $this->controller()->upload($id, new Request());

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('PRODUCT_IMAGE_REQUIRED', $this->body($response)['error']['code']);
    }

    public function testAnUnknownProductOrImageAnswersNotFound(): void
    {
        $this->admin();
        $id = $this->productId();

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->upload(987654)->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->list(987654)->getStatusCode());
        $this->assertSame('PRODUCT_IMAGE_NOT_FOUND', $this->body($this->controller()->delete($id, 987654))['error']['code']);
    }

    private function setConfig(string $name, string $value): void
    {
        $this->em->getConnection()->executeStatement(
            "UPDATE sys_config SET property_value = ? WHERE property_name = ?",
            [$value, $name],
        );
        self::getContainer()->get(SysConfigRepository::class)->invalidateCache();
    }
}
