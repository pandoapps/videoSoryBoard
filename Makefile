.PHONY: install up down restart logs shell tinker migrate migrate-fresh seed test queue format lint assets-build artisan

# Colors
GREEN  := \033[0;32m
YELLOW := \033[0;33m
NC     := \033[0m

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

install: ## Full setup: build, deps, env, key, migrate, up
	@echo "$(GREEN)Building Docker containers...$(NC)"
	docker compose build
	@echo "$(GREEN)Starting services...$(NC)"
	docker compose up -d
	@echo "$(GREEN)Waiting for services to be ready...$(NC)"
	sleep 5
	@if [ ! -f .env ]; then cp .env.example .env; echo "$(YELLOW).env created from .env.example$(NC)"; fi
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	docker compose exec node npm install --legacy-peer-deps
	@echo "$(GREEN)Installation complete! App available at http://localhost:8080$(NC)"

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

logs: ## Tail all container logs
	docker compose logs -f

shell: ## Open shell in app container
	docker compose exec app bash

tinker: ## Open Laravel Tinker
	docker compose exec app php artisan tinker

migrate: ## Run migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Fresh migrations with seed
	docker compose exec app php artisan migrate:fresh --seed

seed: ## Run seeders
	docker compose exec app php artisan db:seed

test: ## Run tests
	docker compose exec app php artisan test

queue: ## Run queue worker in foreground
	docker compose exec app php artisan queue:work redis --queue=pipeline,default --verbose

format: ## Format PHP code with Pint
	docker compose exec app ./vendor/bin/pint

lint: ## Lint PHP code with Pint (dry-run)
	docker compose exec app ./vendor/bin/pint --test

assets-build: ## Build frontend for production
	docker compose exec node npm run build

artisan: ## Run any artisan command (usage: make artisan cmd="migrate:status")
	docker compose exec app php artisan $(cmd)
