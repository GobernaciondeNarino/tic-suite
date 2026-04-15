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

		<!-- Panel 3: preview + options + shortcode -->
		<section class="tsg-card tsg-card--preview" aria-labelledby="tsg-preview-heading">
			<h2 id="tsg-preview-heading" class="tsg-card__title">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Vista previa', 'tic-suite-graficos' ); ?>
			</h2>

			<fieldset class="tsg-options" id="tsg-options">
				<legend class="tsg-options__legend"><?php esc_html_e( 'Opciones', 'tic-suite-graficos' ); ?></legend>

				<div class="tsg-options__row">
					<label class="tsg-switch">
						<input type="checkbox" data-tsg-opt="legend" checked />
						<span class="tsg-switch__track"></span>
						<span class="tsg-switch__label"><?php esc_html_e( 'Mostrar leyenda', 'tic-suite-graficos' ); ?></span>
					</label>

					<label class="tsg-select-inline">
						<span><?php esc_html_e( 'Estilo:', 'tic-suite-graficos' ); ?></span>
						<select data-tsg-opt="legend_style">
							<option value="text"><?php esc_html_e( 'Texto', 'tic-suite-graficos' ); ?></option>
							<option value="icons"><?php esc_html_e( 'Iconos', 'tic-suite-graficos' ); ?></option>
						</select>
					</label>
				</div>

				<div class="tsg-options__row">
					<label class="tsg-switch">
						<input type="checkbox" data-tsg-opt="toolbar" checked />
						<span class="tsg-switch__track"></span>
						<span class="tsg-switch__label"><?php esc_html_e( 'Barra de acciones', 'tic-suite-graficos' ); ?></span>
					</label>
				</div>

				<div class="tsg-options__row tsg-options__axes">
					<label class="tsg-text-inline">
						<span><?php esc_html_e( 'Eje X:', 'tic-suite-graficos' ); ?></span>
						<input type="text" data-tsg-opt="x_title" placeholder="<?php esc_attr_e( 'auto', 'tic-suite-graficos' ); ?>" />
					</label>
					<label class="tsg-text-inline">
						<span><?php esc_html_e( 'Eje Y:', 'tic-suite-graficos' ); ?></span>
						<input type="text" data-tsg-opt="y_title" placeholder="<?php esc_attr_e( 'auto', 'tic-suite-graficos' ); ?>" />
					</label>
				</div>

				<div class="tsg-options__row tsg-options__actions">
					<span class="tsg-options__label"><?php esc_html_e( 'Acciones:', 'tic-suite-graficos' ); ?></span>
					<label class="tsg-chip"><input type="checkbox" data-tsg-action-opt="detalle" checked /><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Detalle', 'tic-suite-graficos' ); ?></label>
					<label class="tsg-chip"><input type="checkbox" data-tsg-action-opt="compartir" checked /><span class="dashicons dashicons-share" aria-hidden="true"></span><?php esc_html_e( 'Compartir', 'tic-suite-graficos' ); ?></label>
					<label class="tsg-chip"><input type="checkbox" data-tsg-action-opt="datos" checked /><span class="dashicons dashicons-editor-table" aria-hidden="true"></span><?php esc_html_e( 'Datos', 'tic-suite-graficos' ); ?></label>
					<label class="tsg-chip"><input type="checkbox" data-tsg-action-opt="imagen" checked /><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php esc_html_e( 'Imagen', 'tic-suite-graficos' ); ?></label>
					<label class="tsg-chip"><input type="checkbox" data-tsg-action-opt="descarga" checked /><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Descarga', 'tic-suite-graficos' ); ?></label>
				</div>
			</fieldset>

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
