# Deterministic checkout E2E runner

This is test tooling only. It is not loaded by WordPress, WooCommerce, or any production build.

The runner has exactly three foreground commands. It uses Playwright storage state only between `prepare` and `checkout-and-submit`; the complete Step 2 → Step 3 → Step 4 → order journey remains in one uninterrupted browser process. It never uses a persistent Chromium profile, an interactive terminal, debugger, cached `ElementHandle`, or DOM assignment to set field values.

## Run

Use a Node.js environment with Playwright installed and point `NODE_PATH` at its package directory when it is not installed locally:

```sh
node checkout-e2e.js prepare --run-id checkout-e2e-<unique-id>
node checkout-e2e.js checkout-and-submit --run-id checkout-e2e-<same-id>
node checkout-e2e.js verify-and-cleanup --run-id checkout-e2e-<same-id>
```

Useful optional environment variables:

- `AK_CHECKOUT_E2E_BASE_URL` — defaults to `https://teszt.appleklinika.com`
- `AK_CHECKOUT_E2E_EXPECTED_SHA` — defaults to the approved test-server revision
- `AK_CHECKOUT_E2E_SSH_TARGET` — the configured non-interactive test-server SSH target
- `AK_CHECKOUT_E2E_CHROME` — explicit Chrome/Chromium executable path
- `AK_CHECKOUT_E2E_ARTIFACT_DIR` — output directory for non-secret screenshots

The full runner must be used only against a disposable test environment. It creates exactly one timestamped guest checkout identity and removes only records and the Woo session matching that identity during cleanup. Each phase writes `run.json` and an explicit result before it exits.
