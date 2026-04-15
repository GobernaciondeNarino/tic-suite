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

				// Base config — applies to every chart type. `.data()` is
				// NOT set here; each branch in configure() calls .data()
				// itself, possibly after reshaping rows (e.g. wide→long for
				// stacked bar / stacked area).
				viz
					.select( '#' + containerId )
					.detectResize( true )
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
						.data( data )
						.groupBy( dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'stacked_bar': {
					// Wide → long reshape: one row per (dim, measure) pair.
					// Skips "total" / "pct_*" measures since they're derived
					// and would double-count or be on a different scale.
					const stackable = measures.filter( ( m ) => ! /^(total|pct_|participacion|cobertura)/i.test( m ) );
					const useMeasures = stackable.length >= 2 ? stackable : measures.slice( 0, 3 );
					const long = [];
					( data || [] ).forEach( ( row ) => {
						useMeasures.forEach( ( m ) => {
							long.push( Object.assign( {}, row, {
								_metric: Renderer.humanizeKey( m ),
								_value:  Number( row[ m ] ) || 0,
							} ) );
						} );
					} );
					viz
						.data( long )
						.groupBy( [ '_metric', dims[ 0 ] ] )
						.x( dims[ 0 ] )
						.y( '_value' )
						.stacked( true );
					break;
				}

				case 'line':
					viz
						.data( data )
						.groupBy( dims[ 1 ] || dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'area':
					viz
						.data( data )
						.groupBy( dims[ 1 ] || dims[ 0 ] )
						.x( dims[ 0 ] )
						.y( measures[ 0 ] );
					break;

				case 'stacked_area': {
					const stackable = measures.filter( ( m ) => ! /^(total|pct_|participacion|cobertura)/i.test( m ) );
					const useMeasures = stackable.length >= 2 ? stackable : measures.slice( 0, 3 );
					const long = [];
					( data || [] ).forEach( ( row ) => {
						useMeasures.forEach( ( m ) => {
							long.push( Object.assign( {}, row, {
								_metric: Renderer.humanizeKey( m ),
								_value:  Number( row[ m ] ) || 0,
							} ) );
						} );
					} );
					viz
						.data( long )
						.groupBy( '_metric' )
						.x( dims[ 0 ] )
						.y( '_value' );
					break;
				}

				case 'pie':
				case 'donut':
					viz
						.data( data )
						.groupBy( dims[ 0 ] )
						.value( measures[ 0 ] );
					break;

				case 'treemap':
					viz
						.data( data )
						.groupBy( [ dims[ 0 ] ] )
						.sum( measures[ 0 ] );
					break;

				case 'box_whisker':
					viz
						.data( data )
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

				case 'geomap': {
					// Normalize the join field on every row so "SAN ANDRÉS DE
					// TUMACO" joins to topojson id "SAN ANDRES DE TUMACO".
					const joinField = mapping.join || dims[ 0 ] || 'municipio';
					const normData  = ( data || [] ).map( ( row ) => Object.assign(
						{},
						row,
						{ _municipio_id: Renderer.normalizeMuni( row[ joinField ] ) }
					) );
					viz
						.data( normData )
						.groupBy( '_municipio_id' )
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
					if ( typeof viz.topojsonFilter === 'function' ) {
						// Restrict to the Nariño polygons only.
						viz.topojsonFilter( () => true );
					}
					if ( typeof viz.label === 'function' ) {
						// Show the un-normalized name in tooltips.
						viz.label( ( d ) => d[ joinField ] || d._municipio_id );
					}
					break;
				}

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
		 * Turn a snake_case / kebab-case field name into a human label.
		 * "en_operacion" -> "En operacion"
		 * "inversion_millones_cop" -> "Inversion millones cop"
		 */
		humanizeKey( key ) {
			if ( ! key ) {
				return '';
			}
			return String( key )
				.replace( /[_\-]+/g, ' ' )
				.replace( /\s+/g, ' ' )
				.trim()
				.replace( /^./, ( c ) => c.toUpperCase() );
		},

		/**
		 * Normalize a municipio name to the same form used as `id` in the
		 * topojson (uppercase, no accents, collapsed whitespace). Safe on
		 * null / undefined / non-string inputs.
		 */
		normalizeMuni( value ) {
			if ( value === null || value === undefined ) {
				return '';
			}
			const str = String( value );
			return str
				.normalize( 'NFD' )
				.replace( /[\u0300-\u036f]/g, '' )
				.toUpperCase()
				.trim()
				.replace( /\s+/g, ' ' );
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
