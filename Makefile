.DEFAULT_GOAL := check

COMPOSE := docker compose

.PHONY: install up down test test-unit test-integration test-inventory-catalog test-theme-storefront test-theme-account-shell test-customer-address-book test-customer-address-book-persistence test-customer-address-book-migration test-customer-address-book-account test-buyback test-buyback-domain test-buyback-persistence test-buyback-legacy test-buyback-pricing-admin test-buyback-condition-admin test-buyback-battery-admin test-buyback-offer-mode-admin test-buyback-pricing-engine test-buyback-pricebook-activation test-buyback-public-active-book test-buyback-public-request test-buyback-mail-notifications test-buyback-local-demo lint format static quality quality-fix check

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

test-inventory-catalog:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-inventory/tests/catalog-storage.php

test-theme-storefront:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/themes/appleklinika-theme/tests/product-collection-empty-state.php

test-theme-account-shell:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/themes/appleklinika-theme/tests/account-shell.php

test-customer-address-book:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-customer-address-book/tests/domain.php

test-customer-address-book-persistence:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-customer-address-book/tests/persistence.php

test-customer-address-book-migration:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-customer-address-book/tests/migration.php

test-customer-address-book-account:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-customer-address-book/tests/account.php

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

test-buyback-condition-admin: test-buyback-pricing-admin

test-buyback-battery-admin: test-buyback-pricing-admin

test-buyback-offer-mode-admin: test-buyback-pricing-admin

test-buyback-pricing-engine:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/pricing-engine.php

test-buyback-pricebook-activation:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/pricebook-activation.php

test-buyback-public-active-book:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/public-active-price-book.php

test-buyback-visual-states:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/visual-state-catalogue.php

test-buyback-public-request:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/public-request-submission.php

test-buyback-mail-notifications:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/mail-notifications.php

test-buyback-local-demo:
	$(COMPOSE) exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-buyback/tests/local-demo.php

lint:
	@echo "No linter is configured yet."

format:
	@echo "No formatter is configured yet."

static:
	@echo "No static analysis tool is configured yet."

quality: lint static

quality-fix: format

check: test quality
