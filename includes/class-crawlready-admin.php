<?php
/**
 * The admin side: the AI Readiness page under Tools, the dashboard widget,
 * the plugin row link and the first-run notice.
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrawlReady_Admin {

	const PAGE = 'crawl-readiness';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_crawlready_check', array( __CLASS__, 'handle_check' ) );
		add_action( 'admin_post_crawlready_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CRAWLREADY_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * The page's address.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'tools.php?page=' . self::PAGE );
	}

	/**
	 * Tools → AI Readiness.
	 */
	public static function menu() {
		add_management_page(
			__( 'Crawl Readiness', 'crawl-readiness' ),
			__( 'AI Readiness', 'crawl-readiness' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Styles for the page and the dashboard widget.
	 *
	 * @param string $hook The current admin page.
	 */
	public static function assets( $hook ) {
		if ( 'tools_page_' . self::PAGE === $hook || 'index.php' === $hook ) {
			wp_enqueue_style( 'crawlready-admin', CRAWLREADY_URL . 'assets/admin.css', array(), CRAWLREADY_VERSION );
		}
	}

	/**
	 * "Check site" on the plugins list.
	 *
	 * @param array $links The row's links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Check site', 'crawl-readiness' ) . '</a>' );
		return $links;
	}

	/**
	 * One note after activation, pointing at the page.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'crawlready_activated' ) ) {
			return;
		}
		delete_transient( 'crawlready_activated' );
		echo '<div class="notice notice-info is-dismissible"><p>';
		printf(
			/* translators: %s: link to the AI Readiness page */
			esc_html__( 'Crawl Readiness is on. Run your first check under %s.', 'crawl-readiness' ),
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Tools → AI Readiness', 'crawl-readiness' ) . '</a>'
		);
		echo '</p></div>';
	}

	/**
	 * The dashboard widget.
	 */
	public static function widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'crawlready_widget', __( 'AI readiness', 'crawl-readiness' ), array( __CLASS__, 'render_widget' ) );
	}

	/**
	 * The widget's content.
	 */
	public static function render_widget() {
		$report = CrawlReady_API::last_report();
		echo '<div class="crawlready-widget">';
		if ( $report ) {
			self::score_card( $report, false );
		} else {
			echo '<p>' . esc_html__( 'Not checked yet. See whether AI crawlers can read this site.', 'crawl-readiness' ) . '</p>';
		}
		echo '<p><a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open AI Readiness', 'crawl-readiness' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * POST from the "Check this site" button.
	 */
	public static function handle_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'crawl-readiness' ) );
		}
		check_admin_referer( 'crawlready_check' );
		$result = CrawlReady_API::run_check();
		if ( is_wp_error( $result ) ) {
			set_transient( 'crawlready_message_' . get_current_user_id(), array( 'kind' => 'error', 'text' => $result->get_error_message() ), MINUTE_IN_SECONDS );
		} else {
			set_transient( 'crawlready_message_' . get_current_user_id(), array( 'kind' => 'success', 'text' => __( 'Checked.', 'crawl-readiness' ) ), MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * POST from the settings form.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'crawl-readiness' ) );
		}
		check_admin_referer( 'crawlready_save' );

		$mode  = isset( $_POST['robots_mode'] ) && 'no-training' === $_POST['robots_mode'] ? 'no-training' : 'allow';
		$pages = isset( $_POST['llms_pages'] ) ? (int) $_POST['llms_pages'] : 20;
		$lines = array();
		if ( isset( $_POST['same_as'] ) ) {
			foreach ( preg_split( '/\R/', sanitize_textarea_field( wp_unslash( $_POST['same_as'] ) ) ) as $line ) {
				$line = esc_url_raw( trim( $line ) );
				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}
		}

		CrawlReady_Settings::update(
			array(
				'api_key'     => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
				'llms'        => empty( $_POST['llms'] ) ? 0 : 1,
				'llms_about'  => isset( $_POST['llms_about'] ) ? sanitize_textarea_field( wp_unslash( $_POST['llms_about'] ) ) : '',
				'llms_pages'  => max( 1, min( 100, $pages ) ),
				'robots'      => empty( $_POST['robots'] ) ? 0 : 1,
				'robots_mode' => $mode,
				'meta'        => empty( $_POST['meta'] ) ? 0 : 1,
				'schema'      => empty( $_POST['schema'] ) ? 0 : 1,
				'agents'      => empty( $_POST['agents'] ) ? 0 : 1,
				'same_as'     => implode( "\n", $lines ),
			)
		);
		set_transient( 'crawlready_message_' . get_current_user_id(), array( 'kind' => 'success', 'text' => __( 'Settings saved. Run the check again to see the difference.', 'crawl-readiness' ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Which setting takes care of a finding, by its wording.
	 *
	 * @param string $issue The finding's headline.
	 * @return string|null Setting key, or null when the plugin cannot fix it.
	 */
	public static function fix_setting( $issue ) {
		$issue = strtolower( $issue );
		if ( false !== strpos( $issue, 'llms.txt' ) ) {
			return 'llms';
		}
		if ( false !== strpos( $issue, 'agents.json' ) ) {
			return 'agents';
		}
		if ( false !== strpos( $issue, 'content-signal' ) || false !== strpos( $issue, 'crawlers are blocked' ) ) {
			return 'robots';
		}
		if ( false !== strpos( $issue, 'meta description' ) || false !== strpos( $issue, 'open graph' ) || false !== strpos( $issue, 'og:' ) ) {
			return 'meta';
		}
		if ( false !== strpos( $issue, 'json-ld' ) || false !== strpos( $issue, 'structured data' ) ) {
			return 'schema';
		}
		return null;
	}

	/**
	 * The score, verdict and note.
	 *
	 * @param array $report A stored result.
	 * @param bool  $full   With the address, date and report link.
	 */
	private static function score_card( array $report, $full = true ) {
		$g = $report['grade'];
		echo '<div class="crawlready-score">';
		echo '<div class="crawlready-ring tone-' . esc_attr( $g['tone'] ) . '"><strong>' . (int) $report['score'] . '</strong><span>' . esc_html__( 'of 100', 'crawl-readiness' ) . '</span></div>';
		echo '<div>';
		echo '<p class="crawlready-verdict">' . esc_html( trim( $g['letter'] . ' ' . $g['status'] ) ) . '</p>';
		if ( '' !== $g['note'] ) {
			echo '<p class="crawlready-note">' . esc_html( $g['note'] ) . '</p>';
		}
		if ( $full ) {
			$bits = array();
			if ( ! empty( $report['url'] ) ) {
				$bits[] = esc_html( $report['url'] );
			}
			if ( ! empty( $report['checked_at'] ) ) {
				$bits[] = esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $report['checked_at'] ) );
			}
			if ( ! empty( $report['crawlers']['total'] ) ) {
				$bits[] = esc_html( sprintf(
					/* translators: 1: allowed crawlers, 2: crawlers checked */
					__( '%1$d of %2$d AI crawlers allowed', 'crawl-readiness' ),
					$report['crawlers']['allowed'],
					$report['crawlers']['total']
				) );
			}
			$link = CrawlReady_API::report_link( $report );
			if ( $link ) {
				$bits[] = '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'Full report', 'crawl-readiness' ) . '</a>';
			}
			echo '<p class="crawlready-meta">' . implode( ' · ', $bits ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
			if ( ! empty( $report['usage'] ) && ! empty( $report['usage']['limit'] ) ) {
				echo '<p class="crawlready-meta">' . esc_html( sprintf(
					/* translators: 1: checks used, 2: monthly limit */
					__( '%1$d of %2$d checks used this month on your API key.', 'crawl-readiness' ),
					$report['usage']['used'],
					$report['usage']['limit']
				) ) . '</p>';
			}
		}
		echo '</div></div>';
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$message = get_transient( 'crawlready_message_' . get_current_user_id() );
		if ( $message ) {
			delete_transient( 'crawlready_message_' . get_current_user_id() );
		}
		$report   = CrawlReady_API::last_report();
		$opts     = CrawlReady_Settings::get();
		$seo      = CrawlReady_Settings::seo_plugin_active();
		$seo_name = CrawlReady_Settings::seo_plugin_name();
		$site     = CrawlReady_API::check_url();
		?>
		<div class="wrap crawlready-wrap">
			<h1><?php esc_html_e( 'AI readiness', 'crawl-readiness' ); ?></h1>
			<p><?php esc_html_e( 'Can AI crawlers read this site? The check fetches it from the outside, the way ChatGPT, Claude, Perplexity and Google AI do, and lists what they need. The switches below fix most of it from inside WordPress.', 'crawl-readiness' ); ?></p>

			<?php if ( $message && is_array( $message ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $message['kind'] ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $message['text'] ); ?></p></div>
			<?php endif; ?>

			<div class="crawlready-card">
				<?php if ( $report ) : ?>
					<?php self::score_card( $report, true ); ?>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Not checked yet.', 'crawl-readiness' ); ?></strong>
					<?php
					printf(
						/* translators: %s: site address */
						esc_html__( 'The check runs for %s and takes about ten seconds.', 'crawl-readiness' ),
						'<code>' . esc_html( $site ) . '</code>'
					);
					?>
					</p>
				<?php endif; ?>
				<form class="crawlready-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'crawlready_check' ); ?>
					<input type="hidden" name="action" value="crawlready_check">
					<button type="submit" class="button button-primary"><?php echo $report ? esc_html__( 'Check again', 'crawl-readiness' ) : esc_html__( 'Check this site', 'crawl-readiness' ); ?></button>
					<span class="crawlready-meta"><?php esc_html_e( 'Sends only the site address to crawlreadiness.com.', 'crawl-readiness' ); ?></span>
				</form>
			</div>

			<?php if ( $report && ! empty( $report['fixes'] ) ) : ?>
				<div class="crawlready-card">
					<h2><?php esc_html_e( 'What the check found', 'crawl-readiness' ); ?></h2>
					<div class="crawlready-fixes">
						<?php foreach ( $report['fixes'] as $fix ) : ?>
							<?php
							$key     = self::fix_setting( $fix['issue'] );
							$handled = $key && ! empty( $opts[ $key ] ) && ( ! in_array( $key, array( 'meta', 'schema' ), true ) || ! $seo );
							?>
							<div class="crawlready-fix">
								<h4>
									<span class="crawlready-sev <?php echo esc_attr( $fix['severity'] ); ?>"><?php echo esc_html( $fix['severity'] ); ?></span>
									<?php echo esc_html( $fix['issue'] ); ?>
									<?php if ( $handled ) : ?>
										<span class="crawlready-handled"><?php esc_html_e( 'On below; check again to confirm', 'crawl-readiness' ); ?></span>
									<?php elseif ( $key ) : ?>
										<span class="crawlready-handled"><?php esc_html_e( 'A switch below fixes this', 'crawl-readiness' ); ?></span>
									<?php endif; ?>
								</h4>
								<p><?php echo esc_html( $fix['fix'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php elseif ( $report ) : ?>
				<div class="crawlready-card"><p><?php esc_html_e( 'Nothing outstanding. AI crawlers can read this site and the machine-readable signals are in place.', 'crawl-readiness' ); ?></p></div>
			<?php endif; ?>

			<form class="crawlready-settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'crawlready_save' ); ?>
				<input type="hidden" name="action" value="crawlready_save">
				<div class="crawlready-card">
					<h2><?php esc_html_e( 'Fixes', 'crawl-readiness' ); ?></h2>
					<?php if ( $seo ) : ?>
						<p class="description">
						<?php
						printf(
							/* translators: %s: name of the SEO plugin */
							esc_html__( '%s is active and owns the meta tags and structured data, so those two switches stay off.', 'crawl-readiness' ),
							esc_html( $seo_name )
						);
						?>
						</p>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'llms.txt', 'crawl-readiness' ); ?></th>
							<td>
								<label><input type="checkbox" name="llms" value="1" <?php checked( $opts['llms'] ); ?>> <?php esc_html_e( 'Serve /llms.txt, built from this site’s pages and posts', 'crawl-readiness' ); ?></label>
								<?php if ( CrawlReady_Llms::physical_file_exists() ) : ?>
									<p class="description"><?php esc_html_e( 'An llms.txt file already exists in the web root, so the web server serves that one instead.', 'crawl-readiness' ); ?></p>
								<?php else : ?>
									<p class="description"><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View /llms.txt', 'crawl-readiness' ); ?></a></p>
								<?php endif; ?>
								<p><label for="crawlready-about"><?php esc_html_e( 'About paragraph (optional)', 'crawl-readiness' ); ?></label><br>
								<textarea id="crawlready-about" name="llms_about" rows="3" placeholder="<?php esc_attr_e( 'One or two sentences on what the site is and who it is for.', 'crawl-readiness' ); ?>"><?php echo esc_textarea( $opts['llms_about'] ); ?></textarea></p>
								<p><label for="crawlready-pages"><?php esc_html_e( 'Pages and posts to list', 'crawl-readiness' ); ?></label>
								<input id="crawlready-pages" type="number" name="llms_pages" min="1" max="100" value="<?php echo (int) $opts['llms_pages']; ?>" class="small-text"></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'AI crawlers in robots.txt', 'crawl-readiness' ); ?></th>
							<td>
								<label><input type="checkbox" name="robots" value="1" <?php checked( $opts['robots'] ); ?>> <?php esc_html_e( 'Add a rule for every known AI crawler, and a Content-Signal line', 'crawl-readiness' ); ?></label>
								<p>
									<label><input type="radio" name="robots_mode" value="allow" <?php checked( 'allow', $opts['robots_mode'] ); ?>> <?php esc_html_e( 'Allow all AI crawlers', 'crawl-readiness' ); ?></label><br>
									<label><input type="radio" name="robots_mode" value="no-training" <?php checked( 'no-training', $opts['robots_mode'] ); ?>> <?php esc_html_e( 'Allow AI search and assistants, block AI training', 'crawl-readiness' ); ?></label>
								</p>
								<?php if ( CrawlReady_Robots::physical_file_exists() ) : ?>
									<p class="description"><?php esc_html_e( 'A robots.txt file exists in the web root, so WordPress cannot serve its own and this switch has no effect. Add the rules to that file instead.', 'crawl-readiness' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Blocking training crawlers is a valid choice; the check currently scores every crawler the same, so it lowers the score.', 'crawl-readiness' ); ?> <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View /robots.txt', 'crawl-readiness' ); ?></a></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Meta description and Open Graph', 'crawl-readiness' ); ?></th>
							<td>
								<label><input type="checkbox" name="meta" value="1" <?php checked( $opts['meta'] ); ?> <?php disabled( $seo ); ?>> <?php esc_html_e( 'A description, title, image and canonical address for every page', 'crawl-readiness' ); ?></label>
								<p class="description"><?php esc_html_e( 'From each page’s excerpt or first words, the featured image or your logo. The home page uses the tagline from Settings → General, or the About paragraph above.', 'crawl-readiness' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Structured data (JSON-LD)', 'crawl-readiness' ); ?></th>
							<td>
								<label><input type="checkbox" name="schema" value="1" <?php checked( $opts['schema'] ); ?> <?php disabled( $seo ); ?>> <?php esc_html_e( 'Organization and WebSite for the site, Article for posts', 'crawl-readiness' ); ?></label>
								<p><label for="crawlready-sameas"><?php esc_html_e( 'Your profiles elsewhere, one address per line (optional)', 'crawl-readiness' ); ?></label><br>
								<textarea id="crawlready-sameas" name="same_as" rows="3" placeholder="https://www.linkedin.com/company/…"><?php echo esc_textarea( $opts['same_as'] ); ?></textarea></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'agents.json', 'crawl-readiness' ); ?></th>
							<td>
								<label><input type="checkbox" name="agents" value="1" <?php checked( $opts['agents'] ); ?>> <?php esc_html_e( 'Serve /.well-known/agents.json, a short card pointing AI systems at llms.txt, the sitemap and the feed', 'crawl-readiness' ); ?></label>
								<p class="description"><?php esc_html_e( 'An emerging convention. Harmless, and the check counts it.', 'crawl-readiness' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="crawlready-card">
					<h2><?php esc_html_e( 'API key (optional)', 'crawl-readiness' ); ?></h2>
					<p class="description">
					<?php
					printf(
						/* translators: %s: link to the Crawl Readiness dashboard */
						esc_html__( 'Without a key the check shares a free daily allowance with other sites on your network. A free account at %s gives you a key with 50 checks a month.', 'crawl-readiness' ),
						'<a href="' . esc_url( CRAWLREADY_SITE . '/dashboard?utm_source=wordpress-plugin' ) . '" target="_blank" rel="noopener">crawlreadiness.com</a>'
					);
					?>
					</p>
					<p><input type="text" name="api_key" value="<?php echo esc_attr( $opts['api_key'] ); ?>" class="regular-text" autocomplete="off" placeholder="cr_…"></p>
				</div>

				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'crawl-readiness' ); ?></button></p>
			</form>

			<div class="crawlready-monitor">
				<p><strong><?php esc_html_e( 'Being readable is step one. Being mentioned is the goal.', 'crawl-readiness' ); ?></strong></p>
				<p><?php esc_html_e( 'LLM Monitor asks ChatGPT, Claude, Perplexity and Google AI the questions your customers ask, and tracks whether they name you or a competitor, every week.', 'crawl-readiness' ); ?></p>
				<a class="button" href="<?php echo esc_url( CRAWLREADY_SITE . '/dashboard/monitor?utm_source=wordpress-plugin' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Track your brand in AI answers', 'crawl-readiness' ); ?></a>
			</div>

			<p class="crawlready-meta">
			<?php
			printf(
				/* translators: 1: privacy policy link, 2: terms link */
				esc_html__( 'The check is made by crawlreadiness.com, which fetches your site from the outside and stores the result. Nothing else leaves this site. %1$s · %2$s', 'crawl-readiness' ),
				'<a href="' . esc_url( CRAWLREADY_SITE . '/privacy' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy', 'crawl-readiness' ) . '</a>',
				'<a href="' . esc_url( CRAWLREADY_SITE . '/terms' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms', 'crawl-readiness' ) . '</a>'
			);
			?>
			</p>
		</div>
		<?php
	}
}
