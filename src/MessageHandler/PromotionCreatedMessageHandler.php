<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\NotificationDraft;
use App\Entity\Client;
use App\Entity\CommunicationPromotions;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Message\PromotionCreatedMessage;
use App\Service\NotificationCenterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class PromotionCreatedMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $em,
        private readonly NotificationCenterService $notificationCenter,
    ) {}

    public function __invoke(PromotionCreatedMessage $message): void
    {
        $promotion = $this->em->getRepository(CommunicationPromotions::class)->find($message->getPromotionId());
        if ($promotion === null) {
            return;
        }

        // Este mensaje se despacha una vez por cada usuario técnico
        // destinatario del mismo cliente (CommunicationPromotionService::
        // dispatchPromotionEmails); bumpGroup colapsa esas N repeticiones en
        // una sola notificación por promoción y cliente.
        $client = $this->em->getRepository(Client::class)->find($message->getClientId());
        if ($client !== null) {
            $this->notificationCenter->bumpGroup(
                'promotion_created:' . $message->getPromotionId() . ':' . $message->getClientId(),
                NotificationAudienceEnum::CLIENT,
                new NotificationDraft(
                    type: NotificationTypeEnum::PROMOTION_CREATED,
                    title: sprintf('Nueva promoción: %s', $promotion->getName()),
                    level: NotificationLevelEnum::SUCCESS,
                    link: '/apps/promotions',
                    data: ['promotionId' => $promotion->getId()],
                ),
                client: $client,
            );
        }

        $packages = [];
        foreach ($promotion->getProducts() as $pkg) {
            if ($pkg->getTenant()?->getClient()?->getId() === $message->getClientId()) {
                $packages[] = [
                    'name'          => $pkg->getName(),
                    'price'         => $pkg->getPriceClientPackage()?->getPrice(),
                    'priceCurrency' => $pkg->getPriceClientPackage()?->getPriceCurrency(),
                    'amount'        => $pkg->getAmount(),
                    'currency'      => $pkg->getCurrency(),
                ];
            }
        }
        usort($packages, fn($a, $b) => $a['price'] <=> $b['price']);

        $contractWith = $message->getContractWith() ?? 'comremit';
        $senderName = $contractWith === 'comremit'
            ? 'No Reply (Comremit Solutions SL)'
            : 'No Reply - (SendMundo SL)';

        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), $senderName))
            ->to(new Address($message->getRecipientEmail(), $message->getRecipientFirstName() ?? ''))
            ->priority(Email::PRIORITY_NORMAL)
            ->subject(sprintf('Nueva promoción: %s', $promotion->getName()))
            ->htmlTemplate(sprintf('emails/promotions/promotion-created.%s.html.twig', $contractWith))
            ->context([
                'firstName'       => $message->getRecipientFirstName(),
                'promotionName'   => $promotion->getName(),
                'description'     => $promotion->getDescription(),
                'infoDescription' => $promotion->getInfoDescription(),
                'startAt'         => $promotion->getStartAt()?->format('d/m/Y H:i T'),
                'endAt'           => $promotion->getEndAt()?->format('d/m/Y H:i T'),
                'terms'           => $promotion->getTerms(),
                'packages'        => $packages,
            ]);

        $this->mailer->send($mail);
    }
}
