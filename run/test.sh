#!/usr/bin/env bash
set -euo pipefail

# Simple helper to support both Docker Compose v2 ("docker compose") and v1 ("docker-compose")
compose() {
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    docker compose "$@"
  else
    docker-compose "$@"
  fi
}

echo "🧪 Running tests inside PHP 8.3 CLI container..."

# 1) Build the image
compose build php

# 2) Install dependencies and run tests (Pest preferred; PHPUnit fallback)
compose run --rm php sh -lc '
  set -euo pipefail
  php -v
  composer --version

  if [ -f composer.lock ]; then
    composer install --no-interaction --prefer-dist
  else
    composer update --no-interaction --prefer-dist
  fi

  if [ -x ./vendor/bin/pest ]; then
    ./vendor/bin/pest --colors=always -vv
  elif [ -x ./vendor/bin/phpunit ]; then
    ./vendor/bin/phpunit --colors=always -vv
  else
    echo "❌ No test runner found: expected Pest (vendor/bin/pest) or PHPUnit (vendor/bin/phpunit)."
    exit 2
  fi
'
