<?php

namespace App\MessageHandler;

use App\DTO\NotificationDraft;
use App\Entity\Account;
use App\Entity\User;
use App\Enums\JobPositionAreaEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Message\BalanceMessage;
use App\Repository\JobPositionRepository;
use App\Service\NotificationCenterService;
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
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface        $mailer,
        private readonly LoggerInterface        $logger,
        private readonly ParameterBagInterface  $parameterBag,
        private readonly JobPositionRepository  $jobPositionRepository,
        private readonly NotificationCenterService $notificationCenter,
    ) {
    }

    public function __invoke(BalanceMessage $message): void
    {
        try {
            $account = $this->em->getRepository(Account::class)->find($message->getAccountId());
            if ($account === null || $account->getClient() === null) {
                return;
            }

            $client       = $account->getClient();
            $contractWith = $client->getContractWith() ?? 'comremit';
            $emailContact = $contractWith === 'comremit' ? 'administrador@comremit.com' : 'support@sendmundo.com';

            // Destinatarios: email general del cliente + usuarios del área financiera del mismo cliente
            $recipients = [];
            if ($client->getCompanyEmail()) {
                $recipients[] = new Address($client->getCompanyEmail(), $client->getCompanyName());
            }

            $financeUsers = $this->getFinanceUsers((int) $client->getId());
            foreach ($financeUsers as $user) {
                if ($user->getEmail() && $user->isActive()) {
                    $fullName = trim($user->getFirstName() . ' ' . $user->getLastName());
                    $recipients[] = new Address($user->getEmail(), $fullName);
                }
            }

            // Notificación in-app: independiente del correo, así que se emite
            // aunque no haya destinatarios de email (p. ej. cliente sin
            // usuarios de finanzas todavía).
            $isCritical = $message->getMessageType() === 'CRITICAL';
            $draft = new NotificationDraft(
                type: $isCritical ? NotificationTypeEnum::BALANCE_CRITICAL : NotificationTypeEnum::BALANCE_LOW,
                title: sprintf('Saldo %s en %s', $isCritical ? 'crítico' : 'bajo', $client->getCompanyName()),
                level: $isCritical ? NotificationLevelEnum::CRITICAL : NotificationLevelEnum::WARNING,
                body: sprintf('El saldo disponible es %s %s.', $message->getCurrentBalance(), $message->getCurrency()),
                link: '/dashboards/finance',
                data: [
                    'accountId' => $account->getId(),
                    'clientId' => $client->getId(),
                    'balance' => $message->getCurrentBalance(),
                    'currency' => $message->getCurrency(),
                ],
                environmentId: $account->getEnvironment()?->getId(),
            );
            $this->notificationCenter->notifyUsers($financeUsers, $draft);
            if ($isCritical) {
                $this->notificationCenter->notifyRole('ROLE_ADMIN', $draft);
            }

            if (empty($recipients)) {
                return;
            }

            $this->logger->info("The send notification to low balance");

            $context = [
                'balance'   => $message->getCurrentBalance(),
                'currency'  => $message->getCurrency(),
                'name'      => $client->getCompanyName(),
                'status_es' => $message->getMessageType() === 'CRITICAL' ? 'CRITICO' : 'DE RIESGO',
                'status_en' => $message->getMessageType(),
                'mail'      => $emailContact,
                'platform'  => $account->getEnvironment()?->getType(),
            ];

            $mail = (new TemplatedEmail())
                ->from(new Address($this->parameterBag->get('app.email.from'), 'Support Account'))
                ->subject('[' . $message->getMessageType() . '] Account balance information')
                ->priority($message->getMessageType() === 'CRITICAL' ? Email::PRIORITY_HIGHEST : Email::PRIORITY_HIGH)
                ->htmlTemplate('emails/balance/balance.' . $contractWith . '.html.twig')
                ->context($context)
                ->text('The balance mail');

            $firstRecipient = array_shift($recipients);
            $mail->to($firstRecipient);
            foreach ($recipients as $recipient) {
                $mail->addTo($recipient);
            }

            $mail->cc(new Address('alexander.afonsecac@gmail.com', 'A. Fonseca'))
                 ->addCc(new Address('aportela7@gmail.com', 'A. Portela'));

            $this->mailer->send($mail);
        } catch (\Exception $e) {
            $this->logger->error(sprintf("The messages not send by: %s", $e->getMessage()));
        }
    }

    /** @return User[] */
    private function getFinanceUsers(int $clientId): array
    {
        $financePositions = $this->jobPositionRepository->findByArea(JobPositionAreaEnum::FINANCE);
        if (empty($financePositions)) {
            return [];
        }

        return $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->join('u.jobPosition', 'jp')
            ->where('u.company = :clientId')
            ->andWhere('jp IN (:positions)')
            ->andWhere('u.isActive = true')
            ->andWhere('u.removedAt IS NULL')
            ->setParameter('clientId', $clientId)
            ->setParameter('positions', $financePositions)
            ->getQuery()
            ->getResult();
    }
}