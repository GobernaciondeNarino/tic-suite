<?php
/**
 * Shortcode handler: [tsg_grafico view="..." type="..." ...]
 *
 * The server emits only a lightweight placeholder figure with data-* attrs;
 * the actual chart is rendered client-side by /assets/js/frontend.js, which
 * fetches the view payload from the REST API. This keeps the HTML safe (no
 * inline JSON leaks) and lets pages be cached.
 *
 * Supported attributes:
 *
 *   view     (required) — view id (see /data/views/*.json)
 *   type     (required) — chart key (see TSG_Chart_Types)
 *   height              — pixel height, 160..1600, default 420
 *   title               — caption shown above the chart
 *   theme               — "tic-suite" (default) or "dark"
 *   legend              — "true" | "false"     default true
 *   legend_style        — "text" | "icons"     default "text"
 *   toolbar             — "true" | "false"     default true
 *   actions             — comma list of: detalle,compartir,datos,imagen,descarga
 *                          default: "detalle,compartir,datos,imagen,descarga"
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

	private const VALID_ACTIONS = [ 'detalle', 'compartir', 'datos', 'imagen', 'descarga' ];

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
				'view'         => '',
				'type'         => '',
				'height'       => '420',
				'title'        => '',
				'theme'        => 'tic-suite',
				'legend'       => 'true',
				'legend_style' => 'text',
				'toolbar'      => 'true',
				'actions'      => 'detalle,compartir,datos,imagen,descarga',
				'x_title'      => '',
				'y_title'      => '',
			],
			is_array( $atts ) ? $atts : [],
			'tsg_grafico'
		);

		$view_id     = $this->security->sanitize_view_id( (string) $atts['view'] );
		$chart_type  = $this->security->sanitize_chart_type( (string) $atts['type'], $this->chart_types );
		$height      = max( 160, min( 1600, absint( $atts['height'] ) ) );
		$title       = $this->security->esc_label( (string) $atts['title'] );
		$theme       = sanitize_html_class( (string) $atts['theme'], 'tic-suite' );
		$show_legend = $this->to_bool( $atts['legend'] );
		$show_tools  = $this->to_bool( $atts['toolbar'] );
		$legend_style = in_array( $atts['legend_style'], [ 'text', 'icons' ], true ) ? $atts['legend_style'] : 'text';
		$actions     = $this->sanitize_actions( (string) $atts['actions'] );
		$x_title     = $this->security->esc_label( (string) $atts['x_title'] );
		$y_title     = $this->security->esc_label( (string) $atts['y_title'] );

		if ( '' === $view_id || '' === $chart_type ) {
			return sprintf(
				'<div class="tsg-empty" role="note">%s</div>',
				esc_html__( 'Shortcode TSG inválido: se requieren los atributos "view" y "type".', 'tic-suite-graficos' )
			);
		}

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
		$figure_id    = 'tsg-fig-' . wp_generate_uuid4();

		ob_start();
		?>
		<figure
			id="<?php echo esc_attr( $figure_id ); ?>"
			class="tsg-figure tsg-theme-<?php echo esc_attr( $theme ); ?>"
			data-tsg-figure="1"
			data-view="<?php echo esc_attr( $view_id ); ?>"
			data-type="<?php echo esc_attr( $chart_type ); ?>"
			data-legend="<?php echo $show_legend ? '1' : '0'; ?>"
			data-legend-style="<?php echo esc_attr( $legend_style ); ?>"
			data-x-title="<?php echo esc_attr( $x_title ); ?>"
			data-y-title="<?php echo esc_attr( $y_title ); ?>"
		>
			<?php if ( '' !== $title ) : ?>
				<figcaption class="tsg-figure__title"><?php echo esc_html( $title ); ?></figcaption>
			<?php endif; ?>

			<?php if ( $show_tools && ! empty( $actions ) ) : ?>
				<div class="tsg-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Acciones del gráfico', 'tic-suite-graficos' ); ?>">
					<?php foreach ( $actions as $action ) : ?>
						<button
							type="button"
							class="tsg-action"
							data-tsg-action="<?php echo esc_attr( $action ); ?>"
							title="<?php echo esc_attr( $this->action_label( $action ) ); ?>"
							aria-label="<?php echo esc_attr( $this->action_label( $action ) ); ?>"
						>
							<span class="dashicons dashicons-<?php echo esc_attr( $this->action_icon( $action ) ); ?>" aria-hidden="true"></span>
							<span class="tsg-action__label"><?php echo esc_html( $this->action_label( $action ) ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div
				id="<?php echo esc_attr( $container_id ); ?>"
				class="tsg-chart"
				style="height: <?php echo esc_attr( (string) $height ); ?>px; min-height: <?php echo esc_attr( (string) $height ); ?>px;"
				data-tsg-chart="1"
				data-view="<?php echo esc_attr( $view_id ); ?>"
				data-type="<?php echo esc_attr( $chart_type ); ?>"
				data-height="<?php echo esc_attr( (string) $height ); ?>"
				data-legend="<?php echo $show_legend ? '1' : '0'; ?>"
				data-legend-style="<?php echo esc_attr( $legend_style ); ?>"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: chart title */ __( 'Gráfico TIC Suite: %s', 'tic-suite-graficos' ), $title ?: $view['name'] ) ); ?>"
				role="img"
			>
				<div class="tsg-chart__loading"><?php esc_html_e( 'Cargando gráfico…', 'tic-suite-graficos' ); ?></div>
			</div>

			<div class="tsg-legend" data-tsg-legend="1" hidden></div>

			<div class="tsg-modal" data-tsg-modal="1" hidden>
				<div class="tsg-modal__backdrop" data-tsg-modal-close="1"></div>
				<div class="tsg-modal__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $figure_id ); ?>-modal-title">
					<header class="tsg-modal__header">
						<h3 id="<?php echo esc_attr( $figure_id ); ?>-modal-title" class="tsg-modal__title"></h3>
						<button type="button" class="tsg-modal__close" data-tsg-modal-close="1" aria-label="<?php esc_attr_e( 'Cerrar', 'tic-suite-graficos' ); ?>">
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						</button>
					</header>
					<div class="tsg-modal__body"></div>
				</div>
			</div>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue frontend assets only when we actually rendered a shortcode.
	 */
	public function maybe_enqueue_assets(): void {
		if ( ! $this->needs_assets ) {
			return;
		}
		wp_enqueue_script( 'tsg-frontend' );
		wp_enqueue_style( 'tsg-frontend' );
	}

	// -------------------------------------------------------------------
	// Attribute helpers
	// -------------------------------------------------------------------

	/**
	 * Truthy: "1", "true", "yes", "on", "si", "sí".
	 */
	private function to_bool( $raw ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}
		$v = strtolower( trim( (string) $raw ) );
		return in_array( $v, [ '1', 'true', 'yes', 'on', 'si', 'sí' ], true );
	}

	/**
	 * Sanitize the comma-separated actions list into an ordered, whitelisted array.
	 */
	private function sanitize_actions( string $raw ): array {
		$items = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		$out   = [];
		foreach ( $items as $i ) {
			$i = strtolower( sanitize_key( $i ) );
			if ( in_array( $i, self::VALID_ACTIONS, true ) && ! in_array( $i, $out, true ) ) {
				$out[] = $i;
			}
		}
		return $out;
	}

	private function action_label( string $action ): string {
		$map = [
			'detalle'   => __( 'Detalle', 'tic-suite-graficos' ),
			'compartir' => __( 'Compartir', 'tic-suite-graficos' ),
			'datos'     => __( 'Datos', 'tic-suite-graficos' ),
			'imagen'    => __( 'Imagen', 'tic-suite-graficos' ),
			'descarga'  => __( 'Descarga', 'tic-suite-graficos' ),
		];
		return $map[ $action ] ?? $action;
	}

	private function action_icon( string $action ): string {
		$map = [
			'detalle'   => 'info-outline',
			'compartir' => 'share',
			'datos'     => 'editor-table',
			'imagen'    => 'format-image',
			'descarga'  => 'download',
		];
		return $map[ $action ] ?? 'admin-generic';
	}
}
