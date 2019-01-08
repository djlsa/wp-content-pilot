<?php
/*ACTIONS*/
add_action( 'wpcp_per_minute_scheduled_events', 'wpcp_run_automatic_campaign' );
add_action( 'wpcp_before_using_link', 'wpcp_mark_link_as_failed' );
add_action( 'wpcp_after_using_link', 'wpcp_mark_link_as_success' );

add_action('wpcp_after_post_publish', 'wpcp_maybe_set_featured_image', 10, 5);
add_action( 'wpcp_after_post_publish', 'update_campaign_status', 10, 3 );
add_action( 'wpcp_after_post_publish', 'wpcp_set_post_categories', 10, 4 );


add_action('wpcp_disable_campaign', 'wpcp_log_campaign_disable', 10);
add_action('wpcp_disable_keyword', 'wpcp_log_keyword_disable', 10, 2);

//action after inserting post
add_action('wpcp_after_post_publish', 'wpcp_limit_title_length', 10, 2);
add_action('wpcp_after_post_publish', 'wpcp_limit_content_length', 10, 2);

/*FILTER*/
add_filter('wpcp_campaign_row_article_data', 'wpcp_campaign_fix_urls_from_article_data', 10, 1);


//rejection filter
add_filter('wpcp_article_before_post_insert', 'wpcp_test_article_acceptance', 10, 3);

//content filter
add_filter( 'wpcp_post_content', 'wpcp_set_content_html_or_text', 10, 3 );
add_filter( 'wpcp_post_content', 'wpcp_remove_unauthorized_html', 15, 3 );
add_filter( 'wpcp_post_content', 'wpcp_maybe_remove_hyperlinks', 20, 3 );
add_filter( 'wpcp_post_content', 'wpcp_maybe_remove_images', 25, 3 );
add_filter( 'wpcp_post_content', 'wpcp_maybe_fix_links', 30, 3 );
add_filter( 'wpcp_post_content', 'wpcp_post_content_as_template', 35, 3 );


//title
add_filter( 'wpcp_post_title', 'wpcp_post_title_as_template', 10, 3 );

//post setup
add_filter('wpcp_post_type', 'wpcp_set_post_type', 10, 3);
add_filter('wpcp_post_status', 'wpcp_set_post_status', 10, 3);
add_filter('wpcp_post_author', 'wpcp_set_post_author', 10, 3);
add_filter('wpcp_post_excerpt', 'wpcp_set_post_excerpt', 10, 3);
add_filter('wpcp_post_comment_status', 'wpcp_set_comment_status', 10, 3);
add_filter('wpcp_post_ping_status', 'wpcp_set_ping_status', 10, 3);


//settings
add_filter('wpcp_fetched_links', 'wpcp_reject_banned_hosts', 10, 1);

//wp related
add_action(  'transition_post_status',  'wpcp_handle_campaign_post_status', 10, 3 );






