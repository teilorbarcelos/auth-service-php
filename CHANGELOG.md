# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/pt-BR/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- P4 #37: Consumers `consume-email.php` e `consume-error-log.php` para processar filas RabbitMQ de email e error log assíncronos
- P4 #40: `.env.production.example` com placeholders de segurança para deploy (JWT_SECRET, METRICS_TOKEN, SMTP, etc.)
- P4 #42: Índice `idx_users_id_role` em `users` para acelerar invalidação de sessão por role
- P4 #43: Licença MIT (LICENSE)
- P4 #44: CHANGELOG.md (este arquivo)

### Changed
- P4 #37: `consume-audit.php` migrado de `createUnsafeMutable` para `loadEnvironment()`
- P4 #41: Removido `predis/predis` do composer.json (runtime usa phpredis, extensão C)

## [1.0.0] — Antes da estruturação P0-P4

### Added
- Projeto inicial: Slim 4 + FrankenPHP + Eloquent + PostgreSQL + Redis + RabbitMQ
- P0: Auditoria assíncrona, rate limit atômico, session versioning JWT, UserSession injetável, RequestIdProcessor, async email/error log
- P1: JSON_PRETTY_PRINT gateado, phpredis, OPcache+JIT+Preload, FrankenPHP worker, circuit breaker PDF, health cache, AMQP timeout, lazy DB init, trailing slash rewrite, DatabaseProvider singleton, índices tb_audit
- P2: Rate limit por userId, métricas autenticadas, role cache Redis, graceful shutdown SIGTERM, Monolog BufferHandler, body size limit, helpers centralizados (IpHelper, resolveRoleId), ErrorAuditService, DashboardRepository
- P3: createImmutable + $_ENV padronizado, Swagger corrigido (supressão E_DEPRECATED, URL absoluta)
- Swagger OpenAPI 3.0 via PHP Attributes
- Geração de módulos CRUD via `scripts/generate.php`
- Storage Strategy (local/s3/gcs/azure) via Flysystem
- Query parser dinâmico (`?filter[name]=...&sort=-created_at&page=2&per_page=20`)
- 320+ testes, 100% coverage, PHPStan nível 9
- 51/51 testes E2E compliance (mage-backend-compliance)
- SonarQube quality gate
