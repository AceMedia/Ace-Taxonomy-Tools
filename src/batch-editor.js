/**
 * Batch term editor. Vanilla JS over the plugin's REST routes.
 *
 * Flow: taxonomy -> (parent / search) -> field picker -> inline table.
 * Each row saves independently on change (debounced); a bulk bar applies one
 * field to every ticked row. Walk-through mode steps one term at a time.
 *
 * @package Ace_Taxonomy_Tools
 */

const CFG = window.ace_taxonomy_tools_batch || {};
const STORAGE = 'ace_tax_batch_state';

const state = {
	taxonomy: '',
	parent: -1,
	search: '',
	page: 1,
	totalPages: 1,
	fields: {},
	chosen: [],
	terms: [],
	sort: { key: '', dir: 1 },
	walk: false,
	walkIndex: 0,
	parents: [],
};

const el = ( tag, attrs = {}, children = [] ) => {
	const node = document.createElement( tag );
	Object.entries( attrs ).forEach( ( [ k, v ] ) => {
		if ( k === 'class' ) node.className = v;
		else if ( k.startsWith( 'on' ) ) node.addEventListener( k.slice( 2 ), v );
		else if ( v !== null && v !== undefined && v !== false ) node.setAttribute( k, v === true ? '' : v );
	} );
	( Array.isArray( children ) ? children : [ children ] ).forEach( ( c ) => {
		if ( c === null || c === undefined ) return;
		node.append( typeof c === 'string' ? document.createTextNode( c ) : c );
	} );
	return node;
};

async function api( path, opts = {} ) {
	const res = await fetch( `${ CFG.rest_url }${ path }`, {
		credentials: 'same-origin',
		headers: { 'X-WP-Nonce': CFG.nonce, 'Content-Type': 'application/json' },
		...opts,
	} );
	const body = await res.json();
	if ( ! res.ok ) throw new Error( body?.message || res.statusText );
	return { body, headers: res.headers };
}

function persist() {
	try {
		localStorage.setItem( STORAGE, JSON.stringify( { taxonomy: state.taxonomy, chosen: state.chosen, walk: state.walk } ) );
	} catch ( e ) {}
}

function restore() {
	try {
		Object.assign( state, JSON.parse( localStorage.getItem( STORAGE ) || '{}' ) );
	} catch ( e ) {}
}

async function loadParents() {
	state.parents = [];
	const tax = ( CFG.taxonomies || [] ).find( ( t ) => t.name === state.taxonomy );
	if ( ! tax?.hierarchical ) return;
	const { body } = await api( `/terms?taxonomy=${ state.taxonomy }&parent=0&per_page=500` );
	state.parents = body.filter( ( t ) => t.children > 0 ).map( ( t ) => ( { id: t.id, name: t.values.name } ) );
}

async function loadFields() {
	if ( ! state.taxonomy ) return;
	await loadParents();
	const { body } = await api( `/fields?taxonomy=${ state.taxonomy }` );
	state.fields = body.fields;
	state.chosen = state.chosen.filter( ( k ) => body.fields[ k ] );
	if ( ! state.chosen.length ) state.chosen = [ 'name' ];
}

async function loadTerms() {
	if ( ! state.taxonomy ) return;
	const q = new URLSearchParams( {
		taxonomy: state.taxonomy,
		parent: state.parent,
		search: state.search,
		page: state.page,
		per_page: CFG.per_page || 50,
	} );
	const { body, headers } = await api( `/terms?${ q }` );
	state.terms = body;
	state.totalPages = parseInt( headers.get( 'X-WP-TotalPages' ) || '1', 10 );
	state.walkIndex = Math.min( state.walkIndex, Math.max( 0, body.length - 1 ) );
}

function fieldInput( term, key, onChange ) {
	const def = state.fields[ key ];
	const value = term.values[ key ];
	const common = { 'data-term': term.id, 'data-field': key, onchange: onChange };
	switch ( def.input ) {
		case 'checkbox':
			return el( 'input', { type: 'checkbox', ...common, checked: !! value } );
		case 'select':
			return el( 'select', common, [ el( 'option', { value: '' }, '—' ), ...Object.entries( def.options || {} ).map( ( [ v, label ] ) => el( 'option', { value: v, selected: String( value ) === String( v ) }, label ) ) ] );
		case 'textarea':
			return el( 'textarea', { rows: 2, ...common }, value ?? '' );
		case 'number':
			return el( 'input', { type: 'number', value: value ?? '', ...common } );
		case 'date':
			return el( 'input', { type: /_at$/.test( key ) ? 'datetime-local' : 'date', value: value ?? '', ...common } );
		case 'url':
			return el( 'input', { type: 'url', value: value ?? '', ...common } );
		case 'color': {
			// Always a colour picker. No value = hatched swatch + "none"; the × clears it.
			const hex = /^#?[0-9a-f]{6}$/i.test( value || '' ) ? ( value.startsWith( '#' ) ? value : `#${ value }` ) : '';
			const hidden = el( 'input', { type: 'hidden', value: value ?? '', ...common } );
			const label = el( 'span', { class: 'ace-tax-color__hex' }, hex || 'none' );
			const pick = el( 'input', { type: 'color', class: 'ace-tax-color__pick' + ( hex ? '' : ' is-empty' ), value: hex || '#ffffff', title: hex || 'No colour set', oninput: ( e ) => { hidden.value = e.target.value; label.textContent = e.target.value; e.target.classList.remove( 'is-empty' ); queueSave( hidden ); } } );
			const clear = el( 'button', { type: 'button', class: 'ace-tax-color__clear', 'aria-label': 'Clear colour', title: 'Clear colour', hidden: ! hex, onclick: () => { hidden.value = ''; label.textContent = 'none'; pick.value = '#ffffff'; pick.classList.add( 'is-empty' ); clear.hidden = true; queueSave( hidden ); } }, '×' );
			pick.addEventListener( 'input', () => { clear.hidden = false; } );
			return el( 'span', { class: 'ace-tax-color' }, [ pick, label, clear, hidden ] );
		}
		case 'media': {
			const hidden = el( 'input', { type: 'hidden', value: value ?? '', ...common } );
			const preview = el( 'span', { class: 'ace-tax-media__preview' }, value ? `#${ value }` : '' );
			const pickBtn = el( 'button', { type: 'button', class: 'button button-small', onclick: () => {
				if ( ! window.wp?.media ) return;
				const frame = window.wp.media( { multiple: false } );
				frame.on( 'select', () => {
					const att = frame.state().get( 'selection' ).first().toJSON();
					hidden.value = att.id; preview.textContent = att.filename || `#${ att.id }`; queueSave( hidden );
				} );
				frame.open();
			} }, value ? 'Change' : 'Choose' );
			const clearBtn = el( 'button', { type: 'button', class: 'button-link-delete', onclick: () => { hidden.value = ''; preview.textContent = ''; queueSave( hidden ); } }, '×' );
			return el( 'span', { class: 'ace-tax-media' }, [ hidden, preview, pickBtn, clearBtn ] );
		}
		default:
			return el( 'input', { type: 'text', value: value ?? '', ...common } );
	}
}

/**
 * Keyboard model, from whichever cell has focus:
 *   Enter          save this cell, move to the same field on the next row
 *   Shift+Enter    save, move to the same field on the previous row
 *   Ctrl/Cmd+Enter save and stay
 *   ↑ / ↓          same field, previous / next row (no save until you leave)
 *   Tab / Shift+Tab next / previous field (browser default)
 *   Ctrl/Cmd+↓ / ↑ last / first row of the page
 *   Alt+→ / Alt+←  next / previous page
 *   Esc            put the cell back to its loaded value
 * Selects and checkboxes keep their native keys; the row shortcuts still apply.
 */
