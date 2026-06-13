# PHP Slim Backend — FrankenPHP Edition

> Boilerplate PHP de altíssimo desempenho pronto para SaaS: FrankenPHP worker mode, JWT com invalidação O(1), audit assíncrono via RabbitMQ, role cache Redis, circuit breaker de PDF, OPcache+JIT+Preload, rate limit por usuário, query parser dinâmico e DX de primeira linha (Swagger, generators, cobertura 100%, PHPStan nível 9, SonarQube).

---

## Sumário

- [Stack](#stack)
- [Por que este boilerplate](#por-que-este-boilerplate)
- [Arquitetura](#arquitetura)
- [Performance & Resiliência](#performance--resiliência)
- [Segurança & Hardening](#segurança--hardening)
- [Developer Experience](#developer-experience)
- [Comandos](#comandos)
- [Configuração (.env)](#configuração-env)
- [Banco de Dados & Migrations](#banco-de-dados--migrations)
- [Auth & JWT — Como funciona](#auth--jwt--como-funciona)
- [Rate Limit](#rate-limit)
- [Auditoria & Mensageria Assíncrona](#auditoria--mensageria-assíncrona)
- [Cache & Redis](#cache--redis)
- [PDF Service com Circuit Breaker](#pdf-service-com-circuit-breaker)
- [Observabilidade](#observabilidade)
- [Geração de Módulos](#geração-de-módulos)
- [Storage Drivers (Strategy)](#storage-drivers-strategy)
- [Testes & Qualidade](#testes--qualidade)
- [Produção (PgBouncer, Workers, Tuning)](#produção-pgbouncer-workers-tuning)

---

## Stack

| Camada | Tecnologia | Por que |
|---|---|---|
| Runtime | **PHP 8.5** | Enums, readonly classes, named args, JIT tracing |
| Servidor | **FrankenPHP 1.12+** (Caddy 2.11) | Worker mode persistente, early-hints, HTTP/2 + HTTP/3 |
| Framework | **Slim Framework 4** + **PHP-DI** | PSR-15 middleware, autowiring puro |
| ORM | **Laravel Eloquent** (standalone, Capsule) | Migrations, eager loading, observers |
| BD | **PostgreSQL 15** (dev/prod) / **SQLite** (testes) | Performance, JSONB, índices parciais |
| Cache/Session | **Redis** via **phpredis** (extensão C) | 2-3x mais rápido que Predis |
| Mensageria | **RabbitMQ 3** (`php-amqplib`) | Audit/email/error-log assíncronos |
| Auth | **JWT** (`lcobucci/jwt`) + Session Versioning | Invalidação O(1) sem scan de tokens |
| PDF | **Guzzle** + Circuit Breaker + Service externo | Resiliência contra dependência |
| Métricas | **promphp/prometheus_client_php** | Counter/gauge/histogram + Redis storage |
| Docs | **Swagger PHP Attributes** + swagger-ui | OpenAPI 3.0 gerado do código |
| Qualidade | **PHPStan 9** + **PHPUnit 100%** + **SonarQube** | Zero débito técnico |

---

## Por que este boilerplate

A maioria dos boilerplates PHP é "Hello World" com Slim + Twig. Este aqui foi desenhado para **SaaS de produção** desde o primeiro commit:

- **Worker mode sem armadilhas**: `RequestIdProcessor` reseta por request, `UserSession` é request-scoped, log bufferiza.
- **Invalidação de sessão O(1)**: trocar senha/role derruba TODAS as sessões anteriores sem scan em Redis.
- **Audit log sem matar latência**: fila RabbitMQ em vez de INSERT síncrono no request.
- **Rate limit por usuário real**: chave por `userId` + rota, não por IP (que se perde atrás de NAT).
- **100% de cobertura + PHPStan nível 9**: garantidos por pre-commit hook e Sonar quality gate.
- **Sem overengineering**: 0 interfaces redundantes, 0 abstrações decorativas. Cada classe tem 1 motivo para mudar.

---

## Arquitetura

```
src/
├── Core/                          # Núcleo reutilizável
│   ├── BaseController.php         # CRUD genérico (list/get/create/update/delete)
│   ├── BaseModel.php              # Eloquent + HasUuids
│   ├── BaseRepository.php         # Query builder + find/list/paginate
│   ├── BaseService.php            # Regras de negócio + validação
│   ├── DatabaseBootstrap.php      # Init idempotente (admin user, role, features)
│   ├── SwaggerController.php      # OpenAPI 3.0 + UI
│   ├── Exceptions/                # ValidationException, BadRequestException
│   ├── Helpers/                   # IpHelper, QueryParserHelper, QueryApplierHelper
│   ├── Traits/                    # ValidatableTrait
│   └── Transformers/              # Fractal-like: User, Role, Feature, Product
│
├── Infrastructure/                # Serviços transversais
│   ├── Audit/ErrorAuditService.php        # Log de erros (sync ou async)
│   ├── Auth/JwtService.php                # createToken, validateToken, sv
│   ├── Auth/UserSession.php               # request-scoped (DI)
│   ├── Database/DatabaseProvider.php      # Singleton Capsule + Dispatcher
│   ├── Email/EmailProvider.php            # Symfony Mailer + fallback RabbitMQ
│   ├── Log/RequestIdProcessor.php         # Request-scoped, reseta no finally
│   ├── Messaging/RabbitMQProvider.php     # Filas: audit, email, error_log
│   ├── Metrics/MetricService.php          # Counter/Gauge/Histogram
│   ├── Pdf/RemotePdfProvider.php          # Circuit breaker 3/10s, 30s reset
│   └── Storage/StorageProvider.php        # Strategy: Local, S3, etc.
│
├── Middleware/                    # PSR-15, ordem LIFO
│   ├── BodySizeLimitMiddleware.php        # 413 se Content-Length > 2M
│   ├── AuthMiddleware.php                 # Valida JWT + sv + token em Redis
│   ├── RateLimitMiddleware.php            # INCR atômico + userId/IP + route
│   ├── PermissionMiddleware.php           # role + feature/action
│   ├── LogMiddleware.php                  # request_id, métricas, IP helper
│   ├── JsonErrorMiddleware.php            # Formata 4xx/5xx + delega a ErrorAuditService
│   ├── CorsMiddleware.php                 # CORS
│   └── TrailingSlashMiddleware.php        # Rewrite silencioso (sem 301)
│
└── Modules/                       # Domínios isolados
    ├── Auth/                      # Login, logout, refresh, password recovery
    ├── User/                      # CRUD + soft delete + restore
    ├── Role/                      # CRUD + cache de features (Redis)
    ├── Feature/                   # CRUD + permissions JSON
    ├── Product/                   # CRUD + owner + soft delete
    ├── Audit/                     # AuditObserver (toda escrita assina)
    ├── Dashboard/                 # Stats (user/product/aggregations)
    ├── Health/                    # /health/live + /health/ready (cached 5s)
    ├── Metrics/                   # /metrics (autenticado por token)
    └── Debug/                     # Endpoints de debug (desabilitados em prod)

public/
├── index.php                      # Entry point: classic mode
└── worker.php                     # Entry point: FrankenPHP worker mode

infra/
├── caddy/Caddyfile                # Caddyfile customizado (max body, worker)
├── php/conf.d/opcache.ini         # JIT tracing 128M + preload
└── metrics/                       # Prometheus + Grafana
```

---

## Performance & Resiliência

| Item | Implementação | Ganho |
|---|---|---|
| **FrankenPHP Worker** | `public/worker.php` + loop `while` + SIGTERM graceful | 1 processo persistente, sem fork/request |
| **OPcache + JIT** | `jit=tracing`, `jit_buffer_size=128M`, `validate_timestamps=0` | 2-5x throughput em CPU-bound |
| **Preload** | `preload.php` carrega container, providers, models, observers | Cold start ≈ 0 |
| **phpredis** | Extensão C nativa em vez de `Predis\Client` (puro PHP) | 2-3x em `GET`/`INCR`/`SISMEMBER` |
| **Session Versioning** | Claim `sv` no JWT + `INCR user:session_version:{uid}` | Invalidação O(1) de TODAS as sessões |
| **JWT Cache LRU** | Cache de validação TTL 5s, max 500 tokens | -80% ida ao Redis em rajada |
| **Async Audit** | RabbitMQ queue `audit` + consumer worker | -50% latência em writes |
| **Async Email** | RabbitMQ queue `email` + consumer | -30% latência em signup/reset |
| **Async Error Log** | RabbitMQ queue `error_log` + consumer | Sem INSERT no path de erro |
| **Monolog BufferHandler** | Buffer 100 mensagens, flush assíncrono | Sem syscall de log por request |
| **Rate Limit atômico** | `INCR` + `EXPIRE` condicional (não `GET`+`SET`) | Sem race em rajada paralela |
| **Rate Limit por userId** | Chave `rate_limit:user:{uid}:{route}` (não IP) | NAT/CDN não compartilham quota |
| **Role Cache** | `role:features:{id}` TTL 120s, invalidação no update | Login de 4 queries → 1 |
| **Health Cache** | Resultado cacheado em Redis 5s | K8s/LB não amplificam oscilação |
| **PDF Circuit Breaker** | 3 falhas em 10s → aberto por 30s, fallback PDF | Worker não trava se PDF cair |
| **AMQP Timeout** | `connection_timeout=3s`, `read_write_timeout=10s` | Sem travamento em falha de rede |
| **Connection Pool** | `DatabaseProvider` singleton + PgBouncer doc | Sem esgotar `max_connections` |
| **Body Size Limit** | `BodySizeLimitMiddleware` rejeita 413 se > 2M | Sem DoS por payload gigante |
| **Trailing Slash Rewrite** | Rewrite silencioso no Slim, sem 301 | -1 RTT em chamadas com `/` |
| **Pre-fetch Eager Loading** | `User::with(['role', 'role.features'])` | 0 N+1 |

---

## Segurança & Hardening

| Item | Implementação |
|---|---|
| **JWT seguro** | HMAC-SHA256 + validação `LooseValidAt` + `iss`/`aud` |
| **JWT_SECRET obrigatório em prod** | `LogicException` se secret default em `APP_ENV=production` |
| **Session Versioning** | `sv` no token + INCR no Redis em login, password reset, role update, deactivation |
| **Refresh Token Rotation** | `SREM` do token antigo + novo par gerado |
| **Rate Limit por usuário** | Chave por `userId` + route, 60 req/min default |
| **Rate Limit por IP** | Fallback anônimo, com helper `IpHelper` (X-Forwarded-For aware) |
| **Admin Bypass** | `admin`/`administrator` tokens ignoram rate limit |
| **Bearer /metrics** | Token interno via `METRICS_TOKEN` env (HTTP 403 se faltar) |
| **CORS** | `CorsMiddleware` outer-most para garantir headers em erros |
| **UUID v4 em todas PKs** | `HasUuids` trait + `$incrementing=false` |
| **Error Audit UUID guard** | `Str::isUuid($id_user)` antes de gravar erro |
| **Sem stack trace em prod** | `JsonErrorMiddleware` omite `trace` se `APP_DEBUG=false` |
| **Body size limit** | 413 antes de chegar no PHP parser |
| **JWT secret por env** | Nunca hardcoded — `createImmutable` no `load-env.php` |

---

## Developer Experience

| Item | Como |
|---|---|
| **Swagger OpenAPI 3.0** | PHP Attributes em cada controller (`#[OA\Get]`, `#[OA\Post]`, etc.) → `/v1/swagger.json` + `/v1/docs` |
| **Module generator** | `php scripts/generate.php ModuleName` cria Model/Controller/Service/Repository/Transformer/routes |
| **Storage driver install** | `php scripts/install-storage.php s3` instala Flysystem S3 driver |
| **Query parser dinâmico** | `?filter[name]=foo&sort=-created_at&page=2&per_page=20` em qualquer `listItems` |
| **Migrations** | `make migrate` (Phinx) com timestamps `YYYYMMDDHHMMSS` |
| **Seeds idempotentes** | `make init` roda `scripts/init-app.php` quantas vezes quiser |
| **PHPStan 9** | `make lint` (78 arquivos, 0 erros) |
| **PHPUnit 320 testes, 100%** | `make coverage` (statements, branches, functions, lines) |
| **Pre-commit hook** | `php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text --coverage-clover coverage/clover.xml && php scripts/check-coverage.php` + PHPStan |
| **Dev mode hot reload** | `make dev` = docker compose watch (rebuild em composer.json/lock/Dockerfile) |
| **Container shell** | `make shell` para bash no app |
| **Compliance E2E** | `mage-backend-compliance` valida comportamento agnóstico do backend (51/51 testes) |

---

## Comandos

```bash
# Ambiente
make dev               # Build + docker compose watch
make up                # Subir containers detached
make down              # Derrubar tudo
make restart           # Reiniciar
make shell             # Bash no container app

# Qualidade
make test              # Testes sem coverage
make coverage          # Testes + 100% coverage
make lint              # PHPStan nível 9

# Banco
make migrate           # Phinx migrate
make seed              # Phinx seed:run
make init              # Idempotente: admin + roles + features
make migrate-dev name=NomeMigration   # Cria migration nova
make migrate-status    # Status das migrations

# Geração
make generate name=Product            # CRUD completo
make storage-driver name=s3           # Instala driver S3

# Logs / Debug
make logs              # docker compose logs -f app

# Métricas
make metrics-up        # Sobe Prometheus + Grafana
make metrics-stop      # Para
make metrics-down      # Remove
```

---

## Configuração (.env)

Copie `.env.example` para `.env` e ajuste:

```bash
# App
APP_ENV=development|production|testing   # testing usa SQLite :memory:
APP_DEBUG=true|false                     # JSON_PRETTY_PRINT e stack trace
APP_TIMEZONE=UTC
APP_URL=http://localhost:8888

# Auth
JWT_SECRET=<uuid-v4>                     # OBRIGATÓRIO ≠ default em produção

# Database
DB_DRIVER=pgsql|sqlite                   # sqlite só em testes
DB_HOST=db
DB_PORT=5432
DB_DATABASE=backend_php_slim_db
DB_USERNAME=postgres
DB_PASSWORD=postgrespw

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Rate Limit
RATE_LIMIT_MAX=60                        # Requests por janela
RATE_LIMIT_WINDOW=60                     # Janela em segundos

# First User
FIRST_USER=admin@email.com
FIRST_PASSWORD=admin@123

# SMTP (opcional, default = noop)
SMTP_HOST=localhost
SMTP_PORT=1025
SMTP_USER=
SMTP_PASS=
SMTP_FROM=no-reply@example.com

# RabbitMQ (auditoria assíncrona)
MESSAGING_ENABLED=false                  # Liga consumer workers
AUDIT_ASYNC=false                        # AuditObserver enfileira em vez de gravar
EMAIL_ASYNC=false                        # EmailProvider enfileira
ERROR_LOG_ASYNC=false                    # ErrorAuditService enfileira

# Storage
STORAGE_DISK=local                       # local | s3 (após install)
STORAGE_PATH=/app/storage/app
STORAGE_URL=http://localhost:8888/storage

# PDF Service
PDF_SERVICE_URL=http://host.docker.internal:8889

# Métricas
METRICS_TOKEN=<random>                   # OBRIGATÓRIO se não vazio (Bcrypt-style)

# Body size limit
REQUEST_BODY_MAX_SIZE=2M                 # K | M | G
```

> **Importante**: Em produção, defina `JWT_SECRET` para um UUID v4 ou string aleatória de 32+ caracteres, e `APP_DEBUG=false`.

---

## Banco de Dados & Migrations

Migrations vivem em `database/migrations/` com timestamp `YYYYMMDDHHMMSS_descricao.php`. Phinx é a ferramenta:

```bash
make migrate-dev name=AddStatusToProducts   # Cria esqueleto
make migrate                                # Roda todas pendentes
make migrate-status                         # Lista status
```

Criar uma migration nova:

```php
<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class AddStatusToProducts extends AbstractMigration
{
    public function up(): void
    {
        $this->table('products')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addIndex(['status'])
            ->update();
    }

    public function down(): void
    {
        $this->table('products')
            ->removeColumn('status')
            ->update();
    }
}
```

**Idempotência**: `scripts/init-app.php` (roda via `make init`) verifica se admin/role/features já existem antes de criar. Pode ser chamado quantas vezes quiser.

---

## Auth & JWT — Como funciona

### Fluxo de login

1. `POST /v1/auth/login` recebe `email` + `password`
2. `AuthService::login` valida bcrypt, monta claims com `role`, `permissions`, `sv`
3. `JwtService::createTokenPair` gera `token` (1h) + `refreshToken` (7d), ambos com `uid` e `sv`
4. `registerTokens($uid, [$token])` adiciona ao set Redis `user:sessions:{uid}` com TTL 7d
5. Cliente recebe `{token, refreshToken, user: {id, name, email, role, permissions}}`

### Validação em cada request

`AuthMiddleware` (executa em todo endpoint protegido):

1. Extrai `Bearer` do header
2. `JwtService::validateToken` valida assinatura, expiração, `iss`, `aud`, **e `sv`**
3. `JwtService::isTokenValid` checa SISMEMBER (com cache LRU TTL 5s)
4. Popula `UserSession` + `request->withAttribute('user', $claims)`

### Invalidação de TODAS as sessões

- **Password reset**: `AuthService::recoverPassword` → `bumpSessionVersion($uid)` → INCR no Redis
- **Role update**: `RoleService::setStatus` → `invalidateUsersWithRole($roleId)` → bump em cada user
- **User deactivation**: `UserService::setStatus` → `bumpSessionVersion`
- **Token reuse** (refresh): o token antigo é `SREM` do set antes de criar novo par

Resultado: se um JWT tiver `sv=3` e o Redis mostrar `sv=5`, é rejeitado em O(1) sem scan.

### Endpoints

```
POST /v1/auth/login         { email, password } → { token, refreshToken, user }
POST /v1/auth/refresh       Bearer refreshToken → novo par
POST /v1/auth/recover       { email } → envia email com link (assíncrono se EMAIL_ASYNC=true)
POST /v1/auth/reset         { token, new_password } → bump sv + set new password
```

---

## Rate Limit

Configurável por env:

```bash
RATE_LIMIT_MAX=60     # Default 60 requests
RATE_LIMIT_WINDOW=60  # em 60 segundos
```

Chave Redis:

- **Autenticado**: `rate_limit:user:{userId}:{route}` (exato, sem NAT collisions)
- **Anônimo**: `rate_limit:{ip}:{route}` com `IpHelper::getClientIp` (X-Forwarded-For aware)

Implementação atômica (`RateLimitMiddleware:55-61`):

```php
$current = $this->redis->incr($key);
if ($current === 1) {
    $this->redis->expire($key, $this->window);   // Só seta no primeiro hit
}
if ($current > $this->limit) { return 429; }
```

**Admin bypass**: tokens com `role ∈ {admin, administrator}` não são limitados, mas recebem headers `X-RateLimit-*` no response.

**Headers retornados**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`, status 429 com JSON explicativo.

---

## Auditoria & Mensageria Assíncrona

### AuditObserver (todas as escritas)

Vinculado em `public/index.php` e `public/worker.php` via container (não mais via `BaseModel::boot()` estático). Observa `User`, `Product`, `Role`, `Feature`:

```php
$observer = $container->get(\App\Modules\Audit\AuditObserver::class);
foreach ([User::class, Product::class, Role::class, Feature::class] as $m) {
    $m::observe($observer);
}
```

Modo:

- **`AUDIT_ASYNC=false`** (default): `Audit::create([...])` síncrono
- **`AUDIT_ASYNC=true`**: publica JSON na fila `audit` no RabbitMQ; `scripts/consume-audit.php` consome e grava

### Email async

```php
// EmailProvider
if ($this->messaging && ($_ENV['EMAIL_ASYNC'] ?? 'false') === 'true') {
    $this->messaging->publish('email', ['to' => ..., 'subject' => ..., 'html' => ..., 'from' => ...]);
    return;
}
// Fallback síncrono via Symfony Mailer
$this->mailer->send($email);
```

### Error log async

`ErrorAuditService` (extraído de `JsonErrorMiddleware` para SRP):

```php
if ($this->messaging && ($_ENV['ERROR_LOG_ASYNC'] ?? 'false') === 'true') {
    $this->messaging->publish('error_log', [...]);
} else {
    ErrorLog::create([...]);  // INSERT em tb_error_log
}
```

### Consumers

```bash
# Subir todos (recomendado: supervisor ou systemd em prod)
php scripts/consume-audit.php
php scripts/consume-email.php
php scripts/consume-error-log.php
```

---

## Cache & Redis

Chaves usadas:

| Chave | TTL | Uso | Invalidação |
|---|---|---|---|
| `user:sessions:{uid}` | 7d | Set de tokens ativos | `SREM` no refresh rotation, `DEL` no password reset |
| `user:session_version:{uid}` | persistente | Versão da sessão (sv) | `INCR` no reset/update/deactivation |
| `rate_limit:user:{uid}:{route}` | 60s | Contador de rate limit | `EXPIRE` no primeiro hit |
| `rate_limit:{ip}:{route}` | 60s | Contador anônimo | mesmo |
| `role:features:{roleId}` | 120s | Lista de features+permissions | `DEL` no `RoleService::setStatus` |
| `health:probe` | 5s | Cache do `/health/ready` | EXPIRE natural |

---

## PDF Service com Circuit Breaker

`RemotePdfProvider` (injetado como `PdfProviderInterface`):

```php
// src/Infrastructure/Pdf/RemotePdfProvider.php
private const TIMEOUT = 2.0;
private const CONNECT_TIMEOUT = 1.0;
private const FAILURE_THRESHOLD = 3;
private const RESET_TIMEOUT = 30;
private const WINDOW = 10;  // segundos
```

**Algoritmo**:

1. Antes de chamar serviço: `isCircuitOpen()`? Se sim, retorna PDF de fallback imediatamente.
2. Falha: `recordFailure()` → se ≥3 falhas em 10s, abre o circuito por 30s.
3. Após 30s, próxima chamada é half-open (tenta novamente).
4. Sucesso: `failures=0, open=false`.

**Fallback**: PDF mínimo válido com texto "Fallback PDF Content" — evita exception 500 ao cliente.

**Substituível**: implementar `PdfProviderInterface` e registrar no `config/container.php`:

```php
\App\Infrastructure\Pdf\PdfProviderInterface::class => \DI\get(MyPdfProvider::class),
```

---

## Observabilidade

### Métricas Prometheus (`/metrics`)

Token de auth: definir `METRICS_TOKEN` no `.env` para qualquer string. Request sem `Authorization: Bearer $METRICS_TOKEN` recebe 403.

Métricas expostas:

```
app_process_resident_memory_bytes
app_process_memory_peak_bytes
php_gc_runs
php_gc_collected_total
php_gc_threshold
php_gc_roots
process_cpu_seconds_total

# Contadores incrementados por request
http_requests_total{method, status, path}
database_queries_total
exceptions_total{type}
```

### Health checks

- `GET /health/live` — sem I/O, só "UP". Para K8s liveness probe.
- `GET /health/ready` — verifica DB, Redis, RabbitMQ, Storage. Cache 5s no Redis.
- `GET /v1/health`, `/v1/health/live`, `/v1/health/ready` — versões sob `/v1` (com rate limit bypass).

### Request ID

Cada request recebe `X-Request-ID` no response. `RequestIdProcessor` adiciona em todo log (e **reseta no finally** — crucial em worker mode para não vazar entre requests).

### Logging

`Monolog\Handler\BufferHandler` com batch de 100, flush automático. Format JSON para parse em Loki/Elasticsearch.

---

## Geração de Módulos

```bash
make generate name=Product
# ou
php scripts/generate.php Product
```

Cria automaticamente em `src/Modules/Product/`:

- `Product.php` (Model Eloquent + UUID)
- `ProductController.php` (CRUD genérico herdado de `BaseController`)
- `ProductService.php` (validações + delegação ao Repository)
- `ProductRepository.php` (query builder)
- `ProductSchemas.php` (OpenAPI attributes)
- `routes.php` (registro no grupo protegido)

E em `src/Core/Transformers/ProductTransformer.php`.

**Não cria migrations** — usar `make migrate-dev name=CreateProducts` depois.

**Adicionar à rota protegida** — `config/routes.php` já tem placeholder `// [GENERATOR_ROUTES]` para os novos módulos:

```php
$protectedGroup->group('/user', require __DIR__ . '/../src/Modules/User/routes.php');
$protectedGroup->group('/product', require __DIR__ . '/../src/Modules/Product/routes.php');
// ...
// [GENERATOR_ROUTES]
```

---

## Storage Drivers (Strategy)

`StorageProvider` delega para um driver configurável via `STORAGE_DISK`:

```bash
# Default: disco local em /app/storage/app
STORAGE_DISK=local
STORAGE_PATH=/app/storage/app

# Instalar S3
php scripts/install-storage.php s3
# (também suporta: gcs, azure, local)
# Adiciona league/flysystem-aws-s3-v3 ao composer.json
STORAGE_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-bucket
```

API uniforme:

```php
$this->storage->put('avatars/user.jpg', $binaryData);
$this->storage->get('avatars/user.jpg');
$this->storage->exists('avatars/user.jpg');
$this->storage->delete('avatars/user.jpg');
$this->storage->url('avatars/user.jpg');
```

Implementar driver custom:

```php
class GcsDriver implements StorageDriverInterface {
    public function put(string $path, string $contents): bool { ... }
    public function get(string $path): ?string { ... }
    public function exists(string $path): bool { ... }
    public function delete(string $path): bool { ... }
    public function url(string $path): string { ... }
}
```

E registrar no container:

```php
\App\Infrastructure\Storage\Drivers\GcsDriver::class => \DI\autowire(),
\App\Infrastructure\Storage\StorageProvider::class => \DI\autowire()
    ->constructorParameter('driver', \DI\get(\App\Infrastructure\Storage\Drivers\GcsDriver::class)),
```

---

## Testes & Qualidade

### Suíte de testes

- **320 testes, 810+ assertions** — Unit + Integration
- **100% coverage** (statements, branches, functions, lines) — `check-coverage.php` falha se < 100%
- **PHPStan nível 9** — 0 erros em 78 arquivos
- **51/51 compliance E2E** (`mage-backend-compliance`)

### Rodar

```bash
make test         # Apenas testes
make coverage     # Testes + 100% coverage
make lint         # PHPStan
```

### Estrutura de testes

```
tests/
├── Unit/                            # Classes isoladas, mocks
│   ├── AuthMiddlewareTest.php
│   ├── BodySizeLimitMiddlewareTest.php
│   ├── EmailProviderTest.php
│   ├── JwtServiceTest.php
│   ├── LogMiddlewareTest.php
│   ├── QueryParserHelperTest.php
│   ├── RemotePdfProviderTest.php
│   ├── RequestIdProcessorTest.php
│   ├── UserSessionTest.php
│   └── ...
├── Integration/                     # Banco :memory:, Redis real ou fake
│   ├── AuthControllerTest.php
│   ├── DashboardControllerTest.php
│   ├── PermissionMiddlewareTest.php
│   ├── TrailingSlashMiddlewareTest.php
│   ├── UserRoutesTest.php
│   └── ...
├── WebTestCase.php                  # Helper para integration
└── Infrastructure/                  # Providers, observability
```

### Pre-commit hook (Husky)

`make coverage` e `make lint` rodam automaticamente em `git commit` via hook (configurado em `.husky/`).

### SonarQube

`sonar-project.properties` está pronto. Após `make coverage`:

```bash
docker run --rm --network host -v $(pwd):/usr/src \
  sonarsource/sonar-scanner-cli \
  -Dsonar.host.url=http://localhost:9000 \
  -Dsonar.token=$SONAR_TOKEN
```

Quality Gate: **100% coverage em new code**, **0 novas violações**, **< 3% duplicação**.

---

## Produção (PgBouncer, Workers, Tuning)

### PgBouncer (obrigatório com workers)

Em FrankenPHP worker mode, cada processo persistente segura uma conexão Postgres. Sem pool, 32 workers × 2 conexões = saturação rápida.

**Setup mínimo PgBouncer (transaction pooling)**:

```ini
# pgbouncer.ini
[databases]
backend_php_slim_db = host=db port=5432 dbname=backend_php_slim_db
[pgbouncer]
listen_addr = 0.0.0.0
listen_port = 6432
auth_type = md5
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 20
```

E apontar a app para `DB_HOST=pgbouncer`, `DB_PORT=6432`. **`ATTR_PERSISTENT` deve ser `false`** quando usar PgBouncer (PgBouncer já faz o pool).

### Worker mode

`public/worker.php` é o entrypoint. `start.sh` ativa via env var `FRANKEN_WORKER=true` injetada no `FRANKENPHP_CONFIG`:

```yaml
environment:
  - FRANKENPHP_CONFIG=${FRANKEN_WORKER:+worker /app/public/worker.php 0}
```

- `worker /app/public/worker.php 0` → 1 worker, escala dinâmica (FrankenPHP adiciona sob demanda)
- `worker /app/public/worker.php 8` → 8 workers fixos

### Graceful shutdown

`worker.php:30-32` registra `pcntl_signal(SIGTERM, ...)` que para o `while` de aceitar requests, drena o que está em curso, e só então o processo morre. K8s `terminationGracePeriodSeconds: 30` deve ser suficiente.

### Tuning PHP (`infra/php/conf.d/opcache.ini`)

```ini
opcache.enable=1
opcache.jit=tracing
opcache.jit_buffer_size=128M
opcache.validate_timestamps=0   # Crítico em prod: nunca revalida
opcache.revalidate_freq=0
opcache.max_accelerated_files=20000
opcache.preload=/app/preload.php
```

`preload.php` carrega o container, providers, models, observers uma vez no boot do worker.

### Tuning FrankenPHP (`infra/caddy/Caddyfile`)

```caddyfile
{
    frankenphp {
        num_threads 16              # 2x CPUs disponíveis
        max_threads 32
        max_wait_time 10s
        max_requests 500            # Restart thread a cada 500 req (mitigates leaks)
    }
}
```

### Checklist de deploy

- [ ] `APP_DEBUG=false`
- [ ] `JWT_SECRET` ≠ default
- [ ] `MESSAGING_ENABLED=true` e consumers rodando
- [ ] `AUDIT_ASYNC=true` se volume justifica
- [ ] `METRICS_TOKEN` definido
- [ ] PgBouncer configurado e `ATTR_PERSISTENT=false` em produção
- [ ] OPcache.validate_timestamps=0
- [ ] `preload.php` carregando o caminho certo
- [ ] `FRANKEN_WORKER=true`
- [ ] Body size limit razoável (`REQUEST_BODY_MAX_SIZE`)
- [ ] Health check respondendo em < 100ms
- [ ] Sonar quality gate OK

---

## Licença & Histórico

### Licença

Este projeto está sob a [MIT License](LICENSE). Você pode usar, modificar, distribuir e usar comercialmente sem restrições, desde que mantenha o aviso de copyright.

### Template de produção

Antes do deploy, copie `.env.production.example` para `.env` e preencha os placeholders (`<GERAR-STRING-32-CHAR-AQUI>`, `<SENHA-FORTE-AQUI>`, `<GERAR-TOKEN-ALEATORIO-AQUI>`, etc.). O template já vem com defaults seguros (`APP_DEBUG=false`, `MESSAGING_ENABLED=true`, `AUDIT_ASYNC=true`).

### Changelog

Mudanças notáveis estão documentadas em [CHANGELOG.md](CHANGELOG.md) no formato [Keep a Changelog](https://keepachangelog.com/pt-BR/). Versões seguem [Semantic Versioning](https://semver.org/pt-BR/).
