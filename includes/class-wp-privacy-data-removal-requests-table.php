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
			// TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43602
			'remove_data' => __( 'Remove Personal Data' ),
		);

		// If we have a user ID, include a delete user action.
		if ( ! empty( $item['user_id'] ) ) {
			// TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43602
			$row_actions['delete_user'] = __( 'Delete User' );
		}

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $row_actions ) );
	}

	/**
	 * Next steps column.
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {
		// TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43602
	}

	/**
	 * Embed scripts used to perform the erasure.
	 * TODO Complete in follow on patch for https://core.trac.wordpress.org/ticket/43602
	 */
	public function embed_scripts() {
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
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
