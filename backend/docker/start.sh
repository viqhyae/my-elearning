#!/bin/sh
set -eu

cd /var/www

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

until nc -z "${DB_HOST:-postgres}" "${DB_PORT:-5432}"; do
  echo "Waiting for postgres..."
  sleep 2
done

run_artisan_with_retry() {
  command_args="$1"
  max_attempts="${2:-30}"
  delay_seconds="${3:-2}"
  attempt=1

  while [ "$attempt" -le "$max_attempts" ]; do
    if php artisan $command_args; then
      return 0
    fi

    echo "php artisan $command_args failed (attempt $attempt/$max_attempts). Retrying in ${delay_seconds}s..."
    attempt=$((attempt + 1))
    sleep "$delay_seconds"
  done

  echo "php artisan $command_args failed after ${max_attempts} attempts."
  return 1
}

run_artisan_with_retry "migrate --force"
run_artisan_with_retry "db:seed --force"

exec php-fpm
