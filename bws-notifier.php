<?php
/*
Plugin Name: BWS Notifier
Description: Плагин для отправки уведомлений на e-mail о публикации новых постов и комментариев.
Version: 1.0
Author: Oleg Sokolov
Author URI: http://thegazer.ru
*/

add_action( 'wp_enqueue_scripts', 'bws_notifier_plugin_scripts' );
function bws_notifier_plugin_scripts() {
	wp_enqueue_style( 'bws-notifier', plugins_url( 'style.css', __FILE__ ) );
}

// Plugin activation
register_activation_hook(__FILE__,'bws_notifier_install');
function bws_notifier_install() {
	update_option( 'bws_notifier_last_mailing', time() );

	global $wpdb;

	$the_page_title = 'Unsubscribe';
	$the_page_name = 'unsubscribe';

	// Change name in menu
	delete_option("bws_notifier_upage_title");
	add_option("bws_notifier_upage_title", $the_page_title, '', 'yes');
	// Page name (slug)
	delete_option("bws_notifier_upage_name");
	add_option("bws_notifier_upage_name", $the_page_name, '', 'yes');
	// ID
	delete_option("bws_notifier_upage_id");
	add_option("bws_notifier_upage_id", '0', '', 'yes');

	$the_page = get_page_by_title( $the_page_title );

	if ( ! $the_page ) {
		// Create post
		$_p = array();
		$_p['post_title'] = $the_page_title;
		$_p['post_content'] = 'The content <a href="#">Hello</a>';
		$_p['post_status'] = 'publish';
		$_p['post_type'] = 'page';
		$_p['comment_status'] = 'closed';
		$_p['ping_status'] = 'closed';
		$_p['post_category'] = array(1); // Default

		// Put in database
		$the_page_id = wp_insert_post( $_p );
	} else {
		// Restore from trash
		$the_page_id = $the_page->ID;

		// Refresh page status
		$the_page->post_status = 'publish';
		$the_page_id = wp_update_post( $the_page );
	}
	// Update id
	delete_option( 'bws_notifier_upage_id' );
	add_option( 'bws_notifier_upage_id', $the_page_id );
}

// Plugin deactivation
register_deactivation_hook( __FILE__, 'bws_notifier_remove' );
function bws_notifier_remove() {
	global $wpdb;

	$the_page_title = get_option( "bws_notifier_upage_title" );
	$the_page_name = get_option( "bws_notifier_upage_name" );

	// Get post id
	$the_page_id = get_option( 'bws_notifier_upage_id' );
	if( $the_page_id ) {
		wp_delete_post( $the_page_id ); // переносим в корзину (полностью не удаляем)
	}

	// Delete options
	delete_option("bws_notifier_upage_title");
	delete_option("bws_notifier_upage_name");
	delete_option("bws_notifier_upage_id");
}

// Plugin setup
add_action( 'plugins_loaded', 'bws_notifier_setup' );
function bws_notifier_setup() {
}

// Handle query
add_filter( 'parse_query', 'bws_notifier_query_parser' );
function bws_notifier_query_parser( $q ) {

	$the_page_name = get_option( "bws_notifier_upage_name" );
	$the_page_id = get_option( 'bws_notifier_upage_id' );

	$qv = $q->query_vars;

	// !PERMALINK?
	if( !$q->did_permalink AND ( isset( $q->query_vars['page_id'] ) ) AND ( intval($q->query_vars['page_id']) == $the_page_id ) ) {
		$q->set('bws_notifier_upage_is_called', TRUE );
		return $q;
	// PERMALINK?
	} elseif( isset( $q->query_vars['pagename'] ) AND ( ($q->query_vars['pagename'] == $the_page_name) OR ($_pos_found = strpos($q->query_vars['pagename'],$the_page_name.'/') === 0) ) ) {
		$q->set('bws_notifier_upage_is_called', TRUE );
		return $q;
	} else {
		$q->set('bws_notifier_upage_is_called', FALSE );
		return $q;
	}
}

