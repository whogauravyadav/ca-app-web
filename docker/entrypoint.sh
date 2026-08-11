#!/bin/sh
set -eu
umask 022

mkdir -p /var/www/html/storage/framework/views \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/cache \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache \
  /var/www/html/storage/app/public

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
fi

# Ensure public/storage symlink exists for local uploads (Ktatva is primary)
if [ ! -e /var/www/html/public/storage ]; then
  php artisan storage:link || true
fi

# Run migrations on boot (safe for this app; seed is idempotent but skip auto-seed)
php artisan migrate --force --no-interaction || true
php artisan config:clear || true
php artisan route:clear || true

exec apache2-foreground
