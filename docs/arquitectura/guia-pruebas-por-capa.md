# Guía de pruebas por capa (código nuevo)

Complemento de [ADR-001](adr-001-monolito-modular-hexagonal.md) y de la regla 12 (TDD) de `CLAUDE.md`. Aplica a `src/Market/<Contexto>` y `src/Delivery`.

## Qué prueba cada capa

| Capa | Base de la prueba | Qué se usa | Qué no se usa |
|---|---|---|---|
| `Domain` | `PHPUnit\Framework\TestCase` | Objetos reales | Kernel, base de datos, mocks de infraestructura |
| `Application` | `PHPUnit\Framework\TestCase` | Puertos **en memoria** (clases reales, no mocks) | Doctrine, Messenger, HTTP |
| `Infrastructure` | `App\Tests\Functional\FunctionalTestCase` | Postgres real dentro de una transacción que se revierte | Mocks del `EntityManager` o de la conexión |

Ejemplos ejecutables de las tres capas en `tests/Examples/Pedido/`.

## Criterio de «hecho» de un contexto

- Un test con base de datos real **por repositorio** y **por consumidor de evento**.
- Cada caso de uso tiene su prueba de `Application` con puertos en memoria.
- Cada evento de dominio tiene su test de contrato.
- Se escribe primero el test y se ve fallar (regla 12).

## Test de contrato por evento

Un evento publicado es una API entre contextos: añadir, quitar o cambiar el tipo de un campo puede romper a los consumidores o a los mensajes que ya están en cola. El trait `App\Tests\Support\AssertsEventContract` comprueba, dentro de un `KernelTestCase`:

```php
final class OrderPlacedTest extends KernelTestCase
{
    use AssertsEventContract;

    public function testContract(): void
    {
        $this->assertEventContract(new OrderPlaced(/* ... */));   // forma == tests/Contracts/OrderPlaced.contract.json
        $this->assertEventRoundTrip(new OrderPlaced(/* ... */));  // codificar y decodificar da el mismo evento
    }
}
```

- Serializa con el mismo serializador del transporte de Messenger, así que prueba lo que viaja de verdad.
- El contrato guarda la clase y la forma (campos ordenados y su tipo) en `tests/Contracts/<Evento>.contract.json`.
- Si el evento cambia de forma, el test falla. Para aceptar el cambio a propósito: `UPDATE_CONTRACTS=1 php bin/phpunit --filter <Test>` y **revisar el diff** del contrato antes de commitearlo; dice a los demás qué cambia en el bus.

## Convenciones de los eventos

- `final readonly class` con propiedades públicas; solo identificadores y datos inmutables, nunca entidades.
- `eventId` como UUID en texto y `occurredAt` con precisión de segundos (el serializador no conserva microsegundos).
- Consumidores idempotentes por `eventId`.

## Qué impide el CI

- `Domain` no importa Symfony, Doctrine ni `App\Service`; `Application` no importa `Infrastructure`; un contexto no importa la `Infrastructure` de otro; el código nuevo no extiende `CommonService`. Lo comprueba phpat dentro de PHPStan (`tests/Architecture/MarketLayersTest.php`).
