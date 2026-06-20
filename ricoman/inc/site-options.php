<?php
/**
 * Ricoman Site options — one admin screen that drives the header (mega menu),
 * the footer (contact, socials, link columns) and code injection (Tag Manager
 * etc.). Everything the marketing team edits day-to-day lives here so the header
 * and footer template parts stay tiny ([ricoman_header] / [ricoman_footer]).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Default settings — seed the mega menu / footer to the brand's structure. */
function ricoman_settings_defaults() {
	return array(
		'brand_logo'       => '',
		'nav_primary'      => "Products | #products\nProjects | /projects/\nAbout | /about/\nContact | /contact/\nCustomisation | /customisation/\nDownload | /downloads/",
		'mega_heading'     => 'See All Products',
		'mega_heading_url' => '/products/',
		'mega_categories'  => "Linear | /products/\nPendants | /products/\nDownlight | /products/\nAcoustic | /products/\nBiophilic | /products/\nEmergency | /products/\nExterior | /products/\nBulkhead | /products/\nPanel | /products/\nTrack Lighting | /products/\nIndustrial | /products/",
		'mega_collections' => "Astrowave | Flexible 360° Rope Light System | /products/\nEstrella Pro | Linear Lighting Solutions | /products/estrella/\nFlow + | Curved Linear | /products/flow-plus/\nZodiac | 48V Track | /products/",
		'mega_apps'        => "Office | /sector/office-lighting/\nRetail | /sector/retail-lighting/\nHospitality | /sector/hospitality-leisure-lighting/\nHealthcare | /sector/healthcare-lighting/\nEducation | /sector/education-lighting/\nIndustrial | /sector/industrial-warehouse-lighting/\nGym | /sector/gym-lighting/\nAll sectors → | /sectors/",
		'mega_card1_img'   => get_theme_file_uri( 'assets/images/rico-soundslikelight.webp' ),
		'mega_card1_title' => 'Sounds Like Light — Acoustic Pendants',
		'mega_card1_url'   => '/products/',
		'mega_card2_img'   => get_theme_file_uri( 'assets/images/rico-downloads.webp' ),
		'mega_card2_title' => 'Request Your Lighting Catalogue',
		'mega_card2_url'   => '/downloads/',
		'flow_banner'      => get_theme_file_uri( 'assets/images/ceiling.webp' ) . " | Flow+ · Curved Linear | Seamless curves of light, made to order | A flexible linear system that bends to any architectural line — continuous and dot-free. | Design your run → | /flow-designer/\n"
			. get_theme_file_uri( 'assets/images/arch-line.webp' ) . " | Made in Britain | Built to your exact geometry | Designed, made and tested in Manchester — bespoke is how our factory is built to work. | Talk to our designers | /contact/",
		'show_search'      => '1',
		'login_url'        => '',
		'login_label'      => 'Login',
		'foot_address'     => "Metroplex Business Park,\n520, Broadway, M50 2UE\nManchester, UK.",
		'foot_phone'       => '0161 451 5913',
		'foot_email'       => 'sales@ricoman.com',
		'foot_hours'       => "Monday to Thursday: 8:30 AM to 5:00 PM\nFriday: 8:30 AM to 4:00 PM\nSaturday - Sunday & Bank Holidays: Closed",
		'soc_instagram'    => '',
		'soc_facebook'     => '',
		'soc_linkedin'     => '',
		'soc_youtube'      => '',
		'foot_col_about'   => "Made in Britain | /manufacturing/\nSustainability | /sustainability/\nLighting Design | /lighting-design/\nCustom Lighting Solutions | /customisation/",
		'foot_col_products'=> "Linear Lighting | /products/\nAcoustic Solutions | /products/\nBiophilic Lighting | /products/\nDownlights | /products/\nTrack Lighting | /products/\nRecessed Modular | /products/\nOutdoor | /products/\nPendants | /products/",
		'foot_col_projects'=> "Office | /sector/office-lighting/\nRetail | /sector/retail-lighting/\nHospitality | /sector/hospitality-leisure-lighting/\nIndustrial | /sector/industrial-warehouse-lighting/\nHealthcare | /sector/healthcare-lighting/\nAll sectors | /sectors/",
		'foot_col_other'   => "News | /news/\nDownloads | /downloads/\nCasambi | /casambi/\nHuman Centric Lighting | /human-centric-lighting/\nAntimicrobial Protection | /antimicrobial-protection/\nProduct Warranty | /product-warranty/",
		'foot_legal'       => "Terms & Conditions | /terms/\nCookie Policy | /cookie-policy/\nPrivacy Policy | /privacy-policy/\nE-mail Notice | /email-notice/\nSlavery & Human Trafficking Statement | /modern-slavery-statement/\nSite Map | /site-map/",
		'code_head'        => '',
		'code_body_open'   => '',
		'code_footer'      => '',
	);
}

