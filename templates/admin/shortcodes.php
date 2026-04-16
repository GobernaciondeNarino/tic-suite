<?php
/**
 * Admin screen: shortcode gallery (project-scoped).
 *
 * @package TicSuite\Graficos
 * @var array  $views   Summaries of registered views for this project.
 * @var string $project Current project slug.
 * @var string $label   Human label for the current project.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$registry      = TSG_Plugin::instance()->chart_types;
$data_provider = TSG_Plugin::instance()->data_provider( $project );
$projects      = TSG_Plugin::instance()->projects();
?>
<div class="wrap tsg-wrap" data-tsg-project="<?php echo esc_attr( $project ); ?>">
	<header class="tsg-header">
		<div class="tsg-header__title">
			<span class="dashicons dashicons-shortcode" aria-hidden="true"></span>
			<h1>
				<?php esc_html_e( 'Galería de shortcodes', 'tic-suite-graficos' ); ?>
				<span class="tsg-header__sub">· <?php echo esc_html( $label ); ?></span>
			</h1>
		</div>
		<p class="tsg-header__lede">
			<?php esc_html_e( 'Cada vista aparece acompañada de sus gráficos compatibles. Copia el shortcode y, debajo, revisa los datos exactos que se graficarán.', 'tic-suite-graficos' ); ?>
		</p>
	</header>

	<form method="get" class="tsg-form-inline">
		<input type="hidden" name="page" value="tic-suite-shortcodes" />
		<label for="tsg-project-select"><?php esc_html_e( 'Proyecto:', 'tic-suite-graficos' ); ?></label>
		<select name="project" id="tsg-project-select" onchange="this.form.submit()">
			<?php foreach ( $projects as $slug => $name ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $project, $slug ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</form>

	<?php if ( empty( $views ) ) : ?>
		<p class="tsg-empty"><?php esc_html_e( 'No hay vistas registradas todavía en este proyecto.', 'tic-suite-graficos' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $views as $summary ) :
		$full       = $data_provider->get_view( $summary['id'] );
		$compatible = $registry->compatible_with_view( $full );
		$project_attr = 'nacion' === $project ? '' : sprintf( ' project="%s"', esc_attr( $project ) );
		?>
		<details class="tsg-view-block" open>
			<summary class="tsg-view-block__summary">
				<span class="tsg-view-block__name"><?php echo esc_html( $summary['name'] ); ?></span>
				<span class="tsg-pill tsg-pill--<?php echo esc_attr( $summary['category'] ); ?>"><?php echo esc_html( $summary['category'] ); ?></span>
				<span class="tsg-view-block__meta"><?php echo (int) $summary['rows']; ?> <?php esc_html_e( 'filas', 'tic-suite-graficos' ); ?></span>
			</summary>
			<p class="tsg-view-block__desc"><?php echo esc_html( $summary['description'] ); ?></p>

			<?php if ( empty( $compatible ) ) : ?>
				<p class="tsg-empty"><?php esc_html_e( 'Ningún gráfico es compatible con esta vista.', 'tic-suite-graficos' ); ?></p>
			<?php else : ?>
				<div class="tsg-shortcode-grid">
					<?php foreach ( $compatible as $chart ) :
						$shortcode = sprintf(
							'[tsg_grafico view="%s" type="%s"%s height="420" title="%s"]',
							esc_attr( $summary['id'] ),
							esc_attr( $chart['key'] ),
							$project_attr,
							esc_attr( $summary['name'] )
						);
						?>
						<article class="tsg-shortcode-card">
							<header class="tsg-shortcode-card__header">
								<span class="dashicons dashicons-<?php echo esc_attr( $registry->get( $chart['key'] )['icon'] ); ?>" aria-hidden="true"></span>
								<h3><?php echo esc_html( $chart['label'] ); ?></h3>
							</header>
							<p class="tsg-shortcode-card__desc"><?php echo esc_html( $chart['description'] ); ?></p>
							<div class="tsg-shortcode-row">
								<input
									type="text"
									readonly
									value="<?php echo esc_attr( $shortcode ); ?>"
									aria-label="<?php esc_attr_e( 'Shortcode', 'tic-suite-graficos' ); ?>"
								/>
								<button type="button" class="button tsg-copy-btn" data-tsg-copy>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<?php esc_html_e( 'Copiar', 'tic-suite-graficos' ); ?>
								</button>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<h4 class="tsg-data-heading">
					<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
					<?php esc_html_e( 'Datos de la vista', 'tic-suite-graficos' ); ?>
				</h4>
				<div class="tsg-table-wrap">
					<table class="widefat striped tsg-data-table">
						<thead>
							<tr>
								<?php
								$fields = array_merge( $full['dimensions'], $full['measures'] );
								foreach ( $fields as $field ) :
									?>
									<th><?php echo esc_html( $field ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $full['data'], 0, 20 ) as $row ) : ?>
								<tr>
									<?php foreach ( $fields as $field ) : ?>
										<td><?php echo esc_html( (string) ( $row[ $field ] ?? '' ) ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( count( $full['data'] ) > 20 ) : ?>
						<p class="tsg-hint">
							<?php
							/* translators: %d: number of hidden rows */
							echo esc_html( sprintf( __( '… y %d filas más.', 'tic-suite-graficos' ), count( $full['data'] ) - 20 ) );
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</details>
	<?php endforeach; ?>
</div>
