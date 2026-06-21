<?php
/**
 * Turnkey site scaffolding.
 *
 * On first run the theme builds a complete, cross-linked demo site:
 *   - the homepage (editable native blocks) set as the static front page,
 *   - the core pages (Lighting Design, Manufacturing, About, My Project),
 *   - product categories + sample products (Flow+, Estrella, Neptune),
 *   - sample projects, with products <-> projects linked both ways,
 *   - pretty permalinks, flushed.
 * /products/ and /projects/ are the post-type archives (no pages created for
 * them). Runs once, never overwrites anything that already exists.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_loaded', 'ricoman_scaffold_site', 20 );

/** Resolve a registered pattern's markup (so pages contain real, editable blocks). */
function ricoman_pattern_content( $slug ) {
	$reg = WP_Block_Patterns_Registry::get_instance();
	if ( $reg->is_registered( $slug ) ) {
		$p = $reg->get_registered( $slug );
		if ( ! empty( $p['content'] ) ) {
			return $p['content'];
		}
	}
	return '<!-- wp:pattern {"slug":"' . esc_attr( $slug ) . '"} /-->';
}

/** Create a page from a pattern if it doesn't already exist (never overwrites). */
function ricoman_upsert_page( $title, $slug, $pattern_slug, $template = '' ) {
	$existing = get_page_by_path( $slug );
	if ( $existing && 'page' === $existing->post_type ) {
		return (int) $existing->ID; // leave existing content untouched.
	}
	return ricoman_make_post( 'page', $title, $slug, ricoman_pattern_content( $pattern_slug ), $template );
}

/** Create a post once (idempotent by slug + type). Returns the ID or 0. */
function ricoman_make_post( $type, $title, $slug, $content, $template = '' ) {
	$existing = get_page_by_path( $slug, OBJECT, $type );
	if ( $existing ) {
		return (int) $existing->ID;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		if ( $template ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}
		return (int) $id;
	}
	return 0;
}

