<?php

namespace App\Service;

use App\DTO\Out\RechargeVerificationOutDto;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Twig\Environment;

/**
 * Arma el PDF visual del comprobante a partir de los mismos datos que ya
 * sirve RechargeVerificationController::verify() en JSON — no vuelve a
 * consultar la base de datos, reusa RechargeVerificationService.
 */
class RechargeReceiptPdfService
{
    private const ?string LOGO_PATH = __DIR__ . '/../../assets/receipt/comremit-logo.svg';

    private const array STATE_LABELS = [
        'Created' => 'Creada',
        'Reserved' => 'Reservada',
        'Pending' => 'Pendiente',
        'Rejected' => 'Rechazada',
        'Completed' => 'Completada',
        'Failed' => 'Fallida',
    ];

    public function __construct(
        private readonly RechargeVerificationService $verificationService,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @throws \App\Exception\MyCurrentException si la venta no existe o no está Completed
     */
    public function renderPdf(string $transactionId, string $verifyBaseUrl): string
    {
        $data = $this->verificationService->verify($transactionId);
        $verifyUrl = rtrim($verifyBaseUrl, '/') . '/api/verify/' . $transactionId . '/receipt';

        $html = $this->twig->render('receipt/recharge.html.twig', [
            'transactionId' => $data->transactionId,
            'etecsaOrderId' => $data->etecsaOrderId,
            'clientId' => $data->clientId,
            'enteredAt' => $this->formatDate($data->enteredAt),
            'phoneMasked' => $data->phoneMasked,
            'package' => $data->package,
            'destinationAmount' => $data->destinationAmount,
            'destinationCurrency' => $data->destinationCurrency,
            'promotion' => $data->promotion,
            'history' => $this->viewHistory($data),
            'logoDataUri' => $this->logoDataUri(),
            'qrDataUri' => $this->qrDataUri($verifyUrl),
            'verifyUrl' => $verifyUrl,
        ]);

        $options = new DompdfOptions();
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return array<int, array{state: string, stateLabel: string, occurredAt: ?string}>
     */
    private function viewHistory(RechargeVerificationOutDto $data): array
    {
        return array_map(
            fn (array $entry) => [
                'state' => $entry['state'],
                'stateLabel' => self::STATE_LABELS[$entry['state']] ?? $entry['state'],
                'occurredAt' => $this->formatDate($entry['occurredAt']),
            ],
            $data->history,
        );
    }

    private function formatDate(?string $isoUtc): ?string
    {
        if ($isoUtc === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $isoUtc, new \DateTimeZone('UTC'));

        return $date !== false ? $date->format('d/m/Y H:i:s') . ' UTC' : $isoUtc;
    }

    private function logoDataUri(): string
    {
        $svg = file_get_contents(self::LOGO_PATH);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function qrDataUri(string $url): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'imageBase64' => true,
        ]);

        return (new QRCode($options))->render($url);
    }
}
