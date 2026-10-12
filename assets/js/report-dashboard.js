/* global ShynhSeoReport */
( function () {
	'use strict';

	var CFG = window.ShynhSeoReport || {};
	var state = {
		posts: { page: 1 },
		keywords: { page: 1 },
		geo: { page: 1 },
		fixes: { page: 1, issue: '' },
		duplicates: { page: 1 }
	};

	function el( id ) { return document.getElementById( id ); }
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

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
		} ).then( function ( res ) {
			var total = parseInt( res.headers.get( 'X-WP-Total' ) || '0', 10 );
			var pages = parseInt( res.headers.get( 'X-WP-TotalPages' ) || '1', 10 );
			return res.json().then( function ( data ) {
				return { data: data, total: total, pages: pages, status: res.status };
			} );
		} );
	}

	function scoreClass( v ) {
		if ( v == null || v === '' ) { return 'ssr-gray'; }
		if ( v >= 70 ) { return 'ssr-green'; }
		if ( v >= 40 ) { return 'ssr-orange'; }
		return 'ssr-red';
	}
	function geoClass( v ) {
		if ( v == null || v === '' ) { return 'ssr-gray'; }
		if ( v >= 80 ) { return 'ssr-green'; }
		if ( v >= 50 ) { return 'ssr-orange'; }
		return 'ssr-red';
	}
	function pill( v, cls ) {
		return '<span class="ssr-pill ' + cls + '">' + ( v == null || v === '' ? 'N/A' : v ) + '</span>';
	}

	/* ---------------- Tabs ---------------- */
	function initTabs() {
		var tabs = document.querySelectorAll( '.ssr-tab' );
		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				tabs.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				t.classList.add( 'is-active' );
				var tab = t.getAttribute( 'data-tab' );
				document.querySelectorAll( '.ssr-panel' ).forEach( function ( p ) {
					var on = p.getAttribute( 'data-panel' ) === tab;
					p.classList.toggle( 'is-active', on );
					p.classList.toggle( 'hidden', ! on );
				} );
				if ( tab === 'posts' && ! state.posts.loaded ) { loadPosts(); }
				if ( tab === 'keywords' && ! state.keywords.loaded ) { loadKeywords(); }
				if ( tab === 'geo' && ! state.geo.loaded ) { loadGeo(); }
				if ( tab === 'fixes' && ! state.fixes.loaded ) { loadFixesSummary(); loadFixes(); }
				if ( tab === 'duplicates' && ! state.duplicates.loaded ) { loadDuplicates(); }
			} );
		} );
	}

	/* ---------------- Overview / KPIs ---------------- */
	function loadSummary( refresh ) {
		var box = el( 'ssr-kpis' );
		box.innerHTML = '<p class="ssr-loading">Đang tải…</p>';
		api( 'GET', '/report/summary', refresh ? { refresh: 1 } : null ).then( function ( r ) {
			var d = r.data || {};
			var s = d.seo_score || {};
			var g = d.geo_aeo || {};
			var byType = ( d.by_post_type || [] ).map( function ( t ) {
				return '<div class="ssr-row"><span>' + esc( t.label ) + '</span><strong>' + t.count + '</strong></div>';
			} ).join( '' );
			box.innerHTML =
				card( 'Tổng nội dung', '<div class="ssr-big">' + ( d.published_content || 0 ) + '</div>' + byType ) +
				card( 'Phân bố điểm SEO',
					row( pill( s.green, 'ssr-green' ) + ' Tốt', s.green ) +
					row( pill( s.orange, 'ssr-orange' ) + ' Khá', s.orange ) +
					row( pill( s.red, 'ssr-red' ) + ' Kém', s.red ) +
					row( pill( s.not_scored, 'ssr-gray' ) + ' Chưa chấm', s.not_scored )
				) +
				card( 'Độ phủ GEO / AEO',
					'<div class="ssr-big">' + ( g.coverage_pct || 0 ) + '%</div>' +
					row( 'Bài bật GEO', g.enabled_count || 0 ) +
					row( 'Điểm GEO TB', g.avg_score || 0 )
				) +
				card( 'Cần khắc phục',
					row( 'Thiếu focus keyword', d.missing_focus_keyword || 0 ) +
					row( 'Thiếu meta description', d.missing_meta_desc || 0 ) +
					row( 'Thiếu meta title', d.missing_meta_title || 0 ) +
					row( 'Trùng từ khóa (cannibal)', d.cannibalization_count || 0 )
				);
			if ( d.cached ) {
				box.insertAdjacentHTML( 'beforeend', '<p class="ssr-loading" style="grid-column:1/-1">Số liệu từ bộ nhớ đệm — bấm "Làm mới số liệu" để tính lại.</p>' );
			}
		} );
	}
	function card( title, body ) { return '<div class="ssr-card"><h3>' + esc( title ) + '</h3>' + body + '</div>'; }
	function row( label, val ) { return '<div class="ssr-row"><span>' + label + '</span><strong>' + ( val == null ? 0 : val ) + '</strong></div>'; }

	/* ---------------- Posts table ---------------- */
	function postFilters() {
		function band( v ) { var p = ( v || '' ).split( ':' ); return { min: p[ 0 ] || '', max: p[ 1 ] || '' }; }
		var sc = band( el( 'ssr-f-score' ).value );
		var ge = band( el( 'ssr-f-geo' ).value );
		return {
			post_type: el( 'ssr-f-posttype' ).value,
			score_min: sc.min, score_max: sc.max,
			geo_min: ge.min, geo_max: ge.max,
			missing: el( 'ssr-f-missing' ).value,
			has_faq: el( 'ssr-f-faq' ).checked ? 1 : '',
			has_schema: el( 'ssr-f-schema' ).checked ? 1 : '',
			search: el( 'ssr-f-search' ).value,
			page: state.posts.page,
			per_page: 20
		};
	}
	function loadPosts() {
		state.posts.loaded = true;
		var wrap = el( 'ssr-posts' );
		wrap.innerHTML = '<p class="ssr-loading">Đang tải…</p>';
		api( 'GET', '/report/rows', postFilters() ).then( function ( r ) {
			var rows = ( r.data || [] ).map( function ( p ) {
				return '<tr>' +
					'<td><strong>' + esc( p.title ) + '</strong><br>' +
						'<a href="' + esc( p.edit_url ) + '">Sửa</a> · <a href="' + esc( p.url ) + '" target="_blank">Xem</a></td>' +
					'<td>' + esc( p.type ) + '</td>' +
					'<td>' + ( p.focus_keyword ? esc( p.focus_keyword ) : '<span class="ssr-muted">—</span>' ) + '</td>' +
					'<td>' + pill( p.seo_score, scoreClass( p.seo_score ) ) + '</td>' +
					'<td>' + pill( p.geo_aeo_score, geoClass( p.geo_aeo_score ) ) + '</td>' +
					'<td>' + ( p.internal_links_out || 0 ) + '</td>' +
					'<td>' + ( p.has_faq ? '✔' : '' ) + ' ' + ( p.has_schema ? '◆' : '' ) + '</td>' +
				'</tr>';
			} ).join( '' );
			wrap.innerHTML =
				'<table class="ssr-table"><thead><tr>' +
				'<th>Tiêu đề</th><th>Loại</th><th>Focus KW</th><th>SEO</th><th>GEO</th><th>Link</th><th>FAQ/Schema</th>' +
				'</tr></thead><tbody>' + ( rows || '<tr><td colspan="7" class="ssr-muted">Không có bài nào.</td></tr>' ) + '</tbody></table>' +
				pager( r, state.posts, loadPosts );
		} );
	}

	/* ---------------- Keywords table ---------------- */
	function loadKeywords() {
		state.keywords.loaded = true;
		var wrap = el( 'ssr-keywords' );
		wrap.innerHTML = '<p class="ssr-loading">Đang tải…</p>';
		api( 'GET', '/report/keywords', { search: el( 'ssr-kw-search' ).value, page: state.keywords.page, per_page: 30 } ).then( function ( r ) {
			var rows = ( r.data || [] ).map( function ( k ) {
				var targets = ( k.targets || [] ).map( function ( t ) {
					return '<a href="' + esc( t.url ) + '" target="_blank">' + esc( t.title ) + '</a>';
				} ).join( ', ' );
				return '<tr>' +
					'<td><strong>' + esc( k.keyword ) + '</strong></td>' +
					'<td>' + k.usage_count + '</td>' +
					'<td>' + ( k.cannibalized ? '<span class="ssr-pill ssr-red">TRÙNG</span>' : '<span class="ssr-pill ssr-green">OK</span>' ) + '</td>' +
					'<td>' + targets + '</td>' +
				'</tr>';
			} ).join( '' );
			wrap.innerHTML =
				'<table class="ssr-table"><thead><tr><th>Từ khóa</th><th>Số bài</th><th>Cannibal</th><th>Bài đã gắn</th></tr></thead><tbody>' +
				( rows || '<tr><td colspan="4" class="ssr-muted">Chưa có từ khóa.</td></tr>' ) + '</tbody></table>' +
				pager( r, state.keywords, loadKeywords );
		} );
	}

	/* ---------------- GEO table ---------------- */
	function loadGeo() {
		state.geo.loaded = true;
		var wrap = el( 'ssr-geo' );
		wrap.innerHTML = '<p class="ssr-loading">Đang tải…</p>';
		api( 'GET', '/report/geo', { page: state.geo.page, per_page: 20 } ).then( function ( r ) {
			var rows = ( r.data || [] ).map( function ( p ) {
				var issues = ( p.issues || [] ).map( function ( i ) {
					return '<span class="ssr-issue">• ' + esc( i.message || i.key ) + '</span>';
				} ).join( '' );
				return '<tr>' +
					'<td><strong>' + esc( p.title ) + '</strong><br><a href="' + esc( p.edit_url ) + '">Sửa</a></td>' +
					'<td>' + pill( p.geo_aeo_score, geoClass( p.geo_aeo_score ) ) + '</td>' +
					'<td>' + pill( p.ai_search_score, scoreClass( p.ai_search_score ) ) + '</td>' +
					'<td>' + ( issues || '<span class="ssr-muted">—</span>' ) + '</td>' +
				'</tr>';
			} ).join( '' );
			wrap.innerHTML =
				'<table class="ssr-table"><thead><tr><th>Tiêu đề</th><th>GEO</th><th>AI Search</th><th>Cần sửa</th></tr></thead><tbody>' +
				( rows || '<tr><td colspan="4" class="ssr-muted">Chưa có bài nào bật GEO/AEO hoặc chưa chấm điểm.</td></tr>' ) + '</tbody></table>' +
				pager( r, state.geo, loadGeo );
		} );
	}

	/* ---------------- Fixes (Cần Sửa) ---------------- */
	function loadFixesSummary() {
		var box = el( 'ssr-fixes-summary' );
		box.innerHTML = '<p class="ssr-loading text-gray-400 italic px-2">Đang tải…</p>';
		api( 'GET', '/report/fixes/summary' ).then( function ( r ) {
			var top = ( r.data && r.data.top_issues ) || [];
			if ( ! top.length ) { box.innerHTML = ''; return; }
			box.innerHTML = top.slice( 0, 6 ).map( function ( t ) {
				return '<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 cursor-pointer hover:shadow-md transition-all" data-issue="' + esc( t.issue ) + '">' +
					'<div class="flex items-center justify-between"><span class="text-sm font-semibold text-gray-800">' + esc( t.issue ) + '</span>' +
					'<span class="text-lg font-bold" style="color:#D4AF37">' + t.posts + '</span></div>' +
					'<p class="text-xs text-gray-500 mt-1">' + esc( t.action_tip || t.message || '' ) + '</p></div>';
			} ).join( '' );
			box.querySelectorAll( '[data-issue]' ).forEach( function ( c ) {
				c.addEventListener( 'click', function () {
					state.fixes.issue = c.getAttribute( 'data-issue' );
					state.fixes.page = 1;
					el( 'ssr-fx-active-filter' ).textContent = 'Lọc lỗi: ' + state.fixes.issue + ' (bấm "Lọc" để bỏ)';
					loadFixes();
				} );
			} );
		} );
	}

	function loadFixes() {
		state.fixes.loaded = true;
		var wrap = el( 'ssr-fixes' );
		wrap.innerHTML = '<p class="ssr-loading text-gray-400 italic p-6">Đang tải…</p>';
		api( 'GET', '/report/fixes', {
			post_type: el( 'ssr-fx-posttype' ).value,
			issue: state.fixes.issue,
			page: state.fixes.page,
			per_page: 15
		} ).then( function ( r ) {
			var rows = ( r.data || [] ).map( function ( p ) {
				var fixes = ( p.seo_fixes || [] ).concat( p.geo_fixes || [] ).map( function ( f ) {
					return '<div class="ssr-issue text-xs text-amber-700 mt-1">• <strong>' + esc( f.key || '' ) + ':</strong> ' + esc( f.action_tip || f.message || '' ) + '</div>';
				} ).join( '' );
				return '<tr>' +
					'<td><strong>' + esc( p.title ) + '</strong><br><a href="' + esc( p.edit_url ) + '">Sửa</a> · <a href="' + esc( p.url ) + '" target="_blank">Xem</a>' + fixes + '</td>' +
					'<td>' + pill( p.seo_score, scoreClass( p.seo_score ) ) + '</td>' +
					'<td><span class="ssr-pill" style="background:#6b7280">' + ( p.impact || 0 ) + '</span></td>' +
				'</tr>';
			} ).join( '' );
			wrap.innerHTML =
				'<table class="ssr-table"><thead><tr><th>Bài viết & việc cần sửa</th><th>SEO</th><th>Tác động</th></tr></thead><tbody>' +
				( rows || '<tr><td colspan="3" class="ssr-muted">Không có bài nào cần sửa (hãy chấm điểm lại ở tab Tổng quan).</td></tr>' ) + '</tbody></table>' +
				pager( r, state.fixes, loadFixes );
		} );
	}

	/* ---------------- Duplicates (Trùng lặp) ---------------- */
	function loadDuplicates() {
		state.duplicates.loaded = true;
		var wrap = el( 'ssr-duplicates' );
		wrap.innerHTML = '<p class="ssr-loading text-gray-400 italic p-6">Đang tải… (nếu trống, bấm "Quét trùng lặp")</p>';
		api( 'GET', '/report/duplicates', { page: state.duplicates.page, per_page: 20 } ).then( function ( r ) {
			var rows = ( r.data || [] ).map( function ( p ) {
				var reasons = ( p.reasons || [] ).map( function ( x ) {
					return '<span class="ssr-pill" style="background:#b45309;margin:1px">' + esc( x.label || x.code ) + '</span>';
				} ).join( ' ' );
				return '<tr>' +
					'<td>' + pill( p.similarity, p.similarity >= 90 ? 'ssr-red' : 'ssr-orange' ) + '%</td>' +
					'<td><a href="' + esc( p.a.edit_url ) + '"><strong>' + esc( p.a.title ) + '</strong></a></td>' +
					'<td><a href="' + esc( p.b.edit_url ) + '"><strong>' + esc( p.b.title ) + '</strong></a></td>' +
					'<td>' + reasons + '</td>' +
				'</tr>';
			} ).join( '' );
			wrap.innerHTML =
				'<table class="ssr-table"><thead><tr><th>Giống</th><th>Bài A</th><th>Bài B</th><th>Lý do trùng</th></tr></thead><tbody>' +
				( rows || '<tr><td colspan="4" class="ssr-muted">Chưa có dữ liệu. Bấm "Quét trùng lặp" để phân tích.</td></tr>' ) + '</tbody></table>' +
				pager( r, state.duplicates, loadDuplicates );
		} );
	}

	function runDuplicateScan() {
		var btn = el( 'ssr-dup-scan' );
		var prog = el( 'ssr-dup-progress' );
		var bar = prog.querySelector( '.ssr-progress-bar' );
		var txt = prog.querySelector( '.ssr-progress-text' );
		btn.disabled = true;
		prog.hidden = false;

		function step( offset ) {
			api( 'POST', '/report/duplicates/scan', { offset: offset, limit: 15 } ).then( function ( r ) {
				var d = r.data || {};
				var pct = d.total ? Math.min( 100, Math.round( ( d.next_offset / d.total ) * 100 ) ) : 100;
				bar.style.width = pct + '%';
				txt.textContent = 'Đang quét ' + Math.min( d.next_offset, d.total ) + '/' + d.total + '…';
				if ( d.done ) {
					txt.textContent = 'Xong: tìm thấy ' + ( d.pairs_found || 0 ) + ' cặp trùng.';
					bar.style.width = '100%';
					btn.disabled = false;
					state.duplicates.page = 1;
					loadDuplicates();
				} else {
					step( d.next_offset );
				}
			} ).catch( function () { txt.textContent = 'Lỗi khi quét.'; btn.disabled = false; } );
		}
		step( 0 );
	}

	/* ---------------- Pager ---------------- */
	function pager( r, st, reload ) {
		if ( ! r.pages || r.pages <= 1 ) { return ''; }
		var html = '<div class="ssr-pager">';
		html += '<button class="button" ' + ( st.page <= 1 ? 'disabled' : '' ) + ' data-dir="-1">«</button>';
		html += '<span>Trang ' + st.page + ' / ' + r.pages + ' (' + r.total + ')</span>';
		html += '<button class="button" ' + ( st.page >= r.pages ? 'disabled' : '' ) + ' data-dir="1">»</button>';
		html += '</div>';
		setTimeout( function () {
			var wrap = reload === loadPosts ? el( 'ssr-posts' )
				: reload === loadKeywords ? el( 'ssr-keywords' )
				: reload === loadFixes ? el( 'ssr-fixes' )
				: reload === loadDuplicates ? el( 'ssr-duplicates' )
				: el( 'ssr-geo' );
			wrap.querySelectorAll( '.ssr-pager button[data-dir]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					st.page += parseInt( b.getAttribute( 'data-dir' ), 10 );
					if ( st.page < 1 ) { st.page = 1; }
					reload();
				} );
			} );
		}, 0 );
		return html;
	}

	/* ---------------- Rescan (chunked) ---------------- */
	function runRescan() {
		var btn = el( 'ssr-rescan' );
		var prog = el( 'ssr-rescan-progress' );
		var bar = prog.querySelector( '.ssr-progress-bar' );
		var txt = prog.querySelector( '.ssr-progress-text' );
		btn.disabled = true;
		prog.hidden = false;
		// Ensure a track element exists behind the bar.
		if ( ! prog.querySelector( '.ssr-track' ) ) {
			var track = document.createElement( 'span' );
			track.className = 'ssr-track';
			track.appendChild( bar );
			prog.insertBefore( track, txt );
		}
		var doneCount = 0;

		function step( offset ) {
			api( 'POST', '/report/rescan', { offset: offset, limit: 5, scope: 'missing' } ).then( function ( r ) {
				var d = r.data || {};
				doneCount += ( d.processed || 0 );
				var total = d.total || 0;
				var pct = total ? Math.min( 100, Math.round( ( doneCount / ( doneCount + total ) ) * 100 ) ) : 100;
				bar.style.flex = '0 0 ' + pct + '%';
				bar.style.width = pct + '%';
				txt.textContent = 'Đã chấm ' + doneCount + ' bài…';
				if ( d.done ) {
					txt.textContent = 'Hoàn tất: đã chấm ' + doneCount + ' bài.';
					bar.style.width = '100%'; bar.style.flex = '0 0 100%';
					btn.disabled = false;
					state.posts.loaded = false; state.geo.loaded = false;
					loadSummary( true );
				} else {
					step( d.next_offset || 0 );
				}
			} ).catch( function () {
				txt.textContent = 'Có lỗi khi chấm điểm. Thử lại.';
				btn.disabled = false;
			} );
		}
		step( 0 );
	}

	/* ---------------- Init ---------------- */
	function init() {
		if ( ! CFG.restBase ) { return; }
		// Populate post-type filters (Posts + Fixes tabs).
		( CFG.postTypes || [] ).forEach( function ( pt ) {
			[ 'ssr-f-posttype', 'ssr-fx-posttype' ].forEach( function ( id ) {
				var sel = el( id );
				if ( ! sel ) { return; }
				var o = document.createElement( 'option' );
				o.value = pt.name; o.textContent = pt.label;
				sel.appendChild( o );
			} );
		} );

		initTabs();
		loadSummary( false );

		el( 'ssr-refresh-summary' ).addEventListener( 'click', function () { loadSummary( true ); } );
		el( 'ssr-rescan' ).addEventListener( 'click', runRescan );
		el( 'ssr-apply' ).addEventListener( 'click', function () { state.posts.page = 1; loadPosts(); } );
		el( 'ssr-kw-apply' ).addEventListener( 'click', function () { state.keywords.page = 1; loadKeywords(); } );

		var fxApply = el( 'ssr-fx-apply' );
		if ( fxApply ) {
			fxApply.addEventListener( 'click', function () {
				state.fixes.issue = '';
				state.fixes.page = 1;
				var af = el( 'ssr-fx-active-filter' );
				if ( af ) { af.textContent = ''; }
				loadFixes();
			} );
		}
		var dupScan = el( 'ssr-dup-scan' );
		if ( dupScan ) { dupScan.addEventListener( 'click', runDuplicateScan ); }
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
