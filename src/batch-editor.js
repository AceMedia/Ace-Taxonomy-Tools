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
	walk: false,
	walkIndex: 0,
};

const el = ( tag, attrs = {}, children = [] ) => {
	const node = document.createElement( tag );
	Object.entries( attrs ).forEach( ( [ k, v ] ) => {
		if ( k === 'class' ) node.className = v;
		else if ( k.startsWith( 'on' ) ) node.addEventListener( k.slice( 2 ), v );
		else if ( v !== null && v !== undefined ) node.setAttribute( k, v );
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

async function loadFields() {
	if ( ! state.taxonomy ) return;
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
	if ( def.type === 'boolean' ) {
		return el( 'input', { type: 'checkbox', ...common, ...( value ? { checked: '' } : {} ) } );
	}
	if ( def.options ) {
		const select = el( 'select', common, Object.entries( def.options ).map( ( [ v, label ] ) => el( 'option', { value: v, ...( String( value ) === String( v ) ? { selected: '' } : {} ) }, label ) ) );
		return select;
	}
	if ( def.type === 'text' ) {
		return el( 'textarea', { rows: 2, ...common }, value ?? '' );
	}
	if ( def.type === 'integer' || def.type === 'number' ) {
		return el( 'input', { type: 'number', value: value ?? '', ...common } );
	}
	if ( /colou?r/i.test( key ) && /^#?[0-9a-f]{6}$/i.test( value || '' ) ) {
		return el( 'input', { type: 'color', value: value.startsWith( '#' ) ? value : `#${ value }`, ...common } );
	}
	return el( 'input', { type: 'text', value: value ?? '', ...common } );
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
		toolbar.append( el( 'input', { type: 'number', placeholder: 'Parent id (-1 = all, 0 = top level)', value: state.parent, onchange: async ( e ) => { state.parent = parseInt( e.target.value, 10 ); state.page = 1; await loadTerms(); render(); } } ) );
	}
	root.append( toolbar );
	if ( ! state.taxonomy ) return;

	// Field picker
	root.append( el( 'div', { class: 'ace-tax-fields' }, Object.entries( state.fields ).map( ( [ key, def ] ) =>
		el( 'label', {}, [ el( 'input', { type: 'checkbox', value: key, ...( state.chosen.includes( key ) ? { checked: '' } : {} ), onchange: ( e ) => { state.chosen = e.target.checked ? [ ...state.chosen, key ] : state.chosen.filter( ( k ) => k !== key ); persist(); render(); } } ), ` ${ def.label } `, el( 'code', {}, key ) ] )
	) ) );

	// Bulk bar
	const bulkField = el( 'select', {}, state.chosen.map( ( k ) => el( 'option', { value: k }, state.fields[ k ].label ) ) );
	const bulkValue = el( 'input', { type: 'text', placeholder: 'Value (1 / empty for booleans)' } );
	const bulkBtn = el( 'button', { class: 'button button-primary', onclick: () => {
		const ids = [ ...root.querySelectorAll( '.ace-tax-pick:checked' ) ].map( ( c ) => parseInt( c.value, 10 ) );
		if ( ! ids.length || ! bulkField.value ) return;
		if ( ! window.confirm( CFG.i18n.apply.replace( '%d', ids.length ) + '?' ) ) return;
		bulkApply( ids, bulkField.value, bulkValue.value );
	} }, 'Apply to ticked' );
	root.append( el( 'div', { class: 'ace-tax-bulk' }, [ el( 'label', {}, [ el( 'input', { type: 'checkbox', onchange: ( e ) => root.querySelectorAll( '.ace-tax-pick' ).forEach( ( c ) => { c.checked = e.target.checked; } ) } ), ' All' ] ), bulkField, bulkValue, bulkBtn, el( 'span', { class: 'ace-tax-bulk-status' } ) ] ) );

	// Table or walk-through
	const rows = state.walk ? state.terms.slice( state.walkIndex, state.walkIndex + 1 ) : state.terms;
	const table = el( 'table', { class: 'widefat striped ace-tax-table' }, [
		el( 'thead', {}, el( 'tr', {}, [ el( 'th', {} ), el( 'th', {}, 'ID' ), ...state.chosen.map( ( k ) => el( 'th', {}, state.fields[ k ].label ) ), el( 'th', {}, 'Posts' ), el( 'th', {}, 'Status' ) ] ) ),
		el( 'tbody', {}, rows.map( ( term ) => el( 'tr', { 'data-row': term.id }, [
			el( 'td', {}, el( 'input', { type: 'checkbox', class: 'ace-tax-pick', value: term.id } ) ),
			el( 'td', {}, el( 'a', { href: term.link, target: '_blank', rel: 'noopener' }, String( term.id ) ) ),
			...state.chosen.map( ( k ) => el( 'td', {}, fieldInput( term, k, ( e ) => queueSave( e.target ) ) ) ),
			el( 'td', {}, String( term.count ) ),
			el( 'td', { class: 'ace-tax-status' } ),
		] ) ) ),
	] );
	root.append( table );

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
