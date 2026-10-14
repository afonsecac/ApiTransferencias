<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un campo fiscal del body de alta o edición de un registro fiscal. Anidado en los DTOs de entrada.
 */
class FiscalFieldDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    #[Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/', message: 'La clave admite minúsculas, números y guion bajo, empezando por letra.')]
    protected ?string $key;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $label;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['text', 'number', 'date', 'select'])]
    protected ?string $type;

    protected ?bool $required;

    #[Assert\Length(max: 255)]
    protected ?string $pattern;

    #[Assert\Length(max: 255)]
    protected ?string $helpText;

    /** @var string[]|null */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 120)])]
    protected ?array $options;

    protected ?bool $active;

    #[Assert\PositiveOrZero]
    protected ?int $position;

    /**
     * @param string[]|null $options
     */
    public function __construct(
        ?string $key = null,
        ?string $label = null,
        ?string $type = null,
        ?bool $required = null,
        ?string $pattern = null,
        ?string $helpText = null,
        ?array $options = null,
        ?bool $active = null,
        ?int $position = null,
    ) {
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
        $this->required = $required;
        $this->pattern = $pattern;
        $this->helpText = $helpText;
        $this->options = $options;
        $this->active = $active;
        $this->position = $position;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(?bool $required): void
    {
        $this->required = $required;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): void
    {
        $this->pattern = $pattern;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): void
    {
        $this->helpText = $helpText;
    }

    /**
     * @return string[]|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    /**
     * @param string[]|null $options
     */
    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }
}
