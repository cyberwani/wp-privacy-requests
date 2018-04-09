<?php
defined( 'ABSPATH' ) || exit;

/**
 * WP_Privacy_Data_Export_Requests_Table class.
 */
class WP_Privacy_Data_Export_Requests_Table extends WP_Privacy_Requests_Table {
	/**
	 * Action name for the requests this table will work with. Classes
	 * which inherit from WP_Privacy_Requests_Table should define this.
	 * e.g. 'export_personal_data'
	 *
	 * @var string $request_type Name of action.
	 */
	protected $request_type = 'export_personal_data';

	/**
	 * Actions column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		$row_actions = array(
			'download_data' => '<a class="download_personal_data" href="#" data-email="' . esc_attr( $item['email'] ) . '">' . __( 'Download Personal Data' ) . '</a>',
		);

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
				submit_button( __( 'Email Data' ), 'secondary', 'export_personal_data_email_send[' . $item['request_id'] . ']', false );
				break;
			case 'action-failed':
				submit_button( __( 'Retry' ), 'secondary', 'export_personal_data_email_retry[' . $item['request_id'] . ']', false );
				break;
			case 'action-completed':
				echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 
					'action' => 'delete', 
					'request_id' => array( $item['request_id'] ) 
				), admin_url( 'tools.php?page=export_personal_data' ) ), 'bulk-privacy_requests' ) ) . '">' . esc_html__( 'Remove request' ) . '</a>';
				break;
		}
	}

	/**
	 * Embed scripts used to perform the export.
	 */
	public function embed_scripts() {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
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
				} );
			} ( jQuery ) );
		</script>
		<?php
	}
}