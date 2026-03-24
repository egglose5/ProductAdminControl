<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Admin_Screen {
	const MENU_SLUG = 'product-admin-tool';

	/**
	 * Get the primary admin page slug for this plugin.
	 *
	 * @return string
	 */
	public static function get_menu_slug(): string {
		return self::MENU_SLUG;
	}

	/**
	 * Check whether a hook suffix belongs to this plugin.
	 *
	 * @param string $hook_suffix Hook suffix from add_menu_page/add_submenu_page.
	 * @return bool
	 */
	public static function is_hook_suffix( string $hook_suffix ): bool {
		if ( '' === $hook_suffix ) {
			return false;
		}

		return false !== strpos( $hook_suffix, self::MENU_SLUG );
	}

	/**
	 * Check whether the current admin screen belongs to this plugin.
	 *
	 * @param WP_Screen|null $screen Optional screen object.
	 * @return bool
	 */
	public static function is_current_screen( $screen = null ): bool {
		if ( ! $screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		if ( ! $screen || ! is_object( $screen ) ) {
			return false;
		}

		if ( isset( $screen->id ) && self::is_hook_suffix( (string) $screen->id ) ) {
			return true;
		}

		if ( isset( $screen->base ) && self::is_hook_suffix( (string) $screen->base ) ) {
			return true;
		}

		if ( isset( $screen->post_type ) && 'product' === $screen->post_type ) {
			return false;
		}

		return false;
	}

	/**
	 * Check whether an arbitrary screen identifier belongs to this plugin.
	 *
	 * @param string $screen_id Screen ID or hook suffix.
	 * @return bool
	 */
	public static function is_screen_id( string $screen_id ): bool {
		if ( '' === $screen_id ) {
			return false;
		}

		return self::is_hook_suffix( $screen_id );
	}
}
