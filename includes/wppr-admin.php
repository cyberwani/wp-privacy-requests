<?php
defined( 'ABSPATH' ) || exit;

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
		.privacy_requests thead td:first-child,
		.privacy_requests tfoot td:first-child {
			border-left: 4px solid #fff;
		}
		.privacy_requests tbody th {
			border-left: 4px solid #fff;
			background: #fff;
			box-shadow: inset 0 -1px 0 rgba(0,0,0,0.1);
		}
		.privacy_requests tbody td {
			background: #fff;
			box-shadow: inset 0 -1px 0 rgba(0,0,0,0.1);
		}
		.privacy_requests .status-action-confirmed th,
		.privacy_requests .status-action-confirmed td {
			background-color: #f7fcfe;
			border-left-color: #00a0d2;
		}
		.privacy_requests .status-action-failed th,
		.privacy_requests .status-action-failed td {
			background-color: #fef7f1;
			border-left-color: #d64d21;
		}
		.status-label {
			font-weight: bold;
		}
		.status-label.status-action-pending {
			font-weight: normal;
			font-style: italic;
			color: #6c7781;
		}
		.status-label.status-action-failed {
			color: #aa0000;
			font-weight: bold;
		}
		.wp-privacy-request-form {
			clear: both;
		}
		.wp-privacy-request-form-field {
			margin: 1.5em 0;
		}
		.wp-privacy-request-form label {
			font-weight: bold;
			line-height: 1.5;
			padding-bottom: .5em;
			display: block;
		}
		.wp-privacy-request-form input {
			line-height: 1.5;
			margin: 0;
		}
		.email-personal-data::before {
			display: inline-block;
			font: normal 20px/1 dashicons;
			margin: 3px 5px 0 -2px;
			speak: none;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			vertical-align: top;
		}
		.email-personal-data--sending::before {
			color: #f56e28;
			content: "\f463";
			-webkit-animation: rotation 2s infinite linear;
			animation: rotation 2s infinite linear;
		}
		.email-personal-data--sent::before {
			color: #79ba49;
			content: "\f147";
		}
		@-webkit-keyframes rotation {
			0% {
				-webkit-transform: rotate(0deg);
				transform: rotate(0deg);
			}
			100% {
				-webkit-transform: rotate(359deg);
				transform: rotate(359deg);
			}
		}
		@keyframes rotation {
			0% {
				-webkit-transform: rotate(0deg);
				transform: rotate(0deg);
			}
			100% {
				-webkit-transform: rotate(359deg);
				transform: rotate(359deg);
			}
		}
	' );
}

/**
 * Handle list table actions.
 */
function _wp_personal_data_handle_actions() {
	if ( isset( $_POST['export_personal_data_email_retry'] ) ) { // WPCS: input var ok.
		check_admin_referer( 'bulk-privacy_requests' );

		$request_id = absint( current( array_keys( (array) wp_unslash( $_POST['export_personal_data_email_retry'] ) ) ) ); // WPCS: input var ok, sanitization ok.
		$result     = _wp_privacy_resend_request( $request_id );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'export_personal_data_email_retry',
				'export_personal_data_email_retry',
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'export_personal_data_email_retry',
				'export_personal_data_email_retry',
				__( 'Confirmation request re-resent successfully.' ),
				'updated'
			);
		}

	} elseif ( isset( $_POST['export_personal_data_email_send'] ) ) { // WPCS: input var ok.
		check_admin_referer( 'bulk-privacy_requests' );

		$request_id = absint( current( array_keys( (array) wp_unslash( $_POST['export_personal_data_email_send'] ) ) ) ); // WPCS: input var ok, sanitization ok.
		$result     = false;

		/**
		 * TODO: Email the data to the user here.
		 */

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'export_personal_data_email_send',
				'export_personal_data_email_send',
				$result->get_error_message(),
				'error'
			);
		} else {
			_wp_privacy_completed_request( $request_id );
			add_settings_error(
				'export_personal_data_email_send',
				'export_personal_data_email_send',
				__( 'Personal data was sent to the user successfully.' ),
				'updated'
			);
		}

	} elseif ( isset( $_POST['action'] ) ) {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		switch ( $action ) {
			case 'add_export_personal_data_request':
			case 'add_remove_personal_data_request':
				check_admin_referer( 'personal-data-request' );

				if ( ! isset( $_POST['type_of_action'], $_POST['username_or_email_to_export'] ) ) { // WPCS: input var ok.
					add_settings_error(
						'action_type',
						'action_type',
						__( 'Invalid action.' ),
						'error'
					);
				}
				$action_type               = sanitize_text_field( wp_unslash( $_POST['type_of_action'] ) ); // WPCS: input var ok.
				$username_or_email_address = sanitize_text_field( wp_unslash( $_POST['username_or_email_to_export'] ) ); // WPCS: input var ok.
				$email_address             = '';

				if ( ! in_array( $action_type, _wp_privacy_action_request_types(), true ) ) {
					add_settings_error(
						'action_type',
						'action_type',
						__( 'Invalid action.' ),
						'error'
					);
				}

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
							__( 'Unable to initiate confirmation request.' ),
							'error'
						);
					} else {
						add_settings_error(
							'username_or_email_to_export',
							'username_or_email_to_export',
							__( 'Confirmation request initiated successfully.' ),
							'updated'
						);
					}
				}
				break;
		}
	}
}

