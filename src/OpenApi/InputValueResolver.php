<?php

namespace App\OpenApi;

use App\DTO\IInput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;

final class InputValueResolver implements ValueResolverInterface
{

    public function __construct(
        private readonly SerializerInterface $serializer
    )
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();

        if (!$argumentType || !is_subclass_of($argumentType, IInput::class)) {
            return [];
        }

        return [
            $this->serializer->deserialize(
                $request->getContent(),
                $argument->getType(),
                'json',
                []
            ),
        ];
    }

}