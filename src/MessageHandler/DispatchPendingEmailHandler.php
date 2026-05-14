<?php

namespace App\MessageHandler;

use App\Message\DispatchPendingEmailMessage;
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
    ) {}

    public function __invoke(DispatchPendingEmailMessage $message): void
    {
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
