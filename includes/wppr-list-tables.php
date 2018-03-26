<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * WP_Personal_Data_Export_Requests_Table class.
 */
class WP_Personal_Data_Export_Requests_Table extends WP_List_Table {
	/**
	 * Set items from data.
	 *
	 * @param array $data Items being shown.
	 */
	public function set_items( $data ) {
		$this->items = $data;
	}

	/**
	 * Get columns to show in the list table.
	 *
	 * @param array Array of columns.
	 */
	public function get_columns() {
		$columns = array(
			'email'     => __( 'Email' ),
			'type'      => __( 'Request type' ),
			'requested' => __( 'Requested' ),
			'confirmed' => __( 'Confirmed' ),
			'actions'   => __( 'Export File Actions' ),
		);
		return $columns;
	}

	/**
	 * Prepare items to output.
	 */
	public function prepare_items() {
		global $export_requests;

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $export_requests;
	}

	/**
	 * Default column handler.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_default( $item, $column_name ) {
		$cell_value = $item[ $column_name ];

		if ( in_array( $column_name, array( 'requested', 'confirmed' ), true ) ) {
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

		return $cell_value;
	}

	/**
	 * Actions column.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_email( $item ) {
		// TODO links, nonces

		$actions = array(
			'resend'   => __( '<a href="#">Re-send verification email</a>' ),
			'delete'   => __( '<a href="#">Delete</a>' ),
		);

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $actions ) );
	}

	/**
	 * Type column.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_type( $item ) {
		switch ( $item['action'] ) {
			case 'remove_personal_data':
				return esc_html__( 'Remove' );
			case 'export_personal_data':
				return esc_html__( 'Export' );
		}
	}

	/**
	 * Actions column.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_actions( $item ) {
		switch ( $item['action'] ) {
			case 'remove_personal_data':
			case 'export_personal_data':
				return '<a href="#">Download</a> | <a href="#">Send via Email</a>';
				break;
		}
	}
}
