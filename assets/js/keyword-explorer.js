/* global cytoscape, ShynhSeoKwExp */
( function () {
	'use strict';

	var CFG = window.ShynhSeoKwExp || {};
	var cy = null;
	var current = null;      // current tree data
	var currentSeed = '';
	var allSeeds = [];       // full seed list (loaded once)
	var seedFilter = { search: '', status: '', source: '', topic: '', sort: 'usage' };

	function el( id ) { return document.getElementById( id ); }
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function api( method, path, params, body ) {
		var url = CFG.restBase + path;
		if ( params ) {
			var qs = Object.keys( params ).filter( function ( k ) { return params[ k ] !== '' && params[ k ] != null; } )
				.map( function ( k ) { return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] ); } ).join( '&' );
			if ( qs ) { url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + qs; }
		}
		return fetch( url, {
			method: method, credentials: 'same-origin',
			headers: { 'X-WP-Nonce': CFG.nonce, 'Content-Type': 'application/json' },
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( r ) { return r.json(); } );
	}

	function nodeColor( n ) {
		if ( n.level === 1 ) { return '#D4AF37'; }
		return n.covered ? '#1a7f37' : '#bd8600';
	}
	function intentBadge( intent ) {
		var map = { transactional: '#b45309', commercial: '#2271b1', informational: '#6b7280', question: '#7c3aed', root: '#9ca3af' };
		return '<span style="background:' + ( map[ intent ] || '#6b7280' ) + '" class="text-white text-xs px-2 py-0.5 rounded-full">' + esc( intent ) + '</span>';
	}

	/* ---------------- Seeds (load once, filter client-side) ---------------- */
	function loadSeeds() {
		var list = el( 'kwexp-seed-list' );
		list.innerHTML = '<li class="text-gray-400 italic px-2 py-1">Đang tải…</li>';
		api( 'GET', '/keywords/seeds', { per_page: 500 } ).then( function ( r ) {
			allSeeds = r.data || [];
			// Populate topic dropdown from unique topics.
			var topics = {};
			allSeeds.forEach( function ( s ) { if ( s.topic ) { topics[ s.topic ] = true; } } );
			var sel = el( 'kwexp-f-topic' );
			Object.keys( topics ).sort().forEach( function ( t ) {
				var o = document.createElement( 'option' );
				o.value = t; o.textContent = t;
				sel.appendChild( o );
			} );
			renderSeeds();
		} );
	}

	function filteredSeeds() {
		var q = seedFilter.search;
		var out = allSeeds.filter( function ( s ) {
			if ( q && ( s.keyword || '' ).toLowerCase().indexOf( q ) === -1 ) { return false; }
			if ( seedFilter.status === 'covered' && ! s.covered ) { return false; }
			if ( seedFilter.status === 'gap' && s.covered ) { return false; }
			if ( seedFilter.status === 'cannibal' && ! s.cannibalized ) { return false; }
			if ( seedFilter.source && s.source !== seedFilter.source ) { return false; }
			if ( seedFilter.topic && s.topic !== seedFilter.topic ) { return false; }
			return true;
		} );
		out.sort( function ( a, b ) {
			if ( seedFilter.sort === 'alpha' ) { return ( a.keyword || '' ).localeCompare( b.keyword || '' ); }
			if ( seedFilter.sort === 'least' ) { return ( a.usage || 0 ) - ( b.usage || 0 ); }
			return ( b.usage || 0 ) - ( a.usage || 0 );
		} );
		return out;
	}

	function renderSeeds() {
		var list = el( 'kwexp-seed-list' );
		var rows = filteredSeeds();
		el( 'kwexp-seed-count' ).textContent = rows.length + ' từ khóa';
		if ( ! rows.length ) { list.innerHTML = '<li class="text-gray-400 italic px-2 py-1">Không khớp bộ lọc.</li>'; return; }
		list.innerHTML = rows.slice( 0, 300 ).map( function ( s ) {
			var dot = s.covered ? '#1a7f37' : '#bd8600';
			var tag = s.source === 'service' ? '<span class="text-[10px] text-blue-600 bg-blue-50 px-1.5 rounded">DV</span>' : '';
			var cann = s.cannibalized ? '<span class="text-[10px] text-rose-600 bg-rose-50 px-1.5 rounded">trùng</span>' : '';
			return '<li><button type="button" class="kwexp-seed w-full text-left px-3 py-2 rounded-lg border border-transparent hover:bg-gray-50 flex items-center gap-2" data-seed="' + esc( s.keyword ) + '">' +
				'<span style="width:8px;height:8px;border-radius:50%;background:' + dot + ';display:inline-block;flex:none"></span>' +
				'<span class="flex-1 truncate">' + esc( s.keyword ) + '</span>' + tag + cann +
				'<span class="text-xs text-gray-400">' + ( s.usage || 0 ) + '</span></button></li>';
		} ).join( '' );
		list.querySelectorAll( '.kwexp-seed' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				list.querySelectorAll( '.kwexp-seed' ).forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				b.classList.add( 'is-active' );
				selectSeed( b.getAttribute( 'data-seed' ) );
			} );
		} );
	}

	/* ---------------- Expand ---------------- */
	function selectSeed( seed ) {
		currentSeed = seed;
		el( 'kwexp-current' ).textContent = seed;
		el( 'kwexp-stats' ).textContent = 'Đang mở rộng…';
		el( 'kwexp-detail' ).classList.add( 'hidden' );
		api( 'GET', '/keywords/expand-seed', { keyword: seed, depth: 4 } ).then( function ( r ) {
			current = r.data || { nodes: [], edges: [] };
			el( 'kwexp-stats' ).textContent = ( current.stats ? ( current.stats.nodes + ' từ khóa · ' + current.stats.gaps + ' cơ hội' ) : '' );
			drawGraph();
			renderTable();
		} );
	}

	function drawGraph() {
		var elements = [];
		( current.nodes || [] ).forEach( function ( n ) {
			elements.push( { data: {
				id: n.id, label: n.label, level: n.level, intent: n.intent,
				covered: n.covered ? 1 : 0, color: nodeColor( n ),
				size: n.level === 1 ? 40 : ( n.level === 2 ? 28 : ( n.level === 3 ? 20 : 16 ) )
			} } );
		} );
		( current.edges || [] ).forEach( function ( e, i ) {
			elements.push( { data: { id: 'e' + i, source: e.source, target: e.target } } );
		} );

		if ( cy ) { cy.destroy(); }
		cy = cytoscape( {
			container: el( 'kwexp-graph' ),
			elements: elements,
			style: [
				{ selector: 'node', style: {
					'background-color': 'data(color)', 'label': 'data(label)', 'font-size': 9,
					'width': 'data(size)', 'height': 'data(size)', 'text-wrap': 'ellipsis', 'text-max-width': 110, 'color': '#1d2327'
				} },
				{ selector: 'edge', style: { 'width': 1, 'line-color': '#e0e0e0', 'target-arrow-color': '#e0e0e0', 'target-arrow-shape': 'triangle', 'curve-style': 'bezier' } }
			],
			layout: { name: 'breadthfirst', directed: true, spacingFactor: 1.15, padding: 24 }
		} );
		cy.on( 'tap', 'node', function ( evt ) { showDetail( evt.target.data() ); } );
	}

	function showDetail( d ) {
		var box = el( 'kwexp-detail' );
		box.classList.remove( 'hidden' );
		box.innerHTML =
			'<div class="flex items-center gap-3 flex-wrap">' +
			'<strong class="text-gray-900">' + esc( d.label ) + '</strong>' +
			intentBadge( d.intent ) +
			( d.covered ? '<span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">Đã có bài</span>' : '<span class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">Cơ hội</span>' ) +
			'<span class="ml-auto flex gap-2">' +
			'<button type="button" id="kwexp-copy" class="bg-white text-gray-700 border border-gray-200 px-4 py-1.5 rounded-lg text-sm hover:bg-gray-50">Copy</button>' +
			( d.covered ? '' : '<button type="button" id="kwexp-draft" class="bg-gradient-to-r from-[#D4AF37] to-[#B8962C] text-white px-4 py-1.5 rounded-lg text-sm font-semibold">Tạo draft</button>' ) +
			'</span></div>';
		var copy = el( 'kwexp-copy' );
		if ( copy ) { copy.addEventListener( 'click', function () { navigator.clipboard && navigator.clipboard.writeText( d.label ); copy.textContent = 'Đã copy'; } ); }
		var draft = el( 'kwexp-draft' );
		if ( draft ) {
			draft.addEventListener( 'click', function () {
				draft.disabled = true; draft.textContent = 'Đang tạo…';
				api( 'POST', '/drafts', null, { title: d.label, focus_keyword: d.label } ).then( function ( res ) {
					if ( res && res.edit_url ) {
						draft.textContent = 'Đã tạo — Mở';
						draft.onclick = function () { window.open( res.edit_url, '_blank' ); };
						draft.disabled = false;
					} else { draft.textContent = 'Lỗi'; }
				} );
			} );
		}
	}

	/* ---------------- Grouped table ---------------- */
	function renderTable() {
		var fLevel = el( 'kwexp-f-level' ).value;
		var fIntent = el( 'kwexp-f-intent' ).value;
		var fGap = el( 'kwexp-f-gap' ).checked;
		var wrap = el( 'kwexp-table' );

		var byLevel = current.by_level || {};
		var levels = Object.keys( byLevel ).sort();
		var html = '';
		levels.forEach( function ( L ) {
			if ( fLevel && fLevel !== L ) { return; }
			var rows = byLevel[ L ].filter( function ( n ) {
				if ( fIntent && n.intent !== fIntent ) { return false; }
				if ( fGap && n.covered ) { return false; }
				return true;
			} );
			if ( ! rows.length ) { return; }
			rows.sort( function ( a, b ) { return ( b.opportunity || 0 ) - ( a.opportunity || 0 ); } );
			html += '<div class="px-4 py-2 bg-gray-50 border-b border-gray-100 text-sm font-bold text-gray-700">Level ' + esc( L ) + ' <span class="text-gray-400 font-normal">(' + rows.length + ')</span></div>';
			html += '<table class="w-full text-sm"><tbody>' + rows.map( function ( n ) {
				return '<tr class="border-b border-gray-50">' +
					'<td class="px-4 py-2">' + esc( n.label ) + '</td>' +
					'<td class="px-2 py-2">' + intentBadge( n.intent ) + '</td>' +
					'<td class="px-2 py-2">' + ( n.covered ? '<span class="text-xs text-emerald-700">● đã có</span>' : '<span class="text-xs text-amber-700">○ cơ hội</span>' ) + '</td>' +
					'<td class="px-2 py-2 text-gray-400 text-xs">op ' + ( n.opportunity || 0 ) + '</td>' +
					'<td class="px-4 py-2 text-right"><button type="button" class="kwexp-row-copy text-primary text-xs" data-kw="' + esc( n.label ) + '">Copy</button></td>' +
				'</tr>';
			} ).join( '' ) + '</tbody></table>';
		} );
		wrap.innerHTML = html || '<p class="text-gray-400 italic p-6">Không có từ khóa khớp bộ lọc.</p>';
		wrap.querySelectorAll( '.kwexp-row-copy' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { navigator.clipboard && navigator.clipboard.writeText( b.getAttribute( 'data-kw' ) ); b.textContent = 'Đã copy'; } );
		} );
	}

	/* ---------------- Init ---------------- */
	function init() {
		if ( ! CFG.restBase || typeof cytoscape === 'undefined' ) { return; }
		loadSeeds();

		// Seed search (instant, client-side).
		var s = el( 'kwexp-seed-search' );
		s.addEventListener( 'input', function () { seedFilter.search = s.value.toLowerCase(); renderSeeds(); } );

		// Status chips.
		document.querySelectorAll( '.kwexp-chip' ).forEach( function ( c ) {
			c.addEventListener( 'click', function () {
				document.querySelectorAll( '.kwexp-chip' ).forEach( function ( x ) { x.classList.remove( 'is-active' ); x.classList.remove( 'bg-gray-50' ); x.classList.add( 'bg-white' ); } );
				c.classList.add( 'is-active' ); c.classList.add( 'bg-gray-50' ); c.classList.remove( 'bg-white' );
				seedFilter.status = c.getAttribute( 'data-status' );
				renderSeeds();
			} );
		} );

		// Source / topic / sort selects.
		el( 'kwexp-f-source' ).addEventListener( 'change', function ( e ) { seedFilter.source = e.target.value; renderSeeds(); } );
		el( 'kwexp-f-topic' ).addEventListener( 'change', function ( e ) { seedFilter.topic = e.target.value; renderSeeds(); } );
		el( 'kwexp-f-sort' ).addEventListener( 'change', function ( e ) { seedFilter.sort = e.target.value; renderSeeds(); } );

		// Expansion table filters (level / intent / gap).
		[ 'kwexp-f-level', 'kwexp-f-intent' ].forEach( function ( id ) { el( id ).addEventListener( 'change', renderTable ); } );
		el( 'kwexp-f-gap' ).addEventListener( 'change', renderTable );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
