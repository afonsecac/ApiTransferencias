<?php

namespace App\MessageHandler;

use App\Message\AccountActivationMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class AccountActivationMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     * @throws \Throwable
     */
    public function __invoke(AccountActivationMessage $message): void
    {
        $contractWith = $message->getContractWith() ?? 'comremit';
        $senderName = $contractWith === 'comremit' ? 'No Reply (Comremit Solutions SL)' : 'No Reply - (SendMundo SL)';

        $baseUrl = $this->parameterBag->get('app.dashboard.url.' . $contractWith);
        $activationUrl = $baseUrl . '/reset-password?email=' . urlencode($message->getEmail());

        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), $senderName))
            ->to(new Address($message->getEmail(), $message->getFirstName() ?? ''))
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Activa tu cuenta / Activate your account')
            ->htmlTemplate('emails/account/activation.' . $contractWith . '.html.twig')
            ->context([
                'code'          => $message->getCode(),
                'activationUrl' => $activationUrl,
                'firstName'     => $message->getFirstName(),
                'email'         => $message->getEmail(),
            ]);

        $this->mailer->send($mail);
    }
}
