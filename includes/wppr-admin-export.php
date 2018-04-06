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
	' );
}

function _wp_personal_data_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	$action = isset( $_POST['action'] ) ? $_POST['action'] : '';

	if ( ! empty( $action ) ) {
		if ( 'add-personal-data-request' === $action && isset( $_POST['type_of_action'], $_POST['username_or_email_to_export'] ) ) {
			check_admin_referer( $action );

			$action_type               = sanitize_text_field( $_POST['type_of_action'] );
			$username_or_email_address = sanitize_text_field( $_POST['username_or_email_to_export'] );
			$email_address             = '';

			if ( ! in_array( $action_type, array( 'personal-data-export' ), true ) ) {
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
		<h1><?php esc_html_e( 'Personal Data Export' ); ?></h1>
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
			<?php wp_nonce_field( 'add-personal-data-request' ); ?>
			<input type="hidden" name="action" value="add-personal-data-request" />
			<input type="hidden" name="type_of_action" value="personal-data-export" />
		</form>
		<hr/>

		<?php $requests_table->views(); ?>

		<form class="search-form wp-clearfix">
			<?php $requests_table->search_box( __( 'Search Requests' ), 'requests' ); ?>
			<input type="hidden" name="page" value="wp-personal-data-export" />
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
	add_submenu_page( 'tools.php', __( 'Personal Data Export' ), __( 'Personal Data Export' ), 'manage_options', 'wp-personal-data-export', '_wp_personal_data_export_page' );
}
add_action( 'admin_menu', '_wp_privacy_hook_export_requests_page' );