// Template for Unsubscribe page
add_filter( 'template_include', 'bws_notifier_unsubscribe_template', 1 );
function bws_notifier_unsubscribe_template( $template_path ) {
	$the_page_id = get_option( 'bws_notifier_upage_id' );
	if ( is_page( $the_page_id ) ) {
		// checks if the file exists in the theme first,
		// otherwise serve the file from the plugin
		if ( $theme_file = locate_template( array( 'bws-notifier-unsubscribe.php' ) ) ) {
			$template_path = $theme_file;
		} else {
			$template_path = plugin_dir_path( __FILE__ ) . '/unsubscribe.php';
		}
	}
	return $template_path;
}

add_action( 'wp_head', 'bws_notifier_mailing' );
// Notifications
function bws_notifier_mailing() {
	$the_page_name = get_option( "bws_notifier_upage_name" );
	// Date of a last mailing
	$last_mailing = get_option( 'bws_notifier_last_mailing' );

	// Update date of a last mailing
	$new_mailing = time();
	update_option( 'bws_notifier_last_mailing', $new_mailing );

	// - Posts notifications
	$date_query = array(
		array(
			'after' => array(
				'year'      => date( 'Y', $last_mailing ),
				'month'     => date( 'm', $last_mailing ),
				'day'       => date( 'd', $last_mailing ),
				'hour'      => date( 'H', $last_mailing ),
				'minute'    => date( 'i', $last_mailing ),
				'second'    => date( 's', $last_mailing )
			),
			'before'    => array(
				'year'      => date( 'Y', $new_mailing ),
				'month'     => date( 'm', $new_mailing ),
				'day'       => date( 'd', $new_mailing ),
				'hour'      => date( 'H', $new_mailing ),
				'minute'    => date( 'i', $new_mailing ),
				'second'    => date( 's', $new_mailing )
			),
		)
	);
	$args = array(
		'date_query' => $date_query
	);

	$q = new WP_Query($args);
	if( $q->have_posts() ) {
		while( $q->have_posts() ){
			$q->the_post();
			// Exclude unsubscribed users
			$uu = get_post_meta( get_the_ID(), 'bws-notifier-unsubcribed' );

			// Creating a mail
			$to = 'turgenoid@gmail.com';

			$users = get_users( array(
				'exclude'   =>  $uu,
				'fields'    => array( 'user_email' )
			) );

			$body = '<h1 style="text-align: center;"><a href="'.get_the_permalink().'">'.get_the_title().'</a></h1>';
			$body .= apply_filters( 'the_content', get_the_content() );

			foreach ( $users as $user ) {
				// Unsubscribe link
				$body .= '<a href="'.home_url().'/'.$the_page_name.'/?bws_uid='.get_user_by( 'email', $user->user_email )->ID
				         .'&bws_pid='.get_the_ID().'&bws_a=u">Unsubscribe from Thread</a>';
				wp_mail( $user->user_email, 'POST#'.get_the_ID().' '.get_the_title(), $body );
			}

			// - Comments notifications
			$comments = get_comments( array(
				'post_id'             => get_the_ID(),
				'date_query'          => $date_query
			) );

			foreach ( $comments as $comment ) {
				$body = '<a href="'.get_the_permalink().'#comment-'.$comment->comment_ID.'">Comment '.$comment->comment_ID.'</a>';
				$body .= apply_filters( 'the_content', $comment->comment_content );

				foreach ( $users as $user ) {
					// Unsubscribe link
					$body .= '<a href="'.home_url().'/'.$the_page_name.'/?bws_uid='.get_user_by( 'email', $user->user_email )->ID
					         .'&bws_pid='.get_the_ID().'&bws_a=u">Unsubscribe from Thread</a>';
					wp_mail( $user->user_email, 'COMMENT#'.$comment->comment_ID, $body );
				}
			}
		}
	}

	wp_reset_postdata();
}
