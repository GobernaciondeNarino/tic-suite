/* global d3plus, TSG_ADMIN */
/**
 * TIC Suite · Gráficos — admin chart builder.
 *
 * Wires the 3-panel UI (views → chart types → preview + shortcode) and
 * the options fieldset (legend / legend_style / toolbar / actions).
 *
 * Option changes live-update:
 *   - the current preview (re-render with new options)
 *   - the generated shortcode string
 */
( function () {
	'use strict';

	const DEFAULT_ACTIONS = [ 'detalle', 'compartir', 'datos', 'imagen', 'descarga', 'cambiar' ];

	const state = {
		project:  'nacion',
		viewId:   null,
		chartKey: null,
		view:     null,
		compatible: [],
		lastPayload: null,
		options: {
			legend:       true,
			legend_style: 'text',
			toolbar:      true,
			actions:      DEFAULT_ACTIONS.slice(),
			x_title:      '',
			y_title:      '',
		},
	};

	/**
	 * Append ?project=… (or &project=…) to a REST url.
	 */
	function withProject( url ) {
		const sep = url.indexOf( '?' ) === -1 ? '?' : '&';
		return url + sep + 'project=' + encodeURIComponent( state.project );
	}

	const els = {};

	const $  = ( sel, root = document ) => root.querySelector( sel );
	const $$ = ( sel, root = document ) => Array.from( root.querySelectorAll( sel ) );

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		// Resolve the active project from the page wrap so every REST call
		// is scoped correctly.
		const wrap = $( '.tsg-wrap[data-tsg-project]' );
		if ( wrap ) {
			state.project = wrap.getAttribute( 'data-tsg-project' ) || 'nacion';
		}

		els.viewList       = $( '.tsg-views-list' );
		els.typesWrap      = $( '#tsg-chart-types' );
		els.preview        = $( '#tsg-preview' );
		els.shortcodeBox   = $( '.tsg-shortcode-box' );
		els.shortcodeInput = $( '#tsg-shortcode-input' );
		els.copyBtn        = $( '#tsg-copy-btn' );
		els.options        = $( '#tsg-options' );

		if ( ! els.viewList ) {
			// Not on the builder screen — only wire gallery copy buttons.
			wireGalleryCopyButtons();
			return;
		}

		$$( '.tsg-view-item', els.viewList ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => onSelectView( btn ) );
		} );

		if ( els.copyBtn ) {
			els.copyBtn.addEventListener( 'click', copyShortcode );
		}

		wireOptionsUI();
		wireGalleryCopyButtons();
	}

	// ------------------------------------------------------------------
	// Options UI
	// ------------------------------------------------------------------

	function wireOptionsUI() {
		if ( ! els.options ) {
			return;
		}

		$$( '[data-tsg-opt]', els.options ).forEach( ( input ) => {
			const handler = () => {
				const key = input.getAttribute( 'data-tsg-opt' );
				if ( input.type === 'checkbox' ) {
					state.options[ key ] = input.checked;
				} else {
					state.options[ key ] = input.value;
				}
				onOptionsChange();
			};
			input.addEventListener( 'change', handler );
			// Live update for text inputs (axis titles).
			if ( input.type === 'text' ) {
				input.addEventListener( 'input', handler );
			}
		} );

		$$( '[data-tsg-action-opt]', els.options ).forEach( ( input ) => {
			input.addEventListener( 'change', () => {
				const action = input.getAttribute( 'data-tsg-action-opt' );
				if ( input.checked ) {
					if ( ! state.options.actions.includes( action ) ) {
						// Keep the canonical order.
						state.options.actions = DEFAULT_ACTIONS.filter( ( a ) =>
							a === action || state.options.actions.includes( a )
						);
					}
				} else {
					state.options.actions = state.options.actions.filter( ( a ) => a !== action );
				}
				onOptionsChange();
			} );
		} );
	}

	function onOptionsChange() {
		if ( state.lastPayload ) {
			renderPreview( state.lastPayload );
		}
		if ( state.chartKey ) {
			renderShortcode();
		}
	}

	function wireGalleryCopyButtons() {
		$$( '[data-tsg-copy]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const row   = btn.closest( '.tsg-shortcode-row' );
				const input = row && row.querySelector( 'input' );
				if ( input ) {
					copyToClipboard( input.value, btn );
				}
			} );
		} );
	}

	// ------------------------------------------------------------------
	// View picker
	// ------------------------------------------------------------------

	async function onSelectView( btn ) {
		$$( '.tsg-view-item', els.viewList ).forEach( ( el ) => {
			el.classList.remove( 'is-active' );
			el.setAttribute( 'aria-selected', 'false' );
		} );
		btn.classList.add( 'is-active' );
		btn.setAttribute( 'aria-selected', 'true' );

		state.viewId      = btn.dataset.viewId;
		state.chartKey    = null;
		state.lastPayload = null;
		els.typesWrap.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.loading }</p>`;
		els.preview.innerHTML   = `<p class="tsg-empty">${ TSG_ADMIN.i18n.loading }</p>`;
		els.shortcodeBox.hidden = true;

		try {
			const res = await fetch( withProject( `${ TSG_ADMIN.restUrl }/views/${ encodeURIComponent( state.viewId ) }` ), {
				headers:     { 'X-WP-Nonce': TSG_ADMIN.nonce },
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

	// ------------------------------------------------------------------
	// Chart render + shortcode
	// ------------------------------------------------------------------

	async function onSelectChart( key, btn ) {
		$$( '.tsg-type-card', els.typesWrap ).forEach( ( el ) => el.classList.remove( 'is-active' ) );
		btn.classList.add( 'is-active' );
		state.chartKey = key;

		els.preview.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.loading }</p>`;

		try {
			const url = withProject( `${ TSG_ADMIN.restUrl }/render?view=${ encodeURIComponent( state.viewId ) }&type=${ encodeURIComponent( key ) }` );
			const res = await fetch( url, {
				headers:     { 'X-WP-Nonce': TSG_ADMIN.nonce },
				credentials: 'same-origin',
			} );
			if ( ! res.ok ) {
				throw new Error( `HTTP ${ res.status }` );
			}
			state.lastPayload = await res.json();
			renderPreview( state.lastPayload );
			renderShortcode();
		} catch ( err ) {
			console.error( '[TSG]', err );
			els.preview.innerHTML = `<p class="tsg-empty">${ TSG_ADMIN.i18n.noCompatible }</p>`;
		}
	}

	/**
	 * Build a full frontend-style figure inside the preview panel so the
	 * admin sees exactly what the shortcode will produce — toolbar, legend
	 * strip, modal, everything.
	 */
	function renderPreview( payload ) {
		const opts         = state.options;
		const containerId  = 'tsg-preview-canvas';
		const figureId     = 'tsg-preview-figure';
		const actions      = opts.actions.map( ( a ) => toolbarButtonHtml( a ) ).join( '' );
		const showToolbar  = opts.toolbar && opts.actions.length > 0;
		els.preview.innerHTML = `
			<figure id="${ figureId }" class="tsg-figure tsg-preview__figure"
					data-view="${ escapeAttr( state.viewId ) }"
					data-type="${ escapeAttr( state.chartKey ) }">
				<figcaption class="tsg-figure__title">${ escapeHtml( state.view.name || '' ) }</figcaption>
				${ showToolbar ? `<div class="tsg-toolbar">${ actions }</div>` : '' }
				<div id="${ containerId }" class="tsg-chart tsg-preview__canvas"
					 style="height: 440px; min-height: 440px;"></div>
			</figure>
		`;

		const rendererOpts = {
			legend: opts.legend,
			legendStyle: opts.legend_style || 'text',
			xTitle: opts.x_title || '',
			yTitle: opts.y_title || '',
		};
		window.TSGRenderer.render( containerId, payload, rendererOpts );

		// Populate the chart-type selector (if rendered) with the compatible
		// types and wire the live swap.
		const sel = $( '[data-tsg-type-selector="1"]', els.preview );
		if ( sel ) {
			sel.innerHTML = state.compatible.map( ( c ) => {
				const selAttr = c.key === state.chartKey ? ' selected' : '';
				return `<option value="${ escapeAttr( c.key ) }"${ selAttr }>${ escapeHtml( c.label ) }</option>`;
			} ).join( '' );
			sel.addEventListener( 'change', ( ev ) => onPreviewTypeChange( ev.target.value ) );
		}
	}

	async function onPreviewTypeChange( newType ) {
		if ( ! newType || newType === state.chartKey ) {
			return;
		}
		// Sync the selection with Panel 2's cards so the admin sees
		// consistent active state.
		const card = $( `.tsg-type-card[data-type-key="${ newType }"]`, els.typesWrap );
		if ( card ) {
			$$( '.tsg-type-card', els.typesWrap ).forEach( ( el ) => el.classList.remove( 'is-active' ) );
			card.classList.add( 'is-active' );
		}
		state.chartKey = newType;

		try {
			const url = withProject( `${ TSG_ADMIN.restUrl }/render?view=${ encodeURIComponent( state.viewId ) }&type=${ encodeURIComponent( newType ) }` );
			const res = await fetch( url, {
				headers:     { 'X-WP-Nonce': TSG_ADMIN.nonce },
				credentials: 'same-origin',
			} );
			if ( ! res.ok ) {
				throw new Error( `HTTP ${ res.status }` );
			}
			state.lastPayload = await res.json();
			renderPreview( state.lastPayload );
			renderShortcode();
		} catch ( err ) {
			console.error( '[TSG] preview swap error', err );
		}
	}

	function toolbarButtonHtml( action ) {
		if ( action === 'cambiar' ) {
			return `
				<label class="tsg-action tsg-action--select" title="${ escapeHtml( TSG_ADMIN.i18n.changeChart || 'Cambiar tipo' ) }">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<span class="tsg-action__label">${ escapeHtml( TSG_ADMIN.i18n.typeLabel || 'Tipo' ) }</span>
					<select class="tsg-action__select" data-tsg-type-selector="1"></select>
				</label>
			`;
		}
		const meta = ACTION_META[ action ];
		if ( ! meta ) {
			return '';
		}
		return `
			<button type="button" class="tsg-action" disabled title="${ escapeHtml( meta.label ) }">
				<span class="dashicons dashicons-${ meta.icon }" aria-hidden="true"></span>
				<span class="tsg-action__label">${ escapeHtml( meta.label ) }</span>
			</button>
		`;
	}

	const ACTION_META = {
		detalle:   { icon: 'info-outline',  label: 'Detalle' },
		compartir: { icon: 'share',         label: 'Compartir' },
		datos:     { icon: 'editor-table',  label: 'Datos' },
		imagen:    { icon: 'format-image',  label: 'Imagen' },
		descarga:  { icon: 'download',      label: 'Descarga' },
	};

	function renderIconLegend( container, payload ) {
		if ( ! container ) {
			return;
		}
		const palette = [
			'#2563eb', '#0ea5e9', '#14b8a6', '#22c55e',
			'#eab308', '#f97316', '#ef4444', '#a855f7',
			'#ec4899', '#6366f1',
		];
		const items = computeLegendItems( payload );
		container.innerHTML = items
			.map( ( it, i ) => `<span class="tsg-legend__item" title="${ escapeHtml( it.label ) }">
				<span class="tsg-legend__swatch" style="background:${ palette[ i % palette.length ] };"></span>
			</span>` )
			.join( '' );
	}

	function computeLegendItems( payload ) {
		const { chart, view } = payload;
		const dims     = view.dimensions || [];
		const measures = view.measures || [];

		// Match the renderer's zero-pruning.
		const data = ( window.TSGRenderer && window.TSGRenderer.filterMeaningful )
			? window.TSGRenderer.filterMeaningful( payload.data, chart.key, measures )
			: ( payload.data || [] );

		switch ( chart.key ) {
			case 'stacked_bar':
			case 'stacked_area': {
				const stackable = measures.filter( ( m ) => ! /^(total|pct_|participacion|cobertura)|(_pct|_total)$/i.test( m ) );
				const ms = stackable.length >= 2 ? stackable : measures.slice( 0, 3 );
				return ms.map( ( m ) => ( { label: humanize( m ) } ) );
			}
			case 'line':
			case 'area':
				return measures.map( ( m ) => ( { label: humanize( m ) } ) );
			default: {
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
		}
	}

	function humanize( key ) {
		return String( key || '' )
			.replace( /[_\-]+/g, ' ' )
			.replace( /\s+/g, ' ' )
			.trim()
			.replace( /^./, ( c ) => c.toUpperCase() );
	}

	function renderShortcode() {
		const opts  = state.options;
		const parts = [
			`view="${ state.viewId }"`,
			`type="${ state.chartKey }"`,
		];
		// Only emit project if it's not the default (nacion).
		if ( state.project && state.project !== 'nacion' ) {
			parts.push( `project="${ state.project }"` );
		}
		parts.push( `height="420"` );
		parts.push( `title="${ String( state.view.name || '' ).replace( /"/g, "'" ) }"` );
		// Only emit options that differ from the default to keep the
		// shortcode short.
		if ( opts.legend === false ) {
			parts.push( 'legend="false"' );
		}
		if ( opts.legend && opts.legend_style !== 'text' ) {
			parts.push( `legend_style="${ opts.legend_style }"` );
		}
		if ( opts.toolbar === false ) {
			parts.push( 'toolbar="false"' );
		}
		if ( opts.toolbar ) {
			const sameAsDefault = opts.actions.length === DEFAULT_ACTIONS.length &&
				opts.actions.every( ( a, i ) => a === DEFAULT_ACTIONS[ i ] );
			if ( ! sameAsDefault && opts.actions.length > 0 ) {
				parts.push( `actions="${ opts.actions.join( ',' ) }"` );
			}
		}
		if ( opts.x_title ) {
			parts.push( `x_title="${ String( opts.x_title ).replace( /"/g, "'" ) }"` );
		}
		if ( opts.y_title ) {
			parts.push( `y_title="${ String( opts.y_title ).replace( /"/g, "'" ) }"` );
		}
		els.shortcodeInput.value = `[tsg_grafico ${ parts.join( ' ' ) }]`;
		els.shortcodeBox.hidden  = false;
	}

	// ------------------------------------------------------------------
	// Clipboard
	// ------------------------------------------------------------------

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

	// ------------------------------------------------------------------
	// Utilities
	// ------------------------------------------------------------------

	function escapeHtml( str ) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}
	function escapeAttr( str ) {
		return String( str == null ? '' : str ).replace( /[^a-z0-9\-_]/gi, '' );
	}
} )();
