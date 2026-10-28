<?php
/**
 * robots.txt rules for AI crawlers, appended to the file WordPress serves.
 *
 * WordPress only serves a virtual robots.txt when no real one sits in the web
 * root, so a physical file wins and the settings page says so. A site that
 * asked search engines to stay away is left alone.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_Robots {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 20, 2 );
	}

	/**
	 * Tokens whose makers document them as collecting training data only.
	 * "Allow AI search, not training" disallows these and leaves every other
	 * crawler to the site's own rules. The same list the Crawl Readiness
	 * check leaves out of its score, so this mode never costs points there.
	 * Google-Extended and
	 * meta-externalagent are not on it: Google says Google-Extended also
	 * controls grounding in the Gemini apps, and Meta says meta-externalagent
	 * also indexes content for its products, so blocking either costs answers.
	 *
	 * @return array
	 */
	public static function training() {
		return array(
			'GPTBot',
			'ClaudeBot',
			'Applebot-Extended',
			'FacebookBot',
			'CCBot',
			'Bytespider',
			'cohere-training-data-crawler',
			'AI2Bot',
			'AI2Bot-Dolma',
		);
	}

	/**
	 * Whether a real robots.txt sits in the web root and is served instead.
	 *
	 * @return bool
	 */
	public static function physical_file_exists() {
		return file_exists( ABSPATH . 'robots.txt' );
	}

	/**
	 * Append the rules to WordPress's virtual robots.txt.
	 *
	 * @param string $output What WordPress built.
	 * @param bool   $public Whether the site allows search engines.
	 * @return string
	 */
	public static function filter( $output, $public ) {
		if ( ! CrawlReady_Settings::get( 'robots' ) || ! $public ) {
			return $output;
		}
		return rtrim( (string) $output ) . "\n\n" . self::rules();
	}

	/**
	 * The rules block, in the mode the settings ask for.
	 *
	 * @return string
	 */
	public static function rules() {
		$mode = 'no-training' === CrawlReady_Settings::get( 'robots_mode' ) ? 'no-training' : 'allow';
		// "Allow" leaves ai-train out rather than writing "yes": a yes reads as
		// permission to train, which the owner may not mean, and leaving it out
		// still lets every crawler the rules allow in.
		$signal = 'no-training' === $mode ? 'search=yes, ai-input=yes, ai-train=no' : 'search=yes, ai-input=yes';
		// The empty "Disallow:" closes the "*" group. Without a rule there, the
		// next line, "User-agent: GPTBot", joins the same group (robots.txt
		// parsers, Google's included, treat back-to-back user-agent lines as
		// one group), so GPTBot's "Disallow: /" in no-training mode applied to
		// every crawler, Googlebot too. An empty Disallow matches nothing, so
		// it can't loosen any rule the site already has.
		$lines = array(
			'# AI crawlers, managed by the Crawl Readiness plugin',
			'User-agent: *',
			'Content-Signal: ' . $signal,
			'Disallow:',
			'',
		);
		// No crawler gets an "Allow: /" group of its own. A crawler named in a
		// group ignores the "*" group (RFC 9309), so "User-agent: GPTBot /
		// Allow: /" let GPTBot into /wp-admin/ and anything else the site's
		// "*" rules keep out, and merged with an owner's own "Disallow: /" for
		// GPTBot it un-blocked the crawler. AI crawlers the site doesn't name
		// follow its "*" rules, which on a stock site allow them. Only the
		// training crawlers are named, and only to block them.
		if ( 'no-training' === $mode ) {
			$lines[] = '# Crawlers that only collect AI training data';
			foreach ( self::training() as $token ) {
				$lines[] = 'User-agent: ' . $token;
				$lines[] = 'Disallow: /';
			}
		}
		return rtrim( implode( "\n", $lines ) ) . "\n";
	}
}