/** One-time: repoint saved By-Application / footer links to the sector hubs
 * (only if they still hold the old "/projects/" placeholders). Stays editable. */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_sector_links_migrated' ) ) {
		return;
	}
	$opts = get_option( 'ricoman_settings', array() );
	$def  = ricoman_settings_defaults();
	$changed = false;
	foreach ( array( 'mega_apps', 'foot_col_projects' ) as $key ) {
		if ( ! empty( $opts[ $key ] ) && false !== strpos( $opts[ $key ], '| /projects/' ) ) {
			$opts[ $key ] = $def[ $key ];
			$changed = true;
		}
	}
	if ( $changed ) {
		update_option( 'ricoman_settings', $opts );
	}
	update_option( 'ricoman_sector_links_migrated', 1 );
} );

/** Read one setting (falls back to the default). */
function ricoman_opt( $key ) {
	$opts = get_option( 'ricoman_settings', array() );
	$def  = ricoman_settings_defaults();
	if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
		return $opts[ $key ];
	}
	return isset( $def[ $key ] ) ? $def[ $key ] : '';
}

/** Parse "A | B | C" lines into an array of trimmed parts arrays. */
function ricoman_opt_lines( $key ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) ricoman_opt( $key ) ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$out[] = array_map( 'trim', explode( '|', $line ) );
	}
	return $out;
}

/* ------------------------------------------------------------------ admin */

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Ricoman Site', 'ricoman' ),
		__( 'Ricoman', 'ricoman' ),
		'manage_options',
		'ricoman-site',
		'ricoman_settings_page',
		'dashicons-lightbulb',
		3
	);
	add_submenu_page( 'ricoman-site', __( 'Header & Mega Menu', 'ricoman' ), __( 'Header & Menu', 'ricoman' ), 'manage_options', 'ricoman-site', 'ricoman_settings_page' );
	add_submenu_page( 'ricoman-site', __( 'Edit Header / Footer layout', 'ricoman' ), __( 'Edit layout (Site Editor)', 'ricoman' ), 'edit_theme_options', 'site-editor.php?path=/patterns' );
} );

add_action( 'admin_init', function () {
	register_setting( 'ricoman_settings_group', 'ricoman_settings', 'ricoman_sanitize_settings' );
} );

/** Sanitize the whole settings array by field type. */
function ricoman_sanitize_settings( $input ) {
	$out  = array();
	$urls = array( 'brand_logo', 'mega_heading_url', 'mega_card1_img', 'mega_card1_url', 'mega_card2_img', 'mega_card2_url', 'login_url', 'soc_instagram', 'soc_facebook', 'soc_linkedin', 'soc_youtube' );
	$raw  = array( 'code_head', 'code_body_open', 'code_footer' ); // allow scripts (admins only)
	foreach ( ricoman_settings_defaults() as $key => $default ) {
		if ( ! isset( $input[ $key ] ) ) {
			$out[ $key ] = '';
			continue;
		}
		$val = wp_unslash( $input[ $key ] );
		if ( in_array( $key, $raw, true ) ) {
			$out[ $key ] = current_user_can( 'unfiltered_html' ) ? $val : wp_kses_post( $val );
		} elseif ( in_array( $key, $urls, true ) ) {
			$out[ $key ] = esc_url_raw( trim( $val ) );
		} elseif ( false !== strpos( $key, 'foot_address' ) || false !== strpos( $key, 'foot_hours' ) || false !== strpos( $key, 'nav_' ) || false !== strpos( $key, 'mega_' ) || false !== strpos( $key, 'foot_col' ) || false !== strpos( $key, 'foot_legal' ) || 'flow_banner' === $key ) {
			$out[ $key ] = sanitize_textarea_field( $val );
		} else {
			$out[ $key ] = sanitize_text_field( $val );
		}
	}
	return $out;
}

