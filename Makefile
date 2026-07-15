.DEFAULT_GOAL := check

COMPOSE := docker compose

.PHONY: install up down test test-unit test-integration test-buyback lint format static quality quality-fix check

install:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	$(COMPOSE) pull

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

test: test-unit test-integration

test-unit:
	@echo "No unit test suite is configured yet."

test-integration:
	@echo "No integration test suite is configured yet. External live API calls are not allowed in tests."

test-buyback:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/smoke.php

lint:
	@echo "No linter is configured yet."

format:
	@echo "No formatter is configured yet."

static:
	@echo "No static analysis tool is configured yet."

quality: lint static

quality-fix: format

check: test quality
