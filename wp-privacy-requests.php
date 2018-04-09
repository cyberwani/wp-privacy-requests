<?php
/*
Plugin Name: WP Privacy Requests
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( plugin_basename( 'includes/wppr-requests.php' ) );
require_once( plugin_basename( 'includes/class-wp-privacy-requests-table.php' ) );
require_once( plugin_basename( 'includes/class-wp-privacy-data-export-requests-table.php' ) );
require_once( plugin_basename( 'includes/class-wp-privacy-data-removal-requests-table.php' ) );
require_once( plugin_basename( 'includes/wppr-admin-export.php' ) );
