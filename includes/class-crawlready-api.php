<?php
/**
 * The check: one call to crawlreadiness.com, the answer kept for the page.
 *
 * The service fetches the site from the outside, the way an AI crawler
 * would, and answers with a score, a verdict and the list of fixes. Only the
 * site address is sent. An API key is optional; without one the check shares
 * the free daily allowance of the network it comes from.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_API {

	/**
	 * The address the check is run for. Filterable, so a staging or local site
	 * can point the check at the public one.
	 *
	 * @return string
	 */
	public static function check_url() {
		return (string) apply_filters( 'crawlready_check_url', home_url( '/' ) );
	}

	/**
	 * Run the check and keep the result.
	 *
	 * @return array|WP_Error
	 */
	public static function run_check() {
		$url  = self::check_url();
		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'crawl-readiness-wp/' . CRAWLREADY_VERSION . ' (' . home_url( '/' ) . ')',
			),
		);
		$key  = trim( (string) CrawlReady_Settings::get( 'api_key' ) );
		if ( '' !== $key ) {
			$args['headers']['x-api-key'] = $key;
		}

		$response = wp_remote_get( CRAWLREADY_SITE . '/api/check?url=' . rawurlencode( $url ), $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'crawlready_http', $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'crawlready_bad_reply', __( 'The check service sent an unexpected reply. Try again in a minute.', 'crawl-readiness' ) );
		}
		if ( 200 !== $code ) {
			$message = isset( $body['error'] ) ? (string) $body['error'] : sprintf(
				/* translators: %d: HTTP status code */
				__( 'The check failed (HTTP %d).', 'crawl-readiness' ),
				$code
			);
			return new WP_Error( 'crawlready_' . $code, $message );
		}

		$report               = self::slim( $body );
		$report['checked_at'] = time();
		update_option( CrawlReady_Settings::REPORT, $report, false );
		return $report;
	}

	/**
	 * The last stored result, if any.
	 *
	 * @return array|null
	 */
	public static function last_report() {
		$report = get_option( CrawlReady_Settings::REPORT );
		return is_array( $report ) && isset( $report['score'] ) ? $report : null;
	}

	/**
	 * The share link for a stored result.
	 *
	 * @param array $report A stored result.
	 * @return string
	 */
	public static function report_link( $report ) {
		if ( empty( $report['share_id'] ) ) {
			return '';
		}
		return CRAWLREADY_SITE . '/report/' . rawurlencode( $report['share_id'] );
	}

	/**
	 * Keep what the page shows, cleaned. The full report is large and most of
	 * it has a better home on the report page itself.
	 *
	 * @param array $b The service's answer.
	 * @return array
	 */
	private static function slim( array $b ) {
		// The service's advice quotes tags as text ("Add a clear <meta
		// name="description"> ..."). The sanitizers below strip anything
		// tag-shaped, which left "Add a clear  summarizing the page".
		// Encoding the angle brackets first keeps the quote as plain text;
		// esc_html() then shows it as written.
		$as_text = function ( $s ) {
			return str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), (string) $s );
		};
		$fixes   = array();
		foreach ( (array) ( isset( $b['fixes'] ) ? $b['fixes'] : array() ) as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$fixes[] = array(
				'severity'    => sanitize_key( isset( $f['severity'] ) ? $f['severity'] : 'low' ),
				'issue'       => sanitize_text_field( $as_text( isset( $f['issue'] ) ? $f['issue'] : '' ) ),
				'fix'         => sanitize_textarea_field( $as_text( isset( $f['fix'] ) ? $f['fix'] : '' ) ),
				'intentional' => ! empty( $f['intentional'] ),
				// Shown but not scored (the service's "Going further" findings).
				'extra'       => ! empty( $f['extra'] ),
			);
		}
		$grade  = isset( $b['grade'] ) && is_array( $b['grade'] ) ? $b['grade'] : array();
		$robots = isset( $b['robots'] ) && is_array( $b['robots'] ) ? $b['robots'] : array();
		$major  = isset( $robots['summary'] ) && is_array( $robots['summary'] ) ? $robots['summary'] : array();
		// The crawlers the score counts, when the service reports them.
		$scored = isset( $major['scored'] ) && is_array( $major['scored'] );
		if ( $scored ) {
			$major = $major['scored'];
		}
		$usage = isset( $b['usage'] ) && is_array( $b['usage'] ) ? $b['usage'] : null;

		return array(
			'url'            => esc_url_raw( isset( $b['url'] ) ? $b['url'] : '' ),
			'score'          => (int) ( isset( $b['score'] ) ? $b['score'] : 0 ),
			'grade'          => array(
				'letter' => sanitize_text_field( isset( $grade['letter'] ) ? $grade['letter'] : '' ),
				'status' => sanitize_text_field( isset( $grade['status'] ) ? $grade['status'] : '' ),
				'note'   => sanitize_text_field( isset( $grade['note'] ) ? $grade['note'] : '' ),
				'tone'   => sanitize_key( isset( $grade['tone'] ) ? $grade['tone'] : 'neutral' ),
			),
			'share_id'       => preg_replace( '/[^a-z0-9]/i', '', (string) ( isset( $b['shareId'] ) ? $b['shareId'] : '' ) ),
			'fixes'          => $fixes,
			'crawlers'       => array(
				'allowed' => (int) ( isset( $major['allowed'] ) ? $major['allowed'] : 0 ),
				'total'   => (int) ( isset( $major['total'] ) ? $major['total'] : 0 ),
				'scored'  => $scored,
			),
			'llms'           => ! empty( $b['llms']['present'] ),
			'agents'         => ! empty( $b['agentsJson']['present'] ),
			'content_signal' => ! empty( $robots['contentSignals'] ),
			'usage'          => $usage ? array(
				'used'  => (int) ( isset( $usage['used'] ) ? $usage['used'] : 0 ),
				'limit' => (int) ( isset( $usage['limit'] ) ? $usage['limit'] : 0 ),
			) : null,
		);
	}
}
