# CLAUDE.md — Guía de desarrollo ApiTransferencias

## Stack

PHP / Symfony con API Platform y Doctrine ORM. Todos los endpoints del dashboard viven bajo el prefijo global `/dashboard/api` (definido en `config/routes.yaml`). La mayoría requieren `ROLE_ADMIN` o `ROLE_SYSTEM_USER`.

---

## Buenas prácticas

### 1. Los endpoints POST/PUT/PATCH reciben un DTO, no el Request directamente

Nunca leas `$request->toArray()` ni `$request->request->all()` en un controlador para manejar el body. Crea un DTO dedicado.

#### DTOs de entrada vs. salida

| Tipo | Implementa `IInput` | Ubicación | Propósito |
|---|---|---|---|
| **Entrada** | Sí | `src/DTO/` | Recibir y validar el body de una petición |
| **Salida** | No | `src/DTO/Out/` | Estructurar la respuesta que se devuelve al cliente |

Los DTOs de entrada implementan `IInput` para que el `InputValueResolver` los hidrate automáticamente. Los DTOs de salida son estructuras de presentación simples — no necesitan ser deserializados desde el request, por lo que **no** deben implementar `IInput`.

```php
// ✅ DTO de entrada — implementa IInput
// src/DTO/CreateClientPackageDto.php
class CreateClientPackageDto implements IInput { ... }

// ✅ DTO de salida — NO implementa IInput
// src/DTO/Out/ClientDto.php
class ClientDto { ... }
```

El `InputValueResolver` (`src/OpenApi/InputValueResolver.php`) detecta automáticamente cualquier argumento de controlador que implemente `IInput` y deserializa el body JSON en él. No es necesario ningún atributo extra en el método.

```php
// El resolver inyecta el DTO de entrada automáticamente
public function createPackage(CreateClientPackageDto $dto): JsonResponse
```

---

### 2. Los DTOs deben tener descriptores (`#[Assert\]`)

Declara las restricciones de validación directamente sobre las propiedades del DTO usando los atributos de Symfony Validator. No valides manualmente con `if (empty(...))` en el controlador.

```php
use Symfony\Component\Validator\Constraints as Assert;

class CreateClientPackageDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $tenantId;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency;
}
```

Descriptores de uso habitual:

| Caso | Constraint |
|---|---|
| Campo requerido (int/float) | `#[Assert\NotNull]` |
| Entero positivo | `#[Assert\Positive]` |
| Longitud máxima de string | `#[Assert\Length(max: N)]` |
| Longitud exacta (ej. moneda) | `#[Assert\Length(exactly: 3)]` |
| Número ≥ 0 | `#[Assert\PositiveOrZero]` |
| String no vacío | `#[Assert\NotBlank]` |

El controlador ejecuta la validación con `ValidatorInterface`:

```php
$violations = $this->validator->validate($dto);
if (count($violations) > 0) {
    $details = [];
    foreach ($violations as $v) {
        $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
    }
    return $this->json(
        ['error' => ['message' => 'Validation failed', 'details' => $details]],
        Response::HTTP_BAD_REQUEST
    );
}
```

---

### 3. La lógica de negocio vive en servicios, no en controladores

El controlador solo gestiona el contrato HTTP: valida el DTO, delega al servicio, convierte la respuesta. Nunca accedas al `EntityManager` en el controlador para construir o mutar entidades.

**Estructura esperada:**

```
Controlador
  └─ Valida DTO (ValidatorInterface)
  └─ Llama al servicio (única línea de negocio)
  └─ Convierte excepciones en respuestas HTTP
  └─ Serializa y devuelve

Servicio
  └─ Resuelve entidades relacionadas (Account, Environment, etc.)
  └─ Construye / muta la entidad
  └─ persist + flush
  └─ Lanza MyCurrentException si algo no existe o es inválido
```

Ejemplo de controlador correcto:

```php
try {
    $cp = $this->packageService->create($dto);
} catch (MyCurrentException $e) {
    return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
}

return $this->json($this->serializeClientPackageDetail($cp), Response::HTTP_CREATED);
```

---

### 4. Los errores de dominio se comunican con `MyCurrentException`

Cuando un servicio detecta un error de dominio (entidad no encontrada, estado inválido, etc.) lanza `MyCurrentException` (`src/Exception/MyCurrentException.php`) con un `codeWork` descriptivo y el código HTTP como tercer argumento.

```php
throw new MyCurrentException('TENANT_NOT_FOUND', 'Tenant not found', 404);
throw new MyCurrentException('PRICE_PACKAGE_NOT_FOUND', 'Price package not found', 404);
```

El controlador captura la excepción y la convierte en una respuesta JSON con el código HTTP adecuado. No uses excepciones HTTP de Symfony (`NotFoundHttpException`, etc.) en los servicios.

