# Raccourcis de développement Yagaz. Lance `make aide` pour la liste.
.DEFAULT_GOAL := aide
.PHONY: aide infra-up infra-down infra-reset api-serve api-test web-dev web-build app-start

aide: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

infra-up: ## Démarre l'infra locale (db TimescaleDB, MQTT, redis)
	docker compose up -d

infra-down: ## Arrête l'infra locale
	docker compose down

infra-reset: ## Arrête et EFFACE les volumes de données locaux
	docker compose down -v

api-serve: ## Lance l'API Laravel (http://localhost:8000)
	cd yagaz-api && php artisan serve

api-test: ## Lance les tests de l'API
	cd yagaz-api && php artisan test

web-dev: ## Lance le dashboard web en dev
	cd yagaz-web && bun run dev

web-build: ## Build de production du web
	cd yagaz-web && bun run build

app-start: ## Lance l'app mobile (Expo)
	cd yagaz-app && bun run start