/** A labelled textarea / input row. */
function ricoman_field( $key, $label, $type = 'text', $hint = '', $rows = 3 ) {
	$val = ricoman_opt( $key );
	echo '<tr><th scope="row"><label for="rm-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
	if ( 'textarea' === $type ) {
		echo '<textarea id="rm-' . esc_attr( $key ) . '" name="ricoman_settings[' . esc_attr( $key ) . ']" rows="' . (int) $rows . '" class="large-text code">' . esc_textarea( $val ) . '</textarea>';
	} elseif ( 'image' === $type ) {
		echo '<input type="text" id="rm-' . esc_attr( $key ) . '" name="ricoman_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" class="regular-text rm-img-url"> <button type="button" class="button rm-img-pick">' . esc_html__( 'Choose image', 'ricoman' ) . '</button>';
	} else {
		echo '<input type="text" id="rm-' . esc_attr( $key ) . '" name="ricoman_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" class="regular-text">';
	}
	if ( $hint ) {
		echo '<p class="description">' . wp_kses_post( $hint ) . '</p>';
	}
	echo '</td></tr>';
}

function ricoman_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$line_hint = 'One per line, as <code>Label | /url</code>.';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ricoman Site', 'ricoman' ); ?></h1>
		<p><?php esc_html_e( 'Edit the header mega-menu, the footer and tracking scripts. Header & footer pull from here automatically.', 'ricoman' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'ricoman_settings_group' ); ?>

			<h2 class="title"><?php esc_html_e( 'Branding & top navigation', 'ricoman' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				ricoman_field( 'brand_logo', __( 'Logo (white, for the dark header)', 'ricoman' ), 'image', __( 'Optional. Leave blank to use the RICOMAN wordmark.', 'ricoman' ) );
				ricoman_field( 'nav_primary', __( 'Top menu items', 'ricoman' ), 'textarea', $line_hint . ' ' . __( 'Use <code>#products</code> as the URL to open the mega menu.', 'ricoman' ), 7 );
				ricoman_field( 'show_search', __( 'Show search box (1 = yes, 0 = no)', 'ricoman' ), 'text' );
				ricoman_field( 'login_url', __( 'Login link URL (blank = WordPress login)', 'ricoman' ), 'text' );
				ricoman_field( 'login_label', __( 'Login link label (blank = hide)', 'ricoman' ), 'text' );
				?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Products mega menu', 'ricoman' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				ricoman_field( 'mega_heading', __( 'Heading link text', 'ricoman' ), 'text' );
				ricoman_field( 'mega_heading_url', __( 'Heading link URL', 'ricoman' ), 'text' );
				ricoman_field( 'mega_categories', __( 'Categories column', 'ricoman' ), 'textarea', $line_hint, 6 );
				ricoman_field( 'mega_collections', __( 'Collections column', 'ricoman' ), 'textarea', __( 'One per line: <code>Name | sub-label | /url</code>.', 'ricoman' ), 5 );
				ricoman_field( 'mega_apps', __( 'By Application column', 'ricoman' ), 'textarea', $line_hint, 5 );
				ricoman_field( 'mega_card1_img', __( 'Promo card 1 image', 'ricoman' ), 'image' );
				ricoman_field( 'mega_card1_title', __( 'Promo card 1 title', 'ricoman' ), 'text' );
				ricoman_field( 'mega_card1_url', __( 'Promo card 1 link', 'ricoman' ), 'text' );
				ricoman_field( 'mega_card2_img', __( 'Promo card 2 image', 'ricoman' ), 'image' );
				ricoman_field( 'mega_card2_title', __( 'Promo card 2 title', 'ricoman' ), 'text' );
				ricoman_field( 'mega_card2_url', __( 'Promo card 2 link', 'ricoman' ), 'text' );
				?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Flow+ rolling banner', 'ricoman' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The rotating hero banner at the top of the Flow+ product page. One slide per line:', 'ricoman' ); ?> <code>image URL | eyebrow | heading | sub-text | button label | /button-url</code></p>
			<table class="form-table" role="presentation">
				<?php ricoman_field( 'flow_banner', __( 'Banner slides', 'ricoman' ), 'textarea', __( 'Tip: paste image URLs from the Media Library. Leave the button label blank for no button.', 'ricoman' ), 6 ); ?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Footer — contact & socials', 'ricoman' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				ricoman_field( 'foot_address', __( 'Address', 'ricoman' ), 'textarea', '', 3 );
				ricoman_field( 'foot_phone', __( 'Phone', 'ricoman' ), 'text' );
				ricoman_field( 'foot_email', __( 'Email', 'ricoman' ), 'text' );
				ricoman_field( 'foot_hours', __( 'Business hours', 'ricoman' ), 'textarea', '', 3 );
				ricoman_field( 'soc_instagram', __( 'Instagram URL', 'ricoman' ), 'text' );
				ricoman_field( 'soc_facebook', __( 'Facebook URL', 'ricoman' ), 'text' );
				ricoman_field( 'soc_linkedin', __( 'LinkedIn URL', 'ricoman' ), 'text' );
				ricoman_field( 'soc_youtube', __( 'YouTube URL', 'ricoman' ), 'text' );
				?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Footer — link columns', 'ricoman' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				ricoman_field( 'foot_col_about', __( 'About Us column', 'ricoman' ), 'textarea', $line_hint, 5 );
				ricoman_field( 'foot_col_products', __( 'Products column', 'ricoman' ), 'textarea', $line_hint, 8 );
				ricoman_field( 'foot_col_projects', __( 'Projects column', 'ricoman' ), 'textarea', $line_hint, 5 );
				ricoman_field( 'foot_col_other', __( 'Other Links column', 'ricoman' ), 'textarea', $line_hint, 6 );
				ricoman_field( 'foot_legal', __( 'Legal links (bottom bar)', 'ricoman' ), 'textarea', $line_hint, 5 );
				?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Tracking & custom code', 'ricoman' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Paste Google Tag Manager, analytics or verification snippets. Output verbatim on every page.', 'ricoman' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				ricoman_field( 'code_head', __( 'Inside <head>', 'ricoman' ), 'textarea', __( 'e.g. GTM / GA / meta verification.', 'ricoman' ), 4 );
				ricoman_field( 'code_body_open', __( 'After <body> opens', 'ricoman' ), 'textarea', __( 'e.g. GTM noscript.', 'ricoman' ), 4 );
				ricoman_field( 'code_footer', __( 'Before </body>', 'ricoman' ), 'textarea', __( 'e.g. chat widgets, deferred scripts.', 'ricoman' ), 4 );
				?>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	( function () {
		document.querySelectorAll( '.rm-img-pick' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! window.wp || ! wp.media ) { return; }
				var frame = wp.media( { title: 'Choose image', multiple: false, library: { type: 'image' } } );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					var input = btn.parentNode.querySelector( '.rm-img-url' );
					if ( input ) { input.value = a.url; }
				} );
				frame.open();
			} );
		} );
	} )();
	</script>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'ricoman-site' ) ) {
		wp_enqueue_media();
	}
} );

/* ------------------------------------------------- code injection (tracking) */
add_action( 'wp_head', function () {
	$c = (string) ricoman_opt( 'code_head' );
	if ( '' !== trim( $c ) ) {
		echo "\n" . $c . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- admin-provided tracking code.
	}
}, 99 );
add_action( 'wp_body_open', function () {
	$c = (string) ricoman_opt( 'code_body_open' );
	if ( '' !== trim( $c ) ) {
		echo "\n" . $c . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
} );
add_action( 'wp_footer', function () {
	$c = (string) ricoman_opt( 'code_footer' );
	if ( '' !== trim( $c ) ) {
		echo "\n" . $c . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 99 );
