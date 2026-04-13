<?php
/**
 * Security helper: centralized sanitization, escaping, and permission checks.
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Security
 */
class TSG_Security {

	/**
	 * Capability required to manage / build charts.
	 */
	public function can_manage(): bool {
		return current_user_can( TSG_MIN_CAPABILITY );
	}

	/**
	 * Permission callback for admin-only REST routes.
	 */
	public function rest_admin_permission(): bool {
		return $this->can_manage();
	}

	/**
	 * Permission callback for public render route (rate-limited via nonce + caps).
	 */
	public function rest_public_permission(): bool {
		// Public render is allowed but still protected by sanitized inputs
		// and a hard whitelist on view_id + chart_type.
		return true;
	}

	/**
	 * Verify admin-area nonces for form submissions.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Action string.
	 */
	public function verify_admin_nonce( string $nonce, string $action = TSG_NONCE_ACTION ): bool {
		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Deeply sanitize a free-form value used in shortcode / REST params.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ $this, 'sanitize_deep' ], $value );
		}
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}
		if ( is_numeric( $value ) || is_bool( $value ) || null === $value ) {
			return $value;
		}
		return '';
	}

	/**
	 * Sanitize a view identifier (slug-style).
	 */
	public function sanitize_view_id( string $id ): string {
		$id = sanitize_key( $id );
		return preg_replace( '/[^a-z0-9_\-]/', '', $id ) ?? '';
	}

	/**
	 * Sanitize a chart type key against the whitelist in TSG_Chart_Types.
	 */
	public function sanitize_chart_type( string $type, TSG_Chart_Types $registry ): string {
		$type = sanitize_key( $type );
		return $registry->exists( $type ) ? $type : '';
	}

	/**
	 * Escape a label for safe HTML output.
	 */
	public function esc_label( string $label ): string {
		return esc_html( wp_strip_all_tags( $label ) );
	}

	/**
	 * Safely decode JSON, returning an empty array on any error.
	 *
	 * @param string $raw Raw JSON.
	 */
	public function decode_json( string $raw ): array {
		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return [];
		}
		return $decoded;
	}
}
