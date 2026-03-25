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
				'screenSlug'          => PAT_Admin_Screen::get_menu_slug(),
				'nonce'               => class_exists( 'PAT_Save_Controller' ) ? wp_create_nonce( PAT_Save_Controller::NONCE_ACTION ) : wp_create_nonce( 'pat-admin' ),
				'nonceField'          => class_exists( 'PAT_Save_Controller' ) ? PAT_Save_Controller::NONCE_FIELD : 'nonce',
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'saveAction'          => class_exists( 'PAT_Save_Controller' ) ? PAT_Save_Controller::AJAX_ACTION : '',
				'variationAction'     => class_exists( 'PAT_Variation_Controller' ) ? PAT_Variation_Controller::AJAX_ACTION : '',
				'variationNonce'      => class_exists( 'PAT_Variation_Controller' ) ? wp_create_nonce( PAT_Variation_Controller::NONCE_ACTION ) : '',
				'variationNonceField' => class_exists( 'PAT_Variation_Controller' ) ? PAT_Variation_Controller::NONCE_FIELD : 'nonce',
				'variationPreviewAction' => class_exists( 'PAT_Variation_Generator_Controller' ) ? PAT_Variation_Generator_Controller::AJAX_ACTION : '',
				'variationPreviewNonce' => class_exists( 'PAT_Variation_Generator_Controller' ) ? wp_create_nonce( PAT_Variation_Generator_Controller::NONCE_ACTION ) : '',
				'variationPreviewNonceField' => class_exists( 'PAT_Variation_Generator_Controller' ) ? PAT_Variation_Generator_Controller::NONCE_FIELD : 'nonce',
			)
		);
	}
}
