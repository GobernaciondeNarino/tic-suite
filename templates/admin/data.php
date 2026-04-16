<?php
/**
 * Admin screen: raw data browser for a single view (project-scoped).
 *
 * @package TicSuite\Graficos
 * @var array  $views    View summaries for this project.
 * @var string $selected Currently selected view id.
 * @var array  $view     Full view payload (or empty).
 * @var string $project  Current project slug.
 * @var string $label    Human label for the current project.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$projects = TSG_Plugin::instance()->projects();
?>
<div class="wrap tsg-wrap" data-tsg-project="<?php echo esc_attr( $project ); ?>">
	<header class="tsg-header">
		<div class="tsg-header__title">
			<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
			<h1>
				<?php esc_html_e( 'Datos de vista', 'tic-suite-graficos' ); ?>
				<span class="tsg-header__sub">· <?php echo esc_html( $label ); ?></span>
			</h1>
		</div>
	</header>

	<form method="get" class="tsg-form-inline">
		<input type="hidden" name="page" value="tic-suite-datos" />
		<label for="tsg-project-select"><?php esc_html_e( 'Proyecto:', 'tic-suite-graficos' ); ?></label>
		<select name="project" id="tsg-project-select" onchange="this.form.submit()">
			<?php foreach ( $projects as $slug => $name ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $project, $slug ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="tsg-view-select"><?php esc_html_e( 'Vista:', 'tic-suite-graficos' ); ?></label>
		<select name="view" id="tsg-view-select" onchange="this.form.submit()">
			<option value=""><?php esc_html_e( '— Selecciona —', 'tic-suite-graficos' ); ?></option>
			<?php foreach ( $views as $v ) : ?>
				<option value="<?php echo esc_attr( $v['id'] ); ?>" <?php selected( $selected, $v['id'] ); ?>>
					<?php echo esc_html( $v['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</form>

	<?php if ( ! empty( $view ) ) :
		$fields = array_merge( $view['dimensions'], $view['measures'] );
		?>
		<section class="tsg-card">
			<h2 class="tsg-card__title"><?php echo esc_html( $view['name'] ); ?></h2>
			<p><?php echo esc_html( $view['description'] ); ?></p>
			<div class="tsg-table-wrap">
				<table class="widefat striped tsg-data-table">
					<thead>
						<tr>
							<?php foreach ( $fields as $field ) : ?>
								<th><?php echo esc_html( $field ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $view['data'] as $row ) : ?>
							<tr>
								<?php foreach ( $fields as $field ) : ?>
									<td><?php echo esc_html( (string) ( $row[ $field ] ?? '' ) ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>
</div>
