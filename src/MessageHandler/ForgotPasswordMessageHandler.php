<?php

namespace App\MessageHandler;

use App\Message\ForgotPasswordMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class ForgotPasswordMessageHandler
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
    public function __invoke(ForgotPasswordMessage $message): void
    {
        $contractWith = $message->getOrigin() ?? 'comremit';
        $senderName = $contractWith === 'comremit' ? 'No Reply (Comremit Solutions SL)' : 'No Reply - (SendMundo SL)';
        $mailer = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), $senderName))
            ->to(new Address($message->getEmail(), $message->getName() ?? ''))
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Reset password / Cambio de contraseña')
            ->htmlTemplate('emails/password/forgot-password.'.$contractWith.'.html.twig')
            ->context([
                'code' => $message->getCode(),
            ]);

        $this->mailer->send($mailer);
    }
}