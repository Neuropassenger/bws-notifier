<?php
// Redirect for wrong queries
if( !isset( $_GET['bws_uid']) || !isset( $_GET['bws_pid'] ) || !isset( $_GET['bws_a'] ) ) {
	wp_redirect( home_url() );
	exit;
}

get_header();

if( get_current_user_id() == $_GET['bws_uid'] ) {
	// Update list of unsubscribed users
	$the_page_name = get_option( "bws_notifier_upage_name" );
	$uu = get_post_meta( $_GET['bws_pid'], 'bws-notifier-unsubcribed', true );

	if( $uu == '' ) {
		$uu = array();
		$key_uid = false;
	} else {
		$key_uid = array_search( $_GET['bws_uid'], $uu );
	}

	if ( $_GET['bws_a'] == 'u' ) {

		if ( $key_uid === false ) {
			$uu[] = intval( $_GET['bws_uid'] );
			update_post_meta( $_GET['bws_pid'], 'bws-notifier-unsubcribed', $uu );

			echo '<p class="unsubscribe-text">You have been unsubscribed! <a href="' . home_url() . '/' . $the_page_name . '/?bws_uid=' . $_GET['bws_uid']
			     . '&bws_pid=' . $_GET['bws_pid'] . '&bws_a=s">Redo</a>.</p>';
		} else {
			echo '<p class="unsubscribe-text">You already have been unsubscribed! <a href="' . home_url() . '/' . $the_page_name . '/?bws_uid=' . $_GET['bws_uid']
			     . '&bws_pid=' . $_GET['bws_pid'] . '&bws_a=s">Redo</a>.</p>';
		}

	} elseif ( $_GET['bws_a'] == 's' ) {

		if ( $key_uid !== false ) {
			unset( $uu[ $key_uid ] );
			update_post_meta( $_GET['bws_pid'], 'bws-notifier-unsubcribed', $uu );

			echo '<p class="unsubscribe-text">You have been subscribed! <a href="' . home_url() . '/' . $the_page_name . '/?bws_uid=' . $_GET['bws_uid']
			     . '&bws_pid=' . $_GET['bws_pid'] . '&bws_a=u">Redo</a>.</p>';
		} else {

			echo '<p class="unsubscribe-text">You already have been subscribed! <a href="' . home_url() . '/' . $the_page_name . '/?bws_uid=' . $_GET['bws_uid']
			     . '&bws_pid=' . $_GET['bws_pid'] . '&bws_a=u">Redo</a>.</p>';
		}
	} else {
		echo '<p class="unsubscribe-text">Wrong link!</p>';
	}
} else {
	echo '<p class="unsubscribe-text">Wrong link!</p>';
}
?>

<?php get_footer(); ?>