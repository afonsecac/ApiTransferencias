<?php

namespace App\OpenApi;

use App\DTO\IInput;
use App\Exception\MyCurrentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InputValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();

        if (!$argumentType || !is_subclass_of($argumentType, IInput::class)) {
            return [];
        }

        $body = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($body)) {
            $body = [];
        }

        if ($request->headers->has('X-Environment-Id')) {
            $body['environmentId'] = (int) $request->headers->get('X-Environment-Id');
        }
        if ($request->headers->has('X-Account-Id')) {
            $body['accountId'] = (int) $request->headers->get('X-Account-Id');
        }
        if ($request->headers->has('X-Environment-Type')) {
            $body['environmentType'] = $request->headers->get('X-Environment-Type');
        }

        try {
            $dto = $this->serializer->deserialize(json_encode($body), $argumentType, 'json', []);
        } catch (NotEncodableValueException) {
            throw new MyCurrentException('INVALID_JSON_BODY', 'Invalid JSON body', 400);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw new ValidationFailedException($dto, $violations);
        }

        return [$dto];
    }
}