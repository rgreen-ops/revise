<?php
/**
 * Starter SEO copy for product categories.
 *
 * Ships hand-written, unique intro + body/FAQ copy for the core commercial
 * lighting categories (linear, downlights, panels, track, emergency, battens,
 * high bay, floodlights, bulkheads, spotlights, strip/tape, exterior). A one-
 * click seed (Ricoman → Category SEO) fills the per-category SEO fields, but
 * ONLY where they are currently empty — it never overwrites copy the team has
 * written. Categories with no known match are left blank for manual copy, so we
 * never publish thin, templated duplicate text.
 *
 * The body uses the existing [ricoman_faq] shortcode, so each seeded category
 * also gets FAQPage structured data for free.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The starter-copy library. Keyed by a short id; each entry has `match`
 * (lower-case substrings tested against the category name + slug — first match
 * wins) and `intro` / `body` copy. `{name}` is replaced with the real category
 * name at seed time. Filterable so copy can be tuned without editing the theme.
 */
function ricoman_cat_seo_library() {
	$lib = array(
		'highbay' => array(
			'match' => array( 'high bay', 'highbay', 'high-bay', 'low bay', 'lowbay' ),
			'intro' => 'LED high bays from Ricoman light warehouses, factories and sports halls with powerful, efficient output from height. British-made high and low bay fittings with a choice of optics and high lumen packages for large, tall industrial spaces.',
			'body'  => "<h2>About our high bay lighting</h2>\nHigh bays deliver strong, uniform light from height across large industrial floor plates. Ricoman high bays combine high efficacy with rugged construction, sensor compatibility and a choice of beam distributions — all made in Manchester and held in UK stock.\n\n[ricoman_faq]\nQ: What mounting heights suit high bays?\nA: High bays are typically used above around 6m; lower mounting heights may suit a low bay or batten — ask our team for guidance.\nQ: Are sensor options available?\nA: Yes — microwave sensors for occupancy and daylight control are available to cut running costs in industrial spaces.\nQ: What beam angles can I choose?\nA: A range of optics is offered to suit narrow-aisle and open-area layouts; see each product's variant table.\n[/ricoman_faq]",
		),
		'linear' => array(
			'match' => array( 'linear' ),
			'intro' => 'Ricoman’s linear LED lighting delivers clean, continuous lines of light for offices, retail, education and circulation spaces. British-made profiles run as suspended, surface or recessed continuous runs, with seamless joints and a choice of optics, outputs and colour temperatures.',
			'body'  => "<h2>About our linear lighting</h2>\nLinear luminaires are the backbone of modern commercial schemes — efficient, uniform and architecturally clean. Every Ricoman linear range is manufactured in Manchester, held in UK stock for fast lead times, and available as continuous runs, individual modules or made-to-order lengths to suit your drawings.\n\n[ricoman_faq]\nQ: Can linear fittings be joined into continuous runs?\nA: Yes — our linear profiles connect end-to-end with seamless joints so light runs unbroken across the length of a run.\nQ: What colour temperatures and outputs are available?\nA: Most ranges offer 3000K, 4000K and CCT-switchable options across a spread of lumen packages; check each product's variant table for the exact specification.\nQ: Do you offer free lighting design for linear schemes?\nA: Yes — send your drawings to our team and we'll return a costed, compliant lighting layout, typically within 3–5 working days.\n[/ricoman_faq]",
		),
		'downlight' => array(
			'match' => array( 'downlight', 'down light' ),
			'intro' => 'Commercial LED downlights from Ricoman — recessed, fire-rated and adjustable fittings for offices, retail, hospitality and healthcare. British-made and UK-stocked, with tight glare control, high-CRI options and a range of beam angles for crisp, comfortable light.',
			'body'  => "<h2>About our downlights</h2>\nDownlights are the workhorse of commercial ceilings, balancing efficiency, comfort and a discreet aperture. Ricoman downlights are engineered for low glare and accurate colour, with fire-rated and IP-rated options for the most demanding specifications.\n\n[ricoman_faq]\nQ: Are your downlights fire-rated?\nA: Many of our downlights are fire-rated to maintain ceiling integrity; look for the fire-rated badge and specification on each product.\nQ: What beam angles are available?\nA: Beam angles vary by range, from tight accent spots to wide general-lighting distributions — see each product's variant table.\nQ: Can downlights be dimmed?\nA: Yes — most ranges offer mains, DALI or Casambi dimming. Check the product specification for compatible control options.\n[/ricoman_faq]",
		),
		'panel' => array(
			'match' => array( 'panel' ),
			'intro' => 'LED panels from Ricoman provide bright, uniform, low-glare illumination for offices, schools and healthcare. British-made back-lit and edge-lit panels in standard module sizes, with UGR<19 options for screen-based workspaces and a choice of colour temperatures.',
			'body'  => "<h2>About our LED panels</h2>\nPanels deliver even, comfortable light across large floor plates and drop into standard suspended-ceiling grids. Ricoman panels offer low-glare UGR<19 optics for compliant office and education lighting, with emergency and dimmable variants available.\n\n[ricoman_faq]\nQ: What sizes do your panels come in?\nA: Panels are available in standard module sizes such as 600×600 and 1200×300 to suit common suspended-ceiling grids.\nQ: Do you offer low-glare UGR<19 panels?\nA: Yes — UGR<19 options are available for offices and screen-based environments where glare control is critical.\nQ: Are emergency versions available?\nA: Many panels offer an integral emergency variant with a maintained battery backup; see the product specification.\n[/ricoman_faq]",
		),
		'track' => array(
			'match' => array( 'track' ),
			'intro' => 'Track lighting from Ricoman brings flexible, repositionable accent light to retail, gallery and hospitality spaces. British-made spotlights on three-circuit track, with interchangeable optics, high CRI for accurate colour rendering and a choice of finishes.',
			'body'  => "<h2>About our track lighting</h2>\nTrack systems let you aim and reposition light as displays change, making them ideal for retail and exhibition spaces. Ricoman track spots offer high CRI for true colour rendering and a range of beam angles and finishes.\n\n[ricoman_faq]\nQ: What track system do your spotlights use?\nA: Our spotlights are designed for industry-standard three-circuit track, allowing multiple lighting circuits on a single run.\nQ: Can I change the beam angle?\nA: Many spotlights accept interchangeable lenses or are offered in multiple beam angles to suit the application.\nQ: What finishes are available?\nA: Track and spotlights are typically available in black and white finishes; check each product for options.\n[/ricoman_faq]",
		),
		'emergency' => array(
			'match' => array( 'emergency', 'exit sign' ),
			'intro' => 'Emergency lighting from Ricoman keeps escape routes and open areas safe during mains failure. British-made maintained and non-maintained luminaires, exit signs and emergency conversions, designed to support BS 5266 compliance and held in UK stock.',
			'body'  => "<h2>About emergency lighting</h2>\nEmergency lighting is a life-safety requirement for commercial buildings, illuminating escape routes when the mains fails. Ricoman offers maintained and non-maintained fittings, exit signage and emergency variants of standard luminaires to keep specifications simple and compliant.\n\n[ricoman_faq]\nQ: Do your emergency fittings support BS 5266?\nA: Our emergency products are designed to support compliance with BS 5266; always confirm the scheme against the standard and a competent design.\nQ: What's the difference between maintained and non-maintained?\nA: Maintained fittings stay lit in normal use and on battery during failure; non-maintained only illuminate when the mains fails.\nQ: How long do the batteries last in a power cut?\nA: Emergency fittings provide a minimum three-hour duration as standard for most commercial applications; see the product specification.\n[/ricoman_faq]",
		),
		'batten' => array(
			'match' => array( 'batten' ),
			'intro' => 'LED battens from Ricoman deliver efficient, robust general lighting for warehouses, car parks, workshops and back-of-house areas. British-made single and twin batten fittings, with IP-rated options for damp and dusty environments.',
			'body'  => "<h2>About our LED battens</h2>\nBattens are a cost-effective, high-efficiency choice for utility and industrial spaces. Ricoman battens offer strong lumen packages, durable construction and IP-rated versions for demanding environments, with emergency and sensor options available.\n\n[ricoman_faq]\nQ: Are IP-rated battens available for damp areas?\nA: Yes — IP65 weatherproof battens are available for car parks, warehouses and wash-down areas.\nQ: Can battens be linked together?\nA: Many batten ranges support through-wiring and linking for continuous runs; check the product specification.\nQ: Are sensor and emergency versions available?\nA: Yes — microwave/PIR sensor and integral emergency options are available across selected ranges.\n[/ricoman_faq]",
		),
		'floodlight' => array(
			'match' => array( 'flood' ),
			'intro' => 'LED floodlights from Ricoman provide powerful exterior illumination for car parks, facades, yards and sports areas. British-made, IP-rated floodlights with robust construction and a choice of outputs and beam angles.',
			'body'  => "<h2>About our floodlights</h2>\nFloodlights deliver high-output exterior light for security, amenity and sports applications. Ricoman floodlights are IP-rated for outdoor use, with durable housings and a range of wattages and distributions.\n\n[ricoman_faq]\nQ: Are your floodlights weatherproof?\nA: Yes — floodlights are IP-rated for exterior use; check the specific IP rating on each product.\nQ: Do floodlights include sensors?\nA: Selected floodlights offer an integral PIR sensor; sensor versions are noted in the product specification.\nQ: What beam angles are available?\nA: Symmetric and asymmetric distributions are available to suit area and facade lighting.\n[/ricoman_faq]",
		),
		'bulkhead' => array(
			'match' => array( 'bulkhead' ),
			'intro' => 'LED bulkheads from Ricoman offer durable, IP-rated lighting for stairwells, corridors, entrances and external walls. British-made fittings with sensor and emergency options for safe, low-maintenance amenity lighting.',
			'body'  => "<h2>About our bulkheads</h2>\nBulkheads are a tough, versatile choice for circulation and external amenity areas. Ricoman bulkheads are IP-rated and impact-resistant, with sensor and emergency variants for communal and back-of-house spaces.\n\n[ricoman_faq]\nQ: Are bulkheads suitable for outdoor use?\nA: Yes — IP-rated bulkheads are suitable for external walls and entrances; confirm the IP rating per product.\nQ: Are sensor versions available?\nA: Microwave/PIR sensor versions are available for automatic switching in corridors and stairwells.\nQ: Do you offer emergency bulkheads?\nA: Yes — integral emergency variants are available for life-safety in communal areas.\n[/ricoman_faq]",
		),
		'spotlight' => array(
			'match' => array( 'spot' ),
			'intro' => 'Commercial LED spotlights from Ricoman deliver focused accent light for retail, gallery and display. British-made surface and recessed spots with high CRI and a choice of beam angles for crisp, controlled highlighting.',
			'body'  => "<h2>About our spotlights</h2>\nSpotlights pick out products, artwork and architectural features with directional, high-quality light. Ricoman spotlights offer high CRI for accurate colour and a range of beam angles and finishes to suit retail and display schemes.\n\n[ricoman_faq]\nQ: What CRI do your spotlights offer?\nA: High-CRI options are available for retail and gallery use where accurate colour rendering matters; see the product specification.\nQ: Are adjustable spotlights available?\nA: Yes — many spotlights tilt and rotate so light can be aimed precisely at displays.\nQ: Can spotlights be dimmed?\nA: Most ranges support mains, DALI or Casambi dimming; check the product specification for control options.\n[/ricoman_faq]",
		),
		'strip' => array(
			'match' => array( 'strip', 'tape', 'profile' ),
			'intro' => 'LED strip and tape from Ricoman creates concealed, continuous light for coves, joinery and feature detailing. British-supplied tape with aluminium profiles, drivers and accessories for clean architectural detailing.',
			'body'  => "<h2>About our LED strip &amp; tape</h2>\nStrip and tape light hides the source and reveals the effect — perfect for coves, shelving, handrails and bespoke joinery. Ricoman supplies tape, aluminium profiles, diffusers and drivers so a detail can be specified as a complete, coordinated system.\n\n[ricoman_faq]\nQ: Do you supply profiles and diffusers for the tape?\nA: Yes — aluminium profiles with diffusers are available to protect the tape and give a clean, even line of light.\nQ: What driver do I need?\nA: The driver depends on the tape's wattage per metre and total run length; our team can advise on the right driver for your detail.\nQ: Can the tape be cut to length?\nA: LED tape is cut at marked intervals; check each product for the cut points and maximum run length.\n[/ricoman_faq]",
		),
		'exterior' => array(
			'match' => array( 'exterior', 'outdoor', 'external', 'wall pack', 'wallpack', 'bollard' ),
			'intro' => 'Exterior LED lighting from Ricoman lights entrances, facades, pathways and car parks with durable, weatherproof fittings. British-made, IP-rated luminaires built for the UK climate, with sensor options for efficient amenity and security lighting.',
			'body'  => "<h2>About our exterior lighting</h2>\nExterior lighting has to survive the weather while keeping people safe and a building looking its best after dark. Ricoman's exterior range is IP-rated and robustly built, with sensor and emergency options for car parks, entrances and amenity areas.\n\n[ricoman_faq]\nQ: What IP rating do exterior fittings need?\nA: Exterior fittings should be at least IP65 for full weather protection; check the rating on each product.\nQ: Are sensor versions available?\nA: Yes — PIR and microwave sensor versions are available for security and energy savings.\nQ: Are the finishes corrosion-resistant?\nA: Exterior housings are finished for outdoor durability; ask our team about coastal or high-corrosion environments.\n[/ricoman_faq]",
		),
	);
	return apply_filters( 'ricoman_cat_seo_library', $lib );
}

