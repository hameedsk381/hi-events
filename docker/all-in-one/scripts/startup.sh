#!/bin/sh

cd /app/backend

if ! php artisan migrate --force; then
    echo "============================================"
    echo "ERROR: Migrations could not complete. Check the error above."
    echo "Ensure DATABASE_URL is set."
    echo "============================================"
fi

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ensure storage directory structure exists (create if volume is empty)
mkdir -p /app/backend/storage/app/public/organizer_logo
mkdir -p /app/backend/storage/app/public/organizer_cover
mkdir -p /app/backend/storage/app/public/event_image
mkdir -p /app/backend/storage/app/public/product_image
mkdir -p /app/backend/storage/app/public/user_avatar
mkdir -p /app/backend/storage/app/public/ticket_image
mkdir -p /app/backend/storage/logs
mkdir -p /app/backend/storage/framework/cache
mkdir -p /app/backend/storage/framework/sessions
mkdir -p /app/backend/storage/framework/views

# Create storage symlink (will fail silently if already exists)
php artisan storage:link || true

# Fix permissions - ensure www-data can read/write
chown -R www-data:www-data /app/backend/storage /app/backend/bootstrap/cache
chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

# Ensure subdirectories have correct permissions
find /app/backend/storage/app/public -type d -exec chmod 775 {} \;
find /app/backend/storage/app/public -type f -exec chmod 664 {} \;

exec /usr/bin/supervisord -c /etc/supervisord.conf
