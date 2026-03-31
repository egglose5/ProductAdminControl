<?php
/**
 * Plugin Name:       Product Admin Tool
 * Plugin URI:        https://example.com
 * Description:       Admin-first product management tools for WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Codex
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       product-admin-tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAT_VERSION', '0.1.0' );
define( 'PAT_PLUGIN_FILE', __FILE__ );
define( 'PAT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PAT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PAT_PLUGIN_PATH . 'includes/core/class-pat-loader.php';
PAT_Loader::init();

function pat_activate(): void {
	if ( class_exists( 'PAT_Save_History_Store' ) && method_exists( 'PAT_Save_History_Store', 'install' ) ) {
		PAT_Save_History_Store::install();
	}
}

register_activation_hook( __FILE__, 'pat_activate' );

function pat(): PAT_Plugin {
	return PAT_Plugin::instance();
}

pat()->boot();