/**
 * The starter-copy entry matching a term (by name + slug substring), or null.
 */
function ricoman_cat_seo_match( $term ) {
	$hay = strtolower( $term->name . ' ' . $term->slug );
	foreach ( ricoman_cat_seo_library() as $entry ) {
		foreach ( (array) $entry['match'] as $kw ) {
			if ( '' !== $kw && false !== strpos( $hay, $kw ) ) {
				return $entry;
			}
		}
	}
	return null;
}

/**
 * Seed empty SEO fields for all matched categories. Only fills a field that is
 * currently empty; returns the number of fields written.
 */
function ricoman_cat_seo_seed_all() {
	$tax = function_exists( 'ricoman_cat_tax' ) ? ricoman_cat_tax() : 'product-cat';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return 0;
	}
	$written = 0;
	foreach ( $terms as $term ) {
		$entry = ricoman_cat_seo_match( $term );
		if ( ! $entry ) {
			continue;
		}
		$map = array(
			'_rm_cat_seo_intro' => isset( $entry['intro'] ) ? $entry['intro'] : '',
			'_rm_cat_seo_body'  => isset( $entry['body'] ) ? $entry['body'] : '',
		);
		foreach ( $map as $key => $copy ) {
			if ( '' === trim( (string) $copy ) ) {
				continue;
			}
			$existing = (string) get_term_meta( $term->term_id, $key, true );
			if ( '' !== trim( $existing ) ) {
				continue; // Never overwrite copy the team has written.
			}
			$copy = str_replace( '{name}', $term->name, $copy );
			update_term_meta( $term->term_id, $key, wp_kses_post( $copy ) );
			$written++;
		}
	}
	// Products archive body — fill only if still empty.
	if ( '' === trim( (string) get_option( 'rm_products_seo_body', '' ) ) ) {
		update_option( 'rm_products_seo_body', wp_kses_post( ricoman_products_seo_default() ) );
		$written++;
	}
	if ( $written && function_exists( 'ricoman_products_ver' ) ) {
		update_option( 'rm_products_ver', (string) time(), false );
	}
	return $written;
}