/** Copy a bundled theme image into the media library and set it as the post's featured image. */
function ricoman_set_featured_from_theme( $post_id, $file ) {
	if ( ! $post_id || has_post_thumbnail( $post_id ) ) {
		return;
	}
	$src = get_theme_file_path( 'assets/images/' . $file );
	if ( ! file_exists( $src ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$upload = wp_upload_bits( $file, null, file_get_contents( $src ) );
	if ( ! empty( $upload['error'] ) ) {
		return;
	}
	$type    = wp_check_filetype( $upload['file'] );
	$attach  = array(
		'post_mime_type' => $type['type'],
		'post_title'     => sanitize_file_name( pathinfo( $file, PATHINFO_FILENAME ) ),
		'post_status'    => 'inherit',
	);
	$attach_id = wp_insert_attachment( $attach, $upload['file'], $post_id );
	if ( $attach_id && ! is_wp_error( $attach_id ) ) {
		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );
		set_post_thumbnail( $post_id, $attach_id );
	}
}

function ricoman_scaffold_site() {
	// Run the demo scaffold ONCE, ever. After that the installer NEVER touches
	// existing content — pages, products, images and any edits are safe across
	// every theme update. Theme *code* (patterns, blocks, CSS, the Product
	// Builder) still updates freely; only stored content is left alone.
	if ( get_option( 'ricoman_scaffolded' ) ) {
		return;
	}
	// Migrate older installs: if a previous version already scaffolded, mark it
	// done and stop — so we never re-seed over content that already exists.
	foreach ( range( 14, 40 ) as $v ) {
		if ( get_option( 'ricoman_scaffold_v' . $v ) ) {
			update_option( 'ricoman_scaffolded', 1 );
			return;
		}
	}

	$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

	// Pretty permalinks so every URL resolves.
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// Remove earlier auto-created Products/Projects PAGES — they clash with the
	// product/project post-type archives that own /products/ and /projects/.
	foreach ( array( 'products', 'projects' ) as $old ) {
		$page = get_page_by_path( $old );
		if ( $page && 'page' === $page->post_type ) {
			wp_trash_post( $page->ID );
		}
	}

	// ---- Pages: CREATE ONLY (never overwrite an existing page) ----
	$home_page = get_page_by_path( 'home' );
	if ( $home_page && 'page' === $home_page->post_type ) {
		$home_id = $home_page->ID;
	} else {
		$home_content = function_exists( 'ricoman_home_blocks' ) ? ricoman_home_blocks() : ricoman_pattern_content( 'ricoman/home' );
		$home_id      = ricoman_make_post( 'page', 'Home', 'home', $home_content );
	}
	if ( $home_id && ! get_option( 'page_on_front' ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}

	$native_pages = array(
		'lighting-design' => array( 'Lighting Design', 'ricoman_lighting_blocks' ),
		'manufacturing'   => array( 'Manufacturing', 'ricoman_manufacturing_blocks' ),
		'about'           => array( 'About', 'ricoman_about_blocks' ),
		'downloads'       => array( 'Downloads', 'ricoman_downloads_blocks' ),
		'customisation'   => array( 'Customisation', 'ricoman_customisation_blocks' ),
		'contact'         => array( 'Contact', 'ricoman_contact_blocks' ),
		// Feature / enhanced landing pages.
		'casambi'                 => array( 'Casambi', 'ricoman_casambi_blocks' ),
		'human-centric-lighting'  => array( 'Human Centric Lighting', 'ricoman_human_centric_blocks' ),
		'antimicrobial-protection'=> array( 'Antimicrobial Protection', 'ricoman_antimicrobial_blocks' ),
		'fire-safety'             => array( 'Fire Safety', 'ricoman_fire_safety_blocks' ),
		'sustainability'          => array( 'Sustainability', 'ricoman_sustainability_blocks' ),
		'made-in-britain'         => array( 'Made in Britain', 'ricoman_made_in_britain_blocks' ),
		'trade'                   => array( 'Trade', 'ricoman_trade_blocks' ),
		'where-to-buy'            => array( 'Where to Buy', 'ricoman_where_to_buy_blocks' ),
		'i-joist-ceilings'        => array( 'I-Joist Ceilings', 'ricoman_i_joist_ceilings_blocks' ),
		'stock-availability'      => array( 'Stock & Availability', 'ricoman_stock_availability_blocks' ),
		'uae-exports'             => array( 'UAE Exports', 'ricoman_uae_exports_blocks' ),
		'our-showroom'            => array( 'Our Showroom', 'ricoman_our_showroom_blocks' ),
		'our-services'            => array( 'Our Vision & Services', 'ricoman_our_services_blocks' ),
	);
	foreach ( $native_pages as $slug => $info ) {
		if ( get_page_by_path( $slug ) ) {
			continue; // already exists — leave the editor's content untouched.
		}
		$content = function_exists( $info[1] ) ? call_user_func( $info[1] ) : ricoman_pattern_content( 'ricoman/home' );
		ricoman_make_post( 'page', $info[0], $slug, $content, 'page-plain' );
	}

	// Secondary / legal pages — create only.
	if ( function_exists( 'ricoman_info_pages' ) ) {
		foreach ( ricoman_info_pages() as $info_slug => $info ) {
			if ( get_page_by_path( $info_slug ) ) {
				continue;
			}
			$info_content = ricoman_info_blocks( $info[0], $info[1], $info[2], $info[3] );
			$info_title   = html_entity_decode( wp_strip_all_tags( $info[1] ) );
			ricoman_make_post( 'page', $info_title, $info_slug, $info_content, 'page-plain' );
		}
	}

	// My Project — utility page (still a simple pattern).
	ricoman_upsert_page( 'My Project', 'my-project', 'ricoman/page-my-project', 'page-plain' );

	// Flow+ Designer — full-screen embedded customer tool.
	$fd = ricoman_make_post( 'page', 'Flow+ Designer', 'flow-designer', '<!-- wp:paragraph --><p>Flow+ Designer.</p><!-- /wp:paragraph -->', 'page-flow-designer' );
	if ( $fd ) {
		update_post_meta( $fd, '_wp_page_template', 'page-flow-designer' );
	}

	// ---- Product categories ----
	foreach ( array( 'Linear Lighting', 'Pendants', 'Downlights' ) as $cat ) {
		if ( ! term_exists( $cat, 'product-cat' ) ) {
			wp_insert_term( $cat, 'product-cat' );
		}
	}

	// ---- Products (cross-linked to projects) ----
	$products = array(
		'flow-plus' => array(
			'Flow+',
			'Linear Lighting',
			'ceiling.webp',
			'A flexible linear system that bends to any architectural line — continuous, dot-free and made to order in Manchester to your exact geometry.',
			'allianz-hq',
			'Allianz HQ fit-out',
		),
		'estrella'  => array(
			'Estrella',
			'Pendants',
			'rico-office-render.webp',
			'A configurable architectural pendant — choose form, finish and colour temperature, built to spec with CRI 90+ light quality.',
			'flagship-store',
			'our flagship retail scheme',
		),
		'neptune'   => array(
			'Neptune',
			'Downlights',
			'estrella-lounge.webp',
			'A fire-rated, IP65 downlight with switchable CCT and a clean trimless aperture — built for offices, healthcare and education.',
			'allianz-hq',
			'Allianz HQ fit-out',
		),
	);
	$builder = array(
		'flow-plus' => array( 'Seamless curves of light, made to order.', 'Made to order · ~6 day UK lead', "Dot-free continuous run\nBends to any radius\nMade to your exact length\nUp to 180 lm/W\nCRI 90+ colour rendering", 'Matt white, Matt black, Anodised silver', array( '_ricoman_lumens' => 'up to 180 lm/W', '_ricoman_cri' => '90+', '_ricoman_ip' => 'IP20', '_ricoman_warranty' => '5 years', '_ricoman_sku' => 'RM-FLOW-PLUS' ), "Driver | DALI dimmable\nControl | DALI / 1-10V\nMounting | Surface / suspended / recessed" ),
		'estrella'  => array( 'A configurable architectural pendant.', 'Configurable · built to spec', "Choose form, finish & CCT\nCRI 90+ light quality\nDimmable (DALI / phase)\nBespoke sizes", 'Brushed brass, Matt black, Champagne, Matt white', array( '_ricoman_cri' => '90+', '_ricoman_cct' => '2700–4000K', '_ricoman_warranty' => '5 years', '_ricoman_sku' => 'R341101' ), "Mounting | Pendant\nCable drop | Up to 3m\nDriver | Phase / DALI" ),
		'neptune'   => array( 'Fire-rated downlight with a clean trimless aperture.', 'In stock · next-day available', "90-minute fire rating\nIP65 front face\nSwitchable CCT\nTrimless bezel option", 'Matt white, Matt black', array( '_ricoman_wattage' => '8W', '_ricoman_lumens' => '900 lm', '_ricoman_cct' => '3000/4000/6000K', '_ricoman_ip' => 'IP65', '_ricoman_warranty' => '5 years', '_ricoman_sku' => 'RM-NEPTUNE' ), "Cut-out | 68mm\nDriver | Integral\nDimming | Mains / DALI" ),
	);
	foreach ( $products as $slug => $p ) {
		if ( get_page_by_path( $slug, OBJECT, 'product' ) ) {
			continue; // exists — never overwrite content, meta, images or edits.
		}
		// Flow+ keeps its full bespoke "Flow experience" layout; every other product
		// uses the configurator-preview layout (split hero, facts, variant table…).
		if ( 'flow-plus' === $slug && function_exists( 'ricoman_flow_product_blocks' ) ) {
			$content = ricoman_flow_product_blocks();
		} else {
			$content = function_exists( 'ricoman_default_product_blocks' ) ? ricoman_default_product_blocks() : '';
		}
		$pid = ricoman_make_post( 'product', $p[0], $slug, $content );
		if ( $pid ) {
			wp_update_post( array( 'ID' => $pid, 'post_excerpt' => $p[3] ) );
			wp_set_object_terms( $pid, $p[1], 'product-cat' );
			ricoman_set_featured_from_theme( $pid, $p[2] );
			// Demonstrate both product layouts: Neptune = Basic, others = Featured.
			update_post_meta( $pid, '_wp_page_template', 'neptune' === $slug ? 'single-product-basic' : 'single-product-featured' );
			// Seed Product Builder fields.
			if ( isset( $builder[ $slug ] ) ) {
				$b = $builder[ $slug ];
				update_post_meta( $pid, '_ricoman_tagline', $b[0] );
				update_post_meta( $pid, '_ricoman_lead', $b[1] );
				update_post_meta( $pid, '_ricoman_features', $b[2] );
				update_post_meta( $pid, '_ricoman_finishes', $b[3] );
				foreach ( $b[4] as $mk => $mv ) {
					update_post_meta( $pid, $mk, $mv );
				}
				if ( isset( $b[5] ) ) {
					update_post_meta( $pid, '_ricoman_extra_specs', $b[5] );
				}
				// Link to the RICOBOT family (the product title is the family name);
				// the Variant range block lists every variant once RICOBOT is connected.
				update_post_meta( $pid, '_ricoman_family', $p[0] );
			}
		}
	}

	// ---- Projects: [ Title, image, sector, excerpt, products[] ] ----
	$projects = array(
		'allianz-hq'      => array( 'Allianz HQ Fit-out', 'office1.webp', 'Commercial Office', 'A commercial workplace fit-out lit with continuous linear runs and trimless downlights for a clean, low-glare ceiling.', array( 'flow-plus' => 'Flow+', 'neptune' => 'Neptune' ) ),
		'flagship-store'  => array( 'Flagship Retail Store', 'retail.webp', 'Retail', 'A retail flagship using accent and decorative pendants to bring warmth and focus to the merchandising.', array( 'estrella' => 'Estrella' ) ),
		'acoustic-ceiling'=> array( 'Acoustic Linear Ceiling', 'rico-acoustic-corridor.webp', 'Commercial Office', 'Sound-absorbing linear lighting integrated into an exposed-services ceiling for a calm, productive workspace.', array( 'flow-plus' => 'Flow+' ) ),
		'breakout-lounge' => array( 'Breakout Lounge', 'rico-breakout-lounge.webp', 'Workplace', 'Warm, layered light for an informal amenity space — comfortable, flattering and energy-efficient.', array() ),
		'boutique-hotel'  => array( 'Boutique Hotel', 'office2.webp', 'Hospitality', 'Decorative pendants and dimmable downlights creating a warm, welcoming hospitality scheme.', array( 'estrella' => 'Estrella' ) ),
		'betfred-hq'      => array( 'Betfred HQ', 'rico-betfred7.webp', 'Workplace', 'A large headquarters fit-out delivered on programme with UK-made linear lighting throughout.', array( 'flow-plus' => 'Flow+' ) ),
		'kingsgate'       => array( 'Kingsgate', 'rico-kingsgate.webp', 'Retail', 'High-CRI accent lighting bringing focus and warmth to a flagship retail environment.', array() ),
		'estrella-canteen'=> array( 'Estrella Canteen', 'estrella-canteen.webp', 'Hospitality', 'Configurable Estrella pendants over a staff dining space, built to a bespoke layout.', array( 'estrella' => 'Estrella' ) ),
		'campus-library'  => array( 'Campus Library', 'office5.webp', 'Education', 'Comfortable, low-glare light for study and reading areas across a university library.', array( 'neptune' => 'Neptune' ) ),
		'studio-hq'       => array( 'Studio HQ', 'office6.webp', 'Workplace', 'A creative studio headquarters lit for focus and atmosphere in equal measure.', array() ),
	);
	// Show both project layouts: a few rich "feature" case studies, the rest "simple".
	$feature_projects = array( 'allianz-hq', 'acoustic-ceiling', 'flagship-store', 'betfred-hq' );
	foreach ( $projects as $slug => $pr ) {
		if ( get_page_by_path( $slug, OBJECT, 'project' ) ) {
			continue; // exists — never overwrite.
		}
		$is_feature = in_array( $slug, $feature_projects, true );
		if ( $is_feature && function_exists( 'ricoman_project_feature_content' ) ) {
			$content = ricoman_project_feature_content( $pr );
		} elseif ( function_exists( 'ricoman_project_simple_content' ) ) {
			$content = ricoman_project_simple_content( $pr );
		} else {
			$content = '<!-- wp:paragraph --><p>' . esc_html( $pr[3] ) . '</p><!-- /wp:paragraph -->';
		}
		$prid = ricoman_make_post( 'project', $pr[0], $slug, $content );
		if ( $prid ) {
			wp_update_post( array( 'ID' => $prid, 'post_excerpt' => $pr[3] ) );
			wp_set_object_terms( $prid, $pr[2], 'project-cat' );
			ricoman_set_featured_from_theme( $prid, $pr[1] );
			update_post_meta( $prid, '_wp_page_template', $is_feature ? 'single-project' : 'single-project-simple' );
		}
	}

	flush_rewrite_rules( true );
	update_option( 'ricoman_scaffolded', 1 );
}

/* -------------------------------------------------------------------------
 * On-demand: re-apply the theme's marketing page designs.
 *
 * The installer is create-once (it never overwrites your content on update),
 * which means design changes to the built-in marketing pages won't appear on
 * pages that already exist. This opt-in tool lets the team pull the latest
 * page designs when they want them. It ONLY rewrites the theme's own marketing
 * pages — it never touches products, projects, legal pages or anything else.
 * ---------------------------------------------------------------------- */

/** slug => generator function for the theme's built-in marketing pages. */
function ricoman_theme_page_map() {
	return array(
		'home'            => 'ricoman_home_blocks',
		'about'           => 'ricoman_about_blocks',
		'manufacturing'   => 'ricoman_manufacturing_blocks',
		'lighting-design' => 'ricoman_lighting_blocks',
		'downloads'       => 'ricoman_downloads_blocks',
		'customisation'   => 'ricoman_customisation_blocks',
		'contact'         => 'ricoman_contact_blocks',
		'casambi'                 => 'ricoman_casambi_blocks',
		'human-centric-lighting'  => 'ricoman_human_centric_blocks',
		'antimicrobial-protection'=> 'ricoman_antimicrobial_blocks',
		'fire-safety'             => 'ricoman_fire_safety_blocks',
		'sustainability'          => 'ricoman_sustainability_blocks',
		'made-in-britain'         => 'ricoman_made_in_britain_blocks',
		'trade'                   => 'ricoman_trade_blocks',
		'where-to-buy'            => 'ricoman_where_to_buy_blocks',
		'i-joist-ceilings'        => 'ricoman_i_joist_ceilings_blocks',
		'stock-availability'      => 'ricoman_stock_availability_blocks',
		'uae-exports'             => 'ricoman_uae_exports_blocks',
		'our-showroom'            => 'ricoman_our_showroom_blocks',
		'our-services'            => 'ricoman_our_services_blocks',
	);
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Page Designs', 'ricoman' ),
		__( 'Page Designs', 'ricoman' ),
		'manage_options',
		'ricoman-page-designs',
		'ricoman_render_page_designs'
	);
}, 30 );

