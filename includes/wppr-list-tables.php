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
			'type'      => __( 'Action' ),
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
	 * Filters.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		echo '<div class="alignleft actions">';

		if ( 'top' === $which ) {
			$filter_action = isset( $_REQUEST['filter-action'] ) ? sanitize_text_field( $_REQUEST['filter-action'] ) : '';
			$filter_status = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : '';
			?>
			<select name="filter-action">
				<option value=""><?php esc_html_e( 'Show all action types' ); ?></option>
				<?php foreach ( _wp_privacy_actions() as $name => $label ) : ?>
					<option <?php selected( $filter_action, $name ); ?> value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="filter-status">
				<option value=""><?php esc_html_e( 'Show all statuses' ); ?></option>
				<?php foreach ( _wp_privacy_statuses() as $name => $label ) : ?>
					<option <?php selected( $filter_status, $name ); ?> value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			submit_button( __( 'Filter' ), '', 'filter_action', false );
		}

		echo '</div>';
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete request(s)' ),
			'resend' => __( 'Re-send verification email(s)' ),
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-privacy_requests' ) ) {
			return;
		}

		$action      = $this->current_action();
		$request_ids = isset( $_POST['request_id'] ) ? wp_parse_id_list( $_POST['request_id'] ) : array();

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
							'post_status'   => 'action-pending',
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
			'meta_query'     => array(),
		);

		if ( ! empty( $_REQUEST['filter-action'] ) ) {
			$filter_action        = isset( $_REQUEST['filter-action'] ) ? sanitize_text_field( $_REQUEST['filter-action'] ) : '';
			$args['meta_query'][] = array(
				'key'   => '_action_name',
				'value' => $filter_action,
			);
		}

		if ( ! empty( $_REQUEST['filter-status'] ) ) {
			$filter_status       = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : '';
			$args['post_status'] = $filter_status;
		}

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
		return sprintf( '<input type="checkbox" name="request_id[]" value="%1$s" /><span class="spinner"></span>', esc_attr( $item['request_id'] ) );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_status( $item ) {
		$status = get_post_status( $item['request_id'] );
		$status_object = get_post_status_object( $status );
		return $status_object && ! empty( $status_object->label ) ? '<span class="status-label status-' . esc_attr( $status ) . '">' . esc_html( $status_object->label ) . '</span>' : '-';
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
		$row_actions = array();

		if ( 'remove_personal_data' === $item['action'] ) {
			$row_actions['remove_data'] = __( 'Remove personal data' );

			// If we have a user ID, include a delete user action.
			if ( ! empty( $item['user_id'] ) ) {
				$delete_user_url            = wp_nonce_url( "users.php?action=delete&amp;user={$item['user_id']}", 'bulk-users' );
				$row_actions['delete_user'] = "<a class='submitdelete' href='" . $delete_user_url . "'>" . __( 'Delete User' ) . '</a>';
			}

		} else if ( 'export_personal_data' === $item['action'] ) {
			$row_actions['download_data'] = '<a class="download_personal_data" href="#" data-email="' . esc_attr( $item['email'] ) . '">' . __( 'Download Personal Data' ) . '</a>';
			$row_actions['email_data']    = __( 'Email personal data to user' );
		}

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
				return '<span class="dashicons dashicons-trash" title="' . esc_attr__( 'Personal data removal' ) .'"></span>';
			case 'export_personal_data':
				return '<span class="dashicons dashicons-download" title="' . esc_attr__( 'Personal data export' ) .'"></span>';
		}
	}

	public function embed_scripts() {
		$this->embed_exporter_script();
	}

	public function embed_exporter_script() {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );

		$exporter_names = array();
		foreach ( ( array ) $exporters as $exporter ) {
			$exporter_names[] = $exporter['exporter_friendly_name'];
		}

		?>
		<script>
			( function( $ ) {
				$( document ).ready( function() {
					var nonce = <?php echo json_encode( wp_create_nonce( 'wp-privacy-export-personal-data' ) ); ?>;
					var exporterNames = <?php echo json_encode( $exporter_names ); ?>;
					var successMessage = "<?php echo esc_attr( __( 'Export completed successfully' ) ); ?>";
					var failureMessage = "<?php echo esc_attr( __( 'A failure occurred during export' ) ); ?>";
					var spinnerUrl = "<?php echo esc_url( admin_url( '/images/wpspin_light.gif' ) ); ?>";

					$( '.download_personal_data' ).click( function() {
						var downloadData = $( this );
						var emailForExport = downloadData.data( 'email' );
						downloadData.blur();
						var checkColumn = downloadData.parents( 'tr' ).find( '.check-column' );

						function set_row_busy() {
							downloadData.parents( '.row-actions' ).hide();
							checkColumn.find( 'input' ).hide();
							checkColumn.find( '.spinner' ).css( {
								background: 'url( ' + spinnerUrl + ' ) no-repeat',
								'background-size': '16px 16px',
								float: 'right',
								opacity: '.7',
								filter: 'alpha(opacity=70)',
								width: '16px',
								height: '16px',
								margin: '5px 5px 0',
								visibility: 'visible'
							} );
						}

						function set_row_not_busy() {
							downloadData.parents( '.row-actions' ).show();
							checkColumn.find( '.spinner' ).hide();
							checkColumn.find( 'input' ).show();
						}

						function on_exports_done_success( url ) {
							set_row_not_busy();
							alert( successMessage );
							// TODO fetch ZIP
							console.log( url );
						}

						function on_export_failure( textStatus, error ) {
							set_row_not_busy();
							alert( failureMessage );
							alert( error );
						}

						function do_next_export( exporterIndex, pageIndex ) {
							$.ajax( {
								url: ajaxurl,
								data: {
									action: 'wp-privacy-export-personal-data',
									email: emailForExport,
									exporter: exporterIndex,
									page: pageIndex,
									security: nonce,
								},
								method: 'post'
							} ).done( function( response ) {
								var responseData = response.data;
								if ( ! responseData.done ) {
									setTimeout( do_next_export( exporterIndex, pageIndex + 1 ) );
								} else {
									if ( exporterIndex < exporterNames.length ) {
										setTimeout( do_next_export( exporterIndex + 1, 1 ) );
									} else {
										console.log( responseData );
										on_exports_done_success( responseData.url );
									}
								}
							} ).fail( function( jqxhr, textStatus, error ) {
								on_export_failure( textStatus, error );
							} );
						}

						// And now, let's begin
						set_row_busy();
						do_next_export( 1, 1 );
					} )
				} );
			} ( jQuery ) );
		</script>
		<?php
	}
}
