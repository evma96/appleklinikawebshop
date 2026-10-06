# Durable LOCAL development — recovery and reconciliation 2026-09-28

## Current integration runtime

The sections below this one preserve the original recovery record. Since the
separately authorized reconciliation, **both running WordPress containers bind
source from `/Users/apple/Desktop/appleklinika-integration-active`**, branch
`feature/local-fulfillment-backoffice-integration`. The storefront and Back Office
protected source worktrees/branches remain unchanged; they are not the current
runtime source. Databases and WordPress/core volumes remain separate and unchanged.

Start the integrated storefront and Back Office from that directory:

```sh
cd /Users/apple/Desktop/appleklinika-integration-active
make up
docker compose --env-file .env.backoffice up -d
# Stop either without deleting database/core volumes:
docker compose stop
docker compose --env-file .env.backoffice stop
```

The ignored `.env` and `.env.backoffice` retain their respective LOCAL project,
port, database/core volume and URL values. Both select
`docker-compose.yml:docker-compose.local.yml:docker-compose.integration.yml`.
`LOCAL_RUNTIME_NETWORK` identifies each existing project's database network,
not a new/shared database. `LOCAL_UPLOADS_PATH` selects the corresponding private
copy under `.local-runtime/storefront-uploads` or `.local-runtime/backoffice-uploads`.
The protected original uploads are untouched. Keep an empty
`wordpress/wp-content/uploads` mount-point directory in the checkout, and retain
the ignored WooCommerce vendor dependency tree. These private/runtime files must
not be committed or copied into a deployment. Do not overwrite private environment
files with the example.

The integration overlay sets `AK_LOCAL_VERIFICATION=1` and mounts the LOCAL PHP
guard. WordPress HTTP requests and `wp_mail` fail locally; direct cURL execution,
socket transports, URL-file access and sendmail delivery are disabled. The runtime
also retains the recovery overlay's disabled cron/updates and read-only source
mount. Browser-side external embeds are separate. This is a deliberately offline
verification environment: provider failure here is not provider acceptance evidence.
The guard files are loaded only by the opt-in Compose overlay, never by the plugin
or a TEST/production deployment. Provider credentials/options were not edited.

The existing LOCAL Apache compatibility rules now also route `/backoffice/` to
its registered query variable, supporting the storefront's existing plain
permalinks without a database-wide URL/permalink change. Authentication and
capability checks remain in the Back Office router. The lifecycle plugin is now
active on the storefront LOCAL database as well as Back Office.

Run `make test` for the combined offline suites. `make test-backoffice-workflow`
and `make test-order-lifecycle` select that same suite; do not add their counts
as independent coverage. The runner has Docker network disabled.
`make quality` still reports the repository's unconfigured linter/static-analysis
placeholders; PHP syntax validation is part of the real combined suite.

See [LOCAL reconciliation](local-reconciliation.md) for verification and cleanup.

## Historical recovery record

## Sources and URLs

| Environment | Durable source | Branch / checkpoint | URL |
| --- | --- | --- | --- |
| Storefront | `/Users/apple/Desktop/appleklinika-storefront-active` | `feature/local-environment-recovery`, based on `ae80d2140e22918a2040d2bc35d21b32bdb2895c` | http://localhost:8082/ |
| Back Office | `/Users/apple/Desktop/appleklinika-backoffice-v1` | `feature/appleklinika-backoffice-v1`, `4c4ee8353da6dcd4797c623b6f480a23fe00e700` | http://localhost:18080/backoffice/ |

The original `feature/order-email-lifecycle` worktree is retained as a protected copy. Neither checkpoint was changed or merged. The five differing shared implementation files remain distinct and match their respective checkpoints, including inside the running containers.

**Quarantine:** do not use `/Users/apple/Desktop/appleklinikawebshop` as application source. Its unrelated plugin/language/update differences are not authoritative. Keep its shared `.git` storage, which the linked worktrees still require. Do not reset, clean or delete that directory as part of routine startup.

Do not use `/private/tmp`, deleted temporary checkouts, or Codex visualization/session directories for long-lived runtime source.

## Normal start and stop

Start Docker Desktop first. No database import, WordPress installation or dependency update is required.

Storefront:

```sh
cd /Users/apple/Desktop/appleklinika-storefront-active
make up
# Stop without removing containers or data volumes:
docker compose stop
```

Its ignored, private `.env` selects the existing `appleklinikawebshop` Compose project and `docker-compose.yml:docker-compose.local.yml`. Do not replace this file with `.env.example`; the example has no real local database credentials. `docker-compose.local.yml` is an opt-in LOCAL overlay, not TEST/production deployment configuration.

Back Office:

```sh
cd /Users/apple/Desktop/appleklinika-backoffice-v1
COMPOSE_PROJECT_NAME=appleklinika-backoffice make up
# Stop without removing containers or data volumes:
COMPOSE_PROJECT_NAME=appleklinika-backoffice docker compose stop
```

