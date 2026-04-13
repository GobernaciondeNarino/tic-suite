/* global d3plus, TSG_ADMIN */
/**
 * TIC Suite · Gráficos — admin chart builder.
 *
 * Wires the three-panel UI (views → chart types → preview + shortcode).
 * Uses fetch against the REST API with the nonce injected by WordPress.
 */
( function () {
	'use strict';

	const state = {
		viewId:   null,
		chartKey: null,
		view:     null,
		compatible: [],
	};

	const els = {};

	function $( sel, root = document ) {
		return root.querySelector( sel );
	}
	function $$( sel, root = document ) {
		return Array.from( root.querySelectorAll( sel ) );
	}

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		els.viewList      = $( '.tsg-views-list' );
		els.typesWrap     = $( '#tsg-chart-types' );
		els.preview       = $( '#tsg-preview' );
		els.shortcodeBox  = $( '.tsg-shortcode-box' );
		els.shortcodeInput = $( '#tsg-shortcode-input' );
		els.copyBtn       = $( '#tsg-copy-btn' );

		if ( ! els.viewList ) {
			// Not on the builder screen — still wire global copy buttons.
			wireGalleryCopyButtons();
			return;
		}

		$$( '.tsg-view-item', els.viewList ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => onSelectView( btn ) );
		} );

		els.copyBtn && els.copyBtn.addEventListener( 'click', copyShortcode );

		wireGalleryCopyButtons();
	}

	function wireGalleryCopyButtons() {
		$$( '[data-tsg-copy]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', ( ev ) => {
				const row = btn.closest( '.tsg-shortcode-row' );
				const input = row && row.querySelector( 'input' );
				if ( ! input ) {
					return;
				}
				copyToClipboard( input.value, btn );
			} );
		} );
	}

	async function onSelectView( btn ) {
		$$( '.tsg-view-item', els.viewList ).forEach( ( el ) => {
			el.classList.remove( 'is-active' );
			el.setAttribute( 'aria-selected', 'false' );
		} );
		btn.classList.add( 'is-active' );
		btn.setAttribute( 'aria-selected', 'true' );

		state.viewId = btn.dataset.viewId;
		state.chartKey = null;
		els.typesWrap.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.loading }</p>`;
		els.preview.innerHTML   = `<p class="tsg-empty">${ TSG_ADMIN.i18n.loading }</p>`;
		els.shortcodeBox.hidden = true;

		try {
			const res = await fetch( `${ TSG_ADMIN.restUrl }/views/${ encodeURIComponent( state.viewId ) }`, {
				headers: { 'X-WP-Nonce': TSG_ADMIN.nonce },
				credentials: 'same-origin',
			} );
			if ( ! res.ok ) {
				throw new Error( `HTTP ${ res.status }` );
			}
			const payload = await res.json();
			state.view       = payload.view;
			state.compatible = payload.compatible || [];
			renderChartTypes();
			els.preview.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.selectChart }</p>`;
		} catch ( err ) {
			els.typesWrap.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.noCompatible }</p>`;
			console.error( '[TSG]', err );
		}
	}

	function renderChartTypes() {
		if ( ! state.compatible.length ) {
			els.typesWrap.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.noCompatible }</p>`;
			return;
		}
		els.typesWrap.innerHTML = '';
		state.compatible.forEach( ( ct ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'tsg-type-card';
			btn.dataset.typeKey = ct.key;
			btn.innerHTML = `
				<span class="dashicons dashicons-${ escapeAttr( ct.icon || 'chart-area' ) }" aria-hidden="true"></span>
				<span class="tsg-type-card__label">${ escapeHtml( ct.label ) }</span>
				<span class="tsg-type-card__desc">${ escapeHtml( ct.description || '' ) }</span>
			`;
			btn.addEventListener( 'click', () => onSelectChart( ct.key, btn ) );
			els.typesWrap.appendChild( btn );
		} );
	}

	async function onSelectChart( key, btn ) {
		$$( '.tsg-type-card', els.typesWrap ).forEach( ( el ) => el.classList.remove( 'is-active' ) );
		btn.classList.add( 'is-active' );
		state.chartKey = key;

		els.preview.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.loading }</p>`;

		try {
			const url = `${ TSG_ADMIN.restUrl }/render?view=${ encodeURIComponent( state.viewId ) }&type=${ encodeURIComponent( key ) }`;
			const res = await fetch( url, {
				headers: { 'X-WP-Nonce': TSG_ADMIN.nonce },
				credentials: 'same-origin',
			} );
			if ( ! res.ok ) {
				throw new Error( `HTTP ${ res.status }` );
			}
			const payload = await res.json();
			renderPreview( payload );
			renderShortcode();
		} catch ( err ) {
			console.error( '[TSG]', err );
			els.preview.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.noCompatible }</p>`;
		}
	}

	function renderPreview( payload ) {
		els.preview.innerHTML = '<div class="tsg-preview__canvas" id="tsg-preview-canvas"></div>';
		window.TSGRenderer.render( 'tsg-preview-canvas', payload );
	}

	function renderShortcode() {
		const sc = `[tsg_grafico view="${ state.viewId }" type="${ state.chartKey }" height="420" title="${ state.view.name }"]`;
		els.shortcodeInput.value = sc;
		els.shortcodeBox.hidden = false;
	}

	function copyShortcode() {
		copyToClipboard( els.shortcodeInput.value, els.copyBtn );
	}

	function copyToClipboard( text, srcBtn ) {
		const done = () => {
			if ( ! srcBtn ) {
				return;
			}
			const original = srcBtn.innerHTML;
			srcBtn.innerHTML = `<span class="dashicons dashicons-yes" aria-hidden="true"></span> ${ TSG_ADMIN.i18n.copied }`;
			srcBtn.classList.add( 'is-success' );
			setTimeout( () => {
				srcBtn.innerHTML = original;
				srcBtn.classList.remove( 'is-success' );
			}, 1800 );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done ).catch( fallback );
		} else {
			fallback();
		}
		function fallback() {
			const ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.setAttribute( 'readonly', '' );
			ta.style.position = 'absolute';
			ta.style.left = '-9999px';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' );
				done();
			} catch ( e ) {
				window.alert( TSG_ADMIN.i18n.copyFailed );
			}
			document.body.removeChild( ta );
		}
	}

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}
	function escapeAttr( str ) {
		return String( str ).replace( /[^a-z0-9\-_]/gi, '' );
	}
} )();
