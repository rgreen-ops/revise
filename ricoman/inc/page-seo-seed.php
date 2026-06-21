<?php
/**
 * Starter SEO meta for the theme's marketing + feature pages.
 *
 * Ships hand-written, unique SEO titles + meta descriptions for the core and
 * feature pages (Casambi, Fire Safety, Made in Britain, Trade, …) so each
 * keyword-targeted landing page ships with an optimised <title> and description
 * instead of an auto-generated one. A one-click seed (Ricoman → Page SEO) — and
 * the installer + Page Designs refresh — fills the per-page SEO fields, but ONLY
 * where they're currently empty, so it never overwrites copy the team has
 * written. Pages with no entry are left to the theme's automatic description.
 *
 * Writes to the same _ricoman_seo_title / _ricoman_seo_desc post meta the SEO
 * meta box (inc/seo.php) reads, so seeded values are fully editable per page.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The starter SEO library: page slug => [ seo_title, meta_description ].
 * Titles target the page's primary keyword and end with the brand; descriptions
 * are unique, benefit-led and within ~155 characters. Filterable.
 */
function ricoman_page_seo_library() {
	$lib = array(
		// Core marketing pages.
		'about'                    => array( 'About Ricoman — British Commercial Lighting Manufacturer', 'Ricoman designs and manufactures commercial interior LED lighting in Manchester — free scheme design, UK stock and a 5-year warranty. Meet the team.' ),
		'manufacturing'            => array( 'UK Lighting Manufacturing in Manchester | Ricoman', 'See how Ricoman designs, assembles, finishes and tests commercial LED luminaires under one roof in Manchester — bespoke as standard, ~6-day lead times.' ),
		'lighting-design'          => array( 'Free Lighting Design Service | Ricoman', 'Send us your drawings and our in-house designers return a fully specified, DIALux-backed and costed lighting scheme — usually within 3–5 days, free of charge.' ),
		'customisation'            => array( 'Bespoke & Custom Lighting | Ricoman', 'Curved runs, custom lengths, special colour temperatures and brand-matched finishes — bespoke commercial lighting made to order in Manchester.' ),
		'downloads'                => array( 'Downloads — Datasheets, IES/LDT & BIM | Ricoman', 'Download the Ricoman catalogue, technical datasheets, photometric IES/LDT files, BIM objects and installation guides for commercial LED lighting.' ),
		'contact'                  => array( 'Contact Ricoman — Talk to Our Lighting Team', 'Get in touch with Ricoman in Manchester for quotes, lead times, technical help or a free lighting scheme. Call 0161 451 5913 or request a callback.' ),

		// Feature / enhanced landing pages.
		'casambi'                  => array( 'Casambi Wireless Lighting Control | Ricoman', 'Casambi-ready luminaires for wireless dimming, tunable white and scene control — no control wiring or gateway. See how to specify Casambi on your scheme.' ),
		'human-centric-lighting'   => array( 'Human Centric Lighting (Tunable White) | Ricoman', 'Circadian, tunable-white lighting that supports focus, comfort and wellbeing in workplaces, healthcare and education — designed and made in Britain.' ),
		'antimicrobial-protection' => array( 'Antimicrobial Lighting for Healthcare | Ricoman', 'Luminaires with an antimicrobial surface treatment that inhibits bacterial growth — for healthcare, education and food environments. UK-made, IP-rated options.' ),
		'fire-safety'              => array( 'Fire-Rated & Emergency Lighting | Ricoman', 'Fire-rated downlights and BS 5266-aware emergency lighting to protect escape routes and ceiling integrity — British-made and held in UK stock.' ),
		'sustainability'           => array( 'Sustainable LED Lighting, Made in Britain | Ricoman', 'Efficient, long-life commercial lighting made responsibly — high-efficacy LEDs, serviceable fittings and UK manufacturing that cuts waste and transport miles.' ),
		'made-in-britain'          => array( 'Made in Britain — UK Lighting Manufacturer | Ricoman', 'British-made commercial LED lighting designed and built in Manchester — shorter lead times, full traceability, UK stock and a 5-year warranty.' ),
		'trade'                    => array( 'Trade Lighting Supplier for Contractors | Ricoman', 'Fast quotes, UK stock, free scheme design and real support for contractors, wholesalers and design & build teams. Open a trade enquiry with Ricoman.' ),
		'where-to-buy'             => array( 'Where to Buy Ricoman Lighting', 'Buy Ricoman commercial lighting direct or through your wholesaler — UK stock, free scheme design and delivery across the UK, with export available.' ),
		'i-joist-ceilings'         => array( 'Lighting for I-Joist & Timber Ceilings | Ricoman', 'Shallow, fire-rated recessed downlights designed for I-joist and engineered-timber ceilings — the right fit for the void, with free layout design.' ),
		'stock-availability'       => array( 'Lighting Stock & Availability | Ricoman', 'UK stock on core ranges and made-to-order fittings on an average six-day lead — honest availability so your programme stays on track. Check stock with us.' ),
		'uae-exports'              => array( 'British Lighting Exports to the UAE | Ricoman', 'Ricoman supplies commercial lighting projects across the UAE — British design and manufacture, full documentation and export logistics handled for you.' ),
		'our-showroom'             => array( 'Visit Our Lighting Showroom in Manchester | Ricoman', 'See Ricoman luminaires lit in real settings, compare finishes and meet the team at our Manchester showroom. Book a visit — factory tours available.' ),
		'our-services'             => array( 'Our Lighting Services — Design to Delivery | Ricoman', 'Free lighting design, UK manufacturing, stock and delivery, and 5-year support — one British partner for your commercial lighting, start to finish.' ),
	);
	return apply_filters( 'ricoman_page_seo_library', $lib );
}