const KEYS = [
	[ 'Enter', 'save, next row' ], [ 'Shift+Enter', 'save, previous row' ], [ 'Ctrl+Enter', 'save, stay' ],
	[ '↑ ↓', 'same field, row up/down' ], [ 'Tab', 'next field' ], [ 'Ctrl+↑ ↓', 'first / last row' ],
	[ 'Alt+← →', 'previous / next page' ], [ 'Esc', 'undo cell' ],
];

function cellOf( node ) {
	return node && node.closest ? node.closest( '[data-field]' ) || node.closest( 'td' )?.querySelector( '[data-field]' ) : null;
}

function focusCell( row, field ) {
	if ( ! row ) return false;
	const cell = row.querySelector( `[data-field="${ field }"]` );
	const target = cell ? ( cell.type === 'hidden' ? cell.parentElement.querySelector( 'input:not([type=hidden]),button' ) : cell ) : null;
	if ( ! target ) return false;
	target.focus();
	if ( target.select && target.type !== 'color' ) target.select();
	return true;
}

function keyNav( e ) {
	const input = cellOf( e.target );
	if ( ! input ) return;
	const row = input.closest( 'tr' );
	const field = input.dataset.field;
	const mod = e.ctrlKey || e.metaKey;
	const inText = e.target.tagName === 'TEXTAREA';

	if ( e.key === 'Enter' && ! ( inText && ! mod && ! e.shiftKey ) ) {
		e.preventDefault();
		queueSave( input );
		if ( mod ) return;
		if ( ! focusCell( e.shiftKey ? row.previousElementSibling : row.nextElementSibling, field ) && ! e.shiftKey ) pageStep( 1, field );
		return;
	}
	if ( ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) && ! inText && e.target.tagName !== 'SELECT' ) {
		e.preventDefault();
		const body = row.parentElement;
		const target = mod ? ( e.key === 'ArrowDown' ? body.lastElementChild : body.firstElementChild ) : ( e.key === 'ArrowDown' ? row.nextElementSibling : row.previousElementSibling );
		focusCell( target, field );
		return;
	}
	if ( e.altKey && ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) ) {
		e.preventDefault();
		pageStep( e.key === 'ArrowRight' ? 1 : -1, field );
		return;
	}
	if ( e.key === 'Escape' ) {
		const term = state.terms.find( ( t ) => t.id === parseInt( input.dataset.term, 10 ) );
		if ( term ) {
			const v = term.values[ field ];
			if ( input.type === 'checkbox' ) input.checked = !! v; else input.value = v ?? '';
			if ( input.type === 'hidden' ) { render(); focusCell( document.querySelector( `[data-row="${ term.id }"]` ), field ); }
			whereAmI( input );
		}
	}
}

async function pageStep( dir, field ) {
	if ( state.walk ) {
		const next = state.walkIndex + dir;
		if ( next >= 0 && next < state.terms.length ) { state.walkIndex = next; render(); focusCell( document.querySelector( '.ace-tax-table tbody tr' ), field ); return; }
	}
	const page = state.page + dir;
	if ( page < 1 || page > state.totalPages ) return;
	state.page = page; state.walkIndex = dir > 0 ? 0 : Math.max( 0, state.terms.length - 1 );
	await loadTerms(); render();
	const rows = document.querySelectorAll( '.ace-tax-table tbody tr' );
	focusCell( dir > 0 ? rows[ 0 ] : rows[ rows.length - 1 ], field );
}

function whereAmI( node ) {
	const bar = document.querySelector( '.ace-tax-where' );
	const input = cellOf( node );
	if ( ! bar ) return;
	document.querySelectorAll( '.ace-tax-table tr.is-focused' ).forEach( ( r ) => r.classList.remove( 'is-focused' ) );
	if ( ! input ) { bar.textContent = ''; return; }
	const row = input.closest( 'tr' ); row.classList.add( 'is-focused' );
	const idx = [ ...row.parentElement.children ].indexOf( row ) + 1;
	const term = state.terms.find( ( t ) => t.id === parseInt( input.dataset.term, 10 ) );
	bar.textContent = `${ term?.values?.name ?? '#' + input.dataset.term } · ${ state.fields[ input.dataset.field ]?.label ?? input.dataset.field } · row ${ idx } of ${ row.parentElement.children.length } · page ${ state.page } of ${ state.totalPages }`;
}

