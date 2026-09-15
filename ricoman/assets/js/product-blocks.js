/**
 * Ricoman product-section blocks (editor side).
 *
 * Each section is a dynamic block rendered server-side, so the editor canvas
 * shows a live preview of exactly how that part of the product page looks. The
 * sections stack like any blocks, so the ➕ inserter between them lets an admin
 * drop their own patterns wherever they like. No build step: this uses the
 * WordPress global `wp.*` packages directly.
 */
( function ( blocks, element, ssr, blockEditor, data, i18n ) {
	if ( ! blocks || ! ssr ) {
		return;
	}
	var el  = element.createElement;
	var __  = i18n ? i18n.__ : function ( s ) { return s; };
	var ServerSideRender = ssr['default'] ? ssr['default'] : ssr;

	var sections = [
		[ 'hero', __( 'Product: Hero', 'ricoman' ) ],
		[ 'specs', __( 'Product: Specification & details', 'ricoman' ) ],
		[ 'configure', __( 'Product: Configure & order codes', 'ricoman' ) ],
		[ 'accessories', __( 'Product: Accessories', 'ricoman' ) ],
		[ 'related', __( 'Product: You may also like', 'ricoman' ) ],
		[ 'cta', __( 'Product: Specify call-to-action', 'ricoman' ) ]
	];

	sections.forEach( function ( s ) {
		var name = 'ricoman/product-' + s[0];
		blocks.registerBlockType( name, {
			apiVersion: 2,
			title: s[1],
			description: __( 'A live-rendered section of the product page.', 'ricoman' ),
			icon: 'layout',
			category: 'ricoman-product',
			supports: { html: false, reusable: false, multiple: false },
			edit: function ( props ) {
				var postId = data && data.select( 'core/editor' )
					? data.select( 'core/editor' ).getCurrentPostId()
					: 0;
				var wrap = blockEditor && blockEditor.useBlockProps
					? blockEditor.useBlockProps()
					: {};
				return el(
					'div',
					wrap,
					el( ServerSideRender, {
						block: name,
						attributes: props.attributes,
						urlQueryArgs: { post_id: postId }
					} )
				);
			},
			save: function () {
				return null;
			}
		} );
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.serverSideRender,
	window.wp.blockEditor,
	window.wp.data,
	window.wp.i18n
);
