#!/usr/bin/env bash
set -euo pipefail
# Use an already installed WooCommerce source tree. No download/provider traffic.
# Usage: scripts/test-production-lifecycle.sh /absolute/path/to/woocommerce
repo=$(cd "$(dirname "$0")/.." && pwd)
woo=${1:?Pass the local WooCommerce plugin source directory}
test -f "$woo/woocommerce.php"
woo=$(cd "$woo" && pwd)
run=$(mktemp -d "${TMPDIR:-/tmp}/ak-production-simulation.XXXXXX")
project="ak-production-simulation-$(date +%s)-$$"
cleanup() { docker compose -p "$project" -f "$run/compose.yaml" down -v --remove-orphans >/dev/null 2>&1 || true; rm -rf "$run"; }
trap cleanup EXIT
mkdir "$run/control"
chmod 700 "$run" "$run/control"
# Disposable, generated database credentials never enter output or repository.
python3 - "$run" "$repo" "$woo" <<'PYTHON'
import pathlib,secrets,json,sys
run,repo,woo=map(pathlib.Path,sys.argv[1:])
(run/'.env').write_text('DB_PASSWORD='+secrets.token_hex(24)+'\nROOT_PASSWORD='+secrets.token_hex(24)+'\n')
(run/'.env').chmod(0o600)
q=lambda p:json.dumps(str(p))
(run/'offline.ini').write_text('disable_functions=curl_exec,mail\n')
(run/'compose.yaml').write_text('''services:
  db:
    image: mariadb:11.4
    environment:
      MARIADB_DATABASE: ak_production_fixture
      MARIADB_USER: fixture
      MARIADB_PASSWORD: ${DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${ROOT_PASSWORD}
    networks: [isolated]
    volumes: [db:/var/lib/mysql]
  wp:
    image: wordpress:7.0.2-php8.2-apache
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: ak_production_fixture
      WORDPRESS_DB_USER: fixture
      WORDPRESS_DB_PASSWORD: ${DB_PASSWORD}
      WORDPRESS_CONFIG_EXTRA: |
        define('WP_ENVIRONMENT_TYPE','production');
        define('AK_PRODUCTION_SIMULATION',true);
        define('WP_HTTP_BLOCK_EXTERNAL',true);
        define('DISABLE_WP_CRON',true);
        define('AUTOMATIC_UPDATER_DISABLED',true);
        define('WP_HOME','https://shop.example.invalid');
        define('WP_SITEURL','https://shop.example.invalid');
        define('APPLEKLINIKA_PRODUCTION_CONFIG_FILE','/control/activation.json');
        define('WP_DEBUG_DISPLAY',false);
        define('WP_DISABLE_FATAL_ERROR_HANDLER',true);
    volumes:
      - wp:/var/www/html
      - '''+q(str(woo)+':/var/www/html/wp-content/plugins/woocommerce:ro')+'''
      - '''+q(str(repo/'wordpress/wp-content/plugins/appleklinika-backoffice')+':/var/www/html/wp-content/plugins/appleklinika-backoffice:ro')+'''
      - '''+q(str(repo/'wordpress/wp-content/mu-plugins/appleklinika-production-safety.php')+':/var/www/html/wp-content/mu-plugins/appleklinika-production-safety.php:ro')+'''
      - '''+q(str(repo/'wordpress/wp-content/plugins/appleklinika-backoffice/tests/integration/production/providers.php')+':/var/www/html/wp-content/mu-plugins/00-offline-fixture.php:ro')+'''
      - '''+q(str(repo/'wordpress/wp-content/plugins/appleklinika-backoffice/tests/integration/production')+':/fixtures:ro')+'''
      - '''+q(str(repo)+':/source:ro')+'''
      - '''+q(str(run/'control')+':/control')+'''
      - '''+q(str(run/'offline.ini')+':/usr/local/etc/php/conf.d/offline.ini:ro')+'''
    networks: [isolated]
networks:
  isolated:
    internal: true
volumes:
  db:
  wp:
''')
PYTHON
compose=(docker compose --env-file "$run/.env" -p "$project" -f "$run/compose.yaml")
"${compose[@]}" up -d >/dev/null
for attempt in $(seq 1 40); do
 if "${compose[@]}" exec -T db sh -c 'mariadb --protocol=tcp -h127.0.0.1 -ufixture -p"$MARIADB_PASSWORD" -D ak_production_fixture -Nse "SELECT 1"' >/dev/null 2>&1 && "${compose[@]}" exec -T wp test -f /var/www/html/wp-config.php; then break; fi
 sleep 1
done
"${compose[@]}" exec -T wp php -r '$m = new mysqli("db", "fixture", getenv("WORDPRESS_DB_PASSWORD"), "ak_production_fixture"); if ($m->connect_errno) exit(1);'
"${compose[@]}" exec -T wp php /fixtures/install.php
"${compose[@]}" exec -T wp php /fixtures/scenario.php
cat "$run/control/results.json"
# Trap destroys containers, volumes, credentials, generated PDFs and QA data.
