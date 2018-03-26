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
			'cb'        => '<input type = "checkbox" />',
			'email'     => __( 'Requester' ),
			'type'      => __( 'Type' ),
			'status'    => __( 'Status' ),
			'requested' => __( 'Requested' ),
			'confirmed' => __( 'Confirmed' ),
		);
		return $columns;
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'requested' => array( 'date', false ),
			'confirmed' => array( 'confirmed', true ),
			'email'     => array( 'email', true ),
		);
	}

	/**
	 * Default primary column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'email';
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete requests' ),
			'resend' => __( 'Re-send verification email' ),
		);
	}

	/**
	 * Process bylk actions.
	 */
	public function process_bulk_action() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-privacy_requests' ) ) {
			return;
		}

		$action      = $this->current_action();
		$request_ids = wp_parse_id_list( $_POST['request_id'] );

		switch ( $action ) {
			case 'delete':
				foreach ( $request_ids as $request_id ) {
					wp_delete_post( $request_id, true );
				}
				break;
			case 'resend':
				foreach ( $request_ids as $request_id ) {
					$action = get_post_meta( $request_id, '_action_name', true );
					$email  = get_post_meta( $request_id, '_user_email', true );

					if ( is_email( $email ) && $action ) {
						wp_send_account_verification_key( $email, $action, _wp_privacy_action_description( $action ), array(
							'privacy_request_id' => $request_id,
						) );
						wp_update_post( array(
							'ID'            => $request_id,
							'post_status'   => 'pending',
							'post_date'     => current_time( 'mysql', false ),
							'post_date_gmt' => current_time( 'mysql', true ),
						), $wp_error );
					}
				}
				break;
		}
	}

	/**
	 * Prepare items to output.
	 */
	public function prepare_items() {
		global $wpdb;

		$this->process_bulk_action();

		$primary               = $this->get_primary_column_name();
		$this->_column_headers = array( 
			$this->get_columns(), 
			array(), 
			$this->get_sortable_columns(),
			$primary,
		);

		$this->items    = array();
		$posts_per_page = 20;
		$args           = array(
			'post_type'      => 'privacy_request',
			'posts_per_page' => $posts_per_page,
			'offset'         => isset( $_REQUEST['paged'] ) ? max( 0, absint( $_REQUEST['paged'] ) - 1 ) * $posts_per_page: 0,
			'post_status'    => 'any',
		);

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$orderby = sanitize_text_field( $_REQUEST['orderby'] );
			$order   = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_text_field( $_REQUEST['order'] ) ) : '';

			switch ( $orderby ) {
				case 'date':
					$args['orderby'] = 'post_date';
					break;
				case 'confirmed':
					$args['orderby']  = 'meta_value';
					$args['meta_key'] = '_confirmed_timestamp';
					break;
				case 'email':
					$args['orderby']  = 'meta_value';
					$args['meta_key'] = '_user_email';
					break;
			}

			$args['order'] = 'ASC' === $order ? 'ASC' : 'DESC';
		}

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
				'request_id' => $privacy_request->ID,
				'user_id'    => $privacy_request->post_author,
				'email'      => get_post_meta( $privacy_request->ID, '_user_email', true ),
				'action'     => get_post_meta( $privacy_request->ID, '_action_name', true ),
				'requested'  => strtotime( $privacy_request->post_date_gmt ),
				'confirmed'  => get_post_meta( $privacy_request->ID, '_confirmed_timestamp', true ),
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
	 * Checkbox column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="request_id[]" value="%1$s" />', esc_attr( $item['request_id'] ) );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_status( $item ) {
		$status_object = get_post_status_object( get_post_status( $item['request_id'] ) );
		return $status_object && ! empty( $status_object->label ) ? esc_html( $status_object->label ) : '-';
	}

	/**
	 * Default column handler.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$cell_value = $item[ $column_name ];

		if ( in_array( $column_name, array( 'requested', 'confirmed' ), true ) ) {
			if ( empty( $cell_value ) ) {
				return '-';
			}

			$time_diff = current_time( 'timestamp', true ) - $cell_value;

			if ( $time_diff >= 0 && $time_diff < DAY_IN_SECONDS ) {
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
	 * @return string
	 */
	public function column_email( $item ) {
		// TODO links, nonces

		$row_actions = array(
			'something' => __( 'Put action specific links here' ),
		);

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $row_actions ) );
	}

	/**
	 * Type column.
	 *
	 * @param array $item Item being shown.
	 * @return string
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
	 * @return string
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
