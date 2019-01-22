<?php
/**
 * Feed Class
 *
 * @package     WP Content Pilot
 * @subpackage  Feed
 * @copyright   Copyright (c) 2019, MD Sultan Nasir Uddin(manikdrmc@gmail.com)
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCP_Article extends WPCP_Campaign {

	/**
	 * WPCP_Feed constructor.
	 */
	public function __construct() {
		//campaign settings
		add_filter( 'wpcp_modules', array( $this, 'register_module' ) );
		add_action( 'wpcp_after_campaign_keyword_input', array( $this, 'campaign_option_fields' ), 10, 2 );
		add_action( 'wpcp_update_campaign_settings', array( $this, 'update_campaign_settings' ), 10, 2 );
		add_action( 'wpcp_fetching_campaign_contents', array( $this, 'prepare_contents' ) );
	}

	/**
	 * Register article module
	 *
	 * @since 1.0.0
	 *
	 * @param $modules
	 *
	 * @return mixed
	 */
	public function register_module( $modules ) {
		$modules['article'] = [
			'title'       => __( 'Article', 'wp-content-pilot' ),
			'description' => __( 'Scraps articles based on links', 'wp-content-pilot' ),
			'supports'    => self::get_template_tags(),
			'callback'    => __CLASS__,
		];

		return $modules;
	}

	/**
	 * Supported template tags
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_template_tags() {
		return array(
			'title'     => __( 'Title', 'wp-content-pilot' ),
			'except'    => __( 'Summary', 'wp-content-pilot' ),
			'content'   => __( 'Content', 'wp-content-pilot' ),
			'image_url' => __( 'Main image url', 'wp-content-pilot' ),
			'source_url' => __( 'Source link', 'wp-content-pilot' ),
		);
	}

	/**
	 * add extra fields
	 *
	 * @since 1.0.0
	 *
	 * @param $post_id
	 * @param $campaign_type
	 *
	 * @return bool
	 */
	public function campaign_option_fields( $post_id, $campaign_type ) {

		if ( 'article' != $campaign_type ) {
			return false;
		}

		echo content_pilot()->elements->select( array(
			'label'            => __( 'Keyword Type', 'wp-content-pilot' ),
			'name'             => '_keywords_type',
			'placeholder'      => '',
			'show_option_all'  => '',
			'show_option_none' => '',
			'options'          => array(
				'any'   => __( 'Any Words', 'wp-content-pilot' ),
				'exact' => __( 'Exact Word', 'wp-content-pilot' ),
			),
			'required'         => true,
			'double_columns'   => true,
			'selected'         => wpcp_get_post_meta( $post_id, '_keywords_type', 'any' ),
		) );

	}

	/**
	 * update campaign settings postmeta
	 *
	 * @since 1.0.0
	 *
	 * @param $post_id
	 * @param $posted
	 */
	public function update_campaign_settings( $post_id, $posted ) {

		$raw_keywords = empty( $posted['_keywords'] ) ? '' : esc_html( $posted['_keywords'] );
		$keywords     = wpcp_string_to_array( $raw_keywords, ',', array( 'trim' ) );
		$str_words    = implode( ',', $keywords );

		update_post_meta( $post_id, '_keywords', $str_words );
		update_post_meta( $post_id, '_keywords_type', empty( $posted['_keywords_type'] ) ? 'any' : esc_attr( $posted['_keywords_type'] ) );
	}

	public function setup() {
		add_filter( 'wpcp_fetched_links', array( $this, 'skip_base_domain' ) );
	}

	/**
	 * Skip base domain from fetched urls
	 *
	 * @since 1.0.0
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function skip_base_domain( $links ) {

		if ( 'on' != wpcp_get_post_meta( $this->campaign_id, '_skip_base_domain', true ) ) {
			return $links;
		}

		foreach ( $links as $key => $link ) {
			$url_parts = wp_parse_url( $link['url'] );
			if ( strlen( $url_parts['path'] ) < 5 ) {
				unset( $links[ $key ] );
			}
		}

		return $links;
	}

	/**
	 * Discover new links
	 *
	 * since 1.0.0
	 *
	 * @return array|mixed|object
	 */
	public function discover_links() {
		$page  = $this->get_page_number( 0 );
		$links = array();
		if ( ! $page ) {
			for ( $page = 0; $page <= 10; $page ++ ) {
				$links = $this->bing_search( $this->keyword, $page );
				if ( is_wp_error( $links ) ) {
					return $links;
				}

				if ( ! empty( $links ) ) {
					break;
				}
			}
		} else {
			$links = $this->bing_search( $this->keyword, $page );
			if ( is_wp_error( $links ) ) {
				return $links;
			}
		}
		$this->set_page_number( $page + 1 );

		$sanitized_links = array();

		foreach ( $links as $link ) {
			$sanitized_links[] = array(
				'title'       => $link['title'],
				'content'     => $link['description'],
				'url'         => $link['link'],
				'image'       => '',
				'raw_content' => $link['description'],
				'score'       => '0',
				'gmt_date'    => gmdate( 'Y-m-d H:i:s', strtotime( $link['pubDate'] ) ),
				'status'      => 'fetched',
			);

		}

		return $sanitized_links;
	}

	/**
	 * Hook in background process and prepare contents
	 *
	 * @since 1.0.0
	 *
	 * @param $link
	 *
	 * @return bool
	 */
	public function prepare_contents( $link ) {

		if ( 'article' !== $link->camp_type ) {
			return false;
		}

		wpcp_update_link( $link->id, [ 'status' => 'failed' ] );

		$request = wpcp_remote_get( $link->url );
		$body    = wpcp_retrieve_body( $request );

		if ( is_wp_error( $body ) ) {
			wpcp_update_link( $link->id, [ 'status' => 'http_error' ] );

			return false;
		}

		$article = wpcp_get_readability( $body, $link->url );

		if ( is_wp_error( $article ) ) {
			wpcp_update_link( $link->id, [ 'status' => 'readability_error' ] );

			return false;
		}

		wpcp_update_link( $link->id, array(
			'title'       => $article['title'],
			'content'     => trim( $article['content'] ),
			'raw_content' => trim( $article['content'] ),
			'image'       => $article['image'],
			'score'       => wpcp_get_read_ability_score( $article['content'] ),
			'status'      => empty( $article['content'] ) ? 'not_readable' : 'ready',
		) );

	}

	/**
	 * fetch post
	 *
	 * since 1.0.0
	 *
	 * @param $link
	 *
	 * @return array
	 */
	public function get_post( $link ) {
		$article = array(
			'title'       => $link->title,
			'raw_title'   => $link->title,
			'content'     => $link->content,
			'raw_content' => $link->raw_content,
			'image_url'   => $link->image,
			'source_url'  => $link->url,
			'date'        => $link->gmt_date ? get_date_from_gmt( $link->gmt_date ) : current_time( 'mysql' ),
			'score'       => $link->score,
		);

		return $article;
	}


}