The explicit Back Office project name is necessary: using the directory basename as a new project would select different containers/volumes. Its existing private `.env` and Compose file were preserved.

Both WordPress containers use `restart: unless-stopped`. Once Docker Desktop is running they can survive app/machine restarts; explicitly stopped stacks need their start command again. Never use `down -v`, volume prune, or a database import to restart these environments.

## Data ownership and mounts

| Item | Storefront | Back Office |
| --- | --- | --- |
| Compose project | `appleklinikawebshop` | `appleklinika-backoffice` |
| WordPress container | `appleklinikawebshop-wordpress-1` | `appleklinika-backoffice-wordpress-1` |
| MariaDB container | `appleklinikawebshop-mariadb-1` | `appleklinika-backoffice-mariadb-1` |
| Database name | `appleklinika` | `appleklinika` |
| Database volume | `appleklinikawebshop_mariadb_data` | `appleklinika-backoffice_mariadb_data` |
| WordPress/core volume | `appleklinikawebshop_wordpress_data` | `appleklinika-backoffice_wordpress_data` |
| Uploads | storefront durable tree `/wordpress/wp-content/uploads` | Back Office durable tree `/wordpress/wp-content/uploads` |
| phpMyAdmin port | `8081` (loopback only) | existing `18081` |
| MariaDB host port | `3306` (loopback only) | existing `13306` |

The identical database names belong to separate servers/volumes; they are not shared. Existing database and WordPress/core volumes were reused without an import. The storefront retains its existing WordPress core 7.0; Back Office retains 7.0.2. The storefront image tag remains the pre-existing `wordpress:6.5.5-php8.2-apache`; persistent core files determine its actual WordPress version. This recovery does not upgrade or align versions.

All 673 existing storefront upload files were copied byte-for-byte from the old running LOCAL preview. Their original copy is untouched. WooCommerce's ignored `vendor/` dependencies were copied from the protected email checkpoint worktree, not from the quarantined updated plugin tree. Uploads, private `.env` and ignored dependencies are not committed application changes.

The storefront mounts approved `wp-content` read-only in the container, with uploads separately writable. Host-side development remains possible. Its local overlay sets the canonical URL to `http://localhost:8082`, environment to `local`, disables automatic cron/core/plugin changes, and blocks WordPress server-side external HTTP requests. These are LOCAL runtime controls, not changes to provider configuration. Browser-side third-party embeds are separate from that server-side HTTP block.

Port `127.0.0.1:8080` is a compatibility alias to the same durable storefront. It preserves existing absolute LOCAL links stored in database content; WordPress redirects to canonical 8082. No content-wide URL replacement was performed.

The obsolete standalone `ak-homepage-preview` (8082) and `ak-homepage-baseline` (8083) containers are stopped, retain restart policy `no`, and are excluded from normal Compose startup. Do not start them: they still describe deleted temporary mounts. The old Compose WordPress container was recreated with durable mounts and no temporary override-file dependency. Neither active stack has a temporary or Codex-session bind mount.

## Initial recovery verification and limits

- Normal storefront `docker compose stop` followed by `make up` succeeded. Canonical 8082 and the legacy 8080 redirect returned HTTP 200 after restart.
- Desktop/mobile browser checks covered homepage, all eight hero slides/dots, observed autoplay, real category photos, featured WooCommerce products, header/product cards, product page, cart, checkout availability and the My Account login/registration presentation. No redesign or application source change was made.
- The temporary browser cart was emptied. WooCommerce created one empty checkout draft (#2456) on opening checkout; only that newly created draft was removed after validating its status, time, empty customer/payment fields and exact test product. No payment/order submission or provider lifecycle test was performed.
- Contact and its mobile Google Maps / Apple Maps / Waze chooser render. The Google iframe is present immediately (`loading=eager`) with the approved Apple Klinika embed URL, but its visual map surface remained blank in the in-app browser. A direct provider URL responded with its expected iframe-only restriction. This is an unresolved embed-rendering verification limitation, not evidence of a changed Contact implementation; no workaround or source alteration was applied.
- Back Office HTTP/login loading and checkpoint-to-container source identity are verified. Authenticated work-queue browser verification is pending a successful LOCAL login; a TEST session does not authenticate this separate site.
- Before browsing and again after checkout-draft cleanup: storefront 197 products, 73 attachments, 162 orders and 6 users; Back Office 197 products, 50 attachments, 88 orders and 6 users. Active-plugin settings and the existing storefront Barion configuration were preserved. No TEST/production/main access, deployment, push, merge or shared-file reconciliation is part of this recovery.

This section records the initial restoration checks, before the final authenticated Back Office and map-classification verification. The LOCAL recovery configuration and this guide are protected together in the recovery checkpoint. Continue code reconciliation only as a separately authorized task.
