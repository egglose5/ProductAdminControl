<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Plugin {
	private static $instance = null;

	private $admin_menu;
	private $admin_assets;

	public static function instance(): PAT_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$editor_page        = new PAT_Product_Editor_Page();
		$this->admin_menu   = new PAT_Admin_Menu( $editor_page );
		$this->admin_assets = new PAT_Admin_Assets();
	}

	public function boot(): void {
		add_action( 'admin_menu', array( $this->admin_menu, 'register' ) );
		$this->admin_assets->register();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_woocommerce_notice' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'product-admin-tool',
			false,
			dirname( PAT_PLUGIN_BASENAME ) . '/languages'
		);
	}

	public function maybe_show_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( PAT_Requirements::has_woocommerce() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( PAT_Requirements::missing_woocommerce_notice() )
		);
	}
}
