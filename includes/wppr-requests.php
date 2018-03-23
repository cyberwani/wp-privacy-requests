<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function _wp_privacy_get_all_unconfirmed_personal_data_export_requests() {
	global $wpdb;

	$requests = array();

	$registered_user_export_requests = $wpdb->get_results(
		"SELECT * FROM $wpdb->usermeta WHERE meta_key LIKE '_account_action_%'",
		ARRAY_A
	);

	foreach ( (array) $registered_user_export_requests as $export_request ) {
		$user = get_user_by( 'id', $export_request['user_id'] );
		// TODO handle user not found

		$email    = $user->user_email;
		$username = $user->user_login;
		$details  = explode( ':', $export_request['meta_value'] );
		// TODO handle malformed details

		$requests[] = array(
			'email'     => $email,
			'requested' => $details[0],
		);
	}

	$email_only_export_requests = $wpdb->get_results(
		"SELECT * FROM $wpdb->options WHERE option_name LIKE '_account_action_%'",
		ARRAY_A
	);

	foreach ( (array) $email_only_export_requests as $export_request ) {
		$details  = explode( ':', $export_request['option_value'] );
		// TODO handle malformed details

		$requests[] = array(
			'email' => $details[2],
			'requested' => $details[0],
		);
	}

	return $requests;
}