function readInput( input ) {
	if ( input.type === 'checkbox' ) return input.checked ? 1 : '';
	return input.value;
}

const pending = new Map();

function queueSave( input ) {
	const termId = parseInt( input.dataset.term, 10 );
	const key = input.dataset.field;
	if ( ! pending.has( termId ) ) pending.set( termId, {} );
	pending.get( termId )[ key ] = readInput( input );
	clearTimeout( pending.get( termId )._t );
	pending.get( termId )._t = setTimeout( () => flush( termId ), 600 );
}

async function flush( termId ) {
	const fields = { ...pending.get( termId ) };
	delete fields._t;
	pending.delete( termId );
	const row = document.querySelector( `[data-row="${ termId }"]` );
	const status = row?.querySelector( '.ace-tax-status' );
	if ( status ) status.textContent = '…';
	try {
		const { body } = await api( `/terms/${ termId }`, { method: 'POST', body: JSON.stringify( { taxonomy: state.taxonomy, fields } ) } );
		const term = state.terms.find( ( t ) => t.id === termId );
		if ( term ) term.values = body.term.values;
		if ( status ) status.textContent = body.changed.length ? `${ CFG.i18n.saved }: ${ body.changed.join( ', ' ) }` : CFG.i18n.skipped;
		row?.classList.toggle( 'is-saved', body.changed.length > 0 );
	} catch ( err ) {
		if ( status ) status.textContent = `${ CFG.i18n.error }: ${ err.message }`;
		row?.classList.add( 'is-error' );
	}
}

async function bulkApply( ids, field, value ) {
	const bar = document.querySelector( '.ace-tax-bulk-status' );
	if ( bar ) bar.textContent = '…';
	try {
		const { body } = await api( '/bulk', { method: 'POST', body: JSON.stringify( { taxonomy: state.taxonomy, ids, field, value } ) } );
		if ( bar ) bar.textContent = `${ CFG.i18n.saved }: ${ body.changed.length }, ${ CFG.i18n.skipped }: ${ body.skipped }, ${ CFG.i18n.error }: ${ Object.keys( body.errors ).length }`;
		await loadTerms();
		render();
	} catch ( err ) {
		if ( bar ) bar.textContent = `${ CFG.i18n.error }: ${ err.message }`;
	}
}

