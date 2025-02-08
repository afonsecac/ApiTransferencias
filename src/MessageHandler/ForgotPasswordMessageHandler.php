<?php

namespace App\MessageHandler;

use App\Message\ForgotPasswordMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class ForgotPasswordMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function __invoke(ForgotPasswordMessage $message): void
    {
        $contractWith = $message->getOrigin() ?? 'comremit';
        $senderName = $contractWith === 'comremit' ? 'No Reply (Comremit Solutions SL)' : 'No Reply - (SendMundo SL)';
        $emailReply = $contractWith === 'comremit' ? 'admin@comremit.com' : 'support@sendmundo.com';
        $mailer = (new TemplatedEmail())
            ->from(new Address('support@sendmundo.com', $senderName))
            ->to(new Address($message->getEmail(), $message->getName()))
            ->subject('Reset password / Cambio de contraseña')
            ->htmlTemplate('emails/password/forgot-password.'.$contractWith.'.html.twig')
            ->context([
                'code' => $message->getCode(),
            ]);

        $this->mailer->send($mailer);
    }
}