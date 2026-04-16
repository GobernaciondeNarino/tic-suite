<?php
/**
 * Admin menu & screens.
 *
 * Top-level menu: "TIC Suite" (slug: tic-suite).
 * Submenus per project:
 *   - Py Nación   → tic-suite-nacion   (builder)
 *   - Py Ondas    → tic-suite-ondas    (builder)
 * Cross-project screens:
 *   - Shortcodes       → tic-suite-shortcodes (project switcher)
 *   - Datos de vista   → tic-suite-datos      (project switcher)
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

	private TSG_Plugin $plugin;
	private TSG_Chart_Types $chart_types;
	private TSG_Security $security;

	public function __construct(
		TSG_Plugin $plugin,
		TSG_Chart_Types $chart_types,
		TSG_Security $security
	) {
		$this->plugin      = $plugin;
		$this->chart_types = $chart_types;
		$this->security    = $security;
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu(): void {
		$projects = $this->plugin->projects();

		// Top-level menu: TIC Suite.
		add_menu_page(
			__( 'TIC Suite', 'tic-suite-graficos' ),
			__( 'TIC Suite', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite',
			[ $this, 'render_builder_nacion' ],
			'dashicons-chart-area',
			58
		);

		// One builder submenu per project.
		$first = true;
		foreach ( $projects as $slug => $label ) {
			$parent_slug   = 'tic-suite';
			$submenu_slug  = 'tic-suite-' . $slug;
			$callback      = [ $this, 'render_builder_' . $slug ];

			if ( $first ) {
				// Make the first project the landing page of the top-level
				// menu — WordPress uses the top-level slug as the first
				// submenu unless we add one with the same slug.
				add_submenu_page(
					$parent_slug,
					$label,
					$label,
					TSG_MIN_CAPABILITY,
					'tic-suite',
					[ $this, 'render_builder_' . $slug ]
				);
				$first = false;
				continue;
			}

			add_submenu_page(
				$parent_slug,
				$label,
				$label,
				TSG_MIN_CAPABILITY,
				$submenu_slug,
				$callback
			);
		}

		// Cross-project screens.
		add_submenu_page(
			'tic-suite',
			__( 'Shortcodes', 'tic-suite-graficos' ),
			__( 'Shortcodes', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite-shortcodes',
			[ $this, 'render_shortcodes' ]
		);

		add_submenu_page(
			'tic-suite',
			__( 'Datos de vista', 'tic-suite-graficos' ),
			__( 'Datos de vista', 'tic-suite-graficos' ),
			TSG_MIN_CAPABILITY,
			'tic-suite-datos',
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

	// ------------------------------------------------------------------
	// Builder (one render fn per project so admin_page_hook is unique)
	// ------------------------------------------------------------------

	public function render_builder_nacion(): void {
		$this->render_builder_for( 'nacion' );
	}

	public function render_builder_ondas(): void {
		$this->render_builder_for( 'ondas' );
	}

	private function render_builder_for( string $project ): void {
		$this->guard();
		$project = $this->plugin->normalize_project( $project );
		$dp      = $this->plugin->data_provider( $project );
		$views   = $dp->list_views();
		$label   = $this->plugin->projects()[ $project ];
		include TSG_PLUGIN_DIR . 'templates/admin/builder.php';
	}

	// ------------------------------------------------------------------
	// Cross-project screens (project switcher via ?project=…)
	// ------------------------------------------------------------------

	public function render_shortcodes(): void {
		$this->guard();
		$project = isset( $_GET['project'] ) ? $this->plugin->normalize_project( (string) wp_unslash( $_GET['project'] ) ) : TSG_Plugin::DEFAULT_PROJECT;
		$dp      = $this->plugin->data_provider( $project );
		$views   = $dp->list_views();
		$label   = $this->plugin->projects()[ $project ];
		include TSG_PLUGIN_DIR . 'templates/admin/shortcodes.php';
	}

	public function render_data(): void {
		$this->guard();
		$project  = isset( $_GET['project'] ) ? $this->plugin->normalize_project( (string) wp_unslash( $_GET['project'] ) ) : TSG_Plugin::DEFAULT_PROJECT;
		$dp       = $this->plugin->data_provider( $project );
		$views    = $dp->list_views();
		$selected = isset( $_GET['view'] ) ? $this->security->sanitize_view_id( (string) wp_unslash( $_GET['view'] ) ) : '';
		$view     = '' !== $selected ? $dp->get_view( $selected ) : [];
		$label    = $this->plugin->projects()[ $project ];
		include TSG_PLUGIN_DIR . 'templates/admin/data.php';
	}
}
