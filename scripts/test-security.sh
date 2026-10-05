#!/bin/sh
set -eu
root=/usr/src/wordpress/wp-content
for test in "$root"/plugins/appleklinika-backoffice/tests/security/*.php; do
    php -d disable_functions=curl_init,curl_setopt,curl_exec "$test"
done
php -l "$root/themes/appleklinika-theme/functions.php" >/dev/null
php -l "$root/plugins/gls-shipping-for-woocommerce/includes/admin/class-gls-shipping-order.php" >/dev/null
echo 'Security PHP syntax checks passed.'
