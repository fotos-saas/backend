# PhotoStack SaaS - Fejlesztoi Parancsok
# Hasznalat: make <parancs>

COMPOSE = docker compose -f docker-compose.dev.yml
APP = $(COMPOSE) exec app
PREFIX = photostack

.PHONY: help up down restart build logs status \
        artisan tinker migrate fresh seed test lint \
        sync-db sync-schema \
        export import \
        composer-install composer-update \
        shell shell-db shell-redis \
        xdebug-on xdebug-off \
        clean nuke

# === Segitseg ===
help: ## Parancsok listaja
	@echo ""
	@echo "PhotoStack - Fejlesztoi parancsok"
	@echo "================================="
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

# === Docker ===
up: ## Kontenerek inditasa
	$(COMPOSE) up -d
	@echo ""
	@echo "Szolgaltatasok:"
	@echo "  Laravel API:  http://localhost:8000"
	@echo "  Mailpit:      http://localhost:8026"
	@echo "  PostgreSQL:   localhost:5433"
	@echo "  Redis:        localhost:6380"
	@echo ""
	@echo "Frontend: cd ../frontend && npm run start:local"

down: ## Kontenerek leallitasa
	$(COMPOSE) down

restart: ## Kontenerek ujrainditasa
	$(COMPOSE) restart

build: ## Docker image ujraepitese
	$(COMPOSE) build --no-cache app
	$(COMPOSE) up -d

logs: ## Osszes log
	$(COMPOSE) logs -f

logs-app: ## App logok
	$(COMPOSE) logs -f app

logs-queue: ## Queue logok
	$(COMPOSE) logs -f queue

status: ## Kontener statuszok
	$(COMPOSE) ps

# === Laravel ===
artisan: ## Artisan parancs (pl: make artisan cmd="migrate:status")
	$(APP) php artisan $(cmd)

tinker: ## Laravel tinker
	$(APP) php artisan tinker

migrate: ## Migraciok futtatasa
	$(APP) php artisan migrate

fresh: ## migrate:fresh --seed
	$(APP) php artisan migrate:fresh --seed

seed: ## Seeder futtatasa
	$(APP) php artisan db:seed

test: ## PHPUnit tesztek
	$(APP) php artisan test $(filter)

lint: ## Laravel Pint
	$(APP) ./vendor/bin/pint

# === Adatbazis ===
sync-db: ## Prod DB szinkronizalas (anonimizalva)
	bash scripts/db-sync.sh

sync-schema: ## Csak DB schema (adat nelkul)
	bash scripts/db-sync.sh --schema

# === Export / Import ===
export: ## Dev kornyezet exportalasa (DB + storage + .env.dev)
	bash scripts/dev-export.sh

import: ## Dev kornyezet importalasa archivbol
	bash scripts/dev-import.sh $(file)

# === Composer ===
composer-install: ## Composer install
	$(APP) composer install

composer-update: ## Composer update
	$(APP) composer update

# === Shell ===
shell: ## Bash az app kontenerben
	$(APP) bash

shell-db: ## PostgreSQL shell
	docker exec -it $(PREFIX)-postgres psql -U photo_stack -d photo_stack

shell-redis: ## Redis CLI
	docker exec -it $(PREFIX)-redis redis-cli

# === Xdebug ===
xdebug-on: ## Xdebug bekapcsolasa
	XDEBUG_MODE=debug $(COMPOSE) up -d app
	@echo "Xdebug BEKAPCSOLVA (port 9003)"

xdebug-off: ## Xdebug kikapcsolasa
	XDEBUG_MODE=off $(COMPOSE) up -d app
	@echo "Xdebug KIKAPCSOLVA"

# === Takaritas ===
clean: ## Laravel cache torlese
	$(APP) php artisan config:clear
	$(APP) php artisan route:clear
	$(APP) php artisan view:clear
	$(APP) php artisan cache:clear

nuke: ## MINDEN torles (DB adatokkal egyutt!)
	@echo "FIGYELEM: Ez MINDENT torol (volume-ok, DB adatok)!"
	@read -p "Biztosan? (y/N) " confirm && [ "$$confirm" = "y" ] || exit 1
	$(COMPOSE) down -v --rmi local
	@echo "Minden torolve. Ujrainditashoz: make build"
