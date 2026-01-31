#!/bin/bash

set -e

# Change to app directory
cd /var/www/html

# === TIMEOUT CONFIGURATION ===
MAX_RETRIES=30  # 30 * 2s = 60 seconds max wait
RETRY_COUNT=0

# === Wait for PostgreSQL with timeout ===
echo "Waiting for PostgreSQL..."
DB_DATABASE=${DB_DATABASE:-photo_stack}
DB_USERNAME=${DB_USERNAME:-photo_stack}
DB_PASSWORD=${DB_PASSWORD:-secret}

until php -r "try { new PDO('pgsql:host=postgres;port=5432;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
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
REDIS_PASSWORD=${REDIS_PASSWORD:-null}

if [ "$REDIS_PASSWORD" = "null" ] || [ -z "$REDIS_PASSWORD" ]; then
    # No password
    until php -r "try { \$redis = new Redis(); \$redis->connect('redis', 6379); \$redis->ping(); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "ERROR: Redis not available after $MAX_RETRIES attempts (60s). Exiting."
            exit 1
        fi
        echo "Redis is unavailable - sleeping ($RETRY_COUNT/$MAX_RETRIES)"
        sleep 2
    done
else
    # With password
    until php -r "try { \$redis = new Redis(); \$redis->connect('redis', 6379); \$redis->auth('$REDIS_PASSWORD'); \$redis->ping(); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
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

# === Determine container role ===
ROLE=${CONTAINER_ROLE:-app}
echo "Container role: $ROLE"

# Create Laravel storage directories if they don't exist
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing

# Only chown if running as root (for backwards compatibility)
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage
    chmod -R 775 storage
fi

# === APP CONTAINER ===
if [ "$ROLE" = "app" ]; then
    echo "Clearing all caches (including stale bootstrap cache)..."

    echo "Linking storage..."
    php artisan storage:link || true

    echo "Starting PHP-FPM..."
    exec php-fpm
fi

# === SCHEDULER CONTAINER ===
if [ "$ROLE" = "scheduler" ]; then
    echo "Starting Laravel Scheduler..."
    while true; do
        # Timeout 55s to prevent deadlock (leaves 5s buffer before next run)
        timeout 55 php artisan schedule:run --verbose --no-interaction || true
        sleep 60
    done
fi

# === QUEUE WORKER CONTAINER ===
if [ "$ROLE" = "queue" ]; then
    echo "Starting Laravel Queue Worker..."
    # --timeout=90: individual job timeout
    # --max-time=3600: restart worker after 1 hour
    # --max-jobs=1000: restart after 1000 jobs (memory leak prevention)
    exec php artisan queue:work redis \
        --queue=emails,face-recognition,default \
        --sleep=3 \
        --tries=3 \
        --timeout=90 \
        --max-time=3600 \
        --max-jobs=1000 \
        --no-interaction
fi

# === REVERB (WebSocket) CONTAINER ===
if [ "$ROLE" = "reverb" ]; then
    echo "Starting Laravel Reverb WebSocket Server..."
    exec php artisan reverb:start \
        --host=0.0.0.0 \
        --port=8080 \
        --no-interaction
fi

echo "Unknown role: $ROLE"
exit 1
