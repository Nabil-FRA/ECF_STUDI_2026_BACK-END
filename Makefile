# ============================================
# Makefile - Vite & Gourmand
# ============================================
# Commandes rapides pour gérer le projet Docker
# Usage: make <commande>
# ============================================

.PHONY: help build up down restart logs shell db-shell mongo-shell migrate fixtures clear

# Couleurs
GREEN=\033[0;32m
NC=\033[0m

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-15s$(NC) %s\n", $$1, $$2}'

# ============================================
# DOCKER
# ============================================

build: ## Construire les images Docker
	docker-compose build

up: ## Démarrer tous les conteneurs
	docker-compose up -d

down: ## Arrêter tous les conteneurs
	docker-compose down

restart: down up ## Redémarrer tous les conteneurs

logs: ## Voir les logs (tous les services)
	docker-compose logs -f

logs-app: ## Voir les logs de l'application
	docker-compose logs -f app

ps: ## Voir l'état des conteneurs
	docker-compose ps

# ============================================
# SHELL
# ============================================

shell: ## Ouvrir un shell dans le conteneur app
	docker-compose exec app bash

db-shell: ## Ouvrir un shell PostgreSQL
	docker-compose exec database psql -U vg_user -d vite_et_gourmand

mongo-shell: ## Ouvrir un shell MongoDB
	docker-compose exec mongodb mongosh vite_et_gourmand

# ============================================
# SYMFONY
# ============================================

migrate: ## Exécuter les migrations
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Charger les fixtures
	docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction

clear: ## Vider le cache Symfony
	docker-compose exec app php bin/console cache:clear

schema-update: ## Mettre à jour le schéma (dev only)
	docker-compose exec app php bin/console doctrine:schema:update --force

# ============================================
# COMPOSER
# ============================================

composer-install: ## Installer les dépendances
	docker-compose exec app composer install

composer-update: ## Mettre à jour les dépendances
	docker-compose exec app composer update

# ============================================
# INSTALLATION INITIALE
# ============================================

install: build up composer-install migrate ## Installation complète (première fois)
	@echo "$(GREEN)✅ Installation terminée!$(NC)"
	@echo "Application: http://localhost:8080"
	@echo "Mailhog: http://localhost:8025"
	@echo "Adminer: http://localhost:8081"

# ============================================
# NETTOYAGE
# ============================================

clean: down ## Nettoyer les conteneurs et volumes
	docker-compose down -v --remove-orphans
	docker system prune -f

reset: clean install ## Reset complet (supprime les données!)
