.PHONY: test clean help db-shell db-logs

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'

test: ## Run all tests using Docker Compose
	@echo "Running tests with Docker Compose..."
	@docker-compose -f docker-compose.test.yml up --abort-on-container-exit --exit-code-from php-test
	@docker-compose -f docker-compose.test.yml down -v

clean: ## Clean up Docker containers and volumes
	@echo "Cleaning up..."
	@docker-compose -f docker-compose.test.yml down -v 2>/dev/null || true
	@echo "Cleanup complete!"

db-shell: ## Connect to test database shell (run 'make test' first and keep it running)
	@docker-compose -f docker-compose.test.yml exec test-db mysql -u root -ptestpass newsletter_test

db-logs: ## Show database logs
	@docker-compose -f docker-compose.test.yml logs test-db
