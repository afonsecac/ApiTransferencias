# Tests funcionales (KernelTestCase contra Postgres real)

## Por qué `DATABASE_URL` hay que pasarlo explícito

`docker-compose.yaml` fija `DATABASE_URL` como variable de entorno real en
`php-fpm`/`worker` (apuntando a la BD `app`, la de desarrollo con datos
reales). Symfony's Dotenv nunca sobreescribe una variable de entorno real ya
definida, así que `.env.test`/`.env.test.local` no tienen efecto dentro de
esos contenedores. Para no correr tests funcionales contra la BD de
desarrollo, `DATABASE_URL` se debe pasar explícito en cada invocación.

`config/packages/doctrine.yaml` añade `dbname_suffix: '_test'` en
`when@test`, así que la URL debe apuntar a la BD **base** (`app`), no a
`app_test` directamente — Doctrine añade el sufijo solo.

## Preparar la BD de test (una sola vez)

```bash
docker compose exec -T database psql -U app -d app -c "CREATE DATABASE app_test OWNER app;"

docker compose exec -T -e APP_ENV=test \
  -e DATABASE_URL='postgresql://app:app@database:5432/app?serverVersion=18&charset=utf8' \
  php-fpm php bin/console doctrine:migrations:migrate --no-interaction
```

Repetir la migración cada vez que se añadan migraciones nuevas.

## Correr los tests

```bash
docker compose exec -T -e APP_ENV=test \
  -e DATABASE_URL='postgresql://app:app@database:5432/app?serverVersion=18&charset=utf8' \
  php-fpm php bin/phpunit
```

## Aislamiento entre tests

`tests/Functional/FunctionalTestCase.php` envuelve cada test en una
transacción que se revierte en `tearDown()` (Doctrine DBAL 4 anida con
savepoints cualquier `beginTransaction()`/`commit()` interno del código de
negocio, así que un solo `rollBack()` exterior deshace todo). No se usa
`dama/doctrine-test-bundle`: la versión compatible con `doctrine/dbal ^4`
(v8.x) requiere la API de extensiones de PHPUnit 10+, y este proyecto fija
PHPUnit 9.6 vía `symfony/phpunit-bridge`. El wrapper manual cubre el mismo
caso de uso sin esa dependencia.

Las entidades con `id` autogenerado usan secuencias de Postgres, que **no**
son transaccionales — sus valores no se reinician con el rollback, así que
cada test obtiene IDs frescos aunque la fila anterior se haya revertido.

`tests/Functional/Provider/ProviderFunctionalTestCase.php` añade fixtures
reales (Client, Environment, Account, CommunicationProduct/PricePackage/
ClientPackage, ClientProviderRouting) para los tests del enrutado
multi-proveedor.
