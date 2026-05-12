<?php

namespace App\MessageHandler;

use App\Message\TwoFactorEmailCodeMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class TwoFactorEmailCodeHandler
{
    public function __construct(
        private readonly MailerInterface      $mailer,
        private readonly ParameterBagInterface $params,
    ) {}

    public function __invoke(TwoFactorEmailCodeMessage $msg): void
    {
        $brand      = $msg->getContractWith() ?? 'comremit';
        $senderName = $brand === 'comremit'
            ? 'No Reply (Comremit Solutions SL)'
            : 'No Reply - (SendMundo SL)';

        $mail = (new TemplatedEmail())
            ->from(new Address($this->params->get('app.email.from'), $senderName))
            ->to(new Address($msg->getEmail(), $msg->getFirstName()))
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Tu código de verificación / Your verification code')
            ->htmlTemplate('emails/2fa/code.' . $brand . '.html.twig')
            ->context([
                'firstName' => $msg->getFirstName(),
                'code'      => $msg->getCode(),
            ]);

        $this->mailer->send($mail);
    }
}
