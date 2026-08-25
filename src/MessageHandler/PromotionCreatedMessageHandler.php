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
use App\Repository\CommunicationPackageRepository;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\TargetAccountResolver;
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
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly TargetAccountResolver $targetAccountResolver,
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

        $packages = $promotion->getProducts()->count() > 0
            ? $this->packagesForV1Promotion($promotion, $message->getClientId())
            : $this->packagesForV2Promotion($promotion, $message->getClientId());
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

    /**
     * Camino V1: los paquetes de la promoción ya son copias POR TENANT
     * (CommunicationClientPackage), así que basta con filtrar los que
     * pertenecen a este cliente. `price`/`priceCurrency` viene del contrato
     * congelado (si tiene); `amount`/`currency` es el snapshot crudo
     * persistido (getAmount()/getCurrency() sin resolvedSalePrice inyectado
     * en este contexto de background job — no hay request en curso).
     *
     * @return list<array{name: string, price: ?float, priceCurrency: ?string, amount: ?float, currency: ?string}>
     */
    private function packagesForV1Promotion(CommunicationPromotions $promotion, int $clientId): array
    {
        $packages = [];
        foreach ($promotion->getProducts() as $pkg) {
            if ($pkg->getTenant()?->getClient()?->getId() === $clientId) {
                $packages[] = [
                    'name'          => $pkg->getName(),
                    'price'         => $pkg->getPriceClientPackage()?->getPrice(),
                    'priceCurrency' => $pkg->getPriceClientPackage()?->getPriceCurrency(),
                    'amount'        => $pkg->getAmount(),
                    'currency'      => $pkg->getCurrency(),
                ];
            }
        }

        return $packages;
    }

    /**
     * Camino V2 (Fase 2 de la deprecación de V1): el catálogo V2 es
     * COMPARTIDO, no hay una copia por tenant que filtrar — hay que resolver
     * la cuenta activa de este cliente en el entorno de la promoción y
     * consultarle el precio a PackageCatalogResolver::offerFor() por cada
     * paquete de la promoción (CommunicationPackageRepository::findByPromotion()).
     * Sin cuenta activa para este cliente en este entorno, no hay nada que
     * mandar (mismo resultado práctico que el filtro por tenant del camino
     * V1: lista vacía). `amount`/`currency` es directo del paquete
     * (destinationAmount/destinationCurrency — no depende de resolver
     * oferta), igual de "snapshot crudo" que el camino V1.
     *
     * @return list<array{name: string, price: ?float, priceCurrency: ?string, amount: ?float, currency: ?string}>
     */
    private function packagesForV2Promotion(CommunicationPromotions $promotion, int $clientId): array
    {
        $environment = $promotion->getEnvironment();
        $account = $environment !== null
            ? $this->targetAccountResolver->resolveOne($environment, $clientId)
            : null;
        if ($account === null) {
            return [];
        }

        $packages = [];
        foreach ($this->packageRepository->findByPromotion($promotion) as $pkg) {
            $offer = $this->catalogResolver->offerFor($pkg, $account);
            $offerVisible = $offer !== null && $offer->source !== PackageOfferSourceEnum::UNAVAILABLE;

            $packages[] = [
                'name'          => $pkg->getName(),
                'price'         => $offerVisible ? $offer->price : null,
                'priceCurrency' => $offerVisible ? $offer->currency : null,
                'amount'        => $pkg->getDestinationAmount(),
                'currency'      => $pkg->getDestinationCurrency(),
            ];
        }

        return $packages;
    }
}
