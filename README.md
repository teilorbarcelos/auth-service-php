# Auth Service (PHP/Slim 4) 🔐

Microsserviço de autenticação dedicado, extraído do `backend-php-slim`.
Plug-and-play com o monólito: setar `AUTH_MODE=remote` no monólito e subir este serviço na porta `8001`.

---

## 🚀 Tecnologias

- **Runtime:** FrankenPHP (PHP 8.3, worker mode)
- **Framework Web:** Slim 4
- **ORM:** Eloquent ORM (Laravel Illuminate\Database)
- **Migrações:** Phinx
- **Cache/Sessão:** Redis (Session Versioning)
- **Autenticação:** JWT HMAC-SHA256 (lcobucci/jwt) + BCrypt
- **Validação:** Respect\Validation
- **Logs:** Monolog estruturado com request_id
- **Qualidade:** PHPUnit + PCOV (100%), PHPStan nível 9, SonarQube

---

## ✨ Funcionalidades

- **Login / Refresh / Logout** via JWT com refresh token rotation
- **Me** — dados do usuário logado via token
- **Password Reset** — troca de senha autenticada (retorna token no response)
- **JWKS Endpoint** — `GET /v1/auth/.well-known/jwks.json`
- **RBAC no Redis:** Permissões cacheadas em `session:user:{id}:*`
- **Session Epoch:** Invalidação O(1) via `session:ver:%s` (INCR no Redis)
- **Rate Limiting** por usuário/IP via Redis
- **Health Checks:** `/health`, `/liveness`, `/ready`
- **Request Logging:** Log estruturado com MDC

---

## 🔌 Plug-and-Play: Monolito → Microsserviço

O `auth-service-php` substitui o módulo de autenticação do `backend-php-slim` sem alterar o middleware JWT, o RBAC ou a sessão Redis.

### Como funciona

```
FRONTEND                   AUTH SERVICE (8001)         MONOLITH (8888)
   │                            │                          │
   ├─ POST /login ────────────→│                          │
   │                            ├─ SELECT User+Auth+Role  │
   │                            ├─ BCrypt verify          │
   │                            ├─ Redis: create session  │
   │                            ├─ JWT (HS256)            │
   │←── { token, refresh } ────│                          │
   │                                                      │
   ├─ GET /users (JWT) ────────────────────────────────→│
   │                          ├─ valida JWT local (HS256) │
   │                          ├─ checa session:ver:%s     │
   │                          ├─ RBAC check (permissions)│
   │←─────────────────────────────────────────────────────│
```

### Modos de operação

#### Modo Monolítico (default)

O `backend-php-slim` gerencia tudo — auth incluso. **Nenhuma configuração extra.**

```bash
AUTH_MODE=local    # (default) autenticação no próprio monólito
```

#### Modo Microsserviço (opt-in)

Auth extraído para o `auth-service-php`. O monólito mantém validação JWT + RBAC.

```bash
# backend-php-slim/.env
AUTH_MODE=remote   # desliga /v1/auth/* no monólito

# auth-service-php/.env
JWT_SECRET=<mesma do monólito>
DB_DATABASE=backend_php_slim_db    # mesmo banco
REDIS_HOST=<mesmo Redis>
```

### Passo a passo

```bash
# 1. Configure o auth-service
cd auth-service-php
cp .env.example .env
# Edite .env: mesma DB_DATABASE, JWT_SECRET e REDIS_HOST do monólito

# 2. Suba a infraestrutura (ou use a do monólito)
make up

# 3. Inicie o servidor (porta 8001)
make dev

# 4. No monólito, ative o modo remoto
# backend-php-slim/.env → AUTH_MODE=remote

# 5. Frontend passa a chamar:
#   - POST /v1/auth/login        → auth-service (8001)
#   - POST /v1/auth/refresh      → auth-service (8001)
#   - POST /v1/auth/logout       → auth-service (8001)
#   - Demais endpoints           → monólito (8888)

# 6. Pronto! O JWT emitido pelo auth-service é aceito pelo monólito.
```

