<?php
/**
 * WordPress OAuth Server AJAX functionality
 * @var array
 */
$ajax_events = array(
	'remove_client'               => false,
	'regenerate_secret'           => false,
	'wo30notice_dismiss'          => false,
	'users_type_ahead'            => false,
	'remove_self_generated_token' => false,
);

/**
 * loop though all the ajax events and add then as needed
 */
foreach ( $ajax_events as $ajax_event => $nopriv ) {
	add_action( 'wp_ajax_wo_' . $ajax_event, 'wo_ajax_' . $ajax_event );
	if ( $nopriv ) {
		add_action( 'wp_ajax_nopriv_wo_' . $ajax_event, 'wo_ajax_' . $ajax_event );
	}
}

function wo_ajax_remove_self_generated_token() {
	$user_id = get_current_user_id();
	global $wpdb;
	
	$removed = $wpdb->delete(
		"{$wpdb->prefix}oauth_access_tokens",
		array(
			'user_id'      => $user_id,
			'ap_generated' => 1,
		)
	);
	
	print $removed;
	exit;
}

function wo_ajax_users_type_ahead() {
	if ( ! current_user_can( 'manage_options' ) ) {
		exit;
	}
	
	$user_string = sanitize_text_field( $_REQUEST['query'] );
	
	$args = array(
		'search'         => '*' . esc_attr( $user_string ) . '*',
		'search_columns' => array( 'user_login', 'user_email' ),
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => 'first_name',
				'value'   => $user_string,
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'last_name',
				'value'   => $user_string,
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'description',
				'value'   => $user_string,
				'compare' => 'LIKE',
			),
		),
	);
	
	$user_query = new WP_User_Query( $args );
	
	$users = $user_query->get_results();
	
	$new_users = array();
	
	foreach ( $users as $user ) {
		$new_users[] = $user->user_login;
		// array(
		// 'id'   => $user->ID,
		// 'name' => $user->user_login
		// );
	}
	
	print_r( json_encode( $new_users ) );
	
	exit;
}

/**
 * Remove a client
 *
 * @return [type] [description]
 */
function wo_ajax_remove_client() {
	if ( ! current_user_can( 'manage_options' ) && wp_verify_nonce( $_POST['client_id'], 'remove_' . $_POST['client_id'] ) ) {
		exit;
	}
	
	$id = intval( $_POST['client_id'] );
	
	// Verify the post is an OAuth Client
	if ( 'wo_client' == get_post_type( $id ) ) {
		wp_delete_post( $id, true );
		print '1';
		exit;
	}
	
	print '0';
	exit;
}

/**
 * [wo_ajax_regenerate_secret description]
 *
 * @return [type] [description]
 */
function wo_ajax_regenerate_secret() {
	// Check current user caps
	if ( ! current_user_can( 'manage_options' ) ) {
		exit;
	}
	
	// Generate new key
	$new_secret = wo_gen_key();
	
	global $wpdb;
	$action = $wpdb->update( "{$wpdb->prefix}oauth_clients", array( 'client_secret' => $new_secret ), array( 'client_id' => $_POST['data'] ) );
	
	// if the action was good, return
	if ( $action ) {
		print json_encode(
			array(
				'error'      => false,
				'new_secret' => $new_secret,
			)
		);
	} else {
		print json_encode(
			array(
				'error'             => true,
				'error_description' => 'Something went wrong while updating the clients secret',
			)
		);
	}
	
	exit;
}

/**
 * Handle Dismiss of 30 notice
 */
function wo_ajax_wo30notice_dismiss() {
	// Check the current user caps
	if ( ! current_user_can( 'manage_options' ) ) {
		exit;
	}
	
	// Set the option for the 30 notice as today's date and not bug for 24 hours.
	update_option( 'wp_30day_notice', current_time( 'timestamp' ) );
	
	print json_encode(
		array(
			'success' => true,
			'message' => 'Notification time has been set successfully',
		)
	);
	
	exit;
}
