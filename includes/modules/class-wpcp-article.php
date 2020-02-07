<?php
// don't call the file directly
defined( 'ABSPATH' ) || exit();

class WPCP_Article extends WPCP_Module {

	/**
	 * The single instance of the class
	 *
	 * @var $this ;
	 */
	protected static $_instance = null;

	/**
	 * WPCP_Module constructor.
	 */
	public function __construct() {
		//option fields
		add_action( 'wpcp_article_campaign_options_meta_fields', 'wpcp_keyword_suggestion_field' );
		add_action( 'wpcp_article_campaign_options_meta_fields', 'wpcp_keyword_field' );
		parent::__construct( 'article' );
	}

	/**
	 * @return string
	 */
	public function get_module_icon() {
		return '';
	}

	/**
	 * @return array
	 * @since 1.2.0
	 */
	public function get_template_tags() {
		return array(
			'title'      => __( 'Title', 'wp-content-pilot' ),
			'excerpt'    => __( 'Summary', 'wp-content-pilot' ),
			'content'    => __( 'Content', 'wp-content-pilot' ),
			'image_url'  => __( 'Main image url', 'wp-content-pilot' ),
			'source_url' => __( 'Source link', 'wp-content-pilot' ),
		);
	}

	/**
	 * @return string
	 * @since 1.2.0
	 */
	public function get_default_template() {
		$template =
			<<<EOT
<img src="{image_url}">
{content}
<br> <a href="{source_url}" target="_blank">Source</a>
EOT;

		return $template;
	}

	/**
	 * @param $post
	 */
	public function add_campaign_option_fields( $post ) {

	}

	/**
	 * @param $campaign_id
	 * @param $posted
	 */
	public function save_campaign_meta( $campaign_id, $posted ) {

	}

	/**
	 * @param $section
	 *
	 * @return array
	 * @since 1.2.0
	 */
	public function get_setting_section( $sections ) {
		return $sections;
	}

	/**
	 * @param $fields
	 *
	 * @return array
	 * @since 1.2.0
	 */
	public function get_setting_fields( $fields ) {
		return $fields;
	}


	/**
	 * @param int $campaign_id
	 * @param array $keywords
	 *
	 * @return array|mixed|WP_Error
	 * @since 1.2.0
	 */
	public function get_post( $campaign_id, $keywords ) {
		//set last keyword
		$last_keyword = $this->get_last_keyword( $campaign_id );

		wpcp_logger()->info( 'Article Campaign Started', $campaign_id );

		//loop through keywords
		foreach ( $keywords as $keyword ) {
			wpcp_logger()->info( sprintf( 'Looping through keywords now trying with keyword [ %s ]', $keyword ), $campaign_id );

			if ( $this->is_deactivated_key( $campaign_id, $keyword ) ) {
				wpcp_logger()->debug( sprintf( 'The keyword is deactivated for 1 hr because last time could not find any article with keyword [%s]', $keyword ), $campaign_id );
			}

			//if more than 1 then unset last one
			if ( count( $keywords ) > 1 && $last_keyword == $keyword ) {
				wpcp_logger()->debug( sprintf( 'The keyword [%s] already used last time changing to different one if available', $keyword ), $campaign_id );
				continue;
			}

			//get links from database
			$links = $this->get_links( $keyword, $campaign_id );

			if ( empty( $links ) ) {
				wpcp_logger()->info( 'No cached links in store. Generating new links...', $campaign_id );
				$this->discover_links( $campaign_id, $keyword );
				$links = $this->get_links( $keyword, $campaign_id );
			}

			if ( empty( $links ) ) {
				$message = __( 'No links to process the campaign, waiting to run later...' );
				wpcp_logger()->error( $message, $campaign_id );
				$this->deactivate_key( $campaign_id, $keyword );

				return new WP_Error( 'no-links', $message );
			}

			foreach ( $links as $link ) {
				wpcp_logger()->info( sprintf( 'Grabbing article from [%s]', $link->url ), $campaign_id );
				$this->update_link( $link->id, [ 'status' => 'failed' ] );
				$curl = $this->setup_curl();
				$curl->get( $link->url );

				if ( $curl->isError() && $this->initiator != 'cron' ) {
					wpcp_logger()->info( sprintf( "Failed processing link reason [%s]", $curl->getErrorMessage() ), $campaign_id );
					continue;
				}

				$html        = $curl->response;
				$readability = new WPCP_Readability();
				$readable    = $readability->parse( $html, $link->url );
				if ( is_wp_error( $readable ) ) {
					wpcp_logger()->info( sprintf( "Failed readability reason [%s] changing to different link", $readable->get_error_message() ), $campaign_id );
					continue;
				}

				$article = array(
					'title'      => $readability->get_title(),
					'author'     => $readability->get_author(),
					'image_url'  => $readability->get_image(),
					'excerpt'    => $readability->get_excerpt(),
					'language'   => $readability->get_language(),
					'content'    => $readability->get_content(),
					'source_url' => $link->url,
				);

				wpcp_logger()->info( 'Article processed from campaign', $campaign_id );
				$this->update_link( $link->id, [ 'status' => 'success' ] );
				$this->set_last_keyword( $campaign_id, $keyword);
				return $article;
			}

		}

		return new WP_Error( 'campaign-error', __( 'No article generated check log for details.', 'wp-content-pilot' ) );
	}


