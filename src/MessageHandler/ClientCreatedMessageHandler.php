<?php

namespace App\MessageHandler;

use App\Message\ClientCreatedMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class ClientCreatedMessageHandler
{
    public function __construct(
        private readonly MailerInterface       $mailer,
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     * @throws \Throwable
     */
    public function __invoke(ClientCreatedMessage $message): void
    {
        $contractWith = $message->getContractWith() ?? 'comremit';
        $senderName   = $contractWith === 'comremit'
            ? 'No Reply (Comremit Solutions SL)'
            : 'No Reply - (SendMundo SL)';

        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), $senderName))
            ->to(new Address($message->getEmail(), $message->getCompanyName()))
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Datos de acceso a tu cuenta / Account access credentials')
            ->htmlTemplate('emails/account/client_created.' . $contractWith . '.html.twig')
            ->context([
                'companyName'     => $message->getCompanyName(),
                'accessToken'     => $message->getAccessToken(),
                'environmentType' => $message->getEnvironmentType(),
                'origin'          => $message->getOrigin(),
            ]);

        $this->mailer->send($mail);
    }
}
