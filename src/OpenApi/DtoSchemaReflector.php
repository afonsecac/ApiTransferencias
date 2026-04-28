<?php

namespace App\OpenApi;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

final class DtoSchemaReflector
{
    private array $visited = [];

    /**
     * Genera el schema JSON para una clase DTO y registra los schemas de subtipos
     * en $schemas (componentes de OpenAPI).
     *
     * @param class-string      $className
     * @param \ArrayObject<string, mixed> $schemas  referencia mutable a openApi.components.schemas
     * @param class-string|null $itemDto   clase del item cuando responseDto = PaginatedListOutDto
     */
    public function reflect(string $className, \ArrayObject $schemas, ?string $itemDto = null): array
    {
        if (isset($this->visited[$className])) {
            return ['$ref' => '#/components/schemas/' . (new \ReflectionClass($className))->getShortName()];
        }
        $this->visited[$className] = true;

        if (!class_exists($className)) {
            return ['type' => 'object'];
        }

        $rc = new \ReflectionClass($className);
        $allProps = [];

        // Incluir propiedades heredadas (para ClientPackageDetailOutDto → ClientPackageOutDto)
        $class = $rc;
        while ($class !== false) {
            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $prop) {
                if (!isset($allProps[$prop->getName()])) {
                    $allProps[$prop->getName()] = $prop;
                }
            }
            $class = $class->getParentClass();
        }

        /** @var array<string, array<string, mixed>> $schemaProps */
        $schemaProps = [];
        /** @var list<string> $required */
        $required = [];

        foreach ($allProps as $propName => $prop) {
            $oaAttrs = $prop->getAttributes(OAProperty::class);
            if ($oaAttrs) {
                $oaProp = $oaAttrs[0]->newInstance();
                if ($oaProp->schema !== null) {
                    $propSchema = $oaProp->schema;
                    if ($oaProp->description !== null) {
                        $propSchema['description'] = $oaProp->description;
                    }
                    $schemaProps[$propName] = $propSchema;
                    continue;
                }
                // Solo description: continúa la inferencia normal y aplica después
            }

            $type = $prop->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();
            $nullable  = $type->allowsNull();

            $propSchema = $this->typeToSchema($typeName, $nullable, $schemas, $propName === 'results' ? $itemDto : null);

            foreach ($prop->getAttributes() as $attr) {
                $this->applyConstraint($attr, $propSchema, $required, $propName);
            }

            // Aplicar description si está en OAProperty sin schema override
            if ($oaAttrs) {
                $oaProp = $oaAttrs[0]->newInstance();
                if ($oaProp->description !== null) {
                    $propSchema['description'] = $oaProp->description;
                }
            }

            if (!$nullable && !$prop->hasDefaultValue()) {
                if (!in_array($propName, $required)) {
                    $required[] = $propName;
                }
            }

            $schemaProps[$propName] = $propSchema;
        }

        /** @var \ArrayObject<string, mixed> $schema */
        $schema = new \ArrayObject(['type' => 'object', 'properties' => $schemaProps]);
        if (!empty($required)) {
            $schema['required'] = array_values(array_unique($required));
        }

        $schemaName = $rc->getShortName();
        $schemas[$schemaName] = $schema;

        return ['$ref' => '#/components/schemas/' . $schemaName];
    }

    public function resetVisited(): void
    {
        $this->visited = [];
    }

    private function typeToSchema(string $typeName, bool $nullable, \ArrayObject $schemas, ?string $itemDto): array
    {
        $s = match (true) {
            $typeName === 'int'   => ['type' => 'integer'],
            $typeName === 'float' => ['type' => 'number'],
            $typeName === 'string' => ['type' => 'string'],
            $typeName === 'bool'  => ['type' => 'boolean'],
            $typeName === 'array' && $itemDto !== null => [
                'type'  => 'array',
                'items' => $this->reflect($itemDto, $schemas),
            ],
            $typeName === 'array' => ['type' => 'array', 'items' => ['type' => 'object']],
            $typeName === \DateTimeImmutable::class => ['type' => 'string', 'format' => 'date-time'],
            class_exists($typeName) => $this->reflect($typeName, $schemas),
            default => ['type' => 'string'],
        };

        if ($nullable && isset($s['type'])) {
            $s['nullable'] = true;
        }

        return $s;
    }

    private function applyConstraint(\ReflectionAttribute $attr, array &$schema, array &$required, string $propName): void
    {
        $name = $attr->getName();

        if ($name === Assert\NotNull::class || $name === Assert\NotBlank::class) {
            if (!in_array($propName, $required)) {
                $required[] = $propName;
            }
            if ($name === Assert\NotBlank::class) {
                $schema['minLength'] = 1;
            }
        } elseif ($name === Assert\Positive::class) {
            $schema['minimum'] = 1;
        } elseif ($name === Assert\PositiveOrZero::class) {
            $schema['minimum'] = 0;
        } elseif ($name === Assert\Length::class) {
            $args = $attr->getArguments();
            if (isset($args['max'])) {
                $schema['maxLength'] = $args['max'];
            } elseif (isset($args[0])) {
                $schema['maxLength'] = $args[0];
            }
            if (isset($args['min'])) {
                $schema['minLength'] = $args['min'];
            }
            if (isset($args['exactly'])) {
                $schema['minLength'] = $args['exactly'];
                $schema['maxLength'] = $args['exactly'];
            }
        }
    }
}
