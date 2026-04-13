<?php
/**
 * Shortcode handler: [tsg_grafico view="..." type="..." height="..."]
 *
 * The server emits only a lightweight placeholder div; the actual chart is
 * rendered client-side by /assets/js/frontend.js, which fetches the view
 * payload from the REST API. This keeps the HTML output safe (no inline
 * JSON leaks, no XSS sink) and lets pages be cached.
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Shortcode
 */
class TSG_Shortcode {

	private TSG_Data_Provider $data_provider;
	private TSG_Chart_Types $chart_types;
	private TSG_Security $security;

	/**
	 * Track whether we've seen a shortcode on the current render so we can
	 * enqueue assets on-demand.
	 */
	private bool $needs_assets = false;

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
		add_shortcode( 'tsg_grafico', [ $this, 'render' ] );
		add_action( 'wp_footer', [ $this, 'maybe_enqueue_assets' ] );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Inner content.
	 */
	public function render( $atts, $content = null ): string {
		$atts = shortcode_atts(
			[
				'view'   => '',
				'type'   => '',
				'height' => '420',
				'title'  => '',
				'theme'  => 'tic-suite',
			],
			is_array( $atts ) ? $atts : [],
			'tsg_grafico'
		);

		$view_id    = $this->security->sanitize_view_id( (string) $atts['view'] );
		$chart_type = $this->security->sanitize_chart_type( (string) $atts['type'], $this->chart_types );
		$height     = max( 160, min( 1600, absint( $atts['height'] ) ) );
		$title      = $this->security->esc_label( (string) $atts['title'] );
		$theme      = sanitize_html_class( (string) $atts['theme'], 'tic-suite' );

		if ( '' === $view_id || '' === $chart_type ) {
			return sprintf(
				'<div class="tsg-empty" role="note">%s</div>',
				esc_html__( 'Shortcode TSG inválido: se requieren los atributos "view" y "type".', 'tic-suite-graficos' )
			);
		}

		// Verify view exists & chart is compatible.
		$view = $this->data_provider->get_view( $view_id );
		if ( empty( $view ) ) {
			return sprintf(
				'<div class="tsg-empty" role="note">%s</div>',
				esc_html__( 'La vista solicitada no existe.', 'tic-suite-graficos' )
			);
		}
		$compatible_keys = wp_list_pluck( $this->chart_types->compatible_with_view( $view ), 'key' );
		if ( ! in_array( $chart_type, $compatible_keys, true ) ) {
			return sprintf(
				'<div class="tsg-empty" role="note">%s</div>',
				esc_html__( 'El tipo de gráfico solicitado no es compatible con la vista.', 'tic-suite-graficos' )
			);
		}

		$this->needs_assets = true;

		$container_id = 'tsg-chart-' . wp_generate_uuid4();

		ob_start();
		?>
		<figure class="tsg-figure tsg-theme-<?php echo esc_attr( $theme ); ?>">
			<?php if ( '' !== $title ) : ?>
				<figcaption class="tsg-figure__title"><?php echo esc_html( $title ); ?></figcaption>
			<?php endif; ?>
			<div
				id="<?php echo esc_attr( $container_id ); ?>"
				class="tsg-chart"
				style="min-height: <?php echo esc_attr( (string) $height ); ?>px;"
				data-tsg-chart="1"
				data-view="<?php echo esc_attr( $view_id ); ?>"
				data-type="<?php echo esc_attr( $chart_type ); ?>"
				data-height="<?php echo esc_attr( (string) $height ); ?>"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: chart title */ __( 'Gráfico TIC Suite: %s', 'tic-suite-graficos' ), $title ?: $view['name'] ) ); ?>"
				role="img"
			>
				<div class="tsg-chart__loading"><?php esc_html_e( 'Cargando gráfico…', 'tic-suite-graficos' ); ?></div>
			</div>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue frontend assets only when we actually rendered a shortcode
	 * on this page — avoids loading d3plus on pages that don't use it.
	 */
	public function maybe_enqueue_assets(): void {
		if ( ! $this->needs_assets ) {
			return;
		}
		wp_enqueue_script( 'tsg-frontend' );
		wp_enqueue_style( 'tsg-frontend' );
	}
}
