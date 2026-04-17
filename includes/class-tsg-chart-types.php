<?php
/**
 * Chart types registry.
 *
 * Defines the 15 supported d3plus-based chart types, their d3plus class,
 * the type of view data they can render, and the minimum shape the view
 * must expose (fields, dimensions, measures).
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Chart_Types
 */
class TSG_Chart_Types {

	/**
	 * Internal registry.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $types;

	public function __construct() {
		$this->types = $this->build_registry();
	}

	/**
	 * Build the full registry.
	 *
	 * Each entry uses keys:
	 *  - label:        Human-readable name.
	 *  - icon:         Dashicon slug for admin UI.
	 *  - d3plus_class: d3plus constructor name.
	 *  - categories:   Supported view categories.
	 *  - requires:     Minimum field types required (dimensions + measures).
	 *  - description:  Short description.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function build_registry(): array {
		return [
			'bar'         => [
				'label'        => __( 'Barras', 'tic-suite-graficos' ),
				'icon'         => 'chart-bar',
				'd3plus_class' => 'BarChart',
				'categories'   => [ 'categorical', 'statistical', 'geographic', 'hierarchical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Comparación de categorías con una métrica.', 'tic-suite-graficos' ),
			],
			'stacked_bar' => [
				'label'        => __( 'Barras apiladas', 'tic-suite-graficos' ),
				'icon'         => 'chart-bar',
				'd3plus_class' => 'BarChart',
				'categories'   => [ 'categorical', 'statistical', 'geographic', 'hierarchical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 2 ],
				'description'  => __( 'Descomposición de categorías en sub-grupos.', 'tic-suite-graficos' ),
			],
			'line'        => [
				'label'        => __( 'Líneas', 'tic-suite-graficos' ),
				'icon'         => 'chart-line',
				'd3plus_class' => 'LinePlot',
				'categories'   => [ 'temporal', 'statistical', 'categorical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Tendencias en el tiempo.', 'tic-suite-graficos' ),
			],
			'area'        => [
				'label'        => __( 'Área', 'tic-suite-graficos' ),
				'icon'         => 'chart-area',
				'd3plus_class' => 'AreaPlot',
				'categories'   => [ 'temporal', 'categorical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Volumen acumulado sobre un eje temporal.', 'tic-suite-graficos' ),
			],
			'stacked_area' => [
				'label'        => __( 'Área apilada', 'tic-suite-graficos' ),
				'icon'         => 'chart-area',
				'd3plus_class' => 'StackedArea',
				'categories'   => [ 'temporal', 'categorical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 2 ],
				'description'  => __( 'Participación de grupos en el tiempo.', 'tic-suite-graficos' ),
			],
			'pie'         => [
				'label'        => __( 'Pastel', 'tic-suite-graficos' ),
				'icon'         => 'chart-pie',
				'd3plus_class' => 'Pie',
				'categories'   => [ 'categorical', 'geographic', 'hierarchical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Composición porcentual de una categoría.', 'tic-suite-graficos' ),
			],
			'donut'       => [
				'label'        => __( 'Dona', 'tic-suite-graficos' ),
				'icon'         => 'chart-pie',
				'd3plus_class' => 'Donut',
				'categories'   => [ 'categorical', 'geographic', 'hierarchical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Composición porcentual con centro libre.', 'tic-suite-graficos' ),
			],
			'treemap'     => [
				'label'        => __( 'Treemap', 'tic-suite-graficos' ),
				'icon'         => 'grid-view',
				'd3plus_class' => 'Treemap',
				'categories'   => [ 'categorical', 'hierarchical', 'geographic' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Jerarquías rectangulares proporcionales.', 'tic-suite-graficos' ),
			],
			'geomap'      => [
				'label'        => __( 'Mapa coroplético', 'tic-suite-graficos' ),
				'icon'         => 'location-alt',
				'd3plus_class' => 'Geomap',
				'categories'   => [ 'geographic' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Distribución geográfica por municipio.', 'tic-suite-graficos' ),
			],
			'network'     => [
				'label'        => __( 'Red', 'tic-suite-graficos' ),
				'icon'         => 'share',
				'd3plus_class' => 'Network',
				'categories'   => [ 'network' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1, 'edges' => true ],
				'description'  => __( 'Grafo de nodos y relaciones.', 'tic-suite-graficos' ),
			],
			'sankey'      => [
				'label'        => __( 'Sankey', 'tic-suite-graficos' ),
				'icon'         => 'randomize',
				'd3plus_class' => 'Sankey',
				'categories'   => [ 'categorical', 'network', 'hierarchical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1 ],
				'description'  => __( 'Flujo de valores entre categorías o nodos.', 'tic-suite-graficos' ),
			],
			'rings'       => [
				'label'        => __( 'Anillos', 'tic-suite-graficos' ),
				'icon'         => 'marker',
				'd3plus_class' => 'Rings',
				'categories'   => [ 'network' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 1, 'edges' => true ],
				'description'  => __( 'Relaciones radiales desde un nodo focal.', 'tic-suite-graficos' ),
			],
			'priestley'   => [
				'label'        => __( 'Línea temporal (Priestley)', 'tic-suite-graficos' ),
				'icon'         => 'clock',
				'd3plus_class' => 'Priestley',
				'categories'   => [ 'geographic', 'categorical' ],
				'requires'     => [ 'dimensions' => 1, 'measures' => 2 ],
				'description'  => __( 'Barras horizontales que muestran periodos o comparación de vigencias.', 'tic-suite-graficos' ),
			],
		];
	}

	/**
	 * Does a chart type exist?
	 */
	public function exists( string $type ): bool {
		return isset( $this->types[ $type ] );
	}

