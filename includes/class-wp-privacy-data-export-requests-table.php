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
		// TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43546
		$row_actions = array(
			'download_data' => __( 'Download Personal Data' ),
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
				// TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43546
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
	 * TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43546
	 */
	public function embed_scripts() {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		?>
		<script>
			( function( $ ) {
				$( document ).ready( function() {
				} );
			} ( jQuery ) );
		</script>
		<?php
	}
}