---

### 5. Los servicios extienden `CommonService`

Los servicios existentes extienden `App\Service\CommonService`, que inyecta 9 dependencias compartidas via constructor. No redeclares esas dependencias en el servicio hijo.

```php
class CommunicationPackageService extends CommonService
{
    // $this->em, $this->security, $this->serializer disponibles sin constructor adicional
}
```

**Deuda técnica conocida:** `CommonService` inyecta `MailerInterface`, `UserPasswordHasherInterface`, `SysConfigRepository`, etc. aunque la mayoría de servicios no los necesitan. Para servicios nuevos que requieran pocas dependencias, preferir inyección directa sin extender `CommonService`:

```php
// Preferido para servicios nuevos con pocas dependencias
class MiServicio
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}
}
```

---

### 6. Frontera entre API Platform y controladores custom

**Regla:**
- Los recursos consumidos por clientes externos (app móvil, integraciones) usan `#[ApiResource]` con State Providers/Processors en `src/State/`. Viven bajo `/api/*`.
- La gestión administrativa (dashboard) usa controladores custom bajo `/dashboard/api/*`.

No mezcles los dos enfoques para el mismo recurso. Si un endpoint de dashboard necesita lógica compleja de filtrado o serialización, impleméntalo en el controlador; no añadas `#[ApiResource]` a entidades que ya tienen controladores dashboard dedicados.

---

### 7. Migraciones: siempre con descripción y `IF NOT EXISTS` / `IF EXISTS`

```php
public function getDescription(): string
{
    return 'Descripción clara del cambio';  // obligatorio
}

public function up(Schema $schema): void
{
    // Usa IF NOT EXISTS / IF EXISTS para que las migraciones sean idempotentes
    $this->addSql('CREATE INDEX IF NOT EXISTS idx_name ON table (col1, col2)');
    $this->addSql('ALTER TABLE foo ADD COLUMN IF NOT EXISTS bar INT');
}
```

Para operaciones destructivas (`DROP`, `ALTER TABLE ... DROP COLUMN`), envuelve en una transacción explícita o asegúrate de que Doctrine Migrations tenga `transactional: true` en `config/packages/doctrine_migrations.yaml`.

---

### 8. Campos JSON en entidades: validar en el DTO

Los campos `array` persistidos como JSON (`benefits`, `tags`, `service`, `destination`, `validity`, `dataInfo`) no tienen validación de estructura a nivel de base de datos. Asegura su integridad validando en el DTO de entrada:

```php
// En el DTO, marca arrays opcionales pero con tipos esperados en el docblock
/** @var array{name: string, value: float}[]|null */
#[Assert\Valid]   // valida recursivamente si los elementos tienen sus propias constraints
protected ?array $benefits;
```

Hasta que los campos JSON se migren a embeddables o Value Objects, esta validación en DTO es la única barrera de integridad.

---

### 9. Estructura de un DTO `IInput` completo

Sigue el patrón de `BalanceInDto` (`src/DTO/BalanceInDto.php`):
- Propiedades `protected` y nullables
- Constructor con todos los parámetros opcionales (default `null`)
- Getter y setter por cada propiedad
- Atributos `#[Assert\]` sobre las propiedades que lo requieran

```php
class MiDto implements IInput
{
    #[Assert\NotNull]
    protected ?int $campoRequerido;

    #[Assert\Length(max: 255)]
    protected ?string $campoOpcional;

    public function __construct(
        ?int $campoRequerido = null,
        ?string $campoOpcional = null,
    ) {
        $this->campoRequerido = $campoRequerido;
        $this->campoOpcional  = $campoOpcional;
    }

    public function getCampoRequerido(): ?int { return $this->campoRequerido; }
    public function setCampoRequerido(?int $v): void { $this->campoRequerido = $v; }

    public function getCampoOpcional(): ?string { return $this->campoOpcional; }
    public function setCampoOpcional(?string $v): void { $this->campoOpcional = $v; }
}
```

---

## Archivos de referencia

| Propósito | Archivo |
|---|---|
| Interfaz marcadora de DTOs de entrada | `src/DTO/IInput.php` |
| Resolver que hidrata DTOs desde el body | `src/OpenApi/InputValueResolver.php` |
| Ejemplo de DTO de entrada completo | `src/DTO/BalanceInDto.php` |
| Ejemplo de DTO con descriptores Assert | `src/DTO/CreateClientPackageDto.php` |
| Excepción de dominio estándar | `src/Exception/MyCurrentException.php` |
| Clase base de servicios | `src/Service/CommonService.php` |
| Ejemplo de controlador con DTO + servicio + excepción | `src/Controller/DashboardClientPackagesController.php` |
| Ejemplo de migración con índices compuestos | `migrations/Version20260428140304.php` |