function render() {
	const root = document.getElementById( 'ace-tax-batch-app' );
	if ( ! root ) return;
	root.replaceChildren();

	// Toolbar
	const taxSelect = el( 'select', { onchange: async ( e ) => { state.taxonomy = e.target.value; state.parent = -1; state.page = 1; persist(); await loadFields(); await loadTerms(); render(); } },
		[ el( 'option', { value: '' }, '— taxonomy —' ), ...( CFG.taxonomies || [] ).map( ( t ) => el( 'option', { value: t.name, ...( t.name === state.taxonomy ? { selected: '' } : {} ) }, t.label ) ) ] );
	const searchInput = el( 'input', { type: 'search', placeholder: 'Search terms', value: state.search, onchange: async ( e ) => { state.search = e.target.value; state.page = 1; await loadTerms(); render(); } } );
	const walkToggle = el( 'label', { class: 'ace-tax-walk' }, [ el( 'input', { type: 'checkbox', ...( state.walk ? { checked: '' } : {} ), onchange: ( e ) => { state.walk = e.target.checked; persist(); render(); } } ), ' Walk through one at a time' ] );
	const toolbar = el( 'div', { class: 'ace-tax-toolbar' }, [ taxSelect, searchInput, walkToggle ] );

	const tax = ( CFG.taxonomies || [] ).find( ( t ) => t.name === state.taxonomy );
	if ( tax?.hierarchical ) {
		toolbar.append( el( 'select', { onchange: async ( e ) => { state.parent = parseInt( e.target.value, 10 ); state.page = 1; await loadTerms(); render(); } }, [
			el( 'option', { value: -1, selected: state.parent === -1 }, 'All terms' ),
			el( 'option', { value: 0, selected: state.parent === 0 }, 'Top level only' ),
			...state.parents.map( ( p ) => el( 'option', { value: p.id, selected: state.parent === p.id }, `Children of ${ p.name }` ) ),
		] ) );
	}
	root.append( toolbar );
	if ( ! state.taxonomy ) return;
	root.append( el( 'div', { class: 'ace-tax-keys' }, [
		el( 'span', { class: 'ace-tax-where', 'aria-live': 'polite' } ),
		el( 'span', { class: 'ace-tax-keys__list' }, KEYS.map( ( [ k, what ] ) => el( 'span', { class: 'ace-tax-keys__item' }, [ el( 'kbd', {}, k ), ` ${ what }` ] ) ) ),
	] ) );

	// Field picker
	root.append( el( 'div', { class: 'ace-tax-fields' }, Object.entries( state.fields ).map( ( [ key, def ] ) =>
		el( 'label', {}, [ el( 'input', { type: 'checkbox', value: key, ...( state.chosen.includes( key ) ? { checked: '' } : {} ), onchange: ( e ) => { state.chosen = e.target.checked ? [ ...state.chosen, key ] : state.chosen.filter( ( k ) => k !== key ); persist(); render(); } } ), ` ${ def.label } `, el( 'code', {}, key ) ] )
	) ) );

	// Bulk bar: the value input follows the chosen field's type
	const bulkValueWrap = el( 'span', { class: 'ace-tax-bulk-value' } );
	const bulkValueFor = ( k ) => {
		const def = state.fields[ k ] || {};
		if ( def.input === 'checkbox' ) return el( 'select', {}, [ el( 'option', { value: '1' }, 'Yes' ), el( 'option', { value: '' }, 'No' ) ] );
		if ( def.input === 'select' ) return el( 'select', {}, [ el( 'option', { value: '' }, '—' ), ...Object.entries( def.options || {} ).map( ( [ v, l ] ) => el( 'option', { value: v }, l ) ) ] );
		if ( def.input === 'color' ) return el( 'input', { type: 'text', placeholder: '#rrggbb' } );
		if ( def.input === 'number' || def.input === 'media' ) return el( 'input', { type: 'number', placeholder: def.input === 'media' ? 'Attachment id' : 'Value' } );
		return el( 'input', { type: 'text', placeholder: 'Value' } );
	};
	const bulkField = el( 'select', { onchange: ( e ) => bulkValueWrap.replaceChildren( bulkValueFor( e.target.value ) ) }, state.chosen.map( ( k ) => el( 'option', { value: k }, state.fields[ k ].label ) ) );
	bulkValueWrap.replaceChildren( bulkValueFor( state.chosen[ 0 ] ) );
	const bulkValue = { get value() { return bulkValueWrap.firstChild ? bulkValueWrap.firstChild.value : ''; } };
	const bulkBtn = el( 'button', { class: 'button button-primary', onclick: () => {
		const ids = [ ...root.querySelectorAll( '.ace-tax-pick:checked' ) ].map( ( c ) => parseInt( c.value, 10 ) );
		if ( ! ids.length || ! bulkField.value ) return;
		if ( ! window.confirm( CFG.i18n.apply.replace( '%d', ids.length ) + '?' ) ) return;
		bulkApply( ids, bulkField.value, bulkValue.value );
	} }, 'Apply to ticked' );
	root.append( el( 'div', { class: 'ace-tax-bulk' }, [ el( 'label', {}, [ el( 'input', { type: 'checkbox', onchange: ( e ) => root.querySelectorAll( '.ace-tax-pick' ).forEach( ( c ) => { c.checked = e.target.checked; } ) } ), ' All' ] ), bulkField, bulkValueWrap, bulkBtn, el( 'span', { class: 'ace-tax-bulk-status' } ) ] ) );

	// Table or walk-through
	let ordered = [ ...state.terms ];
	if ( state.sort.key ) {
		const k = state.sort.key;
		ordered.sort( ( a, b ) => {
			const av = k === 'count' ? a.count : a.values[ k ] ?? '';
			const bv = k === 'count' ? b.count : b.values[ k ] ?? '';
			return ( typeof av === 'number' && typeof bv === 'number' ? av - bv : String( av ).localeCompare( String( bv ), undefined, { numeric: true } ) ) * state.sort.dir;
		} );
	}
	const rows = state.walk ? ordered.slice( state.walkIndex, state.walkIndex + 1 ) : ordered;
	const th = ( k, label ) => el( 'th', { class: 'ace-tax-sort' + ( state.sort.key === k ? ' is-sorted' : '' ), title: 'Sort', onclick: () => { state.sort = state.sort.key === k ? { key: k, dir: -state.sort.dir } : { key: k, dir: 1 }; render(); } }, `${ label }${ state.sort.key === k ? ( state.sort.dir > 0 ? ' ▲' : ' ▼' ) : '' }` );
	const table = el( 'table', { class: 'widefat striped ace-tax-table' }, [
		el( 'thead', {}, el( 'tr', {}, [ el( 'th', {} ), el( 'th', {}, 'ID' ), ...state.chosen.map( ( k ) => th( k, state.fields[ k ].label ) ), th( 'count', 'Posts' ), el( 'th', {}, 'Status' ) ] ) ),
		el( 'tbody', {}, rows.map( ( term ) => el( 'tr', { 'data-row': term.id }, [
			el( 'td', {}, el( 'input', { type: 'checkbox', class: 'ace-tax-pick', value: term.id } ) ),
			el( 'td', {}, el( 'a', { href: term.link, target: '_blank', rel: 'noopener' }, String( term.id ) ) ),
			...state.chosen.map( ( k ) => el( 'td', {}, fieldInput( term, k, ( e ) => queueSave( e.target ) ) ) ),
			el( 'td', {}, String( term.count ) ),
			el( 'td', { class: 'ace-tax-status' } ),
		] ) ) ),
	] );
	root.append( table );
	table.addEventListener( 'focusin', ( e ) => whereAmI( e.target ) );
	table.addEventListener( 'keydown', keyNav );

	// Pagination / walk controls
	const nav = el( 'div', { class: 'ace-tax-nav' } );
	if ( state.walk ) {
		nav.append(
			el( 'button', { class: 'button', onclick: () => { state.walkIndex = Math.max( 0, state.walkIndex - 1 ); render(); } }, '← Previous' ),
			el( 'span', {}, ` ${ state.walkIndex + 1 } / ${ state.terms.length } (page ${ state.page } of ${ state.totalPages }) ` ),
			el( 'button', { class: 'button button-primary', onclick: async () => {
				if ( state.walkIndex + 1 < state.terms.length ) { state.walkIndex += 1; render(); return; }
				if ( state.page < state.totalPages ) { state.page += 1; state.walkIndex = 0; await loadTerms(); render(); }
			} }, 'Next →' )
		);
	} else {
		nav.append(
			el( 'button', { class: 'button', ...( state.page <= 1 ? { disabled: '' } : {} ), onclick: async () => { state.page -= 1; await loadTerms(); render(); } }, '← Previous' ),
			el( 'span', {}, ` Page ${ state.page } of ${ state.totalPages } ` ),
			el( 'button', { class: 'button', ...( state.page >= state.totalPages ? { disabled: '' } : {} ), onclick: async () => { state.page += 1; await loadTerms(); render(); } }, 'Next →' )
		);
	}
	root.append( nav );
}

document.addEventListener( 'DOMContentLoaded', async () => {
	restore();
	if ( state.taxonomy && ! ( CFG.taxonomies || [] ).some( ( t ) => t.name === state.taxonomy ) ) state.taxonomy = '';
	if ( state.taxonomy ) {
		await loadFields();
		await loadTerms();
	}
	render();
} );
