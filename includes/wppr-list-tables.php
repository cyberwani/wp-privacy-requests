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
		global $wpdb;

		$this->_column_headers = array( 
			$this->get_columns(), 
			array(), 
			array(
				'requested',
				'confirmed',
			), 
		);

		$this->items    = array();
		$posts_per_page = 20;
		$args           = array(
			'post_type'      => 'privacy_request',
			'posts_per_page' => $posts_per_page,
			'offset'         => isset( $_REQUEST['paged'] ) ? max( 0, absint( $_REQUEST['paged'] ) - 1 ) * $posts_per_page: 0,
			'post_status'    => 'any',
		);

		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_user_email',
					'value'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ): '',
					'compare' => 'LIKE'
				),
			);
		}

		$privacy_requests_query = new WP_Query( $args );
		$privacy_requests       = $privacy_requests_query->posts;

		foreach ( $privacy_requests as $privacy_request ) {
			$this->items[] = array(
				'user_id'   => $privacy_request->post_author,
				'email'     => get_post_meta( $privacy_request->ID, '_user_email', true ),
				'action'    => get_post_meta( $privacy_request->ID, '_action_name', true ),
				'requested' => strtotime( $privacy_request->post_date_gmt ),
				'confirmed' => get_post_meta( $privacy_request->ID, '_confirmed_timestamp', true ),
			);
		}

		$this->set_pagination_args(
			array(
				'total_items' => $privacy_requests_query->found_posts,
				'per_page'    => $posts_per_page,
			)
		);
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
