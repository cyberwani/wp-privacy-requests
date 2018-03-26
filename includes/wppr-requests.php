<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Move to create_initial_post_types in core when done?
 */
add_action( 'init', '_wp_privacy_post_types', 0 );
add_action( 'account_action_confirmed', '_wp_privacy_account_action_confirmed' );
add_action( 'account_action_failed', '_wp_privacy_account_action_failed' );

/**
 * Register CPT to act as a log of requests.
 *
 * @return void
 */
function _wp_privacy_post_types() {
	register_post_type(
		'privacy_request', array(
			'labels'           => array(
				'name'          => __( 'Privacy Requests' ),
				'singular_name' => __( 'Privacy Request' ),
			),
			'public'           => false,
			'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'can_export'       => false,
			'delete_with_user' => false,
		)
	);
	register_post_status(
		'new', array(
			'label'               => 'new',
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			'exclude_from_search' => false,
		)
	);
	register_post_status(
		'confirmed', array(
			'label'               => 'confirmed',
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			'exclude_from_search' => false,
		)
	);
	register_post_status(
		'failed', array(
			'label'               => 'failed',
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			'exclude_from_search' => false,
		)
	);
}

/**
 * Log a request and send to the user.
 *
 * @param string $email_address Email address sending the request to.
 * @param string $action Action being requested.
 * @param string $description Description of request.
 * @return bool|WP_Error depending on success.
 */
function _wp_privacy_create_request( $email_address, $action, $description ) {
	$user_id = 0;
	$user    = get_user_by( 'email', $email_address );

	if ( $user ) {
		$user_id = $user->user_id;
	}

	$privacy_request_id = wp_insert_post( array(
		'post_author' => $user_id,
		'post_status' => 'new',
		'post_type'   => 'privacy_request',
	), true );

	if ( is_wp_error( $privacy_request_id ) ) {
		return $privacy_request_id;
	}

	update_post_meta( $privacy_request_id, '_user_email', $email_address );
	update_post_meta( $privacy_request_id, '_action_name', $action );
	update_post_meta( $privacy_request_id, '_confirmed_timestamp', false );

	return wp_send_account_verification_key( $email_address, $action, $description, array(
		'privacy_request_id' => $privacy_request_id,
	) );
}

/**
 * Update log when privacy action is confirmed.
 *
 * @param array $result Result of the action from the user.
 */
function _wp_privacy_account_action_confirmed( $result ) {
	if ( isset( $result['action'], $result['request_data'], $result['request_data']['privacy_request_id'] ) && in_array( $result['action'], array( 'remove_personal_data', 'export_personal_data' ), true ) ) {
		$privacy_request_id = absint( $result['request_data']['privacy_request_id'] );
		$privacy_request    = get_post( $privacy_request_id );

		if ( ! $privacy_request || 'privacy_request' !== $privacy_request->post_type ) {
			return;
		}

		update_post_meta( $privacy_request_id, '_confirmed_timestamp', time() );
	}
}

/**
 * Update log when privacy action failed.
 *
 * @param array $result Result of the action from the user.
 */
function _wp_privacy_account_action_failed( $result ) {
	// TODO
}

/**
 * Get all requests (from CPT) into a standardized format.
 *
 * @return array Array of requests.
 */
function _wp_privacy_get_all_unconfirmed_personal_data_export_requests() {
	global $wpdb;

	$requests         = array();
	$privacy_requests = get_posts( array(
		'post_type'      => 'privacy_request',
		'posts_per_page' => -1,
		'post_status'    => 'any',
	) );

	foreach ( $privacy_requests as $privacy_request ) {
		$requests[] = array(
			'user_id'   => $privacy_request->post_author,
			'email'     => get_post_meta( $privacy_request->ID, '_user_email', true ),
			'action'    => get_post_meta( $privacy_request->ID, '_action_name', true ),
			'requested' => strtotime( $privacy_request->post_date_gmt ),
			'confirmed' => get_post_meta( $privacy_request->ID, '_confirmed_timestamp', true ),
		);
	}

	return $requests;
}
