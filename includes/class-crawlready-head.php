<?php
/**
 * The document head: a meta description, Open Graph tags and JSON-LD.
 *
 * Only when no SEO plugin is active. Two plugins writing the same tags is
 * worse than one, so this steps aside for Yoast, Rank Math, All in One SEO,
 * SEOPress, The SEO Framework and Slim SEO.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_Head {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 1 );
	}

	/**
	 * Print the tags for the current view.
	 */
	public static function output() {
		if ( CrawlReady_Settings::seo_plugin_active() ) {
			return;
		}
		$meta   = CrawlReady_Settings::get( 'meta' );
		$schema = CrawlReady_Settings::get( 'schema' );
		if ( ! $meta && ! $schema ) {
			return;
		}

		if ( $meta ) {
			$title = wp_get_document_title();
			$desc  = self::description();
			$url   = self::current_url();
			$image = self::image();

			echo "<!-- Crawl Readiness -->\n";
			if ( '' !== $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
			echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
			if ( '' !== $desc ) {
				echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
			echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
			if ( $image ) {
				echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
			}
			echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		}

		if ( $schema ) {
			echo '<script type="application/ld+json">' . wp_json_encode( self::graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	/**
	 * A description for the current view.
	 *
	 * @return string
	 */
	public static function description() {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$text = '' !== $post->post_excerpt ? $post->post_excerpt : strip_shortcodes( $post->post_content );
				return self::text( wp_trim_words( $text, 30, '…' ) );
			}
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$d = term_description();
			if ( '' !== $d ) {
				return self::text( $d );
			}
		}
		return self::text( get_bloginfo( 'description' ) );
	}

	/**
	 * The canonical address of the current view.
	 *
	 * @return string
	 */
	public static function current_url() {
		if ( is_singular() ) {
			return get_permalink();
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return esc_url_raw( home_url( (string) wp_parse_url( $uri, PHP_URL_PATH ) ) );
	}

	/**
	 * An image for previews: the post's own, else the logo, else the site icon.
	 *
	 * @return string
	 */
	public static function image() {
		if ( is_singular() && has_post_thumbnail() ) {
			$img = get_the_post_thumbnail_url( null, 'large' );
			if ( $img ) {
				return $img;
			}
		}
		$logo = self::logo();
		if ( $logo ) {
			return $logo;
		}
		$icon = get_site_icon_url( 512 );
		return $icon ? $icon : '';
	}

	/**
	 * The theme's custom logo, if one is set.
	 *
	 * @return string
	 */
	public static function logo() {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * Social profile links from the settings, one per line.
	 *
	 * @return array
	 */
	public static function same_as() {
		$out = array();
		foreach ( preg_split( '/\R/', (string) CrawlReady_Settings::get( 'same_as' ) ) as $line ) {
			$line = esc_url_raw( trim( $line ) );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * The JSON-LD graph: Organization, WebSite, and Article on posts.
	 *
	 * @return array
	 */
	public static function graph() {
		$home = home_url( '/' );
		$name = self::text( get_bloginfo( 'name' ) );
		$org  = array(
			'@type' => 'Organization',
			'@id'   => $home . '#organization',
			'name'  => $name,
			'url'   => $home,
		);
		$logo = self::logo();
		if ( $logo ) {
			$org['logo'] = $logo;
		}
		$same = self::same_as();
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		$site = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'name'            => $name,
			'url'             => $home,
			'publisher'       => array( '@id' => $home . '#organization' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
		$desc = self::text( get_bloginfo( 'description' ) );
		if ( '' !== $desc ) {
			$org['description']  = $desc;
			$site['description'] = $desc;
		}
		$graph = array( $org, $site );

		if ( is_singular( 'post' ) ) {
			$post    = get_queried_object();
			$article = array(
				'@type'            => 'Article',
				'@id'              => get_permalink( $post ) . '#article',
				'headline'         => self::text( get_the_title( $post ) ),
				'url'              => get_permalink( $post ),
				'mainEntityOfPage' => get_permalink( $post ),
				'datePublished'    => get_the_date( 'c', $post ),
				'dateModified'     => get_the_modified_date( 'c', $post ),
				'author'           => array(
					'@type' => 'Person',
					'name'  => self::text( get_the_author_meta( 'display_name', $post->post_author ) ),
				),
				'publisher'        => array( '@id' => $home . '#organization' ),
				'isPartOf'         => array( '@id' => $home . '#website' ),
			);
			$d = self::description();
			if ( '' !== $d ) {
				$article['description'] = $d;
			}
			if ( has_post_thumbnail( $post ) ) {
				$img = get_the_post_thumbnail_url( $post, 'large' );
				if ( $img ) {
					$article['image'] = $img;
				}
			}
			$graph[] = $article;
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
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
