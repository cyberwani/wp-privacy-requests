<?php
defined( 'ABSPATH' ) || exit;


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

	foreach ( _wp_privacy_statuses() as $name => $label ) {
		register_post_status(
			$name, array(
				'label'               => $label,
				'internal'            => true,
				'_builtin'            => true, /* internal use only. */
				'exclude_from_search' => false,
			)
		);
	}
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
		'post_author'   => $user_id,
		'post_status'   => 'action-pending',
		'post_type'     => 'privacy_request',
		'post_date'     => current_time( 'mysql', false ),
		'post_date_gmt' => current_time( 'mysql', true ),
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
		wp_update_post( array(
			'ID'          => $privacy_request_id,
			'post_status' => 'action-confirmed',
		), $wp_error );
	}
}

/**
 * Update log when privacy action failed.
 *
 * @param array $result Result of the action from the user.
 */
function _wp_privacy_account_action_failed( $result ) {
	if ( isset( $result['action'], $result['request_data'], $result['request_data']['privacy_request_id'] ) && in_array( $result['action'], array( 'remove_personal_data', 'export_personal_data' ), true ) ) {
		$privacy_request_id = absint( $result['request_data']['privacy_request_id'] );
		$privacy_request    = get_post( $privacy_request_id );

		if ( ! $privacy_request || 'privacy_request' !== $privacy_request->post_type ) {
			return;
		}

		wp_update_post( array(
			'ID'          => $privacy_request_id,
			'post_status' => 'action-failed',
		), $wp_error );
	}
}

/**
 * Get action description from the name.
 */
function _wp_privacy_action_description( $action_name ) {
	switch ( $action_name ) {
		case 'export_personal_data':
			return __( 'Export personal data' );
		case 'remove_personal_data':
			return __( 'Remove personal data' );
	}
}