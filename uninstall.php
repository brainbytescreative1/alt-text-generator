<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'bbaiat_settings' );
delete_option( 'bbaiat_api_key' );
// Generated attachment alt text is deliberately preserved.
