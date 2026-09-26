<?php
/**
 * Plugin Name:       Crawl Readiness – AI Crawler Check & llms.txt
 * Plugin URI:        https://github.com/Grubbomatic/crawl-readiness-wp
 * Description:       Check whether AI crawlers can read your site, then fix what they need in one click: llms.txt, robots.txt rules for AI crawlers, meta and Open Graph tags, JSON-LD and agents.json.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Crawl Readiness
 * Author URI:        https://www.crawlreadiness.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crawl-readiness
 *
 * @package CrawlReadiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRAWLREADY_VERSION', '1.0.0' );
define( 'CRAWLREADY_FILE', __FILE__ );
define( 'CRAWLREADY_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRAWLREADY_URL', plugin_dir_url( __FILE__ ) );
define( 'CRAWLREADY_SITE', 'https://www.crawlreadiness.com' );

require_once CRAWLREADY_DIR . 'includes/class-crawlready-settings.php';
require_once CRAWLREADY_DIR . 'includes/class-crawlready-api.php';
require_once CRAWLREADY_DIR . 'includes/class-crawlready-llms.php';
require_once CRAWLREADY_DIR . 'includes/class-crawlready-robots.php';
require_once CRAWLREADY_DIR . 'includes/class-crawlready-head.php';
require_once CRAWLREADY_DIR . 'includes/class-crawlready-agents.php';
require_once CRAWLREADY_DIR . 'includes/class-crawlready-admin.php';

register_activation_hook( __FILE__, array( 'CrawlReady_Settings', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CrawlReady_Settings', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		CrawlReady_Llms::init();
		CrawlReady_Robots::init();
		CrawlReady_Head::init();
		CrawlReady_Agents::init();
		if ( is_admin() ) {
			CrawlReady_Admin::init();
		}
	}
);
