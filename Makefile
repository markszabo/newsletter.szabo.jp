.PHONY: test clean help db-shell db-logs

# Detect if we're using podman or docker
CONTAINER_RUNTIME := $(shell command -v podman 2>/dev/null || command -v docker 2>/dev/null)
COMPOSE_CMD := $(CONTAINER_RUNTIME) compose

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'

test: ## Run all tests using Docker/Podman Compose
	@echo "Running tests with $(CONTAINER_RUNTIME) compose..."
	@$(COMPOSE_CMD) -f docker-compose.test.yml up --abort-on-container-exit --exit-code-from php-test
	@$(COMPOSE_CMD) -f docker-compose.test.yml down -v

clean: ## Clean up containers and volumes
	@echo "Cleaning up..."
	@$(COMPOSE_CMD) -f docker-compose.test.yml down -v 2>/dev/null || true
	@echo "Cleanup complete!"

db-shell: ## Connect to test database shell (run 'make test' first and keep it running)
	@$(COMPOSE_CMD) -f docker-compose.test.yml exec test-db mysql -u root -ptestpass newsletter_test

db-logs: ## Show database logs
	@$(COMPOSE_CMD) -f docker-compose.test.yml logs test-db
