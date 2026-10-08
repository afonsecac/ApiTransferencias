<?php

namespace App\EventListener;

use App\Entity\Account;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Respaldo en fichero (canal "request" → var/log/request.{env}.{fecha}.log,
 * 15 días de rotación, ver config/packages/monolog.yaml) de cada POST de
 * recarga/venta del cliente: hora (la del registro), endpoint y body de
 * entrada. Se escribe antes de validar/procesar, así que también quedan las
 * peticiones que luego fallan. Prioridad 7: justo después del firewall (8),
 * cuando ya se conoce la cuenta autenticada.
 *
 * Nunca debe romper la petición: cualquier fallo de escritura se ignora.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
#[WithMonologChannel('request')]
class ClientRequestLogListener
{
    private const LOGGED_PATH = '#^/api/communication/sale/(recharge|recharge/reserve|package)/?$#';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || $request->getMethod() !== Request::METHOD_POST
            || !preg_match(self::LOGGED_PATH, $request->getPathInfo())
        ) {
            return;
        }

        try {
            $user = $this->security->getUser();
            $this->logger->info('Client request', [
                'method' => $request->getMethod(),
                'endpoint' => $request->getPathInfo(),
                'account' => $user instanceof Account ? $user->getUserIdentifier() : null,
                'ip' => $request->getClientIp(),
                'payload' => $request->getContent(),
            ]);
        } catch (\Throwable) {
            // El respaldo es best-effort: no puede tumbar la venta.
        }
    }
}
