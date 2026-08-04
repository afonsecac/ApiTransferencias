# Despliegue — ApiTransferencias

Guía paso a paso para desplegar y operar la API en staging y producción. Ambos
entornos corren en VPS independientes con Docker Compose detrás de Traefik
(TLS automático vía Let's Encrypt).

## 1. Topología

| | Staging | Producción |
|---|---|---|
| Rama | `develop` | `master` |
| Dominio API | `staging-api.comremit.com` | `api.prod.comremit.com` (+ alias `api-tx.sendmundo.com`) |
| Deploy automático | `.github/workflows/deploy-staging.yaml` (push a `develop`) | `.github/workflows/deploy-prod.yaml` (push a `master`) |
| Base de datos | `postgres:18-alpine`, 1 réplica | `postgres:18-alpine`, 1 réplica |
| Workers | 1 contenedor (`worker`) consumiendo todas las colas | 6 contenedores, uno por cola (`worker-notifications`, `worker-clients`, `worker-packages`, `worker-check-status`, `worker-balance`, `worker-scheduler`) |
| Extra | `mailcatcher` (captura correo, no envía real) | — (SMTP real vía `MAILER_DSN`) |
| Recursos por contenedor | Límites bajos (php-fpm: 0.5 CPU / 256M) | Límites más altos (php-fpm: 1.0 CPU / 512M) |

Ambos entornos comparten la misma base de compose (`docker-compose.vps.yaml`:
Traefik, `php-fpm`, `nginx`, `database`, `rabbitmq`) y cada uno la extiende con
su propio overlay (`docker-compose.vps.staging.yaml` /
`docker-compose.vps.prod.yaml`). El pool PHP-FPM dedicado del stream SSE de
notificaciones (`docker/php-fpm/stream.conf`) y la imagen de `nginx` se
construyen igual en ambos, sin overlay propio.

## 2. Prerrequisitos

### 2.1 Acceso SSH al VPS

Cada entorno tiene su propio host, usuario, puerto y llave — **no son
intercambiables entre sí ni con otros servidores del proyecto**. Pide estos
datos al responsable de infraestructura; no están en el repositorio (viven
como GitHub Secrets del entorno `staging`/`production`, ver [§7](#7-secrets-de-github-actions)).

```bash
ssh -p <PUERTO> -i <RUTA_LLAVE_PRIVADA> deploy@<HOST>
```

**El firewall del servidor solo permite SSH desde IPs en su allowlist.** Si la
conexión da *timeout* (no *connection refused*) en el puerto SSH, casi
siempre es esto — no un problema de la aplicación. Ver
[§8.1](#81-ssh-no-conecta-timeout-puro).

### 2.2 Docker en el servidor

```bash
docker --version          # Docker Engine
docker compose version    # Compose v2 (plugin, no docker-compose standalone)
```

Si no está instalado: `curl -fsSL https://get.docker.com | sh`.

### 2.3 Clonar el repositorio (solo la primera vez)

```bash
git clone git@github.com:afonsecac/ApiTransferencias.git /opt/api-transferencias
cd /opt/api-transferencias
git checkout <develop|master>
```

`deploy.sh` asume que el proyecto vive en esa ruta (o la que indique
`STAGING_APP_PATH`/`PROD_APP_PATH` en los workflows) y que ya está en la rama
correcta.

## 3. Primera instalación en un VPS nuevo

```bash
cd /opt/api-transferencias

# 1. Crear el archivo de variables de entorno a partir del ejemplo
cp .env.vps.staging.example .env.vps   # o .env.vps.prod.example en prod
nano .env.vps                          # completar TODOS los CHANGE_ME
```

Variables que **hay que generar**, no dejar con el valor de ejemplo:

| Variable | Cómo generarla |
|---|---|
| `APP_SECRET` | `openssl rand -hex 32` |
| `SYS_CONFIG_ENCRYPTION_KEY` | `openssl rand -hex 32` |
| `POSTGRES_PASSWORD` | contraseña fuerte (32+ caracteres en prod) |
| `RABBITMQ_PASSWORD` | contraseña fuerte; si tiene caracteres especiales, codificarlos en `MESSENGER_TRANSPORT_DSN` (`+`→`%2B`, `/`→`%2F`, `=`→`%3D`, `@`→`%40`) |
| `JWT_MEMORY_KEY` / `JWT_PHRASE_KEY` | cadenas aleatorias largas |
| `TRAEFIK_DASHBOARD_AUTH` | `htpasswd -nB admin` (el `$` del hash se escapa como `$$` en el archivo) |
| `ADMIN_IP_ALLOWLIST` | IPs/CIDR con acceso al dashboard de Traefik y RabbitMQ management; sin definir, solo loopback |

> `deploy.sh` valida automáticamente que ninguna variable lleve comillas
> literales envolventes (`check_env_quoting`) — Docker Compose las pasaría tal
> cual al runtime y rompería credenciales como `MAILER_DSN`. Si el script se
> detiene con un aviso de "comillas", corrige `.env.vps` antes de continuar.

```bash
# 2. Crear el volumen externo de Postgres (una sola vez; el nombre debe
#    coincidir con PG_DATA_VOLUME o el default `apitransferencias_database_data`)
docker volume create apitransferencias_database_data

# 3. Instalación completa: build + up + migraciones + cache + healthcheck
./deploy.sh staging --setup     # o: ./deploy.sh prod --setup
```

`--setup` hace, en orden: `build` → `up -d` → espera a que Postgres esté listo
(`pg_isready`, hasta 30 intentos) → `doctrine:migrations:migrate` →
`cache:clear` → verifica que la ruta `health_live` esté registrada, y termina
imprimiendo las URLs del entorno (API, docs, health, Traefik, RabbitMQ,
mailcatcher en staging).

## 4. Despliegue estándar

### 4.1 Automático (recomendado)

Un `git push` a `develop` (staging) o `master` (producción) dispara el
workflow correspondiente, que por SSH:

1. Backup de la base de datos (`pg_dump` comprimido en `backups/<env>/`).
2. `git fetch` + `checkout` + `pull` de la rama del entorno.
3. `docker compose build` (imágenes `php-fpm`/`nginx`).
4. **Solo en producción**: levanta `database`+`rabbitmq` con `--wait` y corre
   las migraciones *antes* de recrear la app, para no dejar una ventana con
   contenedores nuevos y esquema viejo.
5. `docker compose up -d --remove-orphans --force-recreate` (recrea **todos**
   los servicios del stack, incluido Traefik, aunque no hayan cambiado — ver
   [§8.2](#82-un-cambio-que-no-toca-traefik-lo-recrea-igual)).
6. En staging, las migraciones corren *después* del `up` (con
   `--allow-no-migration`, para no fallar si no hay ninguna pendiente);
   `cache:clear`.
7. `docker image prune -f` y, en prod, purga de backups más allá de los 30
   últimos.
8. Health check: `curl -sf http://localhost/health/ready` — **ver la
   advertencia en** [§8.2](#82-un-cambio-que-no-toca-traefik-lo-recrea-igual)
   sobre por qué esto puede dar falso positivo.
9. Publica el resultado como *commit status* (`deploy/staging` /
   `deploy/production`) en GitHub.

No hace falta ejecutar nada a mano: basta con que el push a la rama
correspondiente pase la CI (`ci.yaml`: tests, PHPStan, lint).

### 4.2 Manual (`deploy.sh`)

Útil para redeploys puntuales sin esperar al pipeline, o cuando no hay acceso
a GitHub Actions en ese momento:

```bash
cd /opt/api-transferencias
./deploy.sh staging              # deploy estándar (sin forzar migraciones)
./deploy.sh prod --migrate        # deploy + migraciones explícitas
```

`deploy` (sin acción) hace `git pull` → `build` → backup (solo prod) → migra
→ detiene los workers con gracia (`stop --timeout 60`) → `up -d
--force-recreate` → `cache:clear` → `docker image prune`.

`--migrate` es igual pero adelanta las migraciones antes de recrear la app
(mismo orden que el workflow de producción) — usarlo cuando la migración
necesita que el contenedor viejo siga sirviendo tráfico mientras se aplica.

## 5. Operación diaria

```bash
./deploy.sh staging --status              # docker compose ps
./deploy.sh staging --logs                # logs de todos los servicios (-f)
./deploy.sh staging --logs php-fpm        # logs de un servicio concreto
./deploy.sh staging --shell               # shell interactivo en php-fpm
./deploy.sh staging --console 'doctrine:migrations:status'
./deploy.sh prod --backup                 # backup manual de la BD
./deploy.sh prod --restore <archivo.sql.gz>
./deploy.sh staging --restart             # docker compose restart (todo el stack)
./deploy.sh staging --stop                # docker compose down
```

### 5.1 Rollback

```bash
./deploy.sh prod --rollback
```

Vuelve a `HEAD~1`, reconstruye, detiene workers con gracia y redespliega. Deja
el repo en *detached HEAD* — para volver a seguir la rama:
`git checkout master` (o `develop`).

> No hace `doctrine:migrations:migrate:down` automáticamente. Si el commit
> revertido incluía una migración, hay que evaluar manualmente si hace falta
> revertirla también (`doctrine:migrations:migrate <version_anterior>`).

## 6. Verificación post-deploy

```bash
curl -sf https://<dominio>/health/live     # liveness — el proceso responde
curl -sf https://<dominio>/health/ready    # readiness — DB/cola alcanzables
curl -s  https://<dominio>/dashboard/api/docs.json | jq '.paths | keys'
./deploy.sh <env> --status                 # todos los servicios "Up" y "healthy"
./deploy.sh <env> --console 'doctrine:migrations:status'   # sin migraciones pendientes
```

Si el endpoint público no autenticado funciona pero algo específico falla,
revisar logs del servicio concreto (`--logs php-fpm`, `--logs nginx`,
`--logs worker`) antes de asumir que es un problema de infraestructura.

## 7. Secrets de GitHub Actions

Configurados en **Settings → Environments → `staging` / `production`** del
repositorio (no en Secrets globales, para poder exigir aprobación manual si
se configura *environment protection*):

| Secret | Entorno | Uso |
|---|---|---|
| `STAGING_HOST` / `PROD_HOST` | ambos | IP o dominio del VPS |
| `STAGING_USER` / `PROD_USER` | ambos | usuario SSH (`deploy`) |
| `STAGING_SSH_KEY` / `PROD_SSH_KEY` | ambos | llave privada (formato OpenSSH) |
| `STAGING_SSH_PORT` / `PROD_SSH_PORT` | ambos | puerto SSH; default `22` si no se define |
| `STAGING_APP_PATH` / `PROD_APP_PATH` | ambos | ruta del repo en el VPS; default `/opt/api-transferencias` |

El puerto real casi nunca es el default — confirmarlo aquí antes de intentar
una conexión manual, en vez de probar puertos al azar.

## 8. Troubleshooting

### 8.1 SSH no conecta (timeout puro)

Si `ssh -p <puerto> ...` se queda colgado hasta agotar el timeout (no da
`Connection refused` ni pide contraseña), **no es el puerto equivocado**: es
el firewall del servidor (`ufw`/`iptables`/grupo de seguridad del proveedor)
descartando el paquete en silencio para IPs no permitidas. Probar otro puerto
da el mismo resultado exacto porque el filtro suele ser por IP, no por
puerto.

Diagnóstico rápido:
```bash
curl -s https://api.ipify.org        # tu IP de salida actual
```
Pide a quien administre el VPS que la añada al allowlist de SSH (o usar una
red/VPN ya permitida) antes de seguir intentando.

### 8.2 Un cambio que no toca Traefik lo recrea igual

`docker compose up -d --force-recreate` recrea **todo** el stack del archivo
compose, no solo los servicios cuya imagen cambió. Un cambio en `php-fpm` o
`nginx` recrea también `traefik`, `database` y `rabbitmq` aunque su
configuración no se haya tocado.

Esto ya causó una caída total de staging (2026-07-04): se fijó
`image: traefik:latest` a una versión concreta en el compose base, el
`force-recreate` disparó el *pull* de esa imagen nueva, y la versión real que
corría en el servidor no era compatible con la config/labels existentes.
Traefik siguió respondiendo TLS pero **dejó de cargar toda la configuración
dinámica**: todos los routers (API, RabbitMQ, su propio dashboard) devolvían
`404 page not found` en texto plano.

- **No re-pinear `traefik:latest` a una versión fija sin antes confirmar,
  en el propio servidor, qué versión está corriendo** (`docker inspect
  traefik` / `docker compose exec traefik traefik version`) y pinear
  exactamente esa.
- El health check del workflow (`curl -sf http://localhost/health/ready`)
  **puede dar falso positivo** en este escenario: solo recibe el redirect
  80→443 y sale con éxito sin comprobar que la app responda de verdad.
- Si todos los hosts (incluido el dashboard de Traefik) dan 404 en texto
  plano con TLS válido, no es la app ni CORS — es Traefik sin routers.

**Pendiente conocido:** el `ADMIN_IP_ALLOWLIST` de `.env.vps` está declarado
pero **todavía no aplicado** como middleware de Traefik sobre los routers del
dashboard de Traefik y RabbitMQ management en `docker-compose.vps.yaml` — hoy
esos paneles quedan expuestos a quien resuelva el subdominio, no solo a la
IP de la allowlist. Falta añadir el middleware `ipallowlist` con la sintaxis
correcta para la versión de Traefik en uso.

### 8.3 Migración pendiente tras un deploy

```bash
./deploy.sh <env> --console 'doctrine:migrations:status'
./deploy.sh <env> --console 'doctrine:migrations:migrate --no-interaction'
```

### 8.4 `.env.vps` con comillas rotas

Si `deploy.sh` se detiene con `Detectados N problema(s) de quoting`, revisar
las líneas que señala: solo `CORS_ALLOW_ORIGIN`, `CORS_DASHBOARD_ORIGIN` y
`TRAEFIK_DASHBOARD_AUTH` pueden llevar comillas/`$` literales en su valor
(regex y hash de contraseña respectivamente); cualquier otra variable con
comillas envolventes o `@`/`:` sin codificar en una URL de conexión romperá
esa credencial en tiempo de ejecución.

## 9. Sync de producción hacia staging

`scripts/sync-prod-to-staging.sh` copia la BD de prod hacia staging: backup
fresco (`deploy.sh prod --backup`), `rsync` a staging por una clave SSH
dedicada restringida por forced-command (`scripts/staging-sync-dispatch.sh`,
solo permite recibir el rsync o disparar `RESTORE_AND_SANITIZE`), y en
staging vacía el schema, restaura, migra y **sanea** los `environment` que
eran PROD (pasan a TEST, credenciales anuladas) para que staging con datos
reales nunca pueda pegarle a las APIs de pago/comunicaciones reales.

Las credenciales de proveedor (`sys_config` bajo `provider.%`) no viven en
`environment`, así que el saneo de arriba no las toca: llegan cifradas con
la `SYS_CONFIG_ENCRYPTION_KEY` de prod, que staging no puede descifrar con
la suya. Decisión explícita (2026-08-04, no la de borrarlas): la clave de
prod se envía por **stdin** de la misma conexión SSH restringida (nunca
como argumento ni a disco), staging la usa una sola vez para descifrar y
re-cifrar con su propia clave (`bin/console
app:staging-sync:reencrypt-provider-secrets`) y la descarta — quedan
funcionales en staging por ahora. Pendiente pensar un mecanismo mejor.

Corre por dos vías, ninguna versionada en git (crontab de `deploy@` en el
host de prod, igual que cualquier otro cron de sistema). El log debe
apuntar a una ruta que `deploy` pueda escribir — `/var/log` es de
`root:syslog`, no de `deploy`, así que un cron que loguee ahí falla en seco
(el redirect no se puede abrir y el comando ni siquiera llega a correr):

```cron
# Mensual, el día 2 a las 03:30
30 3 2 * * cd /opt/api-transferencias && ./scripts/sync-prod-to-staging.sh >> /home/deploy/logs/sync-prod-to-staging.log 2>&1

# Cada 2 minutos: recoge el disparo bajo demanda desde el dashboard
# (App\Service\StagingSyncService::trigger()) — no hace nada si no hay
# ninguna solicitud pendiente.
*/2 * * * * cd /opt/api-transferencias && ./scripts/staging-sync-watcher.sh >> /home/deploy/logs/staging-sync-watcher.log 2>&1
```

El script reporta su propio estado (`RUNNING`/`SUCCESS`/`FAILED`) a la app
en cada checkpoint vía `bin/console app:staging-sync:report` — es lo único
que le permite al dashboard (`GET /admin/staging-sync/stream`, solo
disponible cuando `DEPLOYMENT_STAGE=production`) enterarse en vivo, tanto si
lo disparó el cron mensual como un admin desde la pantalla.