function _wp_personal_data_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	_wp_personal_data_handle_actions();

	$requests_table = new WP_Privacy_Data_Export_Requests_Table( array(
		'plural'   => 'privacy_requests',
		'singular' => 'privacy_request',
	) );
	$requests_table->process_bulk_action();
	$requests_table->prepare_items();
	?>
	<div class="wrap nosubsub">
		<h1><?php esc_html_e( 'Export Personal Data' ); ?></h1>
		<hr class="wp-header-end" />

		<?php settings_errors(); ?>

		<form method="post" class="wp-privacy-request-form">
			<h2><?php esc_html_e( 'Add Data Export Request' ); ?></h2>
			<p><?php esc_html_e( 'An email will be sent to the user at this email address asking them to verify the request.' ); ?></p>

			<div class="wp-privacy-request-form-field">
				<label for="username_or_email_to_export"><?php esc_html_e( 'Username or email address' ); ?></label>
				<input type="text" required class="regular-text" id="username_or_email_to_export" name="username_or_email_to_export" />
				<?php submit_button( __( 'Send Request' ), 'secondary', 'submit', false ); ?>
			</div>
			<?php wp_nonce_field( 'personal-data-request' ); ?>
			<input type="hidden" name="action" value="add_export_personal_data_request" />
			<input type="hidden" name="type_of_action" value="export_personal_data" />
		</form>
		<hr/>

		<?php $requests_table->views(); ?>

		<form class="search-form wp-clearfix">
			<?php $requests_table->search_box( __( 'Search Requests' ), 'requests' ); ?>
			<input type="hidden" name="page" value="export_personal_data" />
			<input type="hidden" name="filter-status" value="<?php echo isset( $_REQUEST['filter-status'] ) ? esc_attr( sanitize_text_field( $_REQUEST['filter-status'] ) ) : ''; ?>" />
			<input type="hidden" name="orderby" value="<?php echo isset( $_REQUEST['orderby'] ) ? esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) : ''; ?>" />
			<input type="hidden" name="order" value="<?php echo isset( $_REQUEST['order'] ) ? esc_attr( sanitize_text_field( $_REQUEST['order'] ) ) : ''; ?>" />
		</form>

		<form method="post">
			<?php
			$requests_table->display();
			$requests_table->embed_scripts();
			?>
		</form>
	</div>
	<?php
}

function _wp_personal_data_removal_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	_wp_personal_data_handle_actions();

	$requests_table = new WP_Privacy_Data_Removal_Requests_Table( array(
		'plural'   => 'privacy_requests',
		'singular' => 'privacy_request',
	) );
	$requests_table->process_bulk_action();
	$requests_table->prepare_items();
	?>
	<div class="wrap nosubsub">
		<h1><?php esc_html_e( 'Remove Personal Data' ); ?></h1>
		<hr class="wp-header-end" />

		<?php settings_errors(); ?>

		<form method="post" class="wp-privacy-request-form">
			<h2><?php esc_html_e( 'Add Data Removal Request' ); ?></h2>
			<p><?php esc_html_e( 'An email will be sent to the user at this email address asking them to verify the request.' ); ?></p>

			<div class="wp-privacy-request-form-field">
				<label for="username_or_email_to_export"><?php esc_html_e( 'Username or email address' ); ?></label>
				<input type="text" required class="regular-text" id="username_or_email_to_export" name="username_or_email_to_export" />
				<?php submit_button( __( 'Send Request' ), 'secondary', 'submit', false ); ?>
			</div>
			<?php wp_nonce_field( 'personal-data-request' ); ?>
			<input type="hidden" name="action" value="add_remove_personal_data_request" />
			<input type="hidden" name="type_of_action" value="remove_personal_data" />
		</form>
		<hr/>

		<?php $requests_table->views(); ?>

		<form class="search-form wp-clearfix">
			<?php $requests_table->search_box( __( 'Search Requests' ), 'requests' ); ?>
			<input type="hidden" name="page" value="export_personal_data" />
			<input type="hidden" name="filter-status" value="<?php echo isset( $_REQUEST['filter-status'] ) ? esc_attr( sanitize_text_field( $_REQUEST['filter-status'] ) ) : ''; ?>" />
			<input type="hidden" name="orderby" value="<?php echo isset( $_REQUEST['orderby'] ) ? esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) : ''; ?>" />
			<input type="hidden" name="order" value="<?php echo isset( $_REQUEST['order'] ) ? esc_attr( sanitize_text_field( $_REQUEST['order'] ) ) : ''; ?>" />
		</form>

		<form method="post">
			<?php
			$requests_table->display();
			$requests_table->embed_scripts();
			?>
		</form>
	</div>
	<?php
}

/**
 * Add requests pages.
 *
 * @return void
 */
function _wp_privacy_hook_requests_page() {
	add_submenu_page( 'tools.php', __( 'Export Personal Data' ), __( 'Export Personal Data' ), 'manage_options', 'export_personal_data', '_wp_personal_data_export_page' );
	add_submenu_page( 'tools.php', __( 'Remove Personal Data' ), __( 'Remove Personal Data' ), 'manage_options', 'remove_personal_data', '_wp_personal_data_removal_page' );
}
add_action( 'admin_menu', '_wp_privacy_hook_requests_page' );
