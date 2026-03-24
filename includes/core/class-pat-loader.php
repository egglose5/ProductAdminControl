<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Loader {
	/**
	 * Load the core class files needed for Phase 1.
	 */
	public static function init(): void {
		$files = array(
			PAT_PLUGIN_PATH . 'includes/support/class-pat-requirements.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-admin-screen.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-admin-assets.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-product-editor-page.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-admin-menu.php',
			PAT_PLUGIN_PATH . 'includes/core/class-pat-plugin.php',
		);

		foreach ( $files as $file ) {
			self::load_file( $file );
		}
	}

	/**
	 * Safely load a class file if it exists.
	 *
	 * @param string $file File path.
	 */
	private static function load_file( string $file ): void {
		if ( ! file_exists( $file ) ) {
			return;
		}

		require_once $file;
	}
}
