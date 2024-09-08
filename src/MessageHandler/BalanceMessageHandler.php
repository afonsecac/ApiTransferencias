<?php

namespace App\MessageHandler;

use App\Entity\Account;
use App\Message\BalanceMessage;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler(fromTransport: 'async_send_notification')]
class BalanceMessageHandler
{
    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Symfony\Component\Mailer\MailerInterface $mailer
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface        $mailer,
        private readonly LoggerInterface        $logger,
        private readonly ParameterBagInterface  $parameterBag,
    )
    {
    }

    /**
     * @param \App\Message\BalanceMessage $message
     * @return void
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function __invoke(BalanceMessage $message): void
    {
        try {
            $account = $this->em->getRepository(Account::class)->find($message->getAccountId());
            if (!is_null($account) && $account->getClient()?->getCompanyEmail()) {
                $this->logger->info("The send notification to low balance");
                $mail = (new TemplatedEmail())->from(new Address($this->parameterBag->get('app.email.from'), 'Support Account'))
                    ->to(new Address($account->getClient()?->getCompanyEmail(), $account->getClient()?->getCompanyName()))
                    ->cc(new Address('alexander.afonsecac@gmail.com', 'A. Fonseca'))
                    ->addCc(new Address('aportela7@gmail.com', 'A. Portela'))
                    ->subject('[' . $message->getMessageType() . '] Account balance information')
                    ->priority($message->getMessageType() === 'CRITICAL' ? Email::PRIORITY_HIGHEST : Email::PRIORITY_HIGH)
                    ->htmlTemplate('balance/balance.html.twig')
                    ->context([
                        'balance' => $message->getCurrentBalance(),
                        'currency' => $message->getCurrency(),
                        'name' => $account->getClient()?->getCompanyName(),
                        'status' => $message->getMessageType() === 'CRITICAL' ? 'CRITICO' : 'DE RIESGO'
                    ])->text('The balance mail');

                $this->mailer->send($mail);
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf("The messages not send by: %s", $e->getMessage()));
        }
    }
}