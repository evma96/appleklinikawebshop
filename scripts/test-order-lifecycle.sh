#!/bin/sh
set -eu
# Run in a disposable PHP/WordPress image, mounting wp-content read-only at
# /usr/src/wordpress/wp-content. No credentials, database, or network required.
root=/usr/src/wordpress/wp-content/plugins
for test in "$root"/appleklinika-backoffice/tests/*.php; do
    php "$test"
done
for plugin in appleklinika-backoffice gls-shipping-for-woocommerce integration-for-szamlazzhu-woocommerce; do
    find "$root/$plugin" -name '*.php' -exec php -l '{}' \; >/dev/null
done
echo 'Lifecycle PHP syntax checks passed.'
