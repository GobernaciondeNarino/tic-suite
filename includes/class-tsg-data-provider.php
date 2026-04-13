<?php
/**
 * Data provider for TIC Suite views.
 *
 * Loads canonical view definitions from JSON files under /data/views/*.json
 * and exposes them to the chart builder, the REST API, and the shortcode
 * renderer. Caches reads via WordPress object cache.
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Data_Provider
 */
class TSG_Data_Provider {

	private const CACHE_GROUP = 'tsg_views';
	private const VIEWS_DIR   = 'views';

	private TSG_Security $security;

	public function __construct( TSG_Security $security ) {
		$this->security = $security;
	}

	/**
	 * Return the absolute path to the views directory.
	 */
	public function views_path(): string {
		return TSG_DATA_DIR . self::VIEWS_DIR . '/';
	}

	/**
	 * Return all available view summaries (id, name, category).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_views(): array {
		$cached = wp_cache_get( 'all_summaries', self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$out = [];
		$dir = $this->views_path();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}

		$files = glob( $dir . '*.json' );
		if ( ! is_array( $files ) ) {
			return $out;
		}
		foreach ( $files as $file ) {
			$view = $this->load_view_file( $file );
			if ( empty( $view ) ) {
				continue;
			}
			$out[] = [
				'id'          => (string) ( $view['id'] ?? basename( $file, '.json' ) ),
				'name'        => (string) ( $view['name'] ?? '' ),
				'description' => (string) ( $view['description'] ?? '' ),
				'category'    => (string) ( $view['category'] ?? '' ),
				'dimensions'  => $view['dimensions'] ?? [],
				'measures'    => $view['measures'] ?? [],
				'rows'        => count( $view['data'] ?? [] ),
			];
		}

		usort(
			$out,
			static fn( $a, $b ) => strcasecmp( (string) $a['name'], (string) $b['name'] )
		);

		wp_cache_set( 'all_summaries', $out, self::CACHE_GROUP, 300 );
		return $out;
	}

	/**
	 * Return a single view, including its full payload, by id.
	 *
	 * @param string $id View id.
	 */
	public function get_view( string $id ): array {
		$id = $this->security->sanitize_view_id( $id );
		if ( '' === $id ) {
			return [];
		}

		$cached = wp_cache_get( 'view_' . $id, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$file = $this->views_path() . $id . '.json';
		if ( ! is_readable( $file ) ) {
			return [];
		}

		$view = $this->load_view_file( $file );
		if ( empty( $view ) ) {
			return [];
		}

		wp_cache_set( 'view_' . $id, $view, self::CACHE_GROUP, 300 );
		return $view;
	}

	/**
	 * Read + decode a view file defensively.
	 *
	 * @param string $file Absolute path.
	 */
	private function load_view_file( string $file ): array {
		// Hard-check: the file must live inside the views dir (no path traversal).
		$real_dir  = realpath( $this->views_path() );
		$real_file = realpath( $file );
		if ( ! $real_dir || ! $real_file || strpos( $real_file, $real_dir ) !== 0 ) {
			return [];
		}

		$raw = file_get_contents( $real_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			return [];
		}

		$view = $this->security->decode_json( $raw );
		return $this->normalize_view( $view );
	}

	/**
	 * Normalize a view payload into a predictable shape.
	 *
	 * @param array $view Raw view.
	 */
	private function normalize_view( array $view ): array {
		$view['id']             = isset( $view['id'] ) ? $this->security->sanitize_view_id( (string) $view['id'] ) : '';
		$view['name']           = isset( $view['name'] ) ? sanitize_text_field( (string) $view['name'] ) : '';
		$view['description']    = isset( $view['description'] ) ? sanitize_text_field( (string) $view['description'] ) : '';
		$view['category']       = isset( $view['category'] ) ? sanitize_key( (string) $view['category'] ) : '';
		$view['dimensions']     = array_values( array_map( 'sanitize_text_field', (array) ( $view['dimensions'] ?? [] ) ) );
		$view['measures']       = array_values( array_map( 'sanitize_text_field', (array) ( $view['measures'] ?? [] ) ) );
		$view['temporal_range'] = ! empty( $view['temporal_range'] );
		$view['edges']          = ! empty( $view['edges'] ) ? array_values( (array) $view['edges'] ) : [];
		$view['data']           = is_array( $view['data'] ?? null ) ? array_values( $view['data'] ) : [];
		return $view;
	}
}
