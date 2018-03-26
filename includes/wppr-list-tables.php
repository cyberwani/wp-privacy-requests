<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WP_Personal_Data_Export_Requests_Table extends WP_List_Table {
	function set_items( $data ) {
		$this->items = $data;
	}

	function get_columns() {
		$columns = array(
			'email'     => __( 'Email' ),
			'type'      => __( 'Request type' ),
			'requested' => __( 'Requested' ),
			'verified'  => __( 'Verified' ),
			'actions'   => __( 'Export File Actions' ),
		);

		return $columns;
	}

	function prepare_items() {
		global $export_requests;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $export_requests;
	}

	function column_default( $item, $column_name ) {
		$cell_value = $item[ $column_name ];

		if ( in_array( $column_name, array( 'requested', 'verified' ) ) ) {
			if ( empty( $cell_value ) ) {
				return '-';
			}

			$time_diff = current_time( 'timestamp', true ) - $cell_value;
			if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
				return sprintf( __( '%s ago' ), human_time_diff( $cell_value ) );
			}

			return
				date( get_option( 'date_format' ), $cell_value ) .
				'<br>' .
				date( get_option( 'time_format' ), $cell_value );
		}

		if ( 'actions' === $column_name ) {
			return '<a href="#">Download</a> | <a href="#">Send via Email</a>';
		}

		return $cell_value;
	}

	function column_email( $item ) {
		// TODO links, nonces

		$actions = array(
			'resend'   => __( '<a href="#">Re-send verification email</a>' ),
			'delete'   => __( '<a href="#">Delete</a>' ),
		);

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $actions ) );
	}

	function column_type( $item ) {
		// TODO nicenames

		echo esc_html( $item['action'] );
	}
}
