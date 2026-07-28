<?php

namespace App\MessageHandler;

use App\DTO\NotificationDraft;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Message\DispatchPendingEmailMessage;
use App\Service\NotificationCenterService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler(fromTransport: 'async_notifications_high')]
class DispatchPendingEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
        private readonly NotificationCenterService $notificationCenter,
    ) {}

    public function __invoke(DispatchPendingEmailMessage $message): void
    {
        // Una sola entrada por día que se va actualizando (bumpGroup), en vez
        // de una notificación por cada despacho.
        $this->notificationCenter->bumpGroup(
            'dispatch_pending:' . (new \DateTimeImmutable())->format('Y-m-d'),
            NotificationAudienceEnum::ROLE,
            new NotificationDraft(
                type: NotificationTypeEnum::DISPATCH_PENDING,
                title: sprintf('%d mensaje(s) despachados al API de comunicaciones hoy', $message->getTotal()),
                level: NotificationLevelEnum::INFO,
                link: '/security/communications-dispatch',
                data: ['total' => $message->getTotal(), 'triggeredBy' => $message->getTriggeredBy()],
                expiresAt: new \DateTimeImmutable('+2 days'),
            ),
            targetRole: 'ROLE_ADMIN',
        );

        try {
            $from = $this->parameterBag->get('app.email.from');

            $triggeredBy = $message->getTriggeredBy();
            $isUserEmail = $triggeredBy !== null && filter_var($triggeredBy, FILTER_VALIDATE_EMAIL);
            $toAddress   = $isUserEmail ? new Address($triggeredBy) : new Address($from, 'Administración');

            $mail = (new TemplatedEmail())
                ->from(new Address($from, 'Sistema — Comremit'))
                ->to($toAddress)
                ->cc(
                    new Address('alexander.afonsecac@gmail.com', 'A. Fonseca'),
                    new Address('aportela7@gmail.com', 'A. Portela'),
                )
                ->priority(Email::PRIORITY_HIGH)
                ->subject(sprintf('[Dispatch] %d mensaje(s) encolado(s) al API de comunicaciones', $message->getTotal()))
                ->htmlTemplate('emails/communications/dispatch-pending.html.twig')
                ->context([
                    'recharges'      => $message->getRecharges(),
                    'packages'       => $message->getPackages(),
                    'total'          => $message->getTotal(),
                    'dispatchedAt'   => $message->getDispatchedAt(),
                    'triggeredBy'    => $triggeredBy ?? 'CLI / Tarea programada',
                    'transactionIds' => $message->getTransactionIds(),
                ]);

            $this->mailer->send($mail);
        } catch (\Exception $e) {
            $this->logger->error('DispatchPendingEmailHandler: ' . $e->getMessage());
        }
    }
}
