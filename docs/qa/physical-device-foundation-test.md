# Physical-device foundation integration and TEST acceptance

Date: 2026-10-27. Owner authorization covers the approved physical-device foundation and current approved product-page visuals, normal integration, TEST-only runtime dependencies and one disposable product-entry rehearsal. No main, production or legal-document change. COD remains deferred and is not a blocker for the accepted Barion / cash-pickup model.

## LOCAL correction

Product #2513 is the existing real iPhone 13 Pro. The supported Woo product API removed its mistaken accessory classification, assigned the iPhone category and restored visible catalog status. Native identity, name/slug, prices, stock configuration/status, model/storage/color/condition and all image IDs were compared before/after and preserved. The guard passed. Missing SKU/IMEI/serial and a previously unmanaged/null quantity were not invented; this narrow classification repair does not silently migrate legacy data.

## Reproducible TEST media runtime

The inspected TEST server is `appleklinika-staging-01`, Compose project `appleklinika-staging`, canonical URL `https://teszt.appleklinika.com`, environment `staging`. Its private operational directory happens to be named `/opt/appleklinika/production`; that directory is on the verified TEST server and is not the production environment.

The existing container is Debian Trixie / PHP 8.2.32 / Imagick 3.8.1 / libheif 1.19.8. The persistent WordPress core is 6.8.10; it is not replaced by the Docker image's 7.0.2 tag. HEIC/HEIF MIME support and writable uploads exist. ExifTool was missing.

`docker/wordpress-test-media/Dockerfile` extends the exact inspected official base digest and installs Debian's libheif decoder and ExifTool. `docker-compose.test-media.yml` overrides only the WordPress image/build and metadata-tool path, preserving the private base Compose file's environment guards, secrets, volumes, source permissions and routing. It must remain in the TEST Compose selection for future rebuilds/recreation; plain `docker restart` also preserves it. Do not apply this overlay to production or LOCAL.

TEST command pattern, from the verified TEST operational directory:

```sh
docker compose -f compose.yaml -f /opt/appleklinika/repo/docker-compose.test-media.yml build wordpress
docker compose -f compose.yaml -f /opt/appleklinika/repo/docker-compose.test-media.yml up -d --no-deps --no-build wordpress
```

## Controlled acceptance interface

`wordpress/wp-content/plugins/appleklinika-inventory/tests/device-product-test-acceptance.php` is CLI-only and checks the exact staging environment/host (or explicitly isolated LOCAL). Modes: `create`, `verify`, `sold`, `restock`, `cleanup`; target argument: `test` or `local`. Creation accepts exactly two HEIC/HEIF input paths, creates one device using business fields through the shared application service, uploads through native WordPress, and publishes with ordered photos. No product data is repaired afterward. A private `/tmp` manifest restricts cleanup to its own product/media/order. HTTP/mail are blocked within the process. The order stays `checkout-draft`; installed Szamlazz line mapping is evaluated offline without transport.

Rehearsal results, exact deployment identities and cleanup evidence are recorded in the release receipt after execution.

The LOCAL dry-run exposed a cleanup-harness ordering issue: deleting a product first detaches its media. The harness now verifies/removes its own filename-bound media before deleting the product and can recover safely from an interrupted cleanup. The original failed run's product/order/media were all removed. Two accessory timestamps changed through the separate existing protection regression; the final acceptance run is isolated from other mutating tests. No application/lifecycle change was needed for this tooling issue.
