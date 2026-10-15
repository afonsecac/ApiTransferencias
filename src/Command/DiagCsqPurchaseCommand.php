<?php

namespace App\Command;

use App\Enums\CommunicationProviderEnum;
use App\Provider\ProviderCredentialsResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * TEMPORAL — replica la petición purchase() de CsqHttpClient contra el
 * sandbox real, pero imprimiendo el BODY completo del 400 (CsqHttpClient
 * solo guarda $e->getMessage(), que no trae el body). Borrar después.
 */
#[AsCommand(name: 'app:diag:csq-purchase-body', description: 'TEMP')]
class DiagCsqPurchaseCommand extends Command
{
    public function __construct(
        private readonly ProviderCredentialsResolver $credentialsResolver,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $credentials = $this->credentialsResolver->get(CommunicationProviderEnum::CSQ, 'TEST');
        $terminal = $credentials['terminal'];
        $operatorId = 8142;
        $localReference = random_int(100000, 999999); // fresco, no choca con nada real

        $timestamp = (string) time();
        $signature = hash('sha256', hash('sha256', $credentials['password']) . hash('sha256', $timestamp));

        $body = [
            'destinationAmountX100' => 60000,
            'localDateTime' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
            'account' => '56085160',
            'dynamicProductId' => (string) $operatorId,
            'receiverEmail' => $credentials['default_receiver_email'] ?? '',
            'documentNumber' => $credentials['default_document_number'] ?? '',
        ];

        $output->writeln('URL: ' . rtrim($credentials['base_url'], '/') . "/pre-paid/recharge/purchase/{$terminal}/{$operatorId}/{$localReference}");
        $output->writeln('BODY: ' . json_encode($body));

        try {
            $response = $this->httpClient->request('POST', rtrim($credentials['base_url'], '/') . "/pre-paid/recharge/purchase/{$terminal}/{$operatorId}/{$localReference}", [
                'headers' => [
                    'U' => $credentials['username'],
                    'ST' => $timestamp,
                    'SH' => $signature,
                    'Accept' => 'application/json',
                ],
                'json' => $body,
            ]);
            $output->writeln('STATUS: ' . $response->getStatusCode());
            $output->writeln('BODY RESPONSE: ' . $response->getContent(false));
        } catch (ClientExceptionInterface $e) {
            $response = $e->getResponse();
            $output->writeln('CLIENT EXCEPTION STATUS: ' . $response->getStatusCode());
            $output->writeln('RAW RESPONSE BODY: ' . $response->getContent(false));
        } catch (\Throwable $e) {
            $output->writeln('OTHER EXCEPTION: ' . get_class($e) . ' :: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
