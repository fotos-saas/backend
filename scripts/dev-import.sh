#!/bin/bash
#
# PhotoStack - Dev kornyezet importalasa archivbol
#
# Hasznalat:
#   make import
#   make import file=path/to/photostack-dev-export.tar.gz
#   bash scripts/dev-import.sh [archiv-eleresi-ut]
#
# Elofeltetelek:
#   - Docker Desktop telepitve
#   - docker-compose.dev.yml elerheto
#

set -euo pipefail

# === Konfiguracio ===
LOCAL_CONTAINER="photostack-postgres"
LOCAL_DB="photo_stack"
LOCAL_USER="photo_stack"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(dirname "$SCRIPT_DIR")"
EXPORT_DIR="$BACKEND_DIR/exports"
ARCHIVE_NAME="photostack-dev-export.tar.gz"
COMPOSE="docker compose -f $BACKEND_DIR/docker-compose.dev.yml"

# Szinek
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}==============================${NC}"
echo -e "${BLUE}PhotoStack Dev Import${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""

# === Archiv megkeresese ===
ARCHIVE_PATH="${1:-$EXPORT_DIR/$ARCHIVE_NAME}"

if [ ! -f "$ARCHIVE_PATH" ]; then
    echo -e "${RED}HIBA: Archiv nem talalhato: $ARCHIVE_PATH${NC}"
    echo ""
    echo "Hasznalat:"
    echo "  make import                          # exports/$ARCHIVE_NAME"
    echo "  make import file=/path/to/archiv.tar.gz"
    exit 1
fi

echo -e "${GREEN}Archiv: $ARCHIVE_PATH${NC}"
ARCHIVE_SIZE=$(du -h "$ARCHIVE_PATH" | cut -f1)
echo -e "${GREEN}Meret:  $ARCHIVE_SIZE${NC}"
echo ""

# === Kicsomagolas temp konyvtarba ===
TEMP_DIR=$(mktemp -d)
trap 'rm -rf "$TEMP_DIR"' EXIT

echo -e "${YELLOW}[1/6] Archiv kicsomagolasa...${NC}"
tar -xzf "$ARCHIVE_PATH" -C "$TEMP_DIR"
echo -e "${GREEN}  Kicsomagolva${NC}"

# === .env.dev ===
echo -e "${YELLOW}[2/6] .env.dev ellenorzese...${NC}"
if [ ! -f "$BACKEND_DIR/.env.dev" ]; then
    if [ -f "$TEMP_DIR/.env.dev" ]; then
        cp "$TEMP_DIR/.env.dev" "$BACKEND_DIR/.env.dev"
        echo -e "${GREEN}  .env.dev visszaallitva${NC}"
    else
        echo -e "${RED}  HIBA: .env.dev nincs az archivban es lokálisan sem!${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}  .env.dev mar letezik, kihagyva${NC}"
fi

# === Docker kontenerek inditasa ===
echo -e "${YELLOW}[3/6] Docker kontenerek ellenorzese...${NC}"
if ! docker ps --format '{{.Names}}' | grep -q "$LOCAL_CONTAINER"; then
    echo "  Kontenerek inditasa..."
    cd "$BACKEND_DIR" && $COMPOSE up -d
    echo "  Varakozas a PostgreSQL-re..."
    sleep 5
    # Varjuk meg, mig a postgres healthy lesz
    for i in $(seq 1 30); do
        if docker exec "$LOCAL_CONTAINER" pg_isready -U "$LOCAL_USER" -d "$LOCAL_DB" >/dev/null 2>&1; then
            break
        fi
        if [ "$i" -eq 30 ]; then
            echo -e "${RED}HIBA: PostgreSQL nem indult el 30 masodperc alatt!${NC}"
            exit 1
        fi
        sleep 1
    done
    echo -e "${GREEN}  Kontenerek futnak${NC}"
else
    echo -e "${GREEN}  Kontenerek mar futnak${NC}"
fi

# === DB importalas ===
echo -e "${YELLOW}[4/6] Adatbazis importalasa...${NC}"
if [ -f "$TEMP_DIR/db.sql.gz" ]; then
    # Terminate connections + Drop + Create
    docker exec "$LOCAL_CONTAINER" bash -c "
        psql -U $LOCAL_USER -d postgres -c \"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$LOCAL_DB' AND pid <> pg_backend_pid();\" 2>/dev/null
        psql -U $LOCAL_USER -d postgres -c 'DROP DATABASE IF EXISTS $LOCAL_DB;'
        psql -U $LOCAL_USER -d postgres -c 'CREATE DATABASE $LOCAL_DB OWNER $LOCAL_USER;'
    "
    # Import
    gunzip -c "$TEMP_DIR/db.sql.gz" | docker exec -i "$LOCAL_CONTAINER" psql -U "$LOCAL_USER" -d "$LOCAL_DB" --quiet 2>/dev/null
    echo -e "${GREEN}  DB importalva${NC}"
else
    echo -e "${YELLOW}  db.sql.gz nem talalhato az archivban, kihagyva${NC}"
fi

# === Storage importalas ===
echo -e "${YELLOW}[5/6] Storage importalasa...${NC}"
if [ -f "$TEMP_DIR/storage.tar.gz" ]; then
    mkdir -p "$BACKEND_DIR/storage/app"
    tar -xzf "$TEMP_DIR/storage.tar.gz" -C "$BACKEND_DIR/storage/app"
    echo -e "${GREEN}  Storage importalva${NC}"
else
    echo -e "${YELLOW}  storage.tar.gz nem talalhato az archivban, kihagyva${NC}"
fi

# === Storage symlink ===
echo -e "${YELLOW}[6/6] Storage symlink ellenorzese...${NC}"
SYMLINK_PATH="$BACKEND_DIR/public/storage"
if [ -L "$SYMLINK_PATH" ]; then
    echo -e "${GREEN}  Symlink mar letezik${NC}"
elif [ -e "$SYMLINK_PATH" ]; then
    echo -e "${YELLOW}  $SYMLINK_PATH letezik de nem symlink - kezi ellenorzes szukseges${NC}"
else
    ln -s "$BACKEND_DIR/storage/app/public" "$SYMLINK_PATH"
    echo -e "${GREEN}  Symlink letrehozva${NC}"
fi

# === Veg ===
echo ""
echo -e "${BLUE}==============================${NC}"
echo -e "${GREEN}Import KESZ!${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""
echo "Szolgaltatasok:"
echo "  Laravel API:  http://localhost:8000"
echo "  Mailpit:      http://localhost:8025"
echo "  PostgreSQL:   localhost:5434"
echo "  Redis:        localhost:6380"
echo ""
echo "Frontend: cd ../frontend && npm install && npm run start:local"
