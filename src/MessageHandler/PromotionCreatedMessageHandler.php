<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CommunicationPromotions;
use App\Message\PromotionCreatedMessage;
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
    ) {}

    public function __invoke(PromotionCreatedMessage $message): void
    {
        $promotion = $this->em->getRepository(CommunicationPromotions::class)->find($message->getPromotionId());
        if ($promotion === null) {
            return;
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
