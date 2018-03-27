<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TODO: Move the core CSS when merged :)
 */
add_action( 'admin_enqueue_scripts', '_wp_privacy_requests_styles' );

function _wp_privacy_requests_styles() {
	wp_add_inline_style( 'list-tables', '
		.privacy_requests .column-email {
			width: 40%;
		}
		.privacy_requests .column-type {
			text-align: center;
		}
		.privacy_requests .column-status .status-label {
			font-size: 0.83em;
			padding: 6px 9px;
			background: #ddd;
			border-radius: 3px;
			border-bottom: 1px solid rgba(0,0,0,.05);
		}
		.privacy_requests .column-status .status-action-confirmed {
			background: #C6E1C6;
			color: #5b841b;
		}
		.privacy_requests .column-status .status-action-failed {
			background: #eba3a3;
    		color: #761919;
		}
	' );
}

function _wp_privacy_requests_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	$action = isset( $_POST['action'] ) ? $_POST['action'] : '';

	if ( ! empty( $action ) ) {
		if ( 'add-personal-data-request' === $action && isset( $_POST['type_of_action'], $_POST['username_or_email_to_export'] ) ) {
			check_admin_referer( $action );

			$action_type               = sanitize_text_field( $_POST['type_of_action'] );
			$username_or_email_address = sanitize_text_field( $_POST['username_or_email_to_export'] );
			$email_address             = '';

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
					$email_address = $user->user_email;
				}
			} else {
				$email_address = $username_or_email_address;
			}

			if ( ! empty( $email_address ) ) {
				$result = false;

				$result = _wp_privacy_create_request( $email_address, $action_type, _wp_privacy_action_description( $action_type ) );

				if ( is_wp_error( $result ) ) {
					add_settings_error(
						'username_or_email_to_export',
						'username_or_email_to_export',
						$result->get_error_message(),
						'error'
					);
				} elseif ( ! $result ) {
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

	$requests_table = new WP_Personal_Data_Export_Requests_Table( array(
		'plural'   => 'privacy_requests',
		'singular' => 'privacy_request',
	) );
	$requests_table->prepare_items();
	?>
	<div class="wrap nosubsub">
		<h1><?php _e( 'Personal Data Requests' ); ?></h1>
		<hr class="wp-header-end" />
		<form class="search-form wp-clearfix">
			<?php $requests_table->search_box( __( 'Search requests' ), 'requests' ); ?>
			<input type="hidden" name="page" value="wp-privacy-requests" />
			<input type="hidden" name="filter-action" value="<?php echo isset( $_REQUEST['filter-action'] ) ? esc_attr( sanitize_text_field( $_REQUEST['filter-action'] ) ) : ''; ?>" />
			<input type="hidden" name="filter-status" value="<?php echo isset( $_REQUEST['filter-status'] ) ? esc_attr( sanitize_text_field( $_REQUEST['filter-status'] ) ) : ''; ?>" />
			<input type="hidden" name="orderby" value="<?php echo isset( $_REQUEST['orderby'] ) ? esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) : ''; ?>" />
		</form>
		<?php settings_errors(); ?>
		<div id="col-container" class="wp-clearfix">
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h2><?php _e( 'New Request' ); ?></h2>
						<p><?php _e( 'An email will be sent to the user at this email address, asking them to verify the request.' ); ?></p>

						<form method="post">
							<input type="hidden" name="action" value="add-personal-data-request" />
							<?php wp_nonce_field( 'add-personal-data-request' ); ?>
							<fieldset>
								<div class="form-field form-required">
									<label for="type_of_action"><?php _e( 'Type of action to request' ); ?></label>
									<select id="type_of_action" name="type_of_action">
										<?php foreach ( _wp_privacy_actions() as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="form-field form-required">
									<label for="username_or_email_to_export"><?php _e( 'Username or email address' ); ?></label>
									<input type="text" class="regular-text" id="username_or_email_to_export" name="username_or_email_to_export" />
								</div>
							</fieldset>
							<?php submit_button( __( 'Send request' ) ); ?>
						</form>
					</div>
				</div>
			</div>
			<div id="col-right">
				<div class="col-wrap">
				<form method="post">
					<?php
					$requests_table->display();
					$requests_table->embed_scripts();
					?>
				</form>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Return actions that can be performed.
 *
 * @return array
 */
function _wp_privacy_actions() {
	return array(
		'export_personal_data' => __( 'Personal data export' ),
		'remove_personal_data' => __( 'Personal data removal' ),
	);
}

/**
 * Return statuses for requests.
 *
 * @return array
 */
function _wp_privacy_statuses() {
	return array(
		'action-pending'   => __( 'Pending' ), // Pending confirmation from user.
		'action-confirmed' => __( 'Confirmed' ), // User has confirmed the action.
		'action-failed'    => __( 'Failed' ), // User failed to confirm the action.
	);
}

function _wp_privacy_hook_export_requests_page() {
	add_submenu_page( 'tools.php', __( 'Personal Data Requests' ), __( 'Personal Data Requests' ), 'manage_options', 'wp-privacy-requests', '_wp_privacy_requests_page' );
}
add_action( 'admin_menu', '_wp_privacy_hook_export_requests_page' );
