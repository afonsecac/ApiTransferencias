<?php

namespace App\MessageHandler;

use App\DTO\NotificationDraft;
use App\Entity\User;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Message\TwoFactorMandatoryNotificationMessage;
use App\Service\NotificationCenterService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $em,
        private readonly NotificationCenterService $notificationCenter,
    ) {}

    public function __invoke(TwoFactorMandatoryNotificationMessage $msg): void
    {
        $brand      = $msg->getContractWith() ?? 'comremit';
        $senderName = $brand === 'comremit'
            ? 'No Reply (Comremit Solutions SL)'
            : 'No Reply - (SendMundo SL)';

        // A diferencia de la invitación de activación, aquí el usuario ya
        // puede iniciar sesión: persiste hasta que resuelva su 2FA (sin
        // expiresAt), por eso conviene buscarlo por email y avisarle in-app.
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $msg->getEmail()]);
        if ($user !== null) {
            $this->notificationCenter->notifyUser($user, new NotificationDraft(
                type: NotificationTypeEnum::TWO_FACTOR_MANDATORY_PENDING,
                title: 'Verificación en dos pasos requerida',
                level: NotificationLevelEnum::WARNING,
                body: sprintf('Tu organización exige 2FA. Actívalo antes del %s.', $msg->getDeadline()),
                link: '/settings/security',
            ));
        }

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
