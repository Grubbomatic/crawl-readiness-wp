<?php
/**
 * Uninstall: remove what the plugin stored. Nothing else was written.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'crawlready_settings' );
delete_option( 'crawlready_last_report' );
delete_transient( 'crawlready_activated' );