### O que muda no monólito

| Componente | Antes (monolito) | Depois (auth-service) |
|---|---|---|
| `POST /v1/auth/login` | Handler local | ❌ Remove |
| `POST /v1/auth/refresh` | Handler local | ❌ Remove |
| `POST /v1/auth/logout` | Handler local | ❌ Remove |
| `POST /v1/auth/me` | Handler local | ❌ Remove |
| Middleware JWT | `AuthMiddleware` | ✅ **Igual** |
| Middleware RBAC | `PermissionMiddleware` | ✅ **Igual** |
| Session version | `session:ver:%s` no Redis | ✅ **Igual** |

> **Apenas 4 handlers são removidos.** Todo o resto (middleware, RBAC, Redis) continua inalterado.

---

## 🏁 Começando

### Pré-requisitos

- Docker + Docker Compose
- PHP 8.3+ (para desenvolvimento local ou via container)
- `backend-php-slim` rodando (para criar as tabelas e dados iniciais)

### Setup

```bash
# 1. Suba infraestrutura
make up
# ou use a mesma infra do monólito (recomendado)

# 2. Configure o ambiente
cp .env.example .env
# Edite DB_DATABASE, JWT_SECRET e REDIS_HOST (mesmos do monólito)

# 3. Execute os testes
make test

# 4. Inicie o servidor
make dev
```

### Variáveis de ambiente

```bash
# App
APP_ENV=development
APP_URL=http://localhost:8001
JWT_SECRET=86941813-8b97-4cad-b0b2-f97734a947d7

# Database
DB_DRIVER=pgsql
DB_HOST=host.docker.internal
DB_PORT=5432
DB_DATABASE=backend_php_slim_db
DB_USERNAME=postgres
DB_PASSWORD=postgrespw

# Redis
REDIS_HOST=host.docker.internal
REDIS_PORT=6379

# Rate Limit
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
```

---

## 📡 Endpoints

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/v1/auth/login` | ❌ | Login (email + password) |
| POST | `/v1/auth/refresh` | ❌ | Renova par de tokens |
| POST | `/v1/auth/logout` | ✅ | Revoga sessão |
| GET | `/v1/auth/me` | ✅ | Dados do usuário logado |
| POST | `/v1/auth/password/request` | ❌ | Solicita reset de senha |
| POST | `/v1/auth/password/validate` | ❌ | Valida token de reset |
| POST | `/v1/auth/password/change` | ❌ | Altera senha |
| GET | `/v1/auth/.well-known/jwks.json` | ❌ | JWKS (placeholder RS256) |
| GET | `/health` | ❌ | Health check |
| GET | `/liveness` | ❌ | Liveness probe |
| GET | `/ready` | ❌ | Readiness probe |

---

## 🧪 Testes

```bash
# Testes unitários e de integração
make test

# Cobertura (100%)
make coverage

# PHPStan (nível 9)
make lint
```

### Compliance (E2E com monólito)

```bash
cd ../mage-backend-compliance

# Modo monolítico
cp .env.slim .env
make test-slim

# Modo microsserviço
cp .env.auth.slim .env
make test-auth-php
```

---

## 📊 Qualidade

- **Cobertura:** 100% em código de produção (PCOV)
- **PHPStan:** nível 9, zero erros
- **SonarQube:** Quality Gate A (0 bugs, 0 vulnerabilidades, 0 code smells novos)

---

## 🛠️ Comandos do Makefile

| Comando | Descrição |
|---------|-----------|
| `make up` | Sobe infraestrutura (via Docker) |
| `make dev` | Sobe servidor com hot reload (Docker watch) |
| `make test` | Roda todos os testes |
| `make coverage` | Testes + verificação de cobertura PCOV |
| `make build` | Compila imagem de produção |
| `make lint` | PHPStan nível 9 |
| `make migrate` | Roda migrations pendentes |
| `make seed` | Roda seeds |
| `make sonar` | Roda SonarQube scan local |
| `make down` | Para infraestrutura |
