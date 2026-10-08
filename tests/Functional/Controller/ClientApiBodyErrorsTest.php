<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Account;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato HTTP real de los POST de venta de /api: un cuerpo inválido es un
 * error del cliente (400 con un mensaje útil), nunca un 500. Pasa por el
 * kernel completo (firewall + API Platform + ExceptionListener).
 *
 * @coversNothing
 */
class ClientApiBodyErrorsTest extends ProviderFunctionalTestCase
{
    private const ENDPOINTS = [
        '/api/communication/sale/recharge',
        '/api/communication/sale/recharge/reserve',
        '/api/communication/sale/package',
    ];

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount($this->createClient(), $this->createEnvironment());
        $this->account->setRoles(['ROLE_COM_API_USER']);
        $this->em->flush();
    }

    /** @return iterable<string, array{string}> */
    public static function endpoints(): iterable
    {
        foreach (self::ENDPOINTS as $uri) {
            yield $uri => [$uri];
        }
    }

    /** @dataProvider endpoints */
    public function testMalformedJsonIsA400WithInvalidJsonBodyCode(string $uri): void
    {
        [$status, $body] = $this->post($uri, '{bad');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $status);
        $this->assertSame('INVALID_JSON_BODY', $body['error']['code']);
    }

    /** @dataProvider endpoints */
    public function testWrongTypeIsA400(string $uri): void
    {
        [$status, $body] = $this->post($uri, '{"packageId":"abc","phoneNumber":"53555555","clientTransactionId":"t1"}');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $status);
        $this->assertSame('Invalid request body', $body['error']['message']);
    }

    /** @dataProvider endpoints */
    public function testBodyThatIsNotAnObjectIsNotAServerError(string $uri): void
    {
        [$status] = $this->post($uri, '[1,2]');

        $this->assertLessThan(Response::HTTP_INTERNAL_SERVER_ERROR, $status);
    }

    public function testMissingRequiredFieldsOnReserveIsA400(): void
    {
        [$status, $body] = $this->post('/api/communication/sale/recharge/reserve', '{}');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $status);
        $this->assertNotEmpty($body['error']['details']);
    }

    /** @return array{int, array<string, mixed>} */
    private function post(string $uri, string $content): array
    {
        $request = Request::create($uri, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTH_TOKEN' => base64_encode((string) $this->account->getAccessToken()),
        ], $content);
        $this->em->clear();

        $response = self::$kernel->handle($request);

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    }
}
