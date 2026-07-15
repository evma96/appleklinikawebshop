<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! empty( $data['title'] ) ) {
	echo '<h2>' . esc_html( $data['title'] ) . '</h2>';
}

include( dirname( __FILE__ ) . '/html-admin-pro-version.php' );

echo '<table class="form-table">' . "\n\n";
if ( ! empty( $data['id'] ) ) {
	do_action( 'woocommerce_settings_' . sanitize_title( $data['id'] ) );
}