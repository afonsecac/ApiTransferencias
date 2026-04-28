<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\RotateTokenProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/accounts/rotate-token',
            processor: RotateTokenProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['rotate-token:read']],
    security: "is_granted('ROLE_REM_API_USER')",
)]
class RotateToken
{
    #[ApiProperty(description: 'New API token (base64-encoded). Use it as the X-AUTH-TOKEN header.')]
    #[Groups(['rotate-token:read'])]
    private ?string $token = null;

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }
}
