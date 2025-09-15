#!/usr/bin/env bash
set -e

cd /var/www/html

# Installe les deps uniquement si vendor/ manque (ou si composer.json a changé et pas de lock)
if [ ! -d vendor ]; then
  echo "[entrypoint] Installing Composer dependencies…"
  composer install --no-dev --prefer-dist --no-interaction
fi

# (Optionnel) cache prod si APP_ENV=prod
if [ "${APP_ENV}" = "prod" ]; then
  php bin/console cache:clear --env=prod || true
  php bin/console cache:warmup --env=prod || true
fi

exec php-fpm
