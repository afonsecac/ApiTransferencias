<?php

namespace App\MessageHandler;

use App\Message\TwoFactorMandatoryNotificationMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class TwoFactorMandatoryNotificationHandler
{
    public function __construct(
        private readonly MailerInterface       $mailer,
        private readonly ParameterBagInterface $params,
    ) {}

    public function __invoke(TwoFactorMandatoryNotificationMessage $msg): void
    {
        $brand      = $msg->getContractWith() ?? 'comremit';
        $senderName = $brand === 'comremit'
            ? 'No Reply (Comremit Solutions SL)'
            : 'No Reply - (SendMundo SL)';

        $mail = (new TemplatedEmail())
            ->from(new Address($this->params->get('app.email.from'), $senderName))
            ->to(new Address($msg->getEmail(), $msg->getFirstName()))
            ->priority(Email::PRIORITY_NORMAL)
            ->subject('Verificación en dos pasos requerida / Two-factor authentication required')
            ->htmlTemplate('emails/2fa/mandatory-notice.' . $brand . '.html.twig')
            ->context([
                'firstName' => $msg->getFirstName(),
                'deadline'  => $msg->getDeadline(),
            ]);

        $this->mailer->send($mail);
    }
}
