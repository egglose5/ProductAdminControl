<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Plugin {
	private static $instance = null;

	private $admin_menu;
	private $admin_assets;
	private $save_controller;
	private $variation_controller;
	private $variation_generator_controller;

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
		$this->save_controller = class_exists( 'PAT_Save_Controller' ) ? new PAT_Save_Controller() : null;
		$this->variation_controller = class_exists( 'PAT_Variation_Controller' ) ? new PAT_Variation_Controller() : null;
		$this->variation_generator_controller = class_exists( 'PAT_Variation_Generator_Controller' ) ? new PAT_Variation_Generator_Controller() : null;
	}

	public function boot(): void {
		add_action( 'admin_menu', array( $this->admin_menu, 'register' ) );
		$this->admin_assets->register();
		if ( $this->save_controller && method_exists( $this->save_controller, 'register' ) ) {
			$this->save_controller->register();
		}
		if ( $this->variation_controller && method_exists( $this->variation_controller, 'register' ) ) {
			$this->variation_controller->register();
		}
		if ( $this->variation_generator_controller && method_exists( $this->variation_generator_controller, 'register' ) ) {
			$this->variation_generator_controller->register();
		}
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
