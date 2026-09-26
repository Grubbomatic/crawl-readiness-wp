<?php
/**
 * /.well-known/agents.json, a short machine-readable card for the site.
 *
 * An emerging convention: a JSON file that names the site and points AI
 * systems at the resources meant for them. Off by default; the check treats
 * it as informational.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_Agents {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 1 );
	}

	/**
	 * Answer /.well-known/agents.json when the setting is on.
	 */
	public static function maybe_serve() {
		if ( ! CrawlReady_Settings::get( 'agents' ) ) {
			return;
		}
		if ( '.well-known/agents.json' !== CrawlReady_Llms::request_path() ) {
			return;
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( self::build(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * The document.
	 *
	 * @return array
	 */
	public static function build() {
		$home = home_url( '/' );
		$doc  = array(
			'name'        => wp_strip_all_tags( get_bloginfo( 'name' ) ),
			'description' => wp_strip_all_tags( get_bloginfo( 'description' ) ),
			'url'         => $home,
			'language'    => get_bloginfo( 'language' ),
			'resources'   => array(),
			'generator'   => 'crawl-readiness-wp/' . CRAWLREADY_VERSION,
		);
		if ( CrawlReady_Settings::get( 'llms' ) ) {
			$doc['resources'][] = array(
				'type'        => 'llms.txt',
				'url'         => $home . 'llms.txt',
				'description' => 'A Markdown summary of the site for AI systems.',
			);
		}
		if ( function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled() ) {
			$doc['resources'][] = array(
				'type'        => 'sitemap',
				'url'         => get_sitemap_url( 'index' ),
				'description' => 'Every public page, as XML.',
			);
		}
		$doc['resources'][] = array(
			'type'        => 'rss',
			'url'         => get_feed_link(),
			'description' => 'Recent posts, as RSS.',
		);
		return $doc;
	}
}
