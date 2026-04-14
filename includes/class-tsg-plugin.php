<?php
/**
 * Main plugin bootstrap.
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Plugin
 *
 * Wires every sub-module of the plugin once WordPress is ready.
 */
final class TSG_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var TSG_Plugin|null
	 */
	private static ?TSG_Plugin $instance = null;

	/**
	 * Security helper.
	 *
	 * @var TSG_Security
	 */
	public TSG_Security $security;

	/**
	 * Chart types registry.
	 *
	 * @var TSG_Chart_Types
	 */
	public TSG_Chart_Types $chart_types;

	/**
	 * Data provider.
	 *
	 * @var TSG_Data_Provider
	 */
	public TSG_Data_Provider $data_provider;

	/**
	 * Shortcode handler.
	 *
	 * @var TSG_Shortcode
	 */
	public TSG_Shortcode $shortcode;

	/**
	 * Admin menu & screens.
	 *
	 * @var TSG_Admin
	 */
	public TSG_Admin $admin;

	/**
	 * REST API controller.
	 *
	 * @var TSG_Rest_Api
	 */
	public TSG_Rest_Api $rest_api;

	/**
	 * Get the singleton.
	 */
	public static function instance(): TSG_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->security      = new TSG_Security();
		$this->chart_types   = new TSG_Chart_Types();
		$this->data_provider = new TSG_Data_Provider( $this->security );
		$this->shortcode     = new TSG_Shortcode( $this->data_provider, $this->chart_types, $this->security );
		$this->admin         = new TSG_Admin( $this->data_provider, $this->chart_types, $this->security );
		$this->rest_api      = new TSG_Rest_Api( $this->data_provider, $this->chart_types, $this->security );
	}

	/**
	 * Register hooks for each submodule.
	 */
	public function run(): void {
		load_plugin_textdomain( 'tic-suite-graficos', false, dirname( TSG_PLUGIN_BASENAME ) . '/languages' );

		// Frontend & shortcode.
		$this->shortcode->register();

		// Admin UI.
		if ( is_admin() ) {
			$this->admin->register();
		}

		// REST API.
		add_action( 'rest_api_init', [ $this->rest_api, 'register_routes' ] );

		// Assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Settings link on plugins page.
		add_filter( 'plugin_action_links_' . TSG_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Activation hook: seed default options and prepare storage.
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		add_option(
			'tsg_settings',
			[
				'allow_shortcode_roles' => [ 'administrator', 'editor' ],
				'default_theme'         => 'tic-suite',
				'enable_cache'          => true,
				'cache_ttl'             => 600,
			]
		);
		if ( ! file_exists( TSG_DATA_DIR ) ) {
			wp_mkdir_p( TSG_DATA_DIR );
		}
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		wp_cache_flush();
	}

	/**
	 * Enqueue public (frontend) assets. d3plus is registered but only loaded
	 * on pages that actually contain a TSG shortcode — see TSG_Shortcode.
	 */
	public function enqueue_public_assets(): void {
		wp_register_script(
			'd3plus',
			TSG_D3PLUS_URL,
			[],
			TSG_D3PLUS_VERSION,
			true
		);

		wp_register_script(
			'tsg-renderer',
			TSG_PLUGIN_URL . 'assets/js/renderer.js',
			[ 'd3plus' ],
			TSG_VERSION,
			true
		);

		wp_register_script(
			'tsg-frontend',
			TSG_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'd3plus', 'tsg-renderer' ],
			TSG_VERSION,
			true
		);

		wp_register_style(
			'tsg-frontend',
			TSG_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			TSG_VERSION
		);

		wp_localize_script(
			'tsg-frontend',
			'TSG_FRONTEND',
			[
				'restUrl' => esc_url_raw( rest_url( TSG_REST_NAMESPACE . '/render' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => [
					'loading' => __( 'Cargando gráfico…', 'tic-suite-graficos' ),
					'error'   => __( 'No fue posible renderizar el gráfico.', 'tic-suite-graficos' ),
					'empty'   => __( 'Sin datos para mostrar.', 'tic-suite-graficos' ),
				],
			]
		);
	}

	/**
	 * Enqueue admin assets only on TSG admin screens.
	 *
	 * @param string $hook Current admin screen hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, 'tic-suite-graficos' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'd3plus',
			TSG_D3PLUS_URL,
			[],
			TSG_D3PLUS_VERSION,
			true
		);

		wp_enqueue_script(
			'tsg-renderer',
			TSG_PLUGIN_URL . 'assets/js/renderer.js',
			[ 'd3plus' ],
			TSG_VERSION,
			true
		);

		wp_enqueue_script(
			'tsg-admin',
			TSG_PLUGIN_URL . 'assets/js/admin.js',
			[ 'd3plus', 'tsg-renderer', 'wp-i18n' ],
			TSG_VERSION,
			true
		);

		wp_enqueue_style(
			'tsg-admin',
			TSG_PLUGIN_URL . 'assets/css/admin.css',
			[ 'dashicons' ],
			TSG_VERSION
		);

		wp_localize_script(
			'tsg-admin',
			'TSG_ADMIN',
			[
				'restUrl'     => esc_url_raw( rest_url( TSG_REST_NAMESPACE ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'adminNonce'  => wp_create_nonce( TSG_NONCE_ACTION ),
				'chartTypes'  => $this->chart_types->all_for_js(),
				'i18n'        => [
					'selectView'      => __( 'Selecciona una vista', 'tic-suite-graficos' ),
					'selectChart'     => __( 'Selecciona un tipo de gráfico', 'tic-suite-graficos' ),
					'copied'          => __( '¡Shortcode copiado!', 'tic-suite-graficos' ),
					'copyFailed'      => __( 'No se pudo copiar al portapapeles.', 'tic-suite-graficos' ),
					'noCompatible'    => __( 'Esta vista no tiene gráficos compatibles.', 'tic-suite-graficos' ),
					'loading'         => __( 'Cargando…', 'tic-suite-graficos' ),
					'preview'         => __( 'Vista previa', 'tic-suite-graficos' ),
				],
			]
		);
	}

	/**
	 * Add a "Configuración" link next to the plugin on the plugins list.
	 *
	 * @param array $links Existing links.
	 */
	public function plugin_action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=tic-suite-graficos' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Abrir constructor', 'tic-suite-graficos' )
			)
		);
		return $links;
	}
}
