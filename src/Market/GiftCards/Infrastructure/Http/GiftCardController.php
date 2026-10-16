<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Http;

use App\Entity\User;
use App\Market\GiftCards\Application\CancelGiftCard;
use App\Market\GiftCards\Application\GetGiftCard;
use App\Market\GiftCards\Application\GiftCardRow;
use App\Market\GiftCards\Application\IssueGiftCardBatch;
use App\Market\GiftCards\Application\IssuePlan;
use App\Market\GiftCards\Application\ListGiftCards;
use App\Market\GiftCards\Application\ListRedemptions;
use App\Market\GiftCards\Application\RedeemGiftCard;
use App\Market\GiftCards\Application\RevealGiftCardPin;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardAlreadyRedeemed;
use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardProductInvalid;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Market\GiftCards\Domain\GiftCardTransitionInvalid;
use App\Market\GiftCards\Domain\InvalidGiftCard;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gift cards propias (COMR-276): emisión por lotes, listado, detalle con traza, PIN (con registro), anulación y canje idempotente.
 * El controlador solo traduce HTTP; el PIN solo sale por su endpoint propio.
 */
#[AsController]
#[Route('/market/gift-cards')]
#[IsGranted('ROLE_API_ADMIN')]
final class GiftCardController
{
    public function __construct(
        private readonly IssueGiftCardBatch $issue,
        private readonly ListGiftCards $list,
        private readonly GetGiftCard $get,
        private readonly RevealGiftCardPin $reveal,
        private readonly CancelGiftCard $cancel,
        private readonly RedeemGiftCard $redeem,
        private readonly ListRedemptions $redemptions,
        private readonly GiftCardProducts $products,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'market_gift_cards_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar tarjetas (filtros: product, status, batch, serial); el PIN nunca aparece', tag: 'Market', responseDto: GiftCardOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $product = $request->query->get('product');
        $batch = $request->query->get('batch');
        $status = $request->query->get('status');
        $serial = $request->query->get('serial');

        return new JsonResponse(array_map(
            static fn (GiftCardRow $r) => GiftCardOutDto::fromRow($r),
            ($this->list)(
                is_string($product) && ctype_digit($product) ? (int) $product : null,
                is_string($status) ? $status : null,
                is_string($batch) && ctype_digit($batch) ? (int) $batch : null,
                is_string($serial) ? $serial : null,
            ),
        ));
    }

