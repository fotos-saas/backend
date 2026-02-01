#!/bin/bash

set -e

# Change to app directory
cd /var/www/html

# === TIMEOUT CONFIGURATION ===
MAX_RETRIES=30  # 30 * 2s = 60 seconds max wait
RETRY_COUNT=0

# === Wait for PostgreSQL with timeout ===
echo "Waiting for PostgreSQL..."
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-photo_stack}
DB_USERNAME=${DB_USERNAME:-photo_stack}
DB_PASSWORD=${DB_PASSWORD:-secret}

echo "Connecting to PostgreSQL at $DB_HOST:$DB_PORT..."

until php -r "try { new PDO('pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); exit(0); } catch(Exception \$e) { echo \$e->getMessage(); exit(1); }" 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: PostgreSQL not available after $MAX_RETRIES attempts (60s). Exiting."
        exit 1
    fi
    echo "PostgreSQL is unavailable - sleeping ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done
echo "PostgreSQL is up"

# === Wait for Redis with timeout ===
echo "Waiting for Redis..."
RETRY_COUNT=0
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_PASSWORD=${REDIS_PASSWORD:-null}

echo "Connecting to Redis at $REDIS_HOST:$REDIS_PORT..."

if [ "$REDIS_PASSWORD" = "null" ] || [ -z "$REDIS_PASSWORD" ]; then
    until php -r "try { \$redis = new Redis(); \$redis->connect('$REDIS_HOST', $REDIS_PORT); \$redis->ping(); exit(0); } catch(Exception \$e) { echo \$e->getMessage(); exit(1); }" 2>&1; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "ERROR: Redis not available after $MAX_RETRIES attempts (60s). Exiting."
            exit 1
        fi
        echo "Redis is unavailable - sleeping ($RETRY_COUNT/$MAX_RETRIES)"
        sleep 2
    done
else
    until php -r "try { \$redis = new Redis(); \$redis->connect('$REDIS_HOST', $REDIS_PORT); \$redis->auth('$REDIS_PASSWORD'); \$redis->ping(); exit(0); } catch(Exception \$e) { echo \$e->getMessage(); exit(1); }" 2>&1; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "ERROR: Redis not available after $MAX_RETRIES attempts (60s). Exiting."
            exit 1
        fi
        echo "Redis is unavailable - sleeping ($RETRY_COUNT/$MAX_RETRIES)"
        sleep 2
    done
fi
echo "Redis is up"

# === Laravel initialization ===
echo "Initializing Laravel..."

# Create Laravel storage directories if they don't exist
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing

# Only chown if running as root
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
fi

# Link storage
echo "Linking storage..."
php artisan storage:link || true

# Cache config for production
echo "Caching configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Laravel initialization complete!"
echo "Starting Nginx + PHP-FPM via Supervisor..."

# Execute the CMD (supervisord)
exec "$@"
