<?php
defined( 'ABSPATH' ) || exit;

/**
 * WP_Privacy_Data_Removal_Requests_Table class.
 */
class WP_Privacy_Data_Removal_Requests_Table extends WP_Privacy_Requests_Table {
	/**
	 * Action name for the requests this table will work with. Classes
	 * which inherit from WP_Privacy_Requests_Table should define this.
	 * e.g. 'export_personal_data'
	 *
	 * @var string $request_type Name of action.
	 */
	protected $request_type = 'remove_personal_data';

	/**
	 * Actions column.
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		$row_actions = array(
			'remove_data' => '<a class="remove_personal_data" href="#" data-email="' . esc_attr( $item['email'] ) . '">' . __( 'Remove Personal Data' ) . '</a>',
		);

		// If we have a user ID, include a delete user action.
		if ( ! empty( $item['user_id'] ) ) {
			$delete_user_url            = wp_nonce_url( "users.php?action=delete&amp;user={$item['user_id']}", 'bulk-users' );
			$row_actions['delete_user'] = "<a class='submitdelete' href='" . $delete_user_url . "'>" . __( 'Delete User' ) . '</a>';
		}

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $row_actions ) );
	}

	/**
	 * Next steps column.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {
		// TODO
	}

	/**
	 * Embed scripts used to perform the erasure.
	 */
	public function embed_scripts() {
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
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
