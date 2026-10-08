<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta autenticada sin el rol que exige el recurso recibe 403 con el
 * contrato de error de la API, no un 500 por un estado HTTP inválido.
 *
 * @coversNothing
 */
class ClientApiAccessDeniedTest extends ProviderFunctionalTestCase
{
    public function testAuthenticatedAccountWithoutTheRequiredRoleGets403(): void
    {
        $account = $this->createAccount($this->createClient(), $this->createEnvironment());
        // /api/bankCards exige ROLE_REM_API_USER; la cuenta solo tiene el de comunicaciones.
        $account->setRoles(['ROLE_COM_API_USER']);
        $this->em->flush();

        $request = Request::create('/api/bankCards', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTH_TOKEN' => base64_encode((string) $account->getAccessToken()),
        ]);
        $this->em->clear();

        $response = self::$kernel->handle($request);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertArrayHasKey('message', $body['error']);
    }

    public function testRequestWithoutCredentialsGets401(): void
    {
        $request = Request::create('/api/bankCards', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response = self::$kernel->handle($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
