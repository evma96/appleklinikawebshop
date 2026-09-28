# Dedicated Back Office staging host

## Diagnosis

The staging proxy already maps `/` and `/backoffice/` on the dedicated host to the Back Office query variable. No permalink or proxy change is needed for the login fix.

Native `auth_redirect()` preserves the requested Back Office URL. Previously, `wp_login_url()` and the login form POST used the canonical storefront `siteurl`. After successful authentication, native `wp_validate_redirect()` rejected the dedicated host because the allowed host came from the storefront `home_url()`; the fallback was `wp-admin`. Additionally, the successful storefront POST issued host-only cookies on the storefront, not the Back Office host. Merely allowlisting a cross-host return would not fix both facts.

## Configuration and security

Set the WordPress option `appleklinika_backoffice_origin` to the explicitly trusted origin for this environment. Staging uses `https://backoffice-teszt.appleklinika.com`; its canonical `home` and `siteurl` remain `https://teszt.appleklinika.com`. An absent or invalid option leaves existing URL behavior unchanged.

`DedicatedHostUrls` filters core `home_url`, `site_url` and `network_site_url` only when `HTTP_HOST` exactly matches that configured authority. It rewrites only the canonical installation's URL authorities, retaining paths and encoded queries. Native login GET and POST, admin-post, logout, application navigation and core safe-redirect validation then agree on the same dedicated origin. The configuration must be an HTTPS origin without credentials, non-root paths, query or fragment; HTTP is allowed only for exact `localhost` or `127.0.0.1` local QA origins. Ports must match explicitly.

No request-supplied origin or return URL is used as configuration. Forwarded hosts, host suffix matches and external URLs do not activate or widen the adapter. Core authentication, nonces, redirect validation and capability checks remain intact. Cookies remain host-only; this is deliberately not cross-subdomain SSO. A login initiated from the Back Office must remain on its dedicated host throughout. The storefront host does not register these URL filters.

Do not modify global `home`, `siteurl`, `permalink_structure`, `COOKIE_DOMAIN` or employee permissions. The proxy must serve login, static assets and admin-post on the dedicated host as well as forwarding the existing Back Office query route. WordPress must recognize HTTPS behind the trusted proxy. No new installation, database or provider configuration is involved.

## Plugin-only staging deployment

The staging runtime directory is named `/opt/appleklinika/production`, but the verified server is `appleklinika-staging-01`, Compose project `appleklinika-staging`, and WordPress environment type `staging`. Verify these and the canonical staging URL before any write; the directory name alone is not an environment proof.

Keep `/opt/appleklinika/repo` at storefront SHA `067d63cbbdd38dc52a95d14fb3bd6ec309088d2d`. After local tests, quality command, PHP syntax and diff checks, commit and push only the relevant feature changes. Export `wordpress/wp-content/plugins/appleklinika-backoffice` from the exact resulting commit SHA. Stage that export separately, verify its file hashes and PHP syntax, preserve the current plugin in a timestamped backup, then replace only `/opt/appleklinika/production/plugins/appleklinika-backoffice`. Record both source SHA and backup path outside the plugin. Do not checkout the feature branch in the storefront repository.

Record the previous dedicated-origin option before configuring it. Rollback restores the backed-up plugin and that previous option (delete the option if previously absent). Retain the existing Caddyfile; if future work requires a proxy change, back it up first, validate it, and reload only on successful validation. No storefront checkout, deployment or provider write is part of this procedure.

## Verification

Local automated suites: 194 assertions, including 47 focused URL/host/configuration regressions. Tests are CLI-only and make no external requests. Native local WordPress checks additionally verified login GET/POST URLs, context retention, safe return and external-target rejection, admin-post/logout URLs, and unchanged canonical options and host-only cookie paths (13 assertions). Local browser login with only `manage_appleklinika_backoffice` reached the queue, survived reload and opened an order; a zero-capability account was denied. Local QA used `http://127.0.0.1:18080` with canonical `http://localhost:18080`, reproducing the original cross-host redirect before enabling the option.

After staging deployment, verify with temporary, narrowly scoped QA users and delete them afterward:

1. Logged-out dedicated root redirects to native login on the same HTTPS host; form action and `redirect_to` agree.
2. A Back Office-only employee signs in and reaches the application, not WooCommerce admin. Reload preserves the session.
3. Queue, order detail, return navigation, search/filter and pagination retain the dedicated host and list context.
4. Logout returns through the same host; a user without the capability receives HTTP 403.
5. Storefront root and login keep the canonical storefront host; `home`, `siteurl`, permalinks and cookie scope are unchanged.
6. Test scripts return HTTP 404. No label, invoice or other external write action is invoked.
7. Recheck the deployed plugin's exact source hashes and the unchanged storefront repository SHA.

No real order mutation, GLS label generation or invoice generation is necessary to validate this login integration. Existing provider operations remain outside this smoke test. `make quality` currently contains placeholders, so it does not replace manual inspection or PHP syntax checks.
