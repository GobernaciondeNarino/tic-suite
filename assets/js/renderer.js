/* global d3plus */
/**
 * TIC Suite · Gráficos — d3plus v3 renderer.
 *
 * Uses @d3plus/core v3 API: chainable config methods + .render().
 * Responsive via .detectResize(true) (ResizeObserver on parent +
 * window resize).
 *
 * Exposes window.TSGRenderer.render( containerId, payload ).
 */
( function () {
	'use strict';

	const PALETTE = [
		'#2563eb', '#0ea5e9', '#14b8a6', '#22c55e',
		'#eab308', '#f97316', '#ef4444', '#a855f7',
		'#ec4899', '#6366f1',
	];

	const Renderer = {
		/**
		 * Wait until window.d3plus is ready (script may still be loading).
		 */
		waitForD3plus( timeoutMs = 8000 ) {
			return new Promise( ( resolve, reject ) => {
				if ( window.d3plus ) {
					return resolve();
				}
				const start = Date.now();
				const tick = () => {
					if ( window.d3plus ) {
						return resolve();
					}
					if ( Date.now() - start > timeoutMs ) {
						return reject( new Error( 'd3plus timeout' ) );
					}
					setTimeout( tick, 80 );
				};
				tick();
			} );
		},

		async render( containerId, payload ) {
			const el = document.getElementById( containerId );
			if ( ! el || ! payload ) {
				return;
			}
			el.innerHTML = '';

			try {
				await this.waitForD3plus();
			} catch ( e ) {
				el.innerHTML = '<p class="tsg-empty">d3plus no disponible.</p>';
				return;
			}

			const Ctor = window.d3plus[ payload.chart.class ];
			if ( typeof Ctor !== 'function' ) {
				el.innerHTML = `<p class="tsg-empty">Tipo de gráfico no soportado: ${ payload.chart.class }</p>`;
				return;
			}

			try {
				const viz = new Ctor();

				// Base config — applies to every chart type.
				viz
					.select( '#' + containerId )
					.detectResize( true )
					.data( payload.data || [] )
					.legend( true );

				this.configure( viz, payload );

				viz.render();

				// Belt-and-suspenders: force a resize tick after the container
				// is laid out so width/height are picked up correctly.
				window.requestAnimationFrame( () => {
					try {
						if ( typeof viz.resize === 'function' ) {
							viz.resize();
						}
					} catch ( _e ) { /* noop */ }
				} );
			} catch ( err ) {
				console.error( '[TSG] render error', err );
				el.innerHTML = '<p class="tsg-empty">No fue posible renderizar el gráfico.</p>';
			}
		},

		/**
		 * Apply chart-specific configuration.
		 */
		configure( viz, payload ) {
			const { mapping, data, view, chart } = payload;
			const dims     = view.dimensions || [];
			const measures = view.measures   || [];

			switch ( chart.key ) {
				case 'bar':
					viz
						.groupBy( dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'stacked_bar':
					viz
						.groupBy( dims[ 1 ] || dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] )
						.stacked( true );
					break;

				case 'line':
					viz
						.groupBy( dims[ 1 ] || dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'area':
					viz
						.groupBy( dims[ 1 ] || dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'stacked_area':
					viz
						.groupBy( dims[ 1 ] || dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'pie':
				case 'donut':
					viz
						.groupBy( dims[ 0 ] )
						.value( measures[ 0 ] );
					break;

				case 'treemap':
					viz
						.groupBy( dims.length ? dims : [ dims[ 0 ] ] )
						.sum( measures[ 0 ] );
					break;

				case 'box_whisker':
					viz
						.groupBy( dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'priestley':
					viz.groupBy( dims[ 0 ] );
					if ( typeof viz.start === 'function' && dims[ 0 ] ) {
						viz.start( dims[ 0 ] );
					}
					if ( typeof viz.end === 'function' && dims[ 1 ] ) {
						viz.end( dims[ 1 ] );
					}
					break;

				case 'network': {
					const nodes = this.buildNodes( data, dims[ 0 ] );
					this.safeCall( viz, 'nodes', nodes );
					this.safeCall( viz, 'links', mapping.links || view.edges || [] );
					viz.groupBy( dims[ 0 ] );
					if ( measures[ 0 ] ) {
						viz.size( measures[ 0 ] );
					}
					break;
				}

				case 'rings': {
					const nodes = this.buildNodes( data, dims[ 0 ] );
					this.safeCall( viz, 'nodes', nodes );
					this.safeCall( viz, 'links', mapping.links || view.edges || [] );
					if ( nodes.length && typeof viz.center === 'function' ) {
						viz.center( nodes[ 0 ].id );
					}
					break;
				}

				case 'sankey': {
					const nodes = this.buildNodes( data, dims[ 0 ] );
					this.safeCall( viz, 'nodes', nodes );
					this.safeCall( viz, 'links', mapping.links || view.edges || [] );
					break;
				}

				case 'tree':
					viz.groupBy( dims );
					if ( measures[ 0 ] ) {
						viz.sum( measures[ 0 ] );
					}
					break;

				case 'geomap':
					viz
						.groupBy( dims[ 0 ] )
						.colorScale( measures[ 0 ] );
					if ( typeof viz.topojson === 'function' && mapping.topojson ) {
						viz.topojson( mapping.topojson );
					}
					if ( typeof viz.topojsonId === 'function' && mapping.topojsonId ) {
						viz.topojsonId( mapping.topojsonId );
					}
					if ( typeof viz.topojsonKey === 'function' && mapping.topojsonKey ) {
						viz.topojsonKey( mapping.topojsonKey );
					}
					break;

				default:
					if ( dims[ 0 ] ) {
						viz.groupBy( dims[ 0 ] );
					}
					if ( measures[ 0 ] ) {
						viz.y( measures[ 0 ] );
					}
			}

			// Consistent minimalist color palette.
			if ( typeof viz.shapeConfig === 'function' ) {
				viz.shapeConfig( {
					fill: ( _d, i ) => PALETTE[ i % PALETTE.length ],
				} );
			}
		},

		/**
		 * Build a unique nodes array from row data keyed on `field`.
		 */
		buildNodes( data, field ) {
			const seen = new Set();
			const out  = [];
			( data || [] ).forEach( ( row ) => {
				const id = row.id || row[ field ];
				if ( ! id || seen.has( id ) ) {
					return;
				}
				seen.add( id );
				out.push( Object.assign( { id }, row ) );
			} );
			return out;
		},

		/**
		 * Call viz.method(value) only if the method exists on the instance.
		 */
		safeCall( viz, method, value ) {
			if ( typeof viz[ method ] === 'function' ) {
				try {
					viz[ method ]( value );
				} catch ( err ) {
					console.warn( `[TSG] viz.${ method }() failed`, err );
				}
			}
		},
	};

	window.TSGRenderer = Renderer;
} )();