/**
 * Seed empty SEO title/description for the known pages. Fills only fields that
 * are currently empty; returns the number of meta fields written.
 *
 * @return int
 */
function ricoman_page_seo_seed_all() {
	$written = 0;
	foreach ( ricoman_page_seo_library() as $slug => $meta ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || 'page' !== $page->post_type ) {
			continue;
		}
		list( $title, $desc ) = $meta;
		if ( $title && '' === trim( (string) get_post_meta( $page->ID, '_ricoman_seo_title', true ) ) ) {
			update_post_meta( $page->ID, '_ricoman_seo_title', sanitize_text_field( $title ) );
			$written++;
		}
		if ( $desc && '' === trim( (string) get_post_meta( $page->ID, '_ricoman_seo_desc', true ) ) ) {
			update_post_meta( $page->ID, '_ricoman_seo_desc', sanitize_textarea_field( $desc ) );
			$written++;
		}
	}
	return $written;
}

/** Admin page: Ricoman → Page SEO. */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Page SEO', 'ricoman' ),
		__( 'Page SEO', 'ricoman' ),
		'manage_options',
		'ricoman-page-seo',
		'ricoman_render_page_seo'
	);
}, 30 );

function ricoman_render_page_seo() {
	$lib = ricoman_page_seo_library();
	echo '<div class="wrap"><h1>' . esc_html__( 'Page SEO', 'ricoman' ) . '</h1>';

	if ( isset( $_GET['seeded'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( 'Filled %d empty SEO field(s) with starter copy.', 'ricoman' ), (int) $_GET['seeded'] )
			. '</p></div>';
	}

	echo '<p>' . esc_html__( 'Each marketing and feature page can have its own SEO title and meta description (edit per page in the “SEO (Ricoman)” box on the page editor). The button below fills in hand-written starter copy for the pages listed — but only where a field is still empty, so it never overwrites anything you have written.', 'ricoman' ) . '</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ricoman_seed_page_seo">';
	wp_nonce_field( 'ricoman_seed_page_seo' );
	submit_button( __( 'Fill empty SEO copy for these pages', 'ricoman' ), 'primary', 'submit', false );
	echo '</form>';

	echo '<table class="widefat striped" style="margin-top:18px;max-width:1000px"><thead><tr>'
		. '<th>' . esc_html__( 'Page', 'ricoman' ) . '</th><th>' . esc_html__( 'SEO title', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Status', 'ricoman' ) . '</th></tr></thead><tbody>';
	foreach ( $lib as $slug => $meta ) {
		$page  = get_page_by_path( $slug );
		if ( $page && 'page' === $page->post_type ) {
			$has  = '' !== trim( (string) get_post_meta( $page->ID, '_ricoman_seo_title', true ) ) || '' !== trim( (string) get_post_meta( $page->ID, '_ricoman_seo_desc', true ) );
			$stat = $has ? '<span style="color:#1a7f37">' . esc_html__( 'Set', 'ricoman' ) . '</span>' : '<span style="color:#996800">' . esc_html__( 'Empty — will be seeded', 'ricoman' ) . '</span>';
		} else {
			$stat = '<em>' . esc_html__( 'Page not found', 'ricoman' ) . '</em>';
		}
		echo '<tr><td><code>/' . esc_html( $slug ) . '/</code></td><td>' . esc_html( $meta[0] ) . '</td><td>' . $stat . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table></div>';
}

add_action( 'admin_post_ricoman_seed_page_seo', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_seed_page_seo' );
	$n = ricoman_page_seo_seed_all();
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-page-seo', 'seeded' => $n ), admin_url( 'admin.php' ) ) );
	exit;
} );
