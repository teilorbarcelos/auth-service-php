#!/bin/bash

echo "Waiting for database..."
until php -r "new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  sleep 1
done

echo "Running migrations..."
vendor/bin/phinx migrate -e development

echo "Running seeds..."
vendor/bin/phinx seed:run -e development

# Modo worker (FRANKEN_WORKER=true) é controlado via env var FRANKENPHP_CONFIG
# injetada pelo docker-compose, que ativa a diretiva `worker` no Caddyfile.
echo "Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
