<?php

namespace App\Serializer;

use App\Message\SaleRechargeMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

class SendMessageSerializer implements SerializerInterface
{
    public function __construct(
        private readonly SymfonySerializerInterface $serializer
    ) {}

    /**
     * @inheritDoc
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        // TODO: Implement decode() method.
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