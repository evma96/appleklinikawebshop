.DEFAULT_GOAL := check

COMPOSE := docker compose

.PHONY: install up down test test-unit test-integration test-buyback test-buyback-domain test-buyback-persistence test-buyback-legacy test-buyback-pricing-admin test-buyback-pricing-engine lint format static quality quality-fix check

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

test-buyback-domain:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/domain.php

test-buyback-persistence:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/persistence.php

test-buyback-legacy:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/legacy.php

test-buyback-pricing-admin:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/pricing-admin.php

test-buyback-pricing-engine:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/pricing-engine.php

lint:
	@echo "No linter is configured yet."

format:
	@echo "No formatter is configured yet."

static:
	@echo "No static analysis tool is configured yet."

quality: lint static

quality-fix: format

check: test quality
