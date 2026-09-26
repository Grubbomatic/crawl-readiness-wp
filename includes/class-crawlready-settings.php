<?php
/**
 * Settings: one option holding every switch, with defaults that respect an
 * SEO plugin already on the site.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_Settings {

	const OPTION = 'crawlready_settings';
	const REPORT = 'crawlready_last_report';

	/**
	 * Defaults. Meta tags and JSON-LD start off when an SEO plugin is active,
	 * because two plugins writing the same tags is worse than one.
	 *
	 * @return array
	 */
	public static function defaults() {
		$seo = self::seo_plugin_active();
		return array(
			'api_key'     => '',
			'llms'        => 1,
			'llms_about'  => '',
			'llms_pages'  => 20,
			'robots'      => 1,
			'robots_mode' => 'allow',
			'meta'        => $seo ? 0 : 1,
			'schema'      => $seo ? 0 : 1,
			'agents'      => 0,
			'same_as'     => '',
		);
	}

	/**
	 * Read one setting, or all of them.
	 *
	 * @param string|null $key Setting name, or null for the whole array.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $opts;
		}
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	/**
	 * Merge new values into the stored settings.
	 *
	 * @param array $values Settings to change.
	 */
	public static function update( array $values ) {
		update_option( self::OPTION, array_merge( self::get(), $values ) );
	}

	/**
	 * Whether a plugin that owns the document head is active.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| class_exists( 'The_SEO_Framework\Load' )
			|| function_exists( 'slim_seo' );
	}

	/**
	 * Name of the active SEO plugin, for the settings page.
	 *
	 * @return string
	 */
	public static function seo_plugin_name() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress';
		}
		if ( class_exists( 'The_SEO_Framework\Load' ) ) {
			return 'The SEO Framework';
		}
		if ( function_exists( 'slim_seo' ) ) {
			return 'Slim SEO';
		}
		return '';
	}

	/**
	 * Activation: store defaults once, and leave a note for the first visit.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
		set_transient( 'crawlready_activated', 1, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Deactivation: nothing to undo; the files this plugin serves are virtual.
	 */
	public static function deactivate() {}
}
