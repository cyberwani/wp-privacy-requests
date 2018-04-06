<?php
defined( 'ABSPATH' ) || exit;

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

		$current_link_attributes = empty( $current_status ) ? ' class="current" aria-current="page"' : '';
		$views['all']            = '<a href="' . esc_url( admin_url( 'tools.php?page=wp-personal-data-export' ) ) . "\" $current_link_attributes>" . esc_html__( 'All' ) . '</a>';

		foreach ( $statuses as $status => $label ) {
			$current_link_attributes = $status === $current_status ? ' class="current" aria-current="page"' : '';
			$views[ $status ] = '<a href="' . esc_url( add_query_arg( 'filter-status', $status, admin_url( 'tools.php?page=wp-personal-data-export' ) ) ) . "\" $current_link_attributes>" . esc_html( $label ) . '</a>';
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

		if ( ! empty( $_REQUEST['filter-status'] ) ) {
			$filter_status       = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : '';
			$args['post_status'] = $filter_status;
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
	 * Actions column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		$row_actions = array();

		if ( 'remove_personal_data' === $item['action'] ) {
			$row_actions['remove_data'] = '<a class="remove_personal_data" href="#" data-email="' . esc_attr( $item['email'] ) . '">' . __( 'Remove Personal Data' ) . '</a>';

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
	 * Next steps column.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {
		$status = get_post_status( $item['request_id'] );

		switch ( $status ) {
			case 'action-pending':
				esc_html_e( 'Waiting for confirmation' );
				break;
			case 'action-confirmed':
				submit_button( __( 'Email Data' ), 'secondary', 'personal-data-export-send', false, array(
					'value' => $item['request_id'],
				) );
				break;
			case 'action-failed':
				submit_button( __( 'Retry' ), 'secondary', 'personal-data-export-retry', false, array(
					'value' => $item['request_id'],
				) );
				break;
			case 'action-completed':
				echo '<a href="' . esc_url( add_query_arg( 'delete', array( $item['request_id'] ), admin_url( 'tools.php?page=wp-personal-data-export' ) ) ) . '">' . esc_html__( 'Remove Request' ) . '</a>';
				break;
		}
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

	public function embed_scripts() {
		$this->embed_exporter_script();
	}

	public function embed_exporter_script() {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		?>
		<script>
			( function( $ ) {
				$( document ).ready( function() {
					var successMessage = "<?php echo esc_attr( __( 'Action completed successfully' ) ); ?>";
					var failureMessage = "<?php echo esc_attr( __( 'A failure occurred during processing' ) ); ?>";
					var spinnerUrl = "<?php echo esc_url( admin_url( '/images/wpspin_light.gif' ) ); ?>";

					function set_request_busy( actionEl ) {
						actionEl.parents( '.row-actions' ).hide();
						var checkColumn = actionEl.parents( 'tr' ).find( '.check-column' );
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

					function set_request_not_busy( actionEl ) {
						actionEl.parents( '.row-actions' ).show();
						var checkColumn = actionEl.parents( 'tr' ).find( '.check-column' );
						checkColumn.find( '.spinner' ).hide();
						checkColumn.find( 'input' ).show();
					}

					$( '.download_personal_data' ).click( function() {
						var exportNonce = <?php echo json_encode( wp_create_nonce( 'wp-privacy-export-personal-data' ) ); ?>;
						var exportersCount = <?php echo json_encode( count( $exporters ) ); ?>;

						var actionEl = $( this );
						var emailForExport = actionEl.data( 'email' );
						actionEl.blur();

						function on_exports_done_success( url ) {
							set_request_not_busy( actionEl );
							// TODO - simplify once 43551 has landed - we won't need to test for a url
							// nor show the successMessage then - we can just kick off the ZIP download
							if ( url ) {
								window.location = url; // kick off ZIP download
							} else {
								alert( successMessage );
							}
						}

						function on_export_failure( textStatus, error ) {
							set_request_not_busy( actionEl );
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
									security: exportNonce,
								},
								method: 'post'
							} ).done( function( response ) {
								var responseData = response.data;
								if ( ! responseData.done ) {
									setTimeout( do_next_export( exporterIndex, pageIndex + 1 ) );
								} else {
									if ( exporterIndex < exportersCount ) {
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
						set_request_busy( actionEl );
						do_next_export( 1, 1 );
					} )

					$( '.remove_personal_data' ).click( function() {
						var eraseNonce = <?php echo json_encode( wp_create_nonce( 'wp-privacy-erase-personal-data' ) ); ?>;
						var erasersCount = <?php echo json_encode( count( $erasers ) ); ?>;

						var actionEl = $( this );
						var emailForErasure = actionEl.data( 'email' );
						actionEl.blur();

						function on_erase_done_success( url ) {
							set_request_not_busy( actionEl );
							alert( successMessage );
						}

						function on_erase_failure( textStatus, error ) {
							set_request_not_busy( actionEl );
							alert( failureMessage );
							alert( error );
						}

						function do_next_erasure( eraserIndex, pageIndex ) {
							$.ajax( {
								url: ajaxurl,
								data: {
									action: 'wp-privacy-erase-personal-data',
									email: emailForErasure,
									eraser: eraserIndex,
									page: pageIndex,
									security: eraseNonce,
								},
								method: 'post'
							} ).done( function( response ) {
								var responseData = response.data;
								if ( ! responseData.done ) {
									setTimeout( do_next_erasure( eraserIndex, pageIndex + 1 ) );
								} else {
									if ( eraserIndex < erasersCount ) {
										setTimeout( do_next_erasure( eraserIndex + 1, 1 ) );
									} else {
										on_erase_done_success( responseData.url );
									}
								}
							} ).fail( function( jqxhr, textStatus, error ) {
								on_erase_failure( textStatus, error );
							} );
						}

						// And now, let's begin
						set_request_busy( actionEl );
						do_next_erasure( 1, 1 );
					} )
				} );
			} ( jQuery ) );
		</script>
		<?php
	}
}
