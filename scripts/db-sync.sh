#!/bin/bash
#
# PhotoStack - Produkcios DB szinkronizalas lokalis Docker-be
#
# Hasznalat:
#   ./scripts/db-sync.sh              # Teljes szinkronizalas + anonimizalas
#   ./scripts/db-sync.sh --no-sanitize # Szanitizalas nelkul (NEM AJANLOTT!)
#   ./scripts/db-sync.sh --schema      # Csak schema (adat nelkul)
#
# Elofeltetelek:
#   - SSH hozzaferes: ssh root@89.167.19.19
#   - Lokalis Docker fut: docker compose -f docker-compose.dev.yml ps

set -euo pipefail

# === Konfiguracio ===
REMOTE_HOST="89.167.19.19"
REMOTE_USER="root"
LOCAL_CONTAINER="photostack-postgres"
LOCAL_DB="photo_stack"
LOCAL_USER="photo_stack"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SANITIZE_SQL="$SCRIPT_DIR/sanitize.sql"

# Szinek
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# === Argumentumok ===
SCHEMA_ONLY=false
NO_SANITIZE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --schema)
            SCHEMA_ONLY=true
            shift
            ;;
        --no-sanitize)
            NO_SANITIZE=true
            shift
            ;;
        *)
            echo -e "${RED}Ismeretlen argumentum: $1${NC}"
            echo "Hasznalat: $0 [--schema] [--no-sanitize]"
            exit 1
            ;;
    esac
done

# === Ellenorzesek ===
echo -e "${BLUE}==============================${NC}"
echo -e "${BLUE}PhotoStack DB Szinkronizalas${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""

# Docker container fut?
if ! docker ps --format '{{.Names}}' | grep -q "$LOCAL_CONTAINER"; then
    echo -e "${RED}HIBA: $LOCAL_CONTAINER container nem fut!${NC}"
    echo "Inditsd el: docker compose -f docker-compose.dev.yml up -d postgres"
    exit 1
fi

# SSH elerheto?
echo -e "${YELLOW}[1/5] SSH kapcsolat ellenorzese...${NC}"
if ! ssh -o ConnectTimeout=5 -o BatchMode=yes "$REMOTE_USER@$REMOTE_HOST" "echo ok" 2>/dev/null; then
    echo -e "${RED}HIBA: SSH kapcsolat sikertelen ($REMOTE_USER@$REMOTE_HOST)${NC}"
    exit 1
fi
echo -e "${GREEN}  SSH OK${NC}"

# === Coolify PostgreSQL container megkeresese ===
echo -e "${YELLOW}[2/5] Coolify PostgreSQL container keresese...${NC}"
REMOTE_PG_CONTAINER=$(ssh "$REMOTE_USER@$REMOTE_HOST" \
    "docker ps --format '{{.Names}}' | grep -i postgres | head -1")

if [ -z "$REMOTE_PG_CONTAINER" ]; then
    echo -e "${RED}HIBA: Nem talalhato PostgreSQL container a szerveren!${NC}"
    echo "Probald: ssh $REMOTE_USER@$REMOTE_HOST 'docker ps | grep postgres'"
    exit 1
fi
echo -e "${GREEN}  Container: $REMOTE_PG_CONTAINER${NC}"

# === Dump letrehozasa ===
echo -e "${YELLOW}[3/5] Adatbazis dump keszitese...${NC}"

PG_DUMP_OPTS="--no-owner --no-acl --clean --if-exists"
if [ "$SCHEMA_ONLY" = true ]; then
    PG_DUMP_OPTS="$PG_DUMP_OPTS --schema-only"
    echo "  (Csak schema, adat nelkul)"
fi

# Dump + pipe kozvetlen a lokalis container-be
echo -e "${YELLOW}[4/5] Dump importalasa lokalis PostgreSQL-be...${NC}"
echo "  Ez percekig tarthat az adatbazis merettol fuggoen..."

# Drop + recreate DB
docker exec "$LOCAL_CONTAINER" bash -c "
    psql -U $LOCAL_USER -d postgres -c 'DROP DATABASE IF EXISTS $LOCAL_DB;'
    psql -U $LOCAL_USER -d postgres -c 'CREATE DATABASE $LOCAL_DB OWNER $LOCAL_USER;'
" 2>/dev/null

# Stream dump kozvetlen a lokalis container-be (nincs temp fajl)
ssh "$REMOTE_USER@$REMOTE_HOST" \
    "docker exec $REMOTE_PG_CONTAINER pg_dump -U $LOCAL_USER -d $LOCAL_DB $PG_DUMP_OPTS" \
    | docker exec -i "$LOCAL_CONTAINER" psql -U "$LOCAL_USER" -d "$LOCAL_DB" --quiet 2>/dev/null

echo -e "${GREEN}  Import kesz!${NC}"

# === Szanitizalas ===
if [ "$NO_SANITIZE" = true ]; then
    echo -e "${RED}[5/5] Szanitizalas KIHAGYVA (--no-sanitize)${NC}"
    echo -e "${RED}  FIGYELMEZTETES: A lokalis DB szanitizalatlan produkcios adatot tartalmaz!${NC}"
else
    echo -e "${YELLOW}[5/5] Adatok szanitizalasa (GDPR)...${NC}"
    if [ -f "$SANITIZE_SQL" ]; then
        docker exec -i "$LOCAL_CONTAINER" psql -U "$LOCAL_USER" -d "$LOCAL_DB" < "$SANITIZE_SQL" 2>/dev/null
        echo -e "${GREEN}  Szanitizalas kesz!${NC}"
    else
        echo -e "${RED}  HIBA: $SANITIZE_SQL nem talalhato!${NC}"
        exit 1
    fi
fi

# === Veg ===
echo ""
echo -e "${BLUE}==============================${NC}"
echo -e "${GREEN}Szinkronizalas KESZ!${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""
echo "Ellenorzes:"
echo "  make shell-db                    # psql shell"
echo "  make tinker                      # Laravel tinker"
echo ""
if [ "$NO_SANITIZE" = false ]; then
    echo "Bejelentkezes:"
    echo "  Email:    admin@dev.test"
    echo "  Jelszo:   password"
fi
