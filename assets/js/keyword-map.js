/* global cytoscape, ShynhSeoKwMap */
( function () {
	'use strict';

	var CFG = window.ShynhSeoKwMap || {};
	var cy = null;

	function el( id ) { return document.getElementById( id ); }

	function api( method, path, params ) {
		var url = CFG.restBase + path;
		if ( params ) {
			var qs = Object.keys( params )
				.filter( function ( k ) { return params[ k ] !== '' && params[ k ] != null; } )
				.map( function ( k ) { return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] ); } )
				.join( '&' );
			if ( qs ) { url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + qs; }
		}
		return fetch( url, {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': CFG.nonce, 'Content-Type': 'application/json' }
		} ).then( function ( r ) { return r.json(); } );
	}

	function nodeColor( n ) {
		if ( n.type === 'post' ) { return '#2271b1'; }
		if ( n.type === 'topic' ) { return '#8c8f94'; }
		if ( n.status === 'suggested' ) { return '#bd8600'; }
		if ( n.cannibalized ) { return '#b32d2e'; }
		return '#1a7f37';
	}

	function draw( data ) {
		var elements = [];
		( data.nodes || [] ).forEach( function ( n ) {
			elements.push( { data: {
				id: n.id, label: n.label, type: n.type, status: n.status || '',
				url: n.url || '', edit: n.edit_url || '', color: nodeColor( n ),
				size: n.type === 'topic' ? 46 : ( n.type === 'post' ? 26 : ( 18 + ( n.usage || 1 ) * 4 ) )
			} } );
		} );
		( data.edges || [] ).forEach( function ( e, i ) {
			elements.push( { data: { id: 'e' + i, source: e.source, target: e.target, rel: e.rel } } );
		} );

		if ( cy ) { cy.destroy(); }
		cy = cytoscape( {
			container: el( 'kwmap-graph' ),
			elements: elements,
			style: [
				{ selector: 'node', style: {
					'background-color': 'data(color)',
					'label': 'data(label)',
					'font-size': 9,
					'width': 'data(size)',
					'height': 'data(size)',
					'text-wrap': 'ellipsis',
					'text-max-width': 90,
					'color': '#1d2327'
				} },
				{ selector: 'node[type="topic"]', style: { 'shape': 'round-rectangle', 'font-weight': 'bold' } },
				{ selector: 'node[type="post"]', style: { 'shape': 'diamond' } },
				{ selector: 'edge', style: {
					'width': 1,
					'line-color': '#c3c4c7',
					'target-arrow-color': '#c3c4c7',
					'target-arrow-shape': 'triangle',
					'curve-style': 'bezier'
				} },
				{ selector: 'edge[rel="expands"]', style: { 'line-style': 'dashed', 'line-color': '#bd8600', 'target-arrow-color': '#bd8600' } }
			],
			layout: { name: 'cose', animate: false, nodeRepulsion: 6000, idealEdgeLength: 90, padding: 20 }
		} );

		cy.on( 'tap', 'node', function ( evt ) {
			var d = evt.target.data();
			var box = el( 'kwmap-detail' );
			box.hidden = false;
			if ( d.type === 'post' && d.edit ) {
				box.innerHTML = '<strong>' + d.label + '</strong> — <a href="' + d.edit + '">Sửa bài</a>' +
					( d.url ? ' · <a href="' + d.url + '" target="_blank">Xem</a>' : '' );
			} else if ( d.type === 'keyword' ) {
				box.innerHTML = '<strong>Từ khóa:</strong> ' + d.label +
					( d.status === 'suggested' ? ' <em>(gợi ý mở rộng)</em>' : '' ) +
					' <button class="button button-small" id="kwmap-copy">Copy</button>';
				var btn = el( 'kwmap-copy' );
				if ( btn ) { btn.addEventListener( 'click', function () { navigator.clipboard && navigator.clipboard.writeText( d.label ); btn.textContent = 'Đã copy'; } ); }
			} else {
				box.innerHTML = '<strong>Chủ đề:</strong> ' + d.label;
			}
		} );

		el( 'kwmap-status' ).textContent = ( data.stats ? ( data.stats.node_count + ' nút, ' + data.stats.edge_count + ' liên kết' ) : '' );
	}

	function reload() {
		el( 'kwmap-status' ).textContent = 'Đang tải…';
		api( 'GET', '/keyword-map', {
			taxonomy: el( 'kwmap-taxonomy' ).value,
			include_suggested: el( 'kwmap-suggested' ).checked ? 1 : '',
			min_usage: el( 'kwmap-minusage' ).value || 1,
			top: 250
		} ).then( draw ).catch( function () { el( 'kwmap-status' ).textContent = 'Lỗi tải bản đồ.'; } );
	}

	function runExpand() {
		var btn = el( 'kwmap-expand' );
		btn.disabled = true;
		el( 'kwmap-status' ).textContent = 'Đang mở rộng từ khóa…';
		api( 'POST', '/keywords/expand' ).then( function ( s ) {
			el( 'kwmap-status' ).textContent = 'Đã sinh ' + ( s.candidates || 0 ) + ' ứng viên, lưu ' + ( s.stored || 0 ) + '.';
			btn.disabled = false;
			reload();
		} ).catch( function () { el( 'kwmap-status' ).textContent = 'Lỗi khi mở rộng.'; btn.disabled = false; } );
	}

	function init() {
		if ( ! CFG.restBase || typeof cytoscape === 'undefined' ) { return; }
		var sel = el( 'kwmap-taxonomy' );
		( CFG.taxonomies || [] ).forEach( function ( t ) {
			var o = document.createElement( 'option' );
			o.value = t.name; o.textContent = t.label;
			sel.appendChild( o );
		} );
		el( 'kwmap-reload' ).addEventListener( 'click', reload );
		el( 'kwmap-expand' ).addEventListener( 'click', runExpand );
		reload();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
