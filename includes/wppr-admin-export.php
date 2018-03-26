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
	<div class="wrap">
		<h1><?php _e( 'Personal Data Export' ); ?></h1>
		<?php settings_errors(); ?>

		<h2><?php _e( 'Export Requests' ); ?></h2>
	<?php
		$requests_table->prepare_items();
		$requests_table->display();
	?>
	<h3><?php _e( 'Add New Request' ); ?></h3>
	<form method="post" action="">
		<input type="hidden" name="action" value="add-export-request" />
		<?php wp_nonce_field( 'add-export-request' ); ?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php _e( 'Enter the username or email address of the user whose personal data you wish to export.' ); ?></span></legend>
			<label for="username_or_email_to_export">
				<input type="text" class="regular-text" name="username_or_email_to_export" />
			</label>
			<p class="description"><?php _e( 'A verification email will be sent to the user at this email address, asking them to verify the request.' ); ?></p>
		</fieldset>
		<?php submit_button( __( 'Add New Request' ) ); ?>
	</form>
	</div>
	<?php
}

function _wp_privacy_hook_export_requests_page() {
	add_submenu_page( 'tools.php', __( 'Personal Data Export' ), __( 'Personal Data Export' ), 'manage_options', 'wp-privacy-export-requests', '_wp_privacy_export_requests_page' );
}
add_action( 'admin_menu', '_wp_privacy_hook_export_requests_page' );