function ricoman_render_page_designs() {
	$map    = ricoman_theme_page_map();
	$action = admin_url( 'admin-post.php' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Page Designs', 'ricoman' ) . '</h1>';

	if ( isset( $_GET['updated'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( 'Re-applied the latest design to %d page(s).', 'ricoman' ), (int) $_GET['updated'] )
			. '</p></div>';
	}

	echo '<p>' . esc_html__( 'Your content is never wiped on a theme update. If you want the newest built-in page designs (headers, sections and layout) applied to the marketing pages below, use this button. It only rewrites these pages — products, projects, legal and any pages you created yourself are left untouched.', 'ricoman' ) . '</p>';

	echo '<p style="color:#b32d2e"><strong>' . esc_html__( 'Heads up:', 'ricoman' ) . '</strong> ' . esc_html__( 'this replaces the content of the pages listed below with the latest theme design, so any manual edits to those specific pages will be overwritten.', 'ricoman' ) . '</p>';

	echo '<ul style="list-style:disc;margin-left:20px">';
	foreach ( $map as $slug => $fn ) {
		$page   = get_page_by_path( $slug );
		$exists = $page && 'page' === $page->post_type;
		echo '<li><strong>' . esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ) . '</strong> — '
			. ( $exists ? esc_html__( 'will be refreshed', 'ricoman' ) : '<em>' . esc_html__( 'not found (skipped)', 'ricoman' ) . '</em>' ) . '</li>';
	}
	echo '</ul>';

	echo '<form method="post" action="' . esc_url( $action ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Re-apply the latest design to the marketing pages? This overwrites those pages only.', 'ricoman' ) ) . '\');">';
	echo '<input type="hidden" name="action" value="ricoman_refresh_pages">';
	wp_nonce_field( 'ricoman_refresh_pages' );
	submit_button( __( 'Re-apply latest page designs', 'ricoman' ), 'primary' );
	echo '</form></div>';
}

add_action( 'admin_post_ricoman_refresh_pages', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_refresh_pages' );

	$done = 0;
	foreach ( ricoman_theme_page_map() as $slug => $fn ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || 'page' !== $page->post_type || ! function_exists( $fn ) ) {
			continue;
		}
		$content = call_user_func( $fn );
		if ( $content ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
			$done++;
		}
	}

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'ricoman-page-designs', 'updated' => $done ),
		admin_url( 'admin.php' )
	) );
	exit;
} );
