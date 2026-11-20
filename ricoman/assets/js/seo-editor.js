/**
 * Ricoman — live SEO score + social preview inside the block editor.
 * Mirrors the weights in inc/seo-score.php so the editor and admin agree.
 * Renders into #ricoman_seo_panel (printed by the SEO meta box).
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.domReady ) {
		return;
	}

	function field( id ) {
		return document.getElementById( id );
	}
	function editorAttr( name ) {
		try {
			return wp.data.select( 'core/editor' ).getEditedPostAttribute( name );
		} catch ( e ) {
			return null;
		}
	}
	function editorContent() {
		try {
			return wp.data.select( 'core/editor' ).getEditedPostContent() || '';
		} catch ( e ) {
			return '';
		}
	}

	function compute() {
		var titleEl = field( 'ricoman_seo_title' );
		var descEl  = field( 'ricoman_seo_desc' );
		var focusEl = field( 'ricoman_seo_focus' );

		var title = ( titleEl && titleEl.value ) || editorAttr( 'title' ) || '';
		var desc  = ( descEl && descEl.value ) || '';
		var focus = ( ( focusEl && focusEl.value ) || '' ).trim().toLowerCase();

		var raw  = editorContent();
		var text = raw.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
		var words = text ? text.split( ' ' ).length : 0;

		var hasImg  = !! editorAttr( 'featured_media' ) || /<img|wp:image/i.test( raw );
		var hasHead = /<h[23]|wp:heading/i.test( raw );
		var hasLink = /<a\s/i.test( raw );

		var lt = title.toLowerCase(), ld = desc.toLowerCase(), lx = text.toLowerCase();
		var kw = focus.length > 0;

		var checks = [
			[ 'Title is 40–60 characters', title.length >= 40 && title.length <= 60, 12 ],
			[ 'Meta description is 120–160 characters', desc.length >= 120 && desc.length <= 160, 14 ],
			[ 'Featured / social image is set', hasImg, 10 ],
			[ 'At least 300 words of content', words >= 300, 12 ],
			[ 'Has a subheading (H2/H3)', hasHead, 8 ],
			[ 'Contains at least one link', hasLink, 6 ],
			[ 'Focus keyphrase is set', kw, 8 ]
		];
		if ( kw ) {
			checks.push( [ 'Keyphrase in the SEO title', lt.indexOf( focus ) >= 0, 12 ] );
			checks.push( [ 'Keyphrase in the meta description', ld.indexOf( focus ) >= 0, 8 ] );
			checks.push( [ 'Keyphrase used in the content', lx.indexOf( focus ) >= 0, 6 ] );
		}

		var total = 0, got = 0;
		checks.forEach( function ( c ) { total += c[ 2 ]; if ( c[ 1 ] ) { got += c[ 2 ]; } } );

		return {
			score: total ? Math.round( got / total * 100 ) : 0,
			checks: checks,
			title: title,
			desc: desc,
			hasImg: hasImg
		};
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function render() {
		var panel = field( 'ricoman_seo_panel' );
		if ( ! panel ) { return; }
		var r = compute();
		var color = r.score >= 80 ? '#008a20' : ( r.score >= 50 ? '#dba617' : '#b32d2e' );
		var label = r.score >= 80 ? 'Good' : ( r.score >= 50 ? 'OK — could improve' : 'Needs work' );

		var html = '<div style="display:flex;align-items:center;gap:10px;margin:14px 0 8px">'
			+ '<span style="font:700 22px/1 sans-serif;color:' + color + '">' + r.score
			+ '<span style="font-weight:400;font-size:12px;color:#646970">/100</span></span>'
			+ '<strong style="color:' + color + '">' + label + '</strong></div>';

		html += '<ul style="margin:0 0 12px;padding:0;list-style:none;font-size:13px">';
		r.checks.forEach( function ( c ) {
			html += '<li style="padding:3px 0;color:' + ( c[ 1 ] ? '#1d2327' : '#646970' ) + '">'
				+ ( c[ 1 ] ? '✔' : '◯' ) + ' ' + esc( c[ 0 ] ) + '</li>';
		} );
		html += '</ul>';

		// Google-style search/social preview.
		html += '<div style="border:1px solid #dcdcde;border-radius:8px;overflow:hidden;max-width:400px">'
			+ ( r.hasImg ? '<div style="background:#f0f0f1;height:84px;display:flex;align-items:center;justify-content:center;color:#a7aaad;font:12px sans-serif">[ social image ]</div>' : '' )
			+ '<div style="padding:10px 12px">'
			+ '<div style="color:#1a0dab;font:600 15px/1.3 Arial,sans-serif;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc( r.title || 'Untitled' ) + '</div>'
			+ '<div style="color:#006621;font:12px Arial,sans-serif">' + esc( location.host ) + '</div>'
			+ '<div style="color:#545454;font:13px/1.4 Arial,sans-serif">' + esc( r.desc || 'A description is generated automatically from your content.' ) + '</div>'
			+ '</div></div>';

		panel.innerHTML = html;
	}

	wp.domReady( function () {
		if ( ! field( 'ricoman_seo_panel' ) ) { return; }
		[ 'ricoman_seo_title', 'ricoman_seo_desc', 'ricoman_seo_focus' ].forEach( function ( id ) {
			var e = field( id );
			if ( e ) { e.addEventListener( 'input', render ); }
		} );
		if ( wp.data && wp.data.subscribe ) {
			var t;
			wp.data.subscribe( function () {
				clearTimeout( t );
				t = setTimeout( render, 400 );
			} );
		}
		render();
	} );
} )( window.wp || {} );