	/**
	 * Get a single chart type definition.
	 */
	public function get( string $type ): array {
		return $this->types[ $type ] ?? [];
	}

	/**
	 * Return all registered chart types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return $this->types;
	}

	/**
	 * Lightweight JSON-safe version of the registry for localization.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_for_js(): array {
		$out = [];
		foreach ( $this->types as $key => $def ) {
			$out[] = [
				'key'         => $key,
				'label'       => $def['label'],
				'icon'        => $def['icon'],
				'class'       => $def['d3plus_class'],
				'categories'  => $def['categories'],
				'description' => $def['description'],
			];
		}
		return $out;
	}

	/**
	 * Given a view definition, return the subset of chart types whose
	 * declared categories intersect with the view's declared category
	 * and whose minimum requires are met.
	 *
	 * @param array $view View definition.
	 * @return array<int, array<string, mixed>>
	 */
	public function compatible_with_view( array $view ): array {
		$view_category = (string) ( $view['category'] ?? '' );
		$dimensions    = count( $view['dimensions'] ?? [] );
		$measures      = count( $view['measures'] ?? [] );
		$has_edges     = ! empty( $view['edges'] );
		$has_range     = ! empty( $view['temporal_range'] );

		$compatible = [];
		foreach ( $this->types as $key => $def ) {
			if ( $view_category && ! in_array( $view_category, $def['categories'], true ) ) {
				continue;
			}
			if ( $dimensions < (int) ( $def['requires']['dimensions'] ?? 0 ) ) {
				continue;
			}
			if ( $measures < (int) ( $def['requires']['measures'] ?? 0 ) ) {
				continue;
			}
			if ( ! empty( $def['requires']['edges'] ) && ! $has_edges ) {
				continue;
			}
			if ( ! empty( $def['requires']['range'] ) && ! $has_range ) {
				continue;
			}
			$compatible[] = [
				'key'         => $key,
				'label'       => $def['label'],
				'icon'        => $def['icon'],
				'class'       => $def['d3plus_class'],
				'description' => $def['description'],
			];
		}
		return $compatible;
	}
}
