<?php

namespace App\Serializer;

use App\Message\SaleRechargeMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SendMessageSerializer implements SerializerInterface
{
    public function __construct(
        private readonly NormalizerInterface $serializer
    ) {}

    /**
     * @inheritDoc
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \LogicException('The decode() method is not implemented.');
    }

    /**
     * @inheritDoc
     */
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        if ($message instanceof SaleRechargeMessage) {
            return $this->serializer->normalize($message, null, [
                'groups' => [
                    'comSales:create'
                ]
            ]);
        } else {
            throw new \Exception('Unsupported message class');
        }
    }
}