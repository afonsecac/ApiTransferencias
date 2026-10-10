<?php

namespace App\Controller;

use App\Exception\MyCurrentException;
use App\Service\RechargeReceiptPdfService;
use App\Service\RechargeVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verificación pública de una recarga — destino del QR impreso en el
 * comprobante que recibe el cliente final. Sin autenticación (lo consulta
 * cualquiera que escanee el QR, ver security.yaml access_control), así que
 * solo responde con datos si la venta existe Y está Completed; cualquier
 * otro caso (no existe, o existe pero sigue Pending/Failed/etc.) responde
 * el mismo 404 genérico, para no confirmar a un tercero el estado de una
 * recarga ajena.
 */
#[Route('/api/verify')]
class RechargeVerificationController extends AbstractController
{
    public function __construct(
        private readonly RechargeVerificationService $verificationService,
        private readonly RechargeReceiptPdfService $receiptPdfService,
    ) {
    }

    #[Route('/{transactionId}', name: 'api_recharge_verify', methods: ['GET'])]
    public function verify(string $transactionId, Request $request): JsonResponse
    {
        try {
            $result = $this->verificationService->verify($transactionId, $request->query->get('token'));
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($result);
    }

    /**
     * Mismo dato que verify(), pero como PDF visual — es lo que abre el
     * botón "Ver comprobante" del dashboard. La URL base para el enlace/QR
     * se toma de la propia petición (esquema+host), así apunta siempre al
     * dominio correcto (staging/prod) sin configuración extra.
     */
    #[Route('/{transactionId}/receipt', name: 'api_recharge_verify_receipt', methods: ['GET'])]
    public function receipt(string $transactionId, Request $request): Response
    {
        try {
            $pdf = $this->receiptPdfService->renderPdf(
                $transactionId,
                $request->query->get('token'),
                $request->getSchemeAndHttpHost(),
            );
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comprobante-' . $transactionId . '.pdf"',
        ]);
    }
}
