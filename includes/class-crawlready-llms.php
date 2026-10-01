<?php
/**
 * /llms.txt, served from the site's own pages and posts.
 *
 * The file is virtual: WordPress answers the request itself, so nothing is
 * written to disk and there is nothing to keep in sync. A physical llms.txt
 * in the web root wins, because the web server serves it before WordPress
 * runs.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_Llms {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 1 );
	}

	/**
	 * Answer /llms.txt when the setting is on.
	 */
	public static function maybe_serve() {
		if ( ! CrawlReady_Settings::get( 'llms' ) ) {
			return;
		}
		if ( 'llms.txt' !== self::request_path() ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		// The browser must treat it as text whatever it contains.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=3600' );
		echo self::build(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a text/plain document (nosniff), built from stripped, escaped parts; HTML-escaping would corrupt its Markdown.
		exit;
	}

	/**
	 * The request path relative to the site's home path, without slashes.
	 *
	 * @return string
	 */
	public static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return trim( $path, '/' );
	}

	/**
	 * Whether a real llms.txt sits in the web root and would be served instead.
	 *
	 * @return bool
	 */
	public static function physical_file_exists() {
		return file_exists( ABSPATH . 'llms.txt' );
	}

	/**
	 * The document.
	 *
	 * @return string
	 */
	public static function build() {
		$name    = self::text( get_bloginfo( 'name' ) );
		$tagline = self::text( get_bloginfo( 'description' ) );
		$about   = self::text( (string) CrawlReady_Settings::get( 'llms_about' ) );
		$max     = max( 1, min( 100, (int) CrawlReady_Settings::get( 'llms_pages' ) ) );

		$lines = array( '# ' . $name );
		if ( '' !== $tagline ) {
			$lines[] = '';
			$lines[] = '> ' . $tagline;
		}
		$lines[] = '';
		if ( '' !== $about ) {
			$lines[] = $about;
		} else {
			$lines[] = sprintf(
				/* translators: 1: site name, 2: home URL */
				__( '%1$s is published at %2$s. This file lists the main pages and recent posts for AI systems that read Markdown.', 'crawl-readiness' ),
				$name,
				home_url( '/' )
			);
		}
		$lines[] = '';

		$pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'number'      => $max,
				'post_status' => 'publish',
			)
		);
		if ( ! empty( $pages ) ) {
			$lines[] = '## ' . __( 'Pages', 'crawl-readiness' );
			foreach ( $pages as $page ) {
				$lines[] = self::entry( $page );
			}
			$lines[] = '';
		}

		$posts = get_posts(
			array(
				'numberposts' => $max,
				'post_status' => 'publish',
			)
		);
		if ( ! empty( $posts ) ) {
			$lines[] = '## ' . __( 'Recent posts', 'crawl-readiness' );
			foreach ( $posts as $post ) {
				$lines[] = self::entry( $post );
			}
			$lines[] = '';
		}

		$lines[] = '## ' . __( 'Optional', 'crawl-readiness' );
		if ( function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled() ) {
			$lines[] = '- [' . __( 'Sitemap', 'crawl-readiness' ) . '](' . esc_url_raw( get_sitemap_url( 'index' ) ) . ')';
		}
		$lines[] = '- [' . __( 'RSS feed', 'crawl-readiness' ) . '](' . esc_url_raw( get_feed_link() ) . ')';

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * One list line: title, link, short description.
	 *
	 * @param WP_Post $post The page or post.
	 * @return string
	 */
	private static function entry( $post ) {
		$title = self::text( get_the_title( $post ) );
		$title = str_replace( array( '[', ']' ), array( '(', ')' ), $title );
		$text  = '' !== $post->post_excerpt ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		$desc  = self::text( wp_trim_words( $text, 25, '…' ) );
		$line  = '- [' . $title . '](' . esc_url_raw( get_permalink( $post ) ) . ')';
		if ( '' !== $desc ) {
			$line .= ': ' . $desc;
		}
		return $line;
	}

	/**
	 * Plain text on one line.
	 *
	 * @param string $s Anything WordPress hands back.
	 * @return string
	 */
	private static function text( $s ) {
		$s = html_entity_decode( wp_strip_all_tags( (string) $s, true ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}
}
