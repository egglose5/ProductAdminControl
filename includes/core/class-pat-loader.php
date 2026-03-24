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
			PAT_PLUGIN_PATH . 'includes/support/class-pat-pagination.php',
			PAT_PLUGIN_PATH . 'includes/support/class-pat-row-validation.php',
			PAT_PLUGIN_PATH . 'includes/support/class-pat-save-result.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-admin-screen.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-admin-assets.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-product-filters.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-product-grid-table.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-variation-row-renderer.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-product-editor-page.php',
			PAT_PLUGIN_PATH . 'includes/admin/class-pat-admin-menu.php',
			PAT_PLUGIN_PATH . 'includes/controllers/class-pat-save-controller.php',
			PAT_PLUGIN_PATH . 'includes/controllers/class-pat-variation-controller.php',
			PAT_PLUGIN_PATH . 'includes/repositories/class-pat-product-repository.php',
			PAT_PLUGIN_PATH . 'includes/repositories/class-pat-variation-repository.php',
			PAT_PLUGIN_PATH . 'includes/services/class-pat-product-grid-service.php',
			PAT_PLUGIN_PATH . 'includes/services/class-pat-product-save-service.php',
			PAT_PLUGIN_PATH . 'includes/services/class-pat-variation-generator-service.php',
			PAT_PLUGIN_PATH . 'includes/services/class-pat-variation-save-service.php',
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
