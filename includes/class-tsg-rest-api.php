<?php
/**
 * REST API controller.
 *
 * Routes:
 *  GET  /tic-suite/v1/views                       → list views (admin)
 *  GET  /tic-suite/v1/views/(?P<id>[a-z0-9_\-]+)  → single view + compatible charts (admin)
 *  GET  /tic-suite/v1/render?view=…&type=…        → public render payload (d3plus config + data)
 *
 * Every route accepts an optional `project` query param (default: "nacion")
 * that scopes the data provider. Valid values are the keys of
 * TSG_Plugin::PROJECTS (currently: nacion, ondas).
 *
 * @package TicSuite\Graficos
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TSG_Rest_Api
 */
class TSG_Rest_Api {

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

	public function register_routes(): void {
		$project_arg = [
			'required'          => false,
			'default'           => TSG_Plugin::DEFAULT_PROJECT,
			'sanitize_callback' => 'sanitize_key',
		];

		register_rest_route(
			TSG_REST_NAMESPACE,
			'/views',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_views' ],
				'permission_callback' => [ $this->security, 'rest_admin_permission' ],
				'args'                => [ 'project' => $project_arg ],
			]
		);

		register_rest_route(
			TSG_REST_NAMESPACE,
			'/views/(?P<id>[a-z0-9_\-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_view' ],
				'permission_callback' => [ $this->security, 'rest_admin_permission' ],
				'args'                => [
					'id'      => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'project' => $project_arg,
				],
			]
		);

		register_rest_route(
			TSG_REST_NAMESPACE,
			'/render',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'render_payload' ],
				'permission_callback' => [ $this->security, 'rest_public_permission' ],
				'args'                => [
					'view'    => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'type'    => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'project' => $project_arg,
				],
			]
		);
	}

	/**
	 * Resolve the requested project slug against the whitelist.
	 */
	private function project_from( WP_REST_Request $request ): string {
		return $this->plugin->normalize_project( (string) $request->get_param( 'project' ) );
	}

	/**
	 * GET /views
	 */
	public function list_views( WP_REST_Request $request ): WP_REST_Response {
		$project = $this->project_from( $request );
		$dp      = $this->plugin->data_provider( $project );
		return new WP_REST_Response( $dp->list_views(), 200 );
	}

	/**
	 * GET /views/{id}
	 */
	public function get_view( WP_REST_Request $request ) {
		$project = $this->project_from( $request );
		$dp      = $this->plugin->data_provider( $project );
		$id      = $this->security->sanitize_view_id( (string) $request->get_param( 'id' ) );
		$view    = $dp->get_view( $id );
		if ( empty( $view ) ) {
			return new WP_Error( 'tsg_view_not_found', __( 'Vista no encontrada.', 'tic-suite-graficos' ), [ 'status' => 404 ] );
		}

		$compatible = $this->chart_types->compatible_with_view( $view );
		return new WP_REST_Response(
			[
				'view'       => $view,
				'compatible' => $compatible,
			],
			200
		);
	}

	/**
	 * GET /render?view=…&type=…
	 *
	 * Returns a minimal, whitelisted payload safe to consume client-side.
	 */
	public function render_payload( WP_REST_Request $request ) {
		$project    = $this->project_from( $request );
		$dp         = $this->plugin->data_provider( $project );
		$view_id    = $this->security->sanitize_view_id( (string) $request->get_param( 'view' ) );
		$chart_type = $this->security->sanitize_chart_type( (string) $request->get_param( 'type' ), $this->chart_types );

		if ( '' === $view_id || '' === $chart_type ) {
			return new WP_Error( 'tsg_bad_request', __( 'Parámetros inválidos.', 'tic-suite-graficos' ), [ 'status' => 400 ] );
		}

		$view = $dp->get_view( $view_id );
		if ( empty( $view ) ) {
			return new WP_Error( 'tsg_view_not_found', __( 'Vista no encontrada.', 'tic-suite-graficos' ), [ 'status' => 404 ] );
		}

		$compatible_keys = wp_list_pluck( $this->chart_types->compatible_with_view( $view ), 'key' );
		if ( ! in_array( $chart_type, $compatible_keys, true ) ) {
			return new WP_Error( 'tsg_incompatible', __( 'Gráfico no compatible con la vista.', 'tic-suite-graficos' ), [ 'status' => 409 ] );
		}

		$chart_def = $this->chart_types->get( $chart_type );
		$payload   = [
			'chart'      => [
				'key'   => $chart_type,
				'class' => $chart_def['d3plus_class'] ?? '',
				'label' => $chart_def['label'] ?? '',
			],
			'view'       => [
				'id'          => $view['id'],
				'name'        => $view['name'],
				'category'    => $view['category'],
				'dimensions'  => $view['dimensions'],
				'measures'    => $view['measures'],
				'edges'       => $view['edges'],
			],
			'data'       => $view['data'],
			'mapping'    => $this->build_mapping( $view, $chart_type ),
			'compatible' => $this->chart_types->compatible_with_view( $view ),
			'project'    => $project,
		];

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Map view fields to d3plus config keys (groupBy, x, y, size, …).
	 *
	 * @param array  $view       View definition.
	 * @param string $chart_type Chart type key.
	 */
	private function build_mapping( array $view, string $chart_type ): array {
		$dims     = $view['dimensions'] ?? [];
		$measures = $view['measures'] ?? [];
		$mapping  = [
			'groupBy' => $dims,
			'x'       => $dims[0] ?? '',
			'y'       => $measures[0] ?? '',
			'value'   => $measures[0] ?? '',
			'size'    => $measures[0] ?? '',
		];

		switch ( $chart_type ) {
			case 'sankey':
				$mapping['nodes'] = $dims;
				$mapping['links'] = $view['edges'] ?? [];
				break;
			case 'network':
			case 'rings':
				$mapping['nodes'] = array_map(
					static fn( $row ) => [ 'id' => $row['id'] ?? '' ],
					$view['data']
				);
				$mapping['links'] = $view['edges'] ?? [];
				break;
			case 'priestley':
				$mapping['start'] = $view['dimensions'][0] ?? '';
				$mapping['end']   = $view['dimensions'][1] ?? '';
				break;
			case 'geomap':
				$mapping['topojson']    = plugins_url( 'data/topo/narino_municipios.topojson', TSG_PLUGIN_FILE );
				$mapping['topojsonId']  = 'id';
				$mapping['topojsonKey'] = 'objects.municipios';
				$mapping['join']        = 'municipio';
				break;
		}

		return $mapping;
	}
}