	/**
	 * @param $campaign_id
	 * @param $keyword
	 *
	 * @return bool|mixed|WP_Error
	 * @since 1.2.0
	 */
	protected function discover_links( $campaign_id, $keyword ) {
		$page_key    = $this->get_unique_key($keyword);
		$page_number = wpcp_get_post_meta( $campaign_id, $page_key, 0 );


		$endpoint = add_query_arg( array(
			'q'      => urlencode( $keyword ),
			'count'  => 10,
			'loc'    => 'en',
			'format' => 'rss',
			'first'  => ( $page_number * 10 ),
		), 'https://www.bing.com/search' );

		wpcp_logger()->debug( sprintf( 'Searching page url [%s]', $endpoint ), $campaign_id );

		$curl = $this->setup_curl();

		$response = $curl->get( $endpoint );
		if ( $curl->isError() ) {
			wpcp_logger()->error( $curl->errorMessage, $campaign_id );
			$this->deactivate_key( $campaign_id, $keyword );

			return $response;
		}

		if ( ! $response instanceof \SimpleXMLElement ) {
			$response = simplexml_load_string( $response );
		}

		$response = json_encode( $response );
		$response = json_decode( $response, true );

		//check if links exist
		if ( empty( $response ) || ! isset( $response['channel'] ) || ! isset( $response['channel']['item'] ) || empty( $response['channel']['item'] ) ) {
			$message = __( 'Could not find any links from search engine, deactivating keyword for an hour.', 'wp-content-pilot' );
			wpcp_logger()->info( $message, $campaign_id );
			$this->deactivate_key( $campaign_id, $keyword );

			return new WP_Error( 'no-links-found', $message );
		}

		$inserted     = 0;
		$items        = $response['channel']['item'];
		$banned_hosts = wpcp_get_settings( 'banned_hosts', 'wpcp_settings_article' );
		$banned_hosts = preg_split( '/\n/', $banned_hosts );
		$banned_hosts = array_merge( $banned_hosts, array( 'youtube.com', 'wikipedia', 'dictionary', 'youtube', 'wikihow' ) );
		foreach ( $items as $item ) {

			foreach ( $banned_hosts as $banned_host ) {
				if ( stristr( $item['link'], $banned_host ) ) {
					continue;
				}
			}

			if ( stristr( $item['link'], 'wikipedia' ) ) {
				continue;
			}

			if ( wpcp_is_duplicate_title( $item['title'] ) ) {
				continue;
			}

			if ( wpcp_is_duplicate_url( $item['link'] ) ) {
				continue;
			}

			$this->insert_link( array(
				'url'          => esc_url( $item['link'] ),
				'title'        => $item['title'],
				'keyword'      => $keyword,
				'pub_date_gmt' => empty( $item['pubDate'] ) ? '' : date( 'Y-m-d H:i:s', strtotime( $item['pubDate'] ) ),
			) );

			$inserted ++;
		}

		if ( $inserted < 1 ) {
			wpcp_logger()->info( 'Could not find any links', $campaign_id );
			$this->deactivate_key( $campaign_id, $keyword );

			return false;
		}

		wpcp_update_post_meta( $campaign_id, $page_key, $page_number + 1 );
		wpcp_logger()->info( sprintf( 'Total found links [%d] and accepted [%d]', count( $items ), $inserted ), $campaign_id );

		return true;
	}


	/**
	 * Main WPCP_Article Instance.
	 *
	 * Ensures only one instance of WPCP_Article is loaded or can be loaded.
	 *
	 * @return WPCP_Article Main instance
	 * @since 1.0.0
	 * @static
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

WPCP_Article::instance();
