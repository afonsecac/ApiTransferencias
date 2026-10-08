<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Account;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Respaldo en fichero de los POST de venta, de punta a punta: firewall →
 * listener → canal "client_request" → var/log/request.{env}.{fecha}.log.
 *
 * @coversNothing
 */
class ClientRequestLogFileTest extends ProviderFunctionalTestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount($this->createClient(), $this->createEnvironment());
        $this->account->setRoles(['ROLE_COM_API_USER']);
        $this->em->flush();
    }

    public function testAuthenticatedPostLeavesOneLineWithEndpointPayloadAndAccountId(): void
    {
        $marker = 'probe-' . bin2hex(random_bytes(6));
        $body = '{"packageId":999999999,"phoneNumber":"53555555","clientTransactionId":"' . $marker . '"}';

        $this->post('/api/communication/sale/recharge', $body);

        $lines = $this->linesContaining($marker);
        $this->assertCount(1, $lines);
        $context = $lines[0]['context'];
        $this->assertSame('Client request', $lines[0]['message']);
        $this->assertSame('POST', $context['method']);
        $this->assertSame('/api/communication/sale/recharge', $context['endpoint']);
        $this->assertSame($body, $context['payload']);
        $this->assertSame($this->account->getId(), $context['account']);
        $this->assertNotEmpty($lines[0]['datetime']);
    }

    public function testTheAccessTokenNeverReachesTheLogFile(): void
    {
        $marker = 'probe-' . bin2hex(random_bytes(6));
        $this->post('/api/communication/sale/package', '{"clientTransactionId":"' . $marker . '"}');

        $token = (string) $this->account->getAccessToken();
        $this->assertNotEmpty($this->linesContaining($marker));
        $this->assertStringNotContainsString($token, (string) file_get_contents($this->logFile()));
        $this->assertStringNotContainsString(base64_encode($token), (string) file_get_contents($this->logFile()));
    }

    public function testSymfonyOwnRequestChannelDoesNotEndUpInTheBackupFile(): void
    {
        // "request" es también el canal interno de Symfony (p. ej. "Matched route"): no es nuestro.
        $marker = 'symfony-channel-' . bin2hex(random_bytes(6));
        $this->logger('monolog.logger.request')->info($marker);
        $ours = 'client-channel-' . bin2hex(random_bytes(6));
        $this->logger('monolog.logger.client_request')->info($ours);

        $this->assertSame([], $this->linesContaining($marker));
        $this->assertCount(1, $this->linesContaining($ours));
    }

    public function testGetRequestsAreNotLogged(): void
    {
        $marker = 'probe-' . bin2hex(random_bytes(6));
        $request = Request::create('/api/communication/sale?clientTransactionId=' . $marker, 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTH_TOKEN' => base64_encode((string) $this->account->getAccessToken()),
        ]);
        $this->em->clear();
        self::$kernel->handle($request);

        $this->assertSame([], $this->linesContaining($marker));
    }

    private function post(string $uri, string $content): void
    {
        $request = Request::create($uri, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTH_TOKEN' => base64_encode((string) $this->account->getAccessToken()),
        ], $content);
        $this->em->clear();
        self::$kernel->handle($request);
    }

    private function logger(string $id): LoggerInterface
    {
        $logger = self::getContainer()->get($id);
        \assert($logger instanceof LoggerInterface);

        return $logger;
    }

    private function logFile(): string
    {
        return self::$kernel->getLogDir() . '/request.' . self::$kernel->getEnvironment() . '.' . date('Y-m-d') . '.log';
    }

    /** @return list<array<string, mixed>> */
    private function linesContaining(string $needle): array
    {
        $file = $this->logFile();
        if (!is_file($file)) {
            return [];
        }
        $found = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_contains($line, $needle) && is_array($decoded = json_decode($line, true))) {
                $found[] = $decoded;
            }
        }

        return $found;
    }
}
