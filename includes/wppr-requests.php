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
		'export_personal_data', array(
			'labels'           => array(
				'name'          => __( 'Export Personal Data Requests' ),
				'singular_name' => __( 'Export Personal Data Request' ),
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

	register_post_type(
		'remove_personal_data', array(
			'labels'           => array(
				'name'          => __( 'Remove Personal Data Requests' ),
				'singular_name' => __( 'Remove Personal Data Request' ),
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
		$user_id = $user->ID;
	}

	$privacy_request_id = wp_insert_post( array(
		'post_author'   => $user_id,
		'post_status'   => 'action-pending',
		'post_type'     => _wp_privacy_action_post_type( $action ),
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
 * Resend an existing request and return the result.
 *
 * @param int $privacy_request_id Request ID.
 * @return bool|WP_Error
 */
function _wp_privacy_resend_request( $privacy_request_id ) {
	$privacy_request_id = absint( $privacy_request_id );
	$privacy_request    = get_post( $privacy_request_id );

	if ( ! $privacy_request || ! in_array( $privacy_request->post_type, array_map( '_wp_privacy_action_post_type', _wp_privacy_action_request_types() ), true ) ) {
		return new WP_Error( 'privacy_request_error', __( 'Invalid request.' ) );
	}

	$email_address = get_post_meta( $privacy_request_id, '_user_email', true );
	$action        = get_post_meta( $privacy_request_id, '_action_name', true );
	$description   = _wp_privacy_action_description( $action );
	$result        = wp_send_account_verification_key( $email_address, $action, $description, array(
		'privacy_request_id' => $privacy_request_id,
	) );

	if ( is_wp_error( $result ) ) {
		return $result;
	} elseif ( ! $result ) {
		return new WP_Error( 'privacy_request_error', __( 'Unable to initiate verification request.' ) );
	}

	wp_update_post( array(
		'ID'            => $privacy_request_id,
		'post_status'   => 'action-pending',
		'post_date'     => current_time( 'mysql', false ),
		'post_date_gmt' => current_time( 'mysql', true ),
	) );

	return true;
}

/**
 * Marks a request as completed by the admin and logs the datetime.
 *
 * @param int $privacy_request_id Request ID.
 * @return bool|WP_Error
 */
function _wp_privacy_completed_request( $privacy_request_id ) {
	$privacy_request_id = absint( $privacy_request_id );
	$privacy_request    = get_post( $privacy_request_id );

	if ( ! $privacy_request || ! in_array( $privacy_request->post_type, array_map( '_wp_privacy_action_post_type', _wp_privacy_action_request_types() ), true ) ) {
		return new WP_Error( 'privacy_request_error', __( 'Invalid request.' ) );
	}

	wp_update_post( array(
		'ID'          => $privacy_request_id,
		'post_status' => 'action-completed',
	) );

	update_post_meta( $privacy_request_id, '_completed_timestamp', time() );
}

/**
 * Update log when privacy action is confirmed.
 *
 * @param array $result Result of the action from the user.
 */
function _wp_privacy_account_action_confirmed( $result ) {
	if ( isset( $result['action'], $result['request_data'], $result['request_data']['privacy_request_id'] ) && in_array( $result['action'], _wp_privacy_action_request_types(), true ) ) {
		$privacy_request_id = absint( $result['request_data']['privacy_request_id'] );
		$privacy_request    = get_post( $privacy_request_id );

		if ( ! $privacy_request || ! in_array( $privacy_request->post_type, array_map( '_wp_privacy_action_post_type', _wp_privacy_action_request_types() ), true ) ) {
			return;
		}

		update_post_meta( $privacy_request_id, '_confirmed_timestamp', time() );
		wp_update_post( array(
			'ID'          => $privacy_request_id,
			'post_status' => 'action-confirmed',
		) );
	}
}

/**
 * Update log when privacy action failed.
 *
 * @param array $result Result of the action from the user.
 */
function _wp_privacy_account_action_failed( $result ) {
	if ( isset( $result['action'], $result['request_data'], $result['request_data']['privacy_request_id'] ) && in_array( $result['action'], _wp_privacy_action_request_types(), true ) ) {
		$privacy_request_id = absint( $result['request_data']['privacy_request_id'] );
		$privacy_request    = get_post( $privacy_request_id );

		if ( ! $privacy_request || ! in_array( $privacy_request->post_type, array_map( '_wp_privacy_action_post_type', _wp_privacy_action_request_types() ), true ) ) {
			return;
		}

		wp_update_post( array(
			'ID'          => $privacy_request_id,
			'post_status' => 'action-failed',
		) );
	}
}

/**
 * Get all request types.
 *
 * @return array
 */
function _wp_privacy_action_request_types() {
	return array(
		'export_personal_data',
		'remove_personal_data',
	);
}

/**
 * Get action description from the name.
 *
 * @return string
 */
function _wp_privacy_action_description( $request_type ) {
	switch ( $request_type ) {
		case 'export_personal_data':
			return __( 'Export Personal Data' );
		case 'remove_personal_data':
			return __( 'Remove Personal Data' );
	}
}

/**
 * Get action post type from the name.
 *
 * @return string
 */
function _wp_privacy_action_post_type( $request_type ) {
	switch ( $request_type ) {
		case 'export_personal_data':
			return 'export_personal_data';
		case 'remove_personal_data':
			return 'remove_personal_data';
	}
}

/**
 * Return statuses for requests.
 *
 * @return array
 */
function _wp_privacy_statuses() {
	return array(
		'action-pending'   => __( 'Pending' ),      // Pending confirmation from user.
		'action-confirmed' => __( 'Confirmed' ),    // User has confirmed the action.
		'action-failed'    => __( 'Failed' ),       // User failed to confirm the action.
		'action-completed'    => __( 'Completed' ), // Admin has handled the request.
	);
}
