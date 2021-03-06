<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * WP_Privacy_Requests_Table class.
 */
abstract class WP_Privacy_Requests_Table extends WP_List_Table {

	/**
	 * Action name for the requests this table will work with. Classes
	 * which inherit from WP_Privacy_Requests_Table should define this.
	 * e.g. 'export_personal_data'
	 *
	 * @var string $request_type Name of action.
	 */
	protected $request_type = 'INVALID';

	/**
	 * Get columns to show in the list table.
	 *
	 * @param array Array of columns.
	 */
	public function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Requester' ),
			'status'     => __( 'Status' ),
			'requested'  => __( 'Requested' ),
			'next_steps' => __( 'Next Steps' ),
		);
		return $columns;
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array();
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
	 * Get an associative array ( id => link ) with the list
	 * of views available on this table.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current_status = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ): '';
		$statuses       = _wp_privacy_statuses();
		$views          = array();
		$admin_url      = admin_url( 'tools.php?page=' . $this->request_type );
		$counts         = wp_count_posts( _wp_privacy_action_post_type( $this->request_type ) );

		$current_link_attributes = empty( $current_status ) ? ' class="current" aria-current="page"' : '';
		$views['all']            = '<a href="' . esc_url( $admin_url ) . "\" $current_link_attributes>" . esc_html__( 'All' ) . ' (' . absint( array_sum( (array) $counts ) ) . ')</a>';

		foreach ( $statuses as $status => $label ) {
			$current_link_attributes = $status === $current_status ? ' class="current" aria-current="page"' : '';
			$views[ $status ]        = '<a href="' . esc_url( add_query_arg( 'filter-status', $status, $admin_url ) ) . "\" $current_link_attributes>" . esc_html( $label ) . ' (' . absint( $counts->$status ) . ')</a>';
		}

		return $views;
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Remove request(s)' ),
			'resend' => __( 'Re-send request(s)' ),
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action      = $this->current_action();
		$request_ids = isset( $_REQUEST['request_id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['request_id'] ) ) : array(); // WPCS: input var ok, CSRF ok.

		if ( $request_ids ) {
			check_admin_referer( 'bulk-privacy_requests' );
		}

		switch ( $action ) {
			case 'delete':
				$count = 0;

				foreach ( $request_ids as $request_id ) {
					if ( wp_delete_post( $request_id, true ) ) {
						$count ++;
					}
				}

				add_settings_error(
					'bulk_action',
					'bulk_action',
					sprintf( _n( 'Deleted %d request', 'Deleted %d requests', $count ), $count ),
					'updated'
				);
				break;
			case 'resend':
				$count = 0;

				foreach ( $request_ids as $request_id ) {
					if ( _wp_privacy_resend_request( $request_id ) ) {
						$count ++;
					}
				}

				add_settings_error(
					'bulk_action',
					'bulk_action',
					sprintf( _n( 'Re-sent %d request', 'Re-sent %d requests', $count ), $count ),
					'updated'
				);
				break;
		}
	}

	/**
	 * Prepare items to output.
	 */
	public function prepare_items() {
		global $wpdb;

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
			'post_type'      => _wp_privacy_action_post_type( $this->request_type ),
			'posts_per_page' => $posts_per_page,
			'offset'         => isset( $_REQUEST['paged'] ) ? max( 0, absint( $_REQUEST['paged'] ) - 1 ) * $posts_per_page: 0,
			'post_status'    => 'any',
		);

		if ( ! empty( $_REQUEST['filter-status'] ) ) {
			$filter_status       = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : '';
			$args['post_status'] = $filter_status;
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['meta_query'] = array(
				$name_query,
				'relation'  => 'AND',
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
				'completed'  => get_post_meta( $privacy_request->ID, '_completed_timestamp', true ),
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
		return sprintf( '<input type="checkbox" name="request_id[]" value="%1$s" /><span class="spinner"></span>', esc_attr( $item['request_id'] ) );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_status( $item ) {
		$status        = get_post_status( $item['request_id'] );
		$status_object = get_post_status_object( $status );

		if ( ! $status_object || empty( $status_object->label ) ) {
			return '-';
		}

		$timestamp = false;

		switch ( $status ) {
			case 'action-confirmed':
				$timestamp = $item['confirmed'];
				break;
			case 'action-completed':
				$timestamp = $item['completed'];
				break;
		}

		echo '<span class="status-label status-' . esc_attr( $status ) . '">';
		echo esc_html( $status_object->label );

		if ( $timestamp ) {
			echo ' (' . $this->get_timestamp_as_date( $timestamp ) . ')';
		}

		echo '</span>';
	}

	/**
	 * Convert timestamp for display.
	 *
	 * @param int $timestamp Event timestamp.
	 * @return string
	 */
	protected function get_timestamp_as_date( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		$time_diff = current_time( 'timestamp', true ) - $timestamp;

		if ( $time_diff >= 0 && $time_diff < DAY_IN_SECONDS ) {
			return sprintf( __( '%s ago' ), human_time_diff( $timestamp ) );
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Default column handler.
	 *
	 * @param array $item         Item being shown.
	 * @param string $column_name Name of column being shown.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$cell_value = $item[ $column_name ];

		if ( in_array( $column_name, array( 'requested' ), true ) ) {
			return $this->get_timestamp_as_date( $cell_value );
		}

		return $cell_value;
	}

	/**
	 * Actions column. Overriden by children.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( array() ) );
	}

	/**
	 * Next steps column. Overriden by children.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @param object $item The current item
	 */
	public function single_row( $item ) {
		$status = get_post_status( $item['request_id'] );

		echo '<tr class="status-' . esc_attr( $status ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Embed scripts used to perform actions. Overriden by children.
	 */
	public function embed_scripts() {}
}