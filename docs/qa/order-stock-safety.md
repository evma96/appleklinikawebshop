# Focused order safety acceptance

## Defect and narrow correction

LOCAL reproduction: A reserves the one-unit product, expires; B reserves and pays; a late Succeeded callback for A formerly marked both orders paid and reduced stock to -1. No provider transaction was made in this reproduction.

The payment boundary now acquires order then sorted native stock-owner locks, refreshes stock and excludes the current order when reading native Woo holds. A native atomic reservation is required before completion. Locks remain held through native stock reduction and session shutdown. The payment status filter refreshes a stale order object, preventing parallel completions of the same order from repeating native effects.

A captured payment with insufficient/reassigned stock or an unavailable safety lock records `_appleklinika_payment_stock_exception`, a private Woo note and a Back Office payment block. No second sale, invoice or automatic refund is performed. Staff must reconcile the existing Barion transaction and stock, and arrange a provider refund if needed. Repeated callbacks preserve the original exception. The vendor status override is suppressed for that exception. No paid historical orders are migrated.

Scope: Barion orders containing recognized iPhone/iPad/MacBook/Watch device lines. Non-device orders and direct in-store cash remain unchanged. Production activation runs before this guard and is untouched.

## Reproducible checks

- `tests/payment-stock-safety.php`: offline domain/application checks, part of `make test`.
- `tests/integration/order-stock-safety.php`: explicit disposable LOCAL/TEST fixture harness; requires `AK_ORDER_SAFETY_QA=20261030`, rejects production, blocks HTTP/mail before bootstrap, and uses the installed Barion callback handler with a stub response. Invoice transport is replaced by a fixture counter. Independent processes and cart sessions exercise native reservations and concurrent completions.
- Cases: active hold competition, parallel success callbacks, stale carts, failed/expired response, elapsed hold, late success with free/reserved/sold stock, repeated callbacks, catalog/search/selector availability and cleanup via Woo APIs.
- #794 GLS and #795 pickup evidence is read only. E-APP-2026-17 and #801 archived evidence are not recreated.
- Email receipt is a separate allowlisted transport verification; never infer inbox delivery from `wp_mail` success.
