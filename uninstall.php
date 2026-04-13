<?php
/**
 * Uninstall handler.
 *
 * Removes any option the plugin wrote. Does NOT delete user-provided
 * JSON views under /data/views — those are treated as content.
 *
 * @package TicSuite\Graficos
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tsg_settings' );
wp_cache_flush();
