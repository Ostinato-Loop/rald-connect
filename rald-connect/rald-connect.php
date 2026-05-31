<?php
/**
 * Plugin Name:       RALD Connect
 * Plugin URI:        https://rald.cloud/connect
 * Description:       Connects WordPress to the RALD Identity platform. Replaces WP native auth with RALD SSO, syncs users, and enables cross-app session exchange.
 * Version:           1.0.0
 * Author:            LILCKY STUDIO LIMITED
 * Author URI:        https://rald.cloud
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rald-connect
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RALD_CONNECT_VERSION',   '1.0.0' );
define( 'RALD_CONNECT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RALD_CONNECT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RALD_CONNECT_AUTH_URL',   'https://auth.rald.cloud' );
define( 'RALD_CONNECT_API_URL',    'https://api.rald.cloud' );

require_once RALD_CONNECT_PLUGIN_DIR . 'includes/class-rald-connect.php';

function rald_connect() {
    return RALD_Connect::get_instance();
}

rald_connect();
