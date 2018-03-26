<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function _wp_privacy_get_all_unconfirmed_personal_data_export_requests() {
	global $wpdb;

	$requests        = array();
	$search_requests = array_merge(
		$wpdb->get_results(
			"SELECT 0 as user_id, option_value as `value` FROM $wpdb->options WHERE option_name LIKE '_verify_action_%'",
			ARRAY_A
		),
		$wpdb->get_results(
			"SELECT user_id, meta_value as `value` FROM $wpdb->usermeta WHERE meta_key LIKE '_verify_action_%'",
			ARRAY_A
		)
	);

	foreach ( (array) $search_requests as $raw_request ) {
		$request_data = wp_parse_args( 
			(array) json_decode( $raw_request['value'], true ), 
			array(
				'action' => '',
				'email'  => '',
				'time'   => '',
			) 
		);

		$request = array(
			'user_id'   => absint( $raw_request['user_id'] ),
			'email'     => sanitize_email( $request_data['email'] ),
			'action'    => $request_data['action'],
			'requested' => $request_data['time'],
		);

		$requests[] = $request;
	}

	return $requests;
}
