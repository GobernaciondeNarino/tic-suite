/* global TSG_FRONTEND, TSGRenderer */
/**
 * TIC Suite · Gráficos — public frontend hydrator.
 *
 * Responsibilities:
 *  1. For every [data-tsg-figure="1"] on the page:
 *     - fetch the render payload for its (view, type)
 *     - hand it to TSGRenderer (d3plus renders into the inner .tsg-chart)
 *     - render the custom legend (text or icon strip) if enabled
 *  2. Wire the toolbar actions: detalle, compartir, datos, imagen, descarga
 *  3. Manage the modal overlay for detalle/datos
 */
( function () {
	'use strict';

	const cache = new Map(); // figure id → { payload, legendData }

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-tsg-figure="1"]' ).forEach( initFigure );
	} );

	// ------------------------------------------------------------------
	// Figure lifecycle
	// ------------------------------------------------------------------

	function initFigure( figure ) {
		const viewId = figure.getAttribute( 'data-view' );
		const type   = figure.getAttribute( 'data-type' );
		if ( ! viewId || ! type ) {
			return;
		}

		const chartEl  = figure.querySelector( '.tsg-chart' );
		const legendEl = figure.querySelector( '[data-tsg-legend="1"]' );
		if ( ! chartEl ) {
			return;
		}

		// Wire toolbar now (works even before data loads).
		wireToolbar( figure );

		const legendOn = figure.getAttribute( 'data-legend' ) === '1';
		const legendStyle = figure.getAttribute( 'data-legend-style' ) || 'text';

		const url = `${ TSG_FRONTEND.restUrl }?view=${ encodeURIComponent( viewId ) }&type=${ encodeURIComponent( type ) }`;

		fetch( url, {
			headers:     { 'X-WP-Nonce': TSG_FRONTEND.nonce },
			credentials: 'same-origin',
		} )
			.then( ( r ) => {
				if ( ! r.ok ) {
					throw new Error( `HTTP ${ r.status }` );
				}
				return r.json();
			} )
			.then( ( payload ) => {
				if ( ! payload || ! payload.data || ! payload.data.length ) {
					chartEl.innerHTML = `<p class="tsg-empty">${ TSG_FRONTEND.i18n.empty }</p>`;
					return;
				}

				cache.set( figure.id, { payload, viewId, type } );

				// Tell the renderer whether to attach d3plus's own legend.
				// When icons mode is selected we hide d3plus' native legend
				// and paint a custom strip ourselves.
				const rendererOpts = {
					legend: legendOn && legendStyle === 'text',
				};

				chartEl.innerHTML = '';
				if ( ! window.TSGRenderer ) {
					chartEl.innerHTML = `<p class="tsg-empty">${ TSG_FRONTEND.i18n.error }</p>`;
					return;
				}

				window.TSGRenderer.render( chartEl.id, payload, rendererOpts );

				// Render the optional icon-strip legend.
				if ( legendEl ) {
					if ( legendOn && legendStyle === 'icons' ) {
						renderIconLegend( legendEl, payload );
					} else {
						legendEl.hidden = true;
					}
				}
			} )
			.catch( ( err ) => {
				console.error( '[TSG]', err );
				chartEl.innerHTML = `<p class="tsg-empty">${ TSG_FRONTEND.i18n.error }</p>`;
			} );
	}

	// ------------------------------------------------------------------
	// Icon-strip legend
	// ------------------------------------------------------------------

	const PALETTE = [
		'#2563eb', '#0ea5e9', '#14b8a6', '#22c55e',
		'#eab308', '#f97316', '#ef4444', '#a855f7',
		'#ec4899', '#6366f1',
	];

	function renderIconLegend( container, payload ) {
		const items = computeLegendItems( payload );
		if ( ! items.length ) {
			container.hidden = true;
			return;
		}
		container.innerHTML = items
			.map( ( it, i ) => {
				const color = PALETTE[ i % PALETTE.length ];
				return `<span class="tsg-legend__item" title="${ escapeHtml( it.label ) }" aria-label="${ escapeHtml( it.label ) }">
					<span class="tsg-legend__swatch" style="background:${ color };"></span>
				</span>`;
			} )
			.join( '' );
		container.hidden = false;
	}

	function computeLegendItems( payload ) {
		const { chart, view, data } = payload;
		const dims     = view.dimensions || [];
		const measures = view.measures || [];

		switch ( chart.key ) {
			case 'stacked_bar':
			case 'stacked_area': {
				const stackable = measures.filter( ( m ) => ! /^(total|pct_|participacion|cobertura)/i.test( m ) );
				const ms = stackable.length >= 2 ? stackable : measures.slice( 0, 3 );
				return ms.map( ( m ) => ( { label: humanize( m ) } ) );
			}
			case 'bar':
			case 'pie':
			case 'donut':
			case 'treemap':
			case 'tree':
			case 'box_whisker':
			case 'geomap': {
				const dim = dims[ 0 ];
				if ( ! dim ) {
					return [];
				}
				const seen = new Set();
				const out  = [];
				( data || [] ).forEach( ( row ) => {
					const v = row[ dim ];
					if ( v !== undefined && v !== null && ! seen.has( v ) ) {
						seen.add( v );
						out.push( { label: String( v ) } );
					}
				} );
				return out.slice( 0, 20 );
			}
			case 'line':
			case 'area':
			case 'stacked_area':
				return measures.map( ( m ) => ( { label: humanize( m ) } ) );
			case 'network':
			case 'rings':
			case 'sankey':
				return []; // nodes are already labeled inside the svg
			default:
				return [];
		}
	}

	function humanize( key ) {
		return String( key || '' )
			.replace( /[_\-]+/g, ' ' )
			.replace( /\s+/g, ' ' )
			.trim()
			.replace( /^./, ( c ) => c.toUpperCase() );
	}

	// ------------------------------------------------------------------
	// Toolbar wiring
	// ------------------------------------------------------------------

	function wireToolbar( figure ) {
		const buttons = figure.querySelectorAll( '[data-tsg-action]' );
		buttons.forEach( ( btn ) => {
			btn.addEventListener( 'click', ( ev ) => {
				ev.preventDefault();
				const action = btn.getAttribute( 'data-tsg-action' );
				runAction( figure, action, btn );
			} );
		} );

		// Modal close wiring.
		figure.querySelectorAll( '[data-tsg-modal-close="1"]' ).forEach( ( el ) => {
			el.addEventListener( 'click', () => closeModal( figure ) );
		} );
		document.addEventListener( 'keydown', ( ev ) => {
			if ( ev.key === 'Escape' ) {
				closeModal( figure );
			}
		} );
	}

	function runAction( figure, action, btn ) {
		const entry = cache.get( figure.id );
		if ( ! entry ) {
			return;
		}
		switch ( action ) {
			case 'detalle':
				openDetalle( figure, entry );
				break;
			case 'compartir':
				shareUrl( figure, btn );
				break;
			case 'datos':
				openDatos( figure, entry );
				break;
			case 'imagen':
				exportImage( figure, entry );
				break;
			case 'descarga':
				downloadData( figure, entry );
				break;
		}
	}

	// ------------------------------------------------------------------
	// Actions
	// ------------------------------------------------------------------

	function openDetalle( figure, entry ) {
		const { payload } = entry;
		const view  = payload.view;
		const chart = payload.chart;
		const html = `
			<p class="tsg-modal__lede">${ escapeHtml( view.name || '' ) }</p>
			<dl class="tsg-modal__dl">
				<dt>${ t( 'Tipo de gráfico' ) }</dt><dd>${ escapeHtml( chart.label || chart.key ) }</dd>
				<dt>${ t( 'Categoría' ) }</dt><dd>${ escapeHtml( view.category || '—' ) }</dd>
				<dt>${ t( 'Dimensiones' ) }</dt><dd>${ ( view.dimensions || [] ).map( humanize ).map( escapeHtml ).join( ', ' ) || '—' }</dd>
				<dt>${ t( 'Medidas' ) }</dt><dd>${ ( view.measures || [] ).map( humanize ).map( escapeHtml ).join( ', ' ) || '—' }</dd>
				<dt>${ t( 'Filas' ) }</dt><dd>${ ( payload.data || [] ).length }</dd>
			</dl>
		`;
		showModal( figure, t( 'Detalle del gráfico' ), html );
	}

	function openDatos( figure, entry ) {
		const { payload } = entry;
		const fields = [ ...( payload.view.dimensions || [] ), ...( payload.view.measures || [] ) ];
		const rows   = payload.data || [];
		const head   = fields.map( ( f ) => `<th>${ escapeHtml( humanize( f ) ) }</th>` ).join( '' );
		const body   = rows
			.map( ( r ) => {
				const cells = fields.map( ( f ) => `<td>${ escapeHtml( formatCell( r[ f ] ) ) }</td>` ).join( '' );
				return `<tr>${ cells }</tr>`;
			} )
			.join( '' );
		const html = `
			<div class="tsg-modal__table-wrap">
				<table class="tsg-modal__table">
					<thead><tr>${ head }</tr></thead>
					<tbody>${ body }</tbody>
				</table>
			</div>
		`;
		showModal( figure, t( 'Datos de la vista' ), html );
	}

	function shareUrl( figure, btn ) {
		const url = window.location.origin + window.location.pathname + '#' + figure.id;
		const done = () => flashButton( btn, t( 'URL copiada' ) );
		if ( navigator.share ) {
			navigator.share( { title: document.title, url } ).catch( () => copyText( url ).then( done ) );
		} else {
			copyText( url ).then( done ).catch( () => flashButton( btn, t( 'Error al copiar' ) ) );
		}
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( ( resolve, reject ) => {
			try {
				const ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				resolve();
			} catch ( e ) {
				reject( e );
			}
		} );
	}

	function exportImage( figure, entry ) {
		const svg = figure.querySelector( '.tsg-chart svg' );
		if ( ! svg ) {
			return;
		}
		const clone = svg.cloneNode( true );
		clone.setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
		const w = svg.clientWidth || 800;
		const h = svg.clientHeight || 600;
		clone.setAttribute( 'width', w );
		clone.setAttribute( 'height', h );

		const xml = new XMLSerializer().serializeToString( clone );
		const blob = new Blob( [ xml ], { type: 'image/svg+xml;charset=utf-8' } );
		const url  = URL.createObjectURL( blob );
		const img  = new Image();

		img.onload = () => {
			const canvas = document.createElement( 'canvas' );
			canvas.width  = w * 2; // 2x for retina
			canvas.height = h * 2;
			const ctx = canvas.getContext( '2d' );
			ctx.fillStyle = '#ffffff';
			ctx.fillRect( 0, 0, canvas.width, canvas.height );
			ctx.drawImage( img, 0, 0, canvas.width, canvas.height );
			URL.revokeObjectURL( url );
			canvas.toBlob( ( pngBlob ) => {
				if ( ! pngBlob ) {
					return;
				}
				triggerDownload( pngBlob, `${ entry.viewId }-${ entry.type }.png` );
			}, 'image/png' );
		};
		img.onerror = () => {
			URL.revokeObjectURL( url );
			// Fallback: download the SVG as-is.
			triggerDownload( blob, `${ entry.viewId }-${ entry.type }.svg` );
		};
		img.src = url;
	}

	function downloadData( figure, entry ) {
		const { payload, viewId } = entry;
		const obj = {
			view: payload.view,
			data: payload.data,
		};
		const blob = new Blob( [ JSON.stringify( obj, null, 2 ) ], { type: 'application/json;charset=utf-8' } );
		triggerDownload( blob, `${ viewId }.json` );
	}

	function triggerDownload( blob, filename ) {
		const a   = document.createElement( 'a' );
		const url = URL.createObjectURL( blob );
		a.href     = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		setTimeout( () => URL.revokeObjectURL( url ), 1000 );
	}

	// ------------------------------------------------------------------
	// Modal
	// ------------------------------------------------------------------

	function showModal( figure, title, bodyHtml ) {
		const modal = figure.querySelector( '[data-tsg-modal="1"]' );
		if ( ! modal ) {
			return;
		}
		modal.querySelector( '.tsg-modal__title' ).textContent = title;
		modal.querySelector( '.tsg-modal__body' ).innerHTML    = bodyHtml;
		modal.hidden = false;
		modal.classList.add( 'is-open' );
	}

	function closeModal( figure ) {
		const modal = figure.querySelector( '[data-tsg-modal="1"]' );
		if ( ! modal ) {
			return;
		}
		modal.hidden = true;
		modal.classList.remove( 'is-open' );
	}

	// ------------------------------------------------------------------
	// Utilities
	// ------------------------------------------------------------------

	function flashButton( btn, text ) {
		if ( ! btn ) {
			return;
		}
		const original = btn.innerHTML;
		btn.innerHTML = `<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="tsg-action__label">${ escapeHtml( text ) }</span>`;
		btn.classList.add( 'is-success' );
		setTimeout( () => {
			btn.innerHTML = original;
			btn.classList.remove( 'is-success' );
		}, 1600 );
	}

	function formatCell( v ) {
		if ( v === null || v === undefined ) {
			return '';
		}
		if ( typeof v === 'number' ) {
			return new Intl.NumberFormat( 'es-CO' ).format( v );
		}
		if ( typeof v === 'boolean' ) {
			return v ? '✓' : '';
		}
		return String( v );
	}

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	function t( key ) {
		return ( TSG_FRONTEND && TSG_FRONTEND.i18n && TSG_FRONTEND.i18n[ key ] ) || key;
	}
} )();
