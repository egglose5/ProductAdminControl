<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Requirements {
	const MENU_SLUG          = 'product-admin-tool';
	const REQUIRED_CAP       = 'manage_woocommerce';
	const TEXT_DOMAIN        = 'product-admin-tool';
	const MISSING_WC_MESSAGE = 'Product Admin Tool requires WooCommerce to be active.';

	/**
	 * Check whether WooCommerce is available.
	 *
	 * @return bool
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get the capability required to access the plugin screens.
	 *
	 * @return string
	 */
	public static function required_capability(): string {
		return self::REQUIRED_CAP;
	}

	/**
	 * Get the main admin menu slug.
	 *
	 * @return string
	 */
	public static function menu_slug(): string {
		return self::MENU_SLUG;
	}

	/**
	 * Determine whether the current admin screen belongs to this plugin.
	 *
	 * @param string $hook_suffix Optional admin hook suffix.
	 * @return bool
	 */
	public static function is_plugin_screen( string $hook_suffix = '' ): bool {
		if ( '' !== $hook_suffix && false !== strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return true;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return false !== strpos( (string) $screen->id, self::MENU_SLUG ) || false !== strpos( (string) $screen->base, self::MENU_SLUG );
	}

	/**
	 * Get the admin notice message shown when WooCommerce is missing.
	 *
	 * @return string
	 */
	public static function missing_woocommerce_notice(): string {
		return __( self::MISSING_WC_MESSAGE, self::TEXT_DOMAIN );
	}
}
