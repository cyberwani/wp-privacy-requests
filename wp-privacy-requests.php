<?php
/*
Plugin Name: WP Privacy Requests
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( plugin_basename( 'includes/wppr-requests.php' ) );
require_once( plugin_basename( 'includes/wppr-list-tables.php' ) );
require_once( plugin_basename( 'includes/wppr-admin-export.php' ) );
