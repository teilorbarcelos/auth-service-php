.PHONY: dev watch test coverage up down restart build logs shell migrate seed lint db-up db-down app-up check-running sonar

# Standardized commands
dev: build
	docker compose watch

watch:
	docker compose watch

check-running:
	@./scripts/check-status.sh

test: check-running
	docker compose exec -T app php -d pcov.enabled=1 vendor/bin/phpunit --no-coverage

coverage: check-running
	docker compose exec -T app php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text --coverage-clover coverage/clover.xml
	docker compose exec -T app php scripts/check-coverage.php

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
	docker compose up -d db redis

db-down:
	docker compose stop db redis

app-up:
	docker compose up -d app

sonar:
	@echo "🔍 Rodando scan do SonarQube..."
	./scripts/sonar-scan.sh "auth-service-php" "Auth Service PHP"
