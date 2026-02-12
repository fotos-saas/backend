#!/bin/bash
set -e
cd /var/www/html

# === .env setup: .env.dev -> .env (ha nincs proper dev .env) ===
if [ -f ".env.dev" ]; then
    if [ ! -f ".env" ] || grep -q "APP_DEBUG=false" ".env" 2>/dev/null; then
        echo "[DEV] .env.dev masolasa .env-be..."
        cp .env.dev .env
        # APP_KEY generalasa ha placeholder
        if grep -q "GENERATE_ME" .env 2>/dev/null; then
            echo "[DEV] APP_KEY generalasa..."
            php artisan key:generate --force --no-interaction 2>/dev/null || true
        fi
    fi
fi

# === TIMEOUT CONFIGURATION ===
MAX_RETRIES=30
RETRY_COUNT=0

# === Wait for PostgreSQL ===
echo "[DEV] Varakozas a PostgreSQL-re..."
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-photo_stack}
DB_USERNAME=${DB_USERNAME:-photo_stack}
DB_PASSWORD=${DB_PASSWORD:-secret}

until php -r "try { new PDO('pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "[DEV] HIBA: PostgreSQL nem erheto el $MAX_RETRIES probalkozas utan."
        exit 1
    fi
    echo "[DEV] PostgreSQL nem erheto el - varakozas ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done
echo "[DEV] PostgreSQL kesz!"

# === Wait for Redis ===
echo "[DEV] Varakozas a Redis-re..."
RETRY_COUNT=0
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}

until php -r "try { \$r = new Redis(); \$r->connect('$REDIS_HOST', $REDIS_PORT); \$r->ping(); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "[DEV] HIBA: Redis nem erheto el."
        exit 1
    fi
    echo "[DEV] Redis nem erheto el - varakozas ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done
echo "[DEV] Redis kesz!"

# === Composer install (dev fuggosegekkel) ===
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "[DEV] composer install futtatasa..."
    composer install --no-interaction
fi

# === Container role ===
ROLE=${CONTAINER_ROLE:-app}
echo "[DEV] Container role: $ROLE"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing

# === APP ===
if [ "$ROLE" = "app" ]; then
    echo "[DEV] Migraciok futtatasa..."
    php artisan migrate --force 2>/dev/null || echo "[DEV] FIGYELMEZTETES: Migracio hiba (valoszinuleg ures DB - futtasd: make sync-db)"

    echo "[DEV] Storage link..."
    php artisan storage:link 2>/dev/null || true

    echo "[DEV] Cache torlese (dev-ben nincs cache)..."
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true

    echo "[DEV] PHP-FPM inditas..."
    exec php-fpm
fi

# === QUEUE WORKER ===
if [ "$ROLE" = "queue" ]; then
    echo "[DEV] Queue worker inditas..."
    exec php artisan queue:work redis \
        --queue=emails,face-recognition,default \
        --sleep=3 \
        --tries=3 \
        --timeout=90 \
        --no-interaction
fi

# === SCHEDULER ===
if [ "$ROLE" = "scheduler" ]; then
    echo "[DEV] Scheduler inditas..."
    while true; do
        timeout 55 php artisan schedule:run --verbose --no-interaction || true
        sleep 60
    done
fi

# === REVERB ===
if [ "$ROLE" = "reverb" ]; then
    echo "[DEV] Reverb WebSocket inditas..."
    exec php artisan reverb:start \
        --host=0.0.0.0 \
        --port=8080 \
        --no-interaction
fi

echo "[DEV] Ismeretlen role: $ROLE"
exit 1
