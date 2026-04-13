<?php
/**
 * Admin menu & screens.
 *
 * Exposes three screens under a top-level "TIC Suite · Gráficos" menu:
 *  1. Constructor     — build a chart: pick view → pick compatible type → preview
 *  2. Shortcodes      — gallery of generated shortcodes with one-click copy
 *  3. Datos de vista  — raw data browser for any registered view
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Admin
 */
class TSG_Admin {

	private TSG_Data_Provider $data_provider;
	private TSG_Chart_Types $chart_types;
	private TSG_Security $security;

	public function __construct(
		TSG_Data_Provider $data_provider,
		TSG_Chart_Types $chart_types,
		TSG_Security $security
	) {
		$this->data_provider = $data_provider;
		$this->chart_types   = $chart_types;
		$this->security      = $security;
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'TIC Suite · Gráficos', 'tic-suite-graficos' ),
			__( 'Gráficos', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite-graficos',
			[ $this, 'render_builder' ],
			'dashicons-chart-area',
			58
		);

		add_submenu_page(
			'tic-suite-graficos',
			__( 'Constructor', 'tic-suite-graficos' ),
			__( 'Constructor', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite-graficos',
			[ $this, 'render_builder' ]
		);

		add_submenu_page(
			'tic-suite-graficos',
			__( 'Shortcodes', 'tic-suite-graficos' ),
			__( 'Shortcodes', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite-graficos-shortcodes',
			[ $this, 'render_shortcodes' ]
		);

		add_submenu_page(
			'tic-suite-graficos',
			__( 'Datos de vista', 'tic-suite-graficos' ),
			__( 'Datos de vista', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite-graficos-datos',
			[ $this, 'render_data' ]
		);
	}

	/**
	 * Shared guard for every admin screen.
	 */
	private function guard(): void {
		if ( ! $this->security->can_manage() ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'tic-suite-graficos' ) );
		}
	}

	public function render_builder(): void {
		$this->guard();
		$views = $this->data_provider->list_views();
		include TSG_PLUGIN_DIR . 'templates/admin/builder.php';
	}

	public function render_shortcodes(): void {
		$this->guard();
		$views = $this->data_provider->list_views();
		include TSG_PLUGIN_DIR . 'templates/admin/shortcodes.php';
	}

	public function render_data(): void {
		$this->guard();
		$views    = $this->data_provider->list_views();
		$selected = isset( $_GET['view'] ) ? $this->security->sanitize_view_id( (string) wp_unslash( $_GET['view'] ) ) : '';
		$view     = '' !== $selected ? $this->data_provider->get_view( $selected ) : [];
		include TSG_PLUGIN_DIR . 'templates/admin/data.php';
	}
}
