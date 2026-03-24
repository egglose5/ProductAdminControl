<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Admin_Assets {
	const STYLE_HANDLE  = 'pat-admin';
	const SCRIPT_HANDLE = 'pat-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! PAT_Admin_Screen::is_hook_suffix( $hook_suffix ) && ! PAT_Admin_Screen::is_current_screen() ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			PAT_PLUGIN_URL . 'assets/css/pat-admin.css',
			array(),
			PAT_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			PAT_PLUGIN_URL . 'assets/js/pat-admin.js',
			array( 'jquery' ),
			PAT_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'PATAdmin',
			array(
				'screenSlug' => PAT_Admin_Screen::get_menu_slug(),
				'nonce'      => wp_create_nonce( 'pat-admin' ),
			)
		);
	}
}
