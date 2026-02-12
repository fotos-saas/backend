#!/bin/bash
#
# PhotoStack - Dev kornyezet exportalasa hordozhato archivba
#
# Hasznalat:
#   make export
#   bash scripts/dev-export.sh
#
# Eredmeny:
#   exports/photostack-dev-export.tar.gz  (DB + storage + .env.dev)
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

# Szinek
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}==============================${NC}"
echo -e "${BLUE}PhotoStack Dev Export${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""

# === Ellenorzesek ===

# Docker container fut?
if ! docker ps --format '{{.Names}}' | grep -q "$LOCAL_CONTAINER"; then
    echo -e "${RED}HIBA: $LOCAL_CONTAINER container nem fut!${NC}"
    echo "Inditsd el: make up"
    exit 1
fi

# .env.dev letezik?
if [ ! -f "$BACKEND_DIR/.env.dev" ]; then
    echo -e "${RED}HIBA: .env.dev nem talalhato!${NC}"
    exit 1
fi

# Export konyvtar
mkdir -p "$EXPORT_DIR"

# Regi export torlese
rm -f "$EXPORT_DIR/db.sql.gz" "$EXPORT_DIR/storage.tar.gz" "$EXPORT_DIR/.env.dev" "$EXPORT_DIR/$ARCHIVE_NAME"

# === 1. DB dump ===
echo -e "${YELLOW}[1/4] Adatbazis exportalasa...${NC}"
docker exec "$LOCAL_CONTAINER" pg_dump -U "$LOCAL_USER" -d "$LOCAL_DB" \
    --no-owner --no-acl --clean --if-exists \
    | gzip > "$EXPORT_DIR/db.sql.gz"
DB_SIZE=$(du -h "$EXPORT_DIR/db.sql.gz" | cut -f1)
echo -e "${GREEN}  DB dump: $DB_SIZE${NC}"

# === 2. Storage ===
echo -e "${YELLOW}[2/4] Storage exportalasa...${NC}"
STORAGE_PATH="$BACKEND_DIR/storage/app/public"
if [ -d "$STORAGE_PATH" ] && [ "$(ls -A "$STORAGE_PATH" 2>/dev/null)" ]; then
    tar -czf "$EXPORT_DIR/storage.tar.gz" -C "$BACKEND_DIR/storage/app" public
    STORAGE_SIZE=$(du -h "$EXPORT_DIR/storage.tar.gz" | cut -f1)
    echo -e "${GREEN}  Storage: $STORAGE_SIZE${NC}"
else
    echo -e "${YELLOW}  Storage ures vagy nem letezik, kihagyva${NC}"
fi

# === 3. .env.dev ===
echo -e "${YELLOW}[3/4] .env.dev masolasa...${NC}"
cp "$BACKEND_DIR/.env.dev" "$EXPORT_DIR/.env.dev"
echo -e "${GREEN}  .env.dev masolva${NC}"

# === 4. Vegso archiv ===
echo -e "${YELLOW}[4/4] Archiv keszitese...${NC}"

# Csak a letező fajlokat csomagoljuk
ARCHIVE_FILES=()
cd "$EXPORT_DIR"
[ -f "db.sql.gz" ] && ARCHIVE_FILES+=("db.sql.gz")
[ -f "storage.tar.gz" ] && ARCHIVE_FILES+=("storage.tar.gz")
[ -f ".env.dev" ] && ARCHIVE_FILES+=(".env.dev")

tar -czf "$ARCHIVE_NAME" "${ARCHIVE_FILES[@]}"

# Koztes fajlok torlese
rm -f db.sql.gz storage.tar.gz .env.dev

ARCHIVE_SIZE=$(du -h "$EXPORT_DIR/$ARCHIVE_NAME" | cut -f1)

echo ""
echo -e "${BLUE}==============================${NC}"
echo -e "${GREEN}Export KESZ!${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""
echo -e "Archiv: ${GREEN}$EXPORT_DIR/$ARCHIVE_NAME${NC}"
echo -e "Meret:  ${GREEN}$ARCHIVE_SIZE${NC}"
echo ""
echo "Hasznalat masik gepen:"
echo "  1. git clone + cp archiv exports/"
echo "  2. cd backend && make import"