    #[Route('/redemptions', name: 'market_gift_cards_redemptions', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Últimos canjes, el más reciente primero', tag: 'Market', responseDto: GiftCardOutDto::class, responseIsArray: true)]
    public function redemptions(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (GiftCardRow $r) => GiftCardOutDto::fromRow($r), ($this->redemptions)(50)));
    }

    #[Route('/products/{id}', name: 'market_gift_cards_product', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Valor de un producto digital y centros de costo de su vendedor, para emitir un lote', tag: 'Market')]
    public function product(int $id): JsonResponse
    {
        $product = $this->products->find($id);
        if ($product === null || !$product->digital) {
            return $this->error('GIFT_CARD_PRODUCT_INVALID', 'El producto no existe o no es digital con valor.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'id' => $product->id,
            'name' => $product->name,
            'fixed' => $product->fixed,
            'currency' => $product->currency,
            'minMinor' => $product->minMinor,
            'maxMinor' => $product->maxMinor,
            'costCenters' => array_map(static fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'principal' => $c->principal, 'active' => $c->active], $product->costCenters),
        ]);
    }

    #[Route('', name: 'market_gift_cards_issue_single', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Emitir una tarjeta individual de un producto digital (serie y PIN no adivinable cifrado)', tag: 'Market', requestDto: IssueGiftCardDto::class, responseStatusCode: 201)]
    public function issueSingle(IssueGiftCardDto $dto): JsonResponse
    {
        return $this->issued(fn () => ($this->issue)((int) $dto->getProductId(), IssuePlan::single($dto->getValue()), $dto->getExpiresAt(), $dto->getCostCenterId(), $this->actor()));
    }

    #[Route('/batches', name: 'market_gift_cards_issue', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Emitir un lote por rango: tarjetas de cada valor de min a max de step en step', tag: 'Market', requestDto: IssueGiftCardBatchDto::class, responseStatusCode: 201)]
    public function issue(IssueGiftCardBatchDto $dto): JsonResponse
    {
        return $this->issued(fn () => ($this->issue)(
            (int) $dto->getProductId(),
            IssuePlan::range($dto->getMin(), $dto->getMax(), $dto->getStep(), (int) $dto->getPerValue()),
            $dto->getExpiresAt(),
            $dto->getCostCenterId(),
            $this->actor(),
        ));
    }

    #[Route('/redeem', name: 'market_gift_cards_redeem', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Canjear una tarjeta por su PIN con una clave de canje idempotente (repetirla devuelve el mismo canje)', tag: 'Market', requestDto: RedeemGiftCardDto::class, responseDto: GiftCardOutDto::class)]
    public function redeem(RedeemGiftCardDto $dto): JsonResponse
    {
        return $this->guard(function () use ($dto): JsonResponse {
            $result = ($this->redeem)((string) $dto->getPin(), (string) $dto->getRedemptionRef(), $this->actor());

            return new JsonResponse(['replay' => $result->replay, 'card' => GiftCardOutDto::fromRow(new GiftCardRow($result->card, null, new \DateTimeImmutable()))]);
        });
    }

    #[Route('/{serial}', name: 'market_gift_cards_get', methods: ['GET'], requirements: ['serial' => 'GC-\d+'])]
    #[DashboardEndpoint(summary: 'Detalle de una tarjeta con su traza (el PIN no se incluye)', tag: 'Market', responseDto: GiftCardOutDto::class)]
    public function get(string $serial): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(GiftCardOutDto::fromDetails(($this->get)($serial))));
    }

    #[Route('/{serial}/pin', name: 'market_gift_cards_pin', methods: ['POST'], requirements: ['serial' => 'GC-\d+'])]
    #[DashboardEndpoint(summary: 'Mostrar el PIN de una tarjeta: deja registro de quién y cuándo', tag: 'Market')]
    public function pin(string $serial): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(['pin' => ($this->reveal)($serial, $this->actor())]));
    }

    #[Route('/{serial}/cancel', name: 'market_gift_cards_cancel', methods: ['POST'], requirements: ['serial' => 'GC-\d+'])]
    #[DashboardEndpoint(summary: 'Anular una tarjeta emitida o activa, con motivo', tag: 'Market', requestDto: CancelGiftCardDto::class, responseDto: GiftCardOutDto::class)]
    public function cancel(string $serial, CancelGiftCardDto $dto): JsonResponse
    {
        return $this->guard(function () use ($serial, $dto): JsonResponse {
            ($this->cancel)($serial, (string) $dto->getReason(), $this->actor());

            return new JsonResponse(GiftCardOutDto::fromDetails(($this->get)($serial)));
        });
    }

    /**
     * @param callable(): \App\Market\GiftCards\Application\IssuedBatch $action
     */
    private function issued(callable $action): JsonResponse
    {
        return $this->guard(function () use ($action): JsonResponse {
            $issued = $action();

            return new JsonResponse([
                'batch' => ['id' => $issued->batch->id(), 'code' => $issued->batch->code(), 'quantity' => $issued->batch->quantity()],
                'serials' => array_map(static fn ($c) => $c->serial(), $issued->cards),
            ], Response::HTTP_CREATED);
        });
    }

    private function actor(): GiftCardActor
    {
        $user = $this->security->getUser();

        return new GiftCardActor($user instanceof User ? $user->getId() : null, $user?->getUserIdentifier() ?? 'sistema');
    }

    /**
     * @param callable(): JsonResponse $action
     */
    private function guard(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (GiftCardNotFound $e) {
            return $this->error('GIFT_CARD_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (GiftCardAlreadyRedeemed $e) {
            return $this->error('GIFT_CARD_ALREADY_REDEEMED', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (GiftCardTransitionInvalid $e) {
            return $this->error('GIFT_CARD_TRANSITION_INVALID', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (GiftCardProductInvalid $e) {
            return $this->error('GIFT_CARD_PRODUCT_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidGiftCard $e) {
            return $this->error('INVALID_GIFT_CARD', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
