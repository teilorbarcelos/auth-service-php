.PHONY: dev watch test coverage generate storage-driver up down restart build logs shell migrate seed lint db-up db-down app-up migrate-dev migrate-status check-running metrics-up metrics-stop metrics-down

# Standardized commands
dev: build
	docker compose watch

watch:
	docker compose watch

check-running:
	@./scripts/check-status.sh

test: check-running
	docker compose exec -T app vendor/bin/phpunit --no-coverage

coverage: check-running
	docker compose exec -T app php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text --coverage-clover coverage/clover.xml
	docker compose exec -T app php scripts/check-coverage.php

# Example: make generate name=Product
generate:
	@php scripts/generate-module.php $(name)

# Example: make storage-driver name=s3
storage-driver:
	@php scripts/install-storage.php $(name)

# Helper / Infrastructure commands
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build

logs:
	docker compose logs -f app

shell:
	docker compose exec app bash

migrate: check-running
	docker compose exec -T app vendor/bin/phinx migrate

seed: check-running
	docker compose exec -T app vendor/bin/phinx seed:run

init: check-running
	docker compose exec -T app php scripts/init-app.php

lint: check-running
	docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=-1

db-up:
	docker compose up -d db redis rabbitmq

db-down:
	docker compose stop db redis rabbitmq

app-up:
	docker compose up -d app

migrate-dev: check-running
	docker compose exec -T app vendor/bin/phinx create $(name)

migrate-status: check-running
	docker compose exec -T app vendor/bin/phinx status

# Métricas (Prometheus & Grafana)
metrics-up:
	@echo "📈 Subindo stack de métricas (Prometheus & Grafana)..."
	docker compose -f docker-compose.metrics.yml up -d

metrics-stop:
	@echo "🛑 Parando stack de métricas..."
	docker compose -f docker-compose.metrics.yml stop

metrics-down:
	@echo "🗑️ Removendo stack de métricas..."
	docker compose -f docker-compose.metrics.yml down
