<?php
/**
 * Admin screen: chart builder.
 *
 * @package TicSuite\Graficos
 * @var array $views Summaries of registered views (passed from TSG_Admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tsg-wrap">
	<header class="tsg-header">
		<div class="tsg-header__title">
			<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
			<h1><?php esc_html_e( 'Constructor de gráficos', 'tic-suite-graficos' ); ?></h1>
		</div>
		<p class="tsg-header__lede">
			<?php esc_html_e( 'Selecciona una vista de TIC Suite, elige uno de los tipos de gráfico compatibles y obtén el shortcode para insertarlo en cualquier página.', 'tic-suite-graficos' ); ?>
		</p>
	</header>

	<section class="tsg-grid">
		<!-- Panel 1: view picker -->
		<aside class="tsg-card tsg-card--views" aria-labelledby="tsg-views-heading">
			<h2 id="tsg-views-heading" class="tsg-card__title">
				<span class="dashicons dashicons-database" aria-hidden="true"></span>
				<?php esc_html_e( 'Vistas', 'tic-suite-graficos' ); ?>
			</h2>
			<ul class="tsg-views-list" role="listbox" aria-label="<?php esc_attr_e( 'Vistas disponibles', 'tic-suite-graficos' ); ?>">
				<?php foreach ( $views as $v ) : ?>
					<li>
						<button
							type="button"
							class="tsg-view-item"
							data-view-id="<?php echo esc_attr( $v['id'] ); ?>"
							role="option"
							aria-selected="false"
						>
							<span class="tsg-view-item__name"><?php echo esc_html( $v['name'] ); ?></span>
							<span class="tsg-view-item__meta">
								<span class="tsg-pill tsg-pill--<?php echo esc_attr( $v['category'] ); ?>"><?php echo esc_html( $v['category'] ); ?></span>
								<span class="tsg-view-item__rows"><?php echo (int) $v['rows']; ?> <?php esc_html_e( 'filas', 'tic-suite-graficos' ); ?></span>
							</span>
							<span class="tsg-view-item__desc"><?php echo esc_html( $v['description'] ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</aside>

		<!-- Panel 2: chart type picker -->
		<section class="tsg-card tsg-card--types" aria-labelledby="tsg-types-heading">
			<h2 id="tsg-types-heading" class="tsg-card__title">
				<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
				<?php esc_html_e( 'Tipo de gráfico', 'tic-suite-graficos' ); ?>
			</h2>
			<div id="tsg-chart-types" class="tsg-chart-types" data-empty="<?php esc_attr_e( 'Selecciona primero una vista.', 'tic-suite-graficos' ); ?>">
				<p class="tsg-empty"><?php esc_html_e( 'Selecciona primero una vista.', 'tic-suite-graficos' ); ?></p>
			</div>
		</section>

		<!-- Panel 3: preview + shortcode -->
		<section class="tsg-card tsg-card--preview" aria-labelledby="tsg-preview-heading">
			<h2 id="tsg-preview-heading" class="tsg-card__title">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Vista previa', 'tic-suite-graficos' ); ?>
			</h2>
			<div id="tsg-preview" class="tsg-preview" aria-live="polite">
				<p class="tsg-empty"><?php esc_html_e( 'Aquí aparecerá tu gráfico.', 'tic-suite-graficos' ); ?></p>
			</div>

			<div class="tsg-shortcode-box" hidden>
				<label for="tsg-shortcode-input">
					<span class="dashicons dashicons-shortcode" aria-hidden="true"></span>
					<?php esc_html_e( 'Shortcode', 'tic-suite-graficos' ); ?>
				</label>
				<div class="tsg-shortcode-row">
					<input type="text" id="tsg-shortcode-input" readonly value="" />
					<button type="button" class="button button-primary" id="tsg-copy-btn">
						<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
						<?php esc_html_e( 'Copiar', 'tic-suite-graficos' ); ?>
					</button>
				</div>
				<p class="tsg-hint"><?php esc_html_e( 'Pégalo en cualquier página o entrada de WordPress.', 'tic-suite-graficos' ); ?></p>
			</div>
		</section>
	</section>
</div>
