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
	 * Every AI crawler token the check knows, by vendor.
	 *
	 * @return array
	 */
	public static function crawlers() {
		return array(
			'OpenAI'             => array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User' ),
			'Anthropic'          => array( 'ClaudeBot', 'Claude-User', 'Claude-SearchBot', 'Claude-Web', 'anthropic-ai' ),
			'Google AI'          => array( 'Google-Extended', 'GoogleOther', 'Gemini-Deep-Research', 'Google-NotebookLM', 'Google-CloudVertexBot', 'GoogleOther-Image', 'GoogleOther-Video' ),
			'Perplexity'         => array( 'PerplexityBot', 'Perplexity-User' ),
			'xAI / Grok'         => array( 'GrokBot', 'Grok-DeepSearch', 'xAI-Grok' ),
			'Microsoft Copilot'  => array( 'Copilot', 'CopilotNative' ),
			'Meta AI'            => array( 'meta-externalagent', 'meta-externalfetcher', 'FacebookBot' ),
			'Apple Intelligence' => array( 'Applebot-Extended' ),
			'Amazon'             => array( 'Amazonbot' ),
			'Huawei'             => array( 'PetalBot' ),
			'Yandex AI'          => array( 'YandexAdditional', 'YandexAdditionalBot' ),
			'DeepSeek'           => array( 'DeepSeekBot' ),
			'Mistral'            => array( 'MistralAI-User' ),
			'Cohere'             => array( 'cohere-ai', 'cohere-training-data-crawler' ),
			'Common Crawl'       => array( 'CCBot' ),
			'DuckDuckGo AI'      => array( 'DuckAssistBot' ),
			'Bytedance'          => array( 'Bytespider' ),
			'Diffbot'            => array( 'Diffbot' ),
			'Allen AI'           => array( 'AI2Bot', 'AI2Bot-Dolma' ),
			'Phind'              => array( 'PhindBot' ),
			'You.com'            => array( 'YouBot' ),
			'Kagi'               => array( 'Kagibot' ),
			'Andi'               => array( 'AndiBot' ),
			'Timpi'              => array( 'Timpibot' ),
			'Sider'              => array( 'SiderAI' ),
			'Hugging Face'       => array( 'HuggingFace-Bot' ),
			'Quora / Poe'        => array( 'Quora-Bot' ),
			'Genspark'           => array( 'GensparkBot' ),
			'Neeva'              => array( 'NeevaBot' ),
			'Elicit'             => array( 'Elicit-Bot' ),
		);
	}

	/**
	 * Tokens that only collect training data. "Allow AI search, not training"
	 * disallows these and allows everything else.
	 *
	 * @return array
	 */
	public static function training() {
		return array(
			'GPTBot',
			'ClaudeBot',
			'anthropic-ai',
			'Google-Extended',
			'GrokBot',
			'meta-externalagent',
			'Applebot-Extended',
			'DeepSeekBot',
			'cohere-training-data-crawler',
			'CCBot',
			'Bytespider',
			'AI2Bot',
			'AI2Bot-Dolma',
			'HuggingFace-Bot',
			'Diffbot',
			'Timpibot',
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
		$mode     = 'no-training' === CrawlReady_Settings::get( 'robots_mode' ) ? 'no-training' : 'allow';
		$train    = 'no-training' === $mode ? 'no' : 'yes';
		$training = array_flip( self::training() );
		$lines    = array(
			'# AI crawlers, managed by the Crawl Readiness plugin',
			'User-agent: *',
			'Content-Signal: search=yes, ai-input=yes, ai-train=' . $train,
			'',
		);
		foreach ( self::crawlers() as $vendor => $tokens ) {
			$lines[] = '# ' . $vendor;
			foreach ( $tokens as $token ) {
				$lines[] = 'User-agent: ' . $token;
				$lines[] = ( 'no-training' === $mode && isset( $training[ $token ] ) ) ? 'Disallow: /' : 'Allow: /';
			}
			$lines[] = '';
		}
		return rtrim( implode( "\n", $lines ) ) . "\n";
	}
}