/* ---------------------------------------------------- products archive copy -- */

/** Hand-written starter SEO body (with FAQ) for the main /products/ archive. */
function ricoman_products_seo_default() {
	return "<h2>Commercial LED lighting, made in Britain</h2>\nRicoman designs and manufactures commercial LED luminaires in Manchester, with over 500 interior and exterior fittings across linear, downlights, panels, track, emergency, battens, high bay and bespoke ranges. Products are held in UK stock for fast lead times and made to order when a project needs something specific.\n\n[ricoman_faq]\nQ: Where are Ricoman luminaires made?\nA: Our fittings are designed and manufactured in Manchester, UK, with stock held here for fast delivery.\nQ: Do you offer free lighting design?\nA: Yes — send us a drawing or finishes schedule and our in-house team returns a costed, compliant scheme, usually within 3–5 working days.\nQ: Can products be customised?\nA: Many ranges can be tailored on output, colour temperature, finish and length; talk to our team about bespoke requirements.\nQ: Are datasheets and photometric files available?\nA: Yes — datasheets, instructions and IES/LDT photometric files are available on each product page for specifiers and contractors.\n[/ricoman_faq]";
}

/**
 * Editable SEO body for the /products/ archive, rendered below the category
 * tiles. Outputs nothing until copy is set (seed it from Ricoman → Category SEO).
 * Runs shortcodes so the [ricoman_faq] block adds FAQPage schema.
 */
