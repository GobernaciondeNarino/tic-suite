/* global TSG_FRONTEND, TSGRenderer */
/**
 * TIC Suite · Gráficos — frontend shortcode renderer.
 *
 * Finds every <div data-tsg-chart="1">, fetches its render payload from the
 * REST API and hands it to TSGRenderer.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', () => {
		const charts = document.querySelectorAll( '[data-tsg-chart="1"]' );
		charts.forEach( hydrate );
	} );

	function hydrate( el ) {
		const viewId = el.getAttribute( 'data-view' );
		const type   = el.getAttribute( 'data-type' );
		if ( ! viewId || ! type ) {
			return;
		}
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
					el.innerHTML = `<p class="tsg-empty">${ TSG_FRONTEND.i18n.empty }</p>`;
					return;
				}
				el.innerHTML = '';
				if ( window.TSGRenderer ) {
					window.TSGRenderer.render( el.id, payload );
				} else {
					el.innerHTML = `<p class="tsg-empty">${ TSG_FRONTEND.i18n.error }</p>`;
				}
			} )
			.catch( ( err ) => {
				console.error( '[TSG]', err );
				el.innerHTML = `<p class="tsg-empty">${ TSG_FRONTEND.i18n.error }</p>`;
			} );
	}
} )();
