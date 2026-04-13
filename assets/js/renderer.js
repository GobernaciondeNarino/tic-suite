/* global d3plus */
/**
 * TIC Suite · Gráficos — d3plus renderer.
 *
 * Exposes window.TSGRenderer.render( containerId, payload ) where payload is
 * the REST response shape:
 *   { chart: { key, class, label }, view, data, mapping }
 *
 * Shared between the admin preview and the public shortcode render.
 */
( function () {
	'use strict';

	const Renderer = {
		render( containerId, payload ) {
			const el = document.getElementById( containerId );
			if ( ! el || ! payload ) {
				return;
			}
			el.innerHTML = '';

			if ( ! window.d3plus ) {
				el.innerHTML = '<p class="tsg-empty">d3plus no disponible.</p>';
				return;
			}

			const Ctor = window.d3plus[ payload.chart.class ];
			if ( typeof Ctor !== 'function' ) {
				el.innerHTML = `<p class="tsg-empty">Tipo de gráfico no soportado: ${ payload.chart.class }</p>`;
				return;
			}

			try {
				const viz = new Ctor().select( '#' + containerId );
				this.configure( viz, payload );
				viz.render();
			} catch ( err ) {
				console.error( '[TSG] render error', err );
				el.innerHTML = '<p class="tsg-empty">No fue posible renderizar el gráfico.</p>';
			}
		},

		configure( viz, payload ) {
			const { mapping, data, view, chart } = payload;

			if ( data && data.length ) {
				viz.data( data );
			}

			switch ( chart.key ) {
				case 'bar':
				case 'stacked_bar':
					viz
						.groupBy( mapping.groupBy )
						.x( mapping.x )
						.y( mapping.y );
					if ( chart.key === 'stacked_bar' ) {
						viz.stacked && viz.stacked( true );
					}
					break;

				case 'line':
				case 'area':
				case 'stacked_area':
					viz
						.groupBy( mapping.groupBy[ 1 ] || mapping.groupBy[ 0 ] )
						.x( mapping.x )
						.y( mapping.y );
					if ( chart.key === 'stacked_area' ) {
						viz.stacked && viz.stacked( true );
					}
					break;

				case 'pie':
				case 'donut':
					viz
						.groupBy( mapping.groupBy[ 0 ] )
						.value( mapping.value );
					break;

				case 'treemap':
					viz
						.groupBy( mapping.groupBy )
						.sum( mapping.value );
					break;

				case 'box_whisker':
					viz
						.groupBy( mapping.groupBy[ 0 ] )
						.x( mapping.groupBy[ 0 ] )
						.y( mapping.y );
					break;

				case 'priestley':
					viz
						.groupBy( mapping.groupBy[ 0 ] )
						.start && viz.start( mapping.start );
					viz.end && viz.end( mapping.end );
					break;

				case 'network':
				case 'rings':
					viz
						.nodes( ( mapping.nodes || [] ).map( ( n ) => ( { id: n.id || n } ) ) )
						.links( mapping.links || [] )
						.size( mapping.size );
					if ( chart.key === 'rings' && viz.center && data[ 0 ] ) {
						viz.center( data[ 0 ].id );
					}
					break;

				case 'sankey':
					viz
						.nodes( data.map( ( d ) => ( { id: d[ view.dimensions[ 0 ] ] } ) ) )
						.links( mapping.links || [] );
					break;

				case 'tree':
					viz
						.groupBy( mapping.groupBy )
						.sum( mapping.value || ( () => 1 ) );
					break;

				case 'geomap':
					viz
						.groupBy( mapping.groupBy[ 0 ] )
						.colorScale( mapping.value )
						.topojson( mapping.topojson )
						.topojsonId( mapping.topojsonId )
						.topojsonKey( mapping.topojsonKey );
					break;

				default:
					viz.groupBy( mapping.groupBy ).x( mapping.x ).y( mapping.y );
			}

			// Minimalist theme defaults — consistent across admin + public.
			viz
				.legend( true )
				.tooltipConfig( {
					background: '#0f172a',
					padding:    '12px',
					fontColor:  '#f8fafc',
					fontSize:   '13px',
				} )
				.shapeConfig( {
					fill: ( d, i ) => Renderer.palette( i ),
				} );
		},

		palette( i ) {
			const colors = [
				'#2563eb', '#0ea5e9', '#14b8a6', '#22c55e',
				'#eab308', '#f97316', '#ef4444', '#a855f7',
				'#ec4899', '#6366f1',
			];
			return colors[ i % colors.length ];
		},
	};

	window.TSGRenderer = Renderer;
} )();