add_shortcode( 'ricoman_products_seo', function () {
	$body = (string) get_option( 'rm_products_seo_body', '' );
	if ( '' === trim( $body ) ) {
		return '';
	}
	$rendered = do_shortcode( shortcode_unautop( wpautop( wp_kses_post( $body ) ) ) );
	return '<div class="rm-pp-wrap"><div class="rm-catarch-body">' . $rendered . '</div></div>';
} );

/* ------------------------------------------------------------------ admin -- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Category SEO', 'ricoman' ),
		__( 'Category SEO', 'ricoman' ),
		'manage_options',
		'ricoman-category-seo',
		'ricoman_render_category_seo'
	);
}, 32 );

function ricoman_render_category_seo() {
	$tax   = function_exists( 'ricoman_cat_tax' ) ? ricoman_cat_tax() : 'product-cat';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	$terms = is_wp_error( $terms ) ? array() : $terms;

	echo '<div class="wrap"><h1>' . esc_html__( 'Category SEO', 'ricoman' ) . '</h1>';
	if ( isset( $_GET['seeded'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( 'Filled %d empty SEO field(s) with starter copy.', 'ricoman' ), (int) $_GET['seeded'] )
			. '</p></div>';
	}
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Products page copy saved.', 'ricoman' ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'Each product category can have its own SEO copy: an intro shown above the products and a body / FAQ shown below them (edit per category under Products → Categories). The button below fills in starter copy for the core lighting categories — but only where a field is still empty, so it never overwrites anything you have written. Categories with no recognised match are left blank for you to write.', 'ricoman' ) . '</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:14px 0 22px">';
	echo '<input type="hidden" name="action" value="ricoman_seed_category_seo">';
	wp_nonce_field( 'ricoman_seed_category_seo' );
	submit_button( __( 'Fill empty SEO copy for known categories', 'ricoman' ), 'primary', 'submit', false );
	echo '</form>';

	// Editable SEO body for the /products/ archive (no term to hang it on).
	$pbody = (string) get_option( 'rm_products_seo_body', '' );
	echo '<h2 style="margin-top:26px">' . esc_html__( 'Products page (/products/) SEO copy', 'ricoman' ) . '</h2>';
	echo '<p>' . esc_html__( 'Body / FAQ shown below the category tiles on the main Products page. Basic HTML and the [ricoman_faq] block are supported (FAQs add rich-results schema). Leave blank to hide it.', 'ricoman' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 22px">';
	echo '<input type="hidden" name="action" value="ricoman_save_products_seo">';
	wp_nonce_field( 'ricoman_save_products_seo' );
	echo '<textarea name="rm_products_seo_body" rows="12" style="width:100%;max-width:780px">' . esc_textarea( $pbody ) . '</textarea><br>';
	submit_button( __( 'Save Products page copy', 'ricoman' ), 'secondary', 'submit', false );
	echo '</form>';

	// Status table.
	echo '<table class="widefat striped" style="max-width:780px"><thead><tr>'
		. '<th>' . esc_html__( 'Category', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Starter copy', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Intro', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Body / FAQ', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Order', 'ricoman' ) . '</th>'
		. '</tr></thead><tbody>';
	$yes = '<span style="color:#1a7f37;font-weight:600">●</span> ';
	$no  = '<span style="color:#a7aaad">—</span>';
	foreach ( $terms as $term ) {
		$match  = ricoman_cat_seo_match( $term );
		$intro  = '' !== trim( (string) get_term_meta( $term->term_id, '_rm_cat_seo_intro', true ) );
		$body   = '' !== trim( (string) get_term_meta( $term->term_id, '_rm_cat_seo_body', true ) );
		$order  = function_exists( 'ricoman_cat_order' ) ? ricoman_cat_order( $term->term_id ) : 0;
		$edit   = get_edit_term_link( $term->term_id, $tax );
		echo '<tr>';
		echo '<td><a href="' . esc_url( $edit ) . '">' . esc_html( $term->name ) . '</a></td>';
		echo '<td>' . ( $match ? $yes . esc_html__( 'available', 'ricoman' ) : $no ) . '</td>';
		echo '<td>' . ( $intro ? $yes . esc_html__( 'set', 'ricoman' ) : $no ) . '</td>';
		echo '<td>' . ( $body ? $yes . esc_html__( 'set', 'ricoman' ) : $no ) . '</td>';
		echo '<td>' . ( $order > 0 ? (int) $order : $no ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

add_action( 'admin_post_ricoman_seed_category_seo', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_seed_category_seo' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$n = ricoman_cat_seo_seed_all();
	wp_safe_redirect( add_query_arg( 'seeded', $n, admin_url( 'admin.php?page=ricoman-category-seo' ) ) );
	exit;
} );

add_action( 'admin_post_ricoman_save_products_seo', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_save_products_seo' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$body = isset( $_POST['rm_products_seo_body'] ) ? wp_kses_post( wp_unslash( $_POST['rm_products_seo_body'] ) ) : '';
	update_option( 'rm_products_seo_body', $body );
	if ( function_exists( 'ricoman_products_ver' ) ) {
		update_option( 'rm_products_ver', (string) time(), false );
	}
	wp_safe_redirect( add_query_arg( 'saved', 1, admin_url( 'admin.php?page=ricoman-category-seo' ) ) );
	exit;
} );
