<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function _wp_privacy_export_requests_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	$action = isset( $_POST['action'] ) ? $_POST['action'] : '';

	if ( ! empty( $action ) ) {
		check_admin_referer( $action );

		if ( 'add-export-request' === $action ) {
			$username_or_email_address = isset( $_POST['username_or_email_to_export'] ) ? $_POST['username_or_email_to_export'] : '';
			$username_or_email_address = sanitize_text_field( $username_or_email_address );

			if ( ! is_email( $username_or_email_address ) ) {
				$user = get_user_by( 'login', $username_or_email_address );
				if ( ! $user instanceof WP_User ) {
					add_settings_error(
						'username_or_email_to_export',
						'username_or_email_to_export',
						__( 'Unable to add export request. A valid email address or username must be supplied.' ),
						'error'
					);
				} else {
					$doing_personal_data_export_for_email = $user->user_email;
				}
			} else {
				$doing_personal_data_export_for_email = $username_or_email_address;
			}

			if ( ! empty( $doing_personal_data_export_for_email ) ) {
				$result = wp_send_account_verification_key( $doing_personal_data_export_for_email, 'export_personal_data', __( 'Export personal data' ) );
				if ( is_wp_error( $result ) || ! $result ) {
					add_settings_error(
						'username_or_email_to_export',
						'username_or_email_to_export',
						__( 'Unable to initiate export verification request.' ),
						'error'
					);
				} else {
					add_settings_error(
						'username_or_email_to_export',
						'username_or_email_to_export',
						__( 'Export verification request initiated successfully.' ),
						'updated'
					);
				}
			}
		}
	}

	global $export_requests;
	$export_requests =_wp_privacy_get_all_unconfirmed_personal_data_export_requests();
	$requests_table = new WP_Personal_Data_Export_Requests_Table();
	$requests_table->set_items( $export_requests );

	?>
	<div class="wrap nosubsub">
		<h1><?php _e( 'Personal Data Requests' ); ?></h1>
		<hr class="wp-header-end" />
		<?php settings_errors(); ?>
		<div id="col-container" class="wp-clearfix">
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h2><?php _e( 'New Request' ); ?></h2>
						<p><?php _e( 'An email will be sent to the user at this email address, asking them to verify the request.' ); ?></p>

						<form method="post" action="">
							<input type="hidden" name="action" value="add-export-request" />
							<?php wp_nonce_field( 'add-export-request' ); ?>
							<fieldset>
								<div class="form-field form-required">
									<label for="type_of_action"><?php _e( 'Type of action to request' ); ?></label>
									<select id="type_of_action">
										<option value="export"><?php esc_html_e( 'Personal data export' ); ?></option>
										<option value="remove"><?php esc_html_e( 'Personal data removal' ); ?></option>
									</select>
								</div>
								<div class="form-field form-required">
									<label for="username_or_email_to_export"><?php _e( 'Username or email address' ); ?></label>
									<input type="text" class="regular-text" name="username_or_email_to_export" />
								</div>
							</fieldset>
							<?php submit_button( __( 'Send request' ) ); ?>
						</form>
					</div>
				</div>
			</div>
			<div id="col-right">
				<div class="col-wrap">
				<?php
				$requests_table->prepare_items();
				$requests_table->display();
				?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function _wp_privacy_hook_export_requests_page() {
	add_submenu_page( 'tools.php', __( 'Personal Data Export' ), __( 'Personal Data Export' ), 'manage_options', 'wp-privacy-export-requests', '_wp_privacy_export_requests_page' );
}
add_action( 'admin_menu', '_wp_privacy_hook_export_requests_page' );
