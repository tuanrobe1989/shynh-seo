/* global SwaggerUIBundle, ShynhSeoApiDocs */
( function () {
	'use strict';

	function init() {
		if ( typeof SwaggerUIBundle === 'undefined' || ! window.ShynhSeoApiDocs ) {
			return;
		}
		var mount = document.getElementById( 'shynh-seo-swagger-ui' );
		if ( ! mount ) {
			return;
		}

		SwaggerUIBundle( {
			url: ShynhSeoApiDocs.specUrl,
			dom_id: '#shynh-seo-swagger-ui',
			deepLinking: true,
			presets: [ SwaggerUIBundle.presets.apis ],
			layout: 'BaseLayout',
			tryItOutEnabled: true,
			// Attach the WordPress REST nonce so a logged-in admin can use
			// "Try it out" without pasting an API key.
			requestInterceptor: function ( req ) {
				if ( ShynhSeoApiDocs.nonce ) {
					req.headers[ 'X-WP-Nonce' ] = ShynhSeoApiDocs.nonce;
				}
				return req;
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
