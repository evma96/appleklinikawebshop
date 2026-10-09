# Conservative LOW security follow-up — TEST

## Scope and evidence

No production/main/legal change or mass dependency update. Fresh encrypted TEST backup `20261009T142437Z` precedes removals. Existing WireGuard alias remains the only administration route.

- LOCAL Back Office `.env` changed from 0644 to 0600; byte hash unchanged.
- TEST has no application-password users, no Jetpack/remote-publishing integration in active inventory, and no XML-RPC request in the available bounded seven-day container log query (50,000-line cap). XML-RPC is not part of current Woo/Barion/GLS/Szamlazz workflows. It is denied at TEST Caddy; application XML-RPC methods are also disabled under the existing LOCAL/TEST guard.
- Anonymous/customer REST user-list/numeric-user reads return 403. Editors/user managers retain access; `users/me`, Woo Store API and non-GET account routes retain normal WordPress authorization. Public author archives and `?author=` become 404 without author-slug canonical redirects. This prevents trivial enumeration; it does not promise all author names disappear from every public content source.
- TEST Caddy adds `nosniff`, `strict-origin-when-cross-origin`, `SAMEORIGIN`, and a camera/microphone-disabled Permissions-Policy with self-origin geolocation. Outbound Google Maps iframe embedding is unaffected; no restrictive CSP or broad HSTS policy is introduced.
- Inactive Akismet 5.3.2 has no configured API key; Hello Dolly 1.7.2 is inactive. Remove these unused defaults and inactive Twenty Twenty-Two/Three/Four themes. Preserve the active Apple Klinika theme, Twenty Twenty-Five fallback, every active plugin and all provider configuration. Do not turn this housekeeping into an untested version upgrade.

The six legacy cloud-firewall TCP/22 /32 rules require the existing Hetzner web session; it expired during verification. No new public-IP lookup, public SSH bootstrap or broad rule is allowed. Remove only those existing recovery sources after confirming the attached TEST firewall and fresh WireGuard handshake, preserve UDP WireGuard and HTTP/HTTPS, then prove a new independent private SSH connection. Hetzner Console remains emergency recovery; no password reset is authorized.

## Verification

Focused offline checks cover REST allow/deny boundaries and canonical-author blocking. Caddy must validate before reload. After deployment verify public storefront/contact/cart/checkout/login/Back Office health, REST storefront and authenticated application paths, XML-RPC denial, author/no-redirect behavior, current PHP/JS errors and retained provider source. This is scoped implementation verification, not the gated final launch audit.
