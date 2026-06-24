<?php
/**
 * Admin experience — a branded "Control Center" hub, setup checklist,
 * quick-action tiles, a dashboard widget, admin-bar shortcut and login
 * branding. Pure back-office polish so the team has one calm place to run
 * the whole site.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Surface the one-time setup / launch tools (Image Alt backfill, Pull Missing
 * Images, …) in the Ricoman menu — they're hidden by default but the team needs
 * them while preparing for launch. */
add_filter( 'ricoman_show_setup_tools', '__return_true' );

/* -------------------------------------------------------------------------
 * Shared: the links + status the hub, widget and admin bar all use.
 * ---------------------------------------------------------------------- */

function ricoman_admin_links() {
	$home_id = (int) get_option( 'page_on_front' );
	return array(
		'home'        => $home_id ? admin_url( 'post.php?post=' . $home_id . '&action=edit' ) : admin_url( 'edit.php?post_type=page' ),
		'pages'       => admin_url( 'edit.php?post_type=page' ),
		'products'    => admin_url( 'edit.php?post_type=product' ),
		'projects'    => admin_url( 'edit.php?post_type=project' ),
		'chrome'      => admin_url( 'site-editor.php?path=/wp_template_part/all' ),
		'patterns'    => admin_url( 'site-editor.php?path=/patterns' ),
		'speed'       => admin_url( 'tools.php?page=ricoman-speed' ),
		'seo'         => admin_url( 'options-general.php?page=ricoman-seo' ),
		'ricobot'     => admin_url( 'options-general.php?page=ricoman-ricobot' ),
		'leads'       => admin_url( 'edit.php?post_type=lead' ),
		'transporter' => admin_url( 'tools.php?page=ricoman-transporter' ),
		// Marketing / growth tools.
		'tracking'    => admin_url( 'admin.php?page=ricoman-tracking' ),
		'pageseo'     => admin_url( 'admin.php?page=ricoman-page-seo' ),
		'catseo'      => admin_url( 'admin.php?page=ricoman-category-seo' ),
		'seoopt'      => admin_url( 'admin.php?page=ricoman-seo-optimiser' ),
		'seotargets'  => admin_url( 'admin.php?page=ricoman-seo-targets' ),
		// Catalogue & media tools.
		'configimg'   => admin_url( 'admin.php?page=ricoman-config-images' ),
		'catorder'    => admin_url( 'admin.php?page=ricoman-cat-order' ),
		'prodorder'   => admin_url( 'admin.php?page=ricoman-product-order' ),
		'imagealt'    => admin_url( 'admin.php?page=ricoman-image-alt' ),
		'pullimages'  => admin_url( 'admin.php?page=ricoman-pull-images' ),
		'mediaclean'  => admin_url( 'admin.php?page=ricoman-media-cleanup' ),
		// Launch tools.
		'redirects'   => admin_url( 'admin.php?page=ricoman-redirects' ),
		'golive'      => admin_url( 'admin.php?page=ricoman-go-live' ),
		'pushlive'    => admin_url( 'admin.php?page=ricoman-push-live' ),
		'permalinks'  => admin_url( 'options-permalink.php' ),
		'view'        => home_url( '/' ),
	);
}

/**
 * The "Things to do" launch checklist: key => [ label, done(bool), fix-link ].
 * Drives the progress bar at the top of the Control Center + the dashboard widget.
 */
function ricoman_admin_status() {
	$l     = ricoman_admin_links();
	$count = function ( $type ) {
		$c = wp_count_posts( $type );
		return $c && isset( $c->publish ) ? (int) $c->publish : 0;
	};
	$seo      = get_option( 'ricoman_seo', array() );
	$tracking = (array) get_option( 'ricoman_tracking', array() );
	$seo_link = ( function_exists( 'ricoman_seo_plugin_active' ) && ricoman_seo_plugin_active() ) ? $l['seoopt'] : $l['pageseo'];
	return array(
		'front'    => array( 'Homepage set as front page', 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ), $l['pages'] ),
		'logo'     => array( 'Site logo uploaded', has_custom_logo(), admin_url( 'site-editor.php' ) ),
		'perma'    => array( 'Pretty permalinks enabled', '' !== (string) get_option( 'permalink_structure' ), $l['permalinks'] ),
		'seo'      => array( 'SEO details filled in', is_array( $seo ) && ! empty( array_filter( $seo ) ), $l['seo'] ),
		'seoseed'  => array( 'Page titles & descriptions seeded', (bool) get_option( 'ricoman_seo_seeded_v1' ), $seo_link ),
		'tracking' => array( 'Analytics / tracking installed', ! empty( array_filter( $tracking ) ), $l['tracking'] ),
		'product'  => array( 'Products added', $count( 'product' ) > 0, $l['products'] ),
		'project'  => array( 'Projects added', $count( 'project' ) > 0, $l['projects'] ),
	);
}

/* -------------------------------------------------------------------------
 * Top-level "Ricoman" admin menu → the Control Center.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Ricoman', 'ricoman' ),
		__( 'Ricoman', 'ricoman' ),
		'edit_posts',
		'ricoman-hub',
		'ricoman_render_hub',
		'dashicons-lightbulb',
		2
	);
	add_submenu_page( 'ricoman-hub', __( 'Control Center', 'ricoman' ), __( 'Control Center', 'ricoman' ), 'edit_posts', 'ricoman-hub', 'ricoman_render_hub' );
}, 9 );

/**
 * Tidy the Ricoman left submenu so it mirrors the Control Center hub: the rare
 * setup tools are hidden from the nav (still reachable from the hub + by URL), and
 * the rest are grouped under the SAME section names the Control Center page uses
 * (Design · Growth & marketing · Catalogue & media · Launch). Section headers are
 * non-clickable and point at Control Center, so they can never 404. Runs after all
 * submenu pages are registered.
 */
add_action( 'admin_menu', function () {
	global $submenu;
	$parent = 'ricoman-hub';
	// 1) Hide rare / setup tools (each has a Control Center tile).
	foreach ( apply_filters( 'ricoman_menu_hide', array(
		'ricoman-config-images', 'ricoman-cat-order', 'ricoman-product-order',
		'ricoman-image-alt', 'ricoman-pull-images', 'ricoman-media-cleanup',
	) ) as $slug ) {
		remove_submenu_page( $parent, $slug );
	}
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}
	// 2) Group what's left under the Control Center section names (exact match).
	$groups = array(
		'Design'             => array( 'Header', 'Edit layout' ),
		'Growth & marketing' => array( 'SEO Targets', 'SEO Optimiser', 'Tracking' ),
		'Catalogue & media'  => array( 'Product Templates', 'Filter Data', 'Studio', 'Image WebP', 'RICOBOT' ),
		'Launch'             => array( 'Links', 'Redirects', 'Go Live', 'Push to Live' ),
	);
	$items   = $submenu[ $parent ];
	$used    = array();
	$ordered = array();
	// Control Center stays at the very top, ungrouped.
	foreach ( $items as $idx => $it ) {
		if ( 'ricoman-hub' === $it[2] ) {
			$ordered[]    = $it;
			$used[ $idx ] = true;
		}
	}
	$header = function ( $label ) {
		// Slug = ricoman-hub so a click just opens Control Center (never a 404).
		return array( '<span class="rm-subhead-lbl">' . esc_html( strtoupper( $label ) ) . '</span>', 'edit_posts', 'ricoman-hub', '', 'rm-subhead' );
	};
	foreach ( $groups as $gname => $keywords ) {
		$bucket = array();
		foreach ( $items as $idx => $it ) {
			if ( isset( $used[ $idx ] ) ) {
				continue;
			}
			$title = wp_strip_all_tags( $it[0] );
			foreach ( $keywords as $kw ) {
				if ( false !== stripos( $title, $kw ) ) {
					$bucket[]     = $it;
					$used[ $idx ] = true;
					break;
				}
			}
		}
		if ( $bucket ) {
			$ordered[] = $header( $gname );
			foreach ( $bucket as $b ) {
				$ordered[] = $b;
			}
		}
	}
	foreach ( $items as $idx => $it ) {
		if ( ! isset( $used[ $idx ] ) ) {
			$ordered[] = $it; // anything unmatched stays visible (ungrouped) so nothing is lost.
		}
	}
	$submenu[ $parent ] = $ordered;
}, 9999 );

/** Style the non-clickable submenu section headers. */
add_action( 'admin_head', function () {
	echo '<style>#adminmenu .rm-subhead a{pointer-events:none;cursor:default;color:#8c8f94 !important;text-transform:uppercase;font-size:10px;letter-spacing:.06em;font-weight:700;padding:8px 12px 2px;opacity:.85}#adminmenu .rm-subhead a:hover{background:transparent !important;color:#8c8f94 !important}</style>';
} );

function ricoman_render_hub() {
	$l      = ricoman_admin_links();
	$status = ricoman_admin_status();
	$done   = count( array_filter( $status, function ( $s ) { return $s[1]; } ) );
	$total  = count( $status );

	$tile = function ( $href, $icon, $title, $desc ) {
		printf(
			'<a class="rm-tile" href="%s"><span class="dashicons dashicons-%s"></span><strong>%s</strong><span class="rm-tile-d">%s</span></a>',
			esc_url( $href ),
			esc_attr( $icon ),
			esc_html( $title ),
			esc_html( $desc )
		);
	};
	?>
	<div class="wrap rm-cc">
		<div class="rm-hero">
			<div>
				<p class="rm-kick"><?php esc_html_e( 'Ricoman Control Center', 'ricoman' ); ?></p>
				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
				<p class="rm-sub"><?php esc_html_e( 'Everything you need to run the site, in one place.', 'ricoman' ); ?></p>
			</div>
			<a class="rm-view button button-hero" href="<?php echo esc_url( $l['view'] ); ?>" target="_blank"><?php esc_html_e( 'View site ↗', 'ricoman' ); ?></a>
		</div>

		<div class="rm-card rm-setup">
			<div class="rm-setup-head">
				<h2>✅ <?php esc_html_e( 'Things to do', 'ricoman' ); ?></h2>
				<span class="rm-progress"><?php echo esc_html( $done . ' / ' . $total ); ?> <?php esc_html_e( 'set up', 'ricoman' ); ?></span>
			</div>
			<div class="rm-bar"><span style="width:<?php echo esc_attr( $total ? round( $done / $total * 100 ) : 0 ); ?>%"></span></div>
			<p class="rm-setup-note"><?php echo $done >= $total ? esc_html__( 'All set — staging is ready. Use Go Live when you’re ready to launch.', 'ricoman' ) : esc_html( sprintf( __( '%d still to set up before launch.', 'ricoman' ), $total - $done ) ); ?></p>
			<ul class="rm-check">
				<?php foreach ( $status as $key => $s ) : ?>
					<li class="<?php echo $s[1] ? 'ok' : 'todo'; ?>">
						<span class="dashicons dashicons-<?php echo $s[1] ? 'yes-alt' : 'marker'; ?>"></span>
						<?php echo esc_html( $s[0] ); ?>
						<?php if ( ! $s[1] && ! empty( $s[2] ) ) : ?>
							<a href="<?php echo esc_url( $s[2] ); ?>"><?php esc_html_e( 'Set up →', 'ricoman' ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<h2 class="rm-h2"><?php esc_html_e( 'Content', 'ricoman' ); ?></h2>
		<div class="rm-grid">
			<?php
			$tile( $l['home'], 'admin-home', __( 'Edit Homepage', 'ricoman' ), __( 'Hero, ranges, projects & CTA', 'ricoman' ) );
			$tile( $l['pages'], 'admin-page', __( 'Pages', 'ricoman' ), __( 'About, Manufacturing, Lighting…', 'ricoman' ) );
			$tile( $l['products'], 'lightbulb', __( 'Products', 'ricoman' ), __( 'Flow+, Estrella, Neptune…', 'ricoman' ) );
			$tile( $l['projects'], 'portfolio', __( 'Projects', 'ricoman' ), __( 'Case studies & sectors', 'ricoman' ) );
			?>
		</div>

		<h2 class="rm-h2"><?php esc_html_e( 'Design', 'ricoman' ); ?></h2>
		<div class="rm-grid">
			<?php
			$tile( $l['chrome'], 'editor-kitchensink', __( 'Header & Footer', 'ricoman' ), __( 'Menu, logo, footer columns', 'ricoman' ) );
			$tile( $l['patterns'], 'layout', __( 'Patterns', 'ricoman' ), __( 'Reusable page sections', 'ricoman' ) );
			$tile( admin_url( 'site-editor.php?path=/wp_global_styles' ), 'admin-customizer', __( 'Colours & Fonts', 'ricoman' ), __( 'Global styles', 'ricoman' ) );
			?>
		</div>

		<h2 class="rm-h2"><?php esc_html_e( 'Growth & marketing', 'ricoman' ); ?></h2>
		<div class="rm-grid">
			<?php
			$tile( $l['leads'], 'email', __( 'Leads', 'ricoman' ), __( 'Enquiries & CRM', 'ricoman' ) );
			$tile( $l['seotargets'], 'chart-line', __( 'SEO Targets', 'ricoman' ), __( 'Focus keywords + coverage review', 'ricoman' ) );
			$tile( $l['tracking'], 'chart-area', __( 'Tracking & Scripts', 'ricoman' ), __( 'GA4, GTM, pixels, custom code', 'ricoman' ) );
			$tile( $l['speed'], 'performance', __( 'SEO & Speed', 'ricoman' ), __( 'Scores + PageSpeed', 'ricoman' ) );
			$tile( $l['seo'], 'search', __( 'SEO Settings', 'ricoman' ), __( 'Org, social, address, AI', 'ricoman' ) );
			// Page SEO / Category SEO screens are hidden when an SEO plugin (Yoast)
			// owns SEO; in that case show the Yoast-aware SEO Optimiser instead.
			if ( function_exists( 'ricoman_seo_plugin_active' ) && ricoman_seo_plugin_active() ) {
				$tile( $l['seoopt'], 'media-text', __( 'SEO Optimiser', 'ricoman' ), __( 'Push optimised titles & meta into Yoast', 'ricoman' ) );
			} else {
				$tile( $l['pageseo'], 'media-text', __( 'Page SEO', 'ricoman' ), __( 'Titles & meta descriptions', 'ricoman' ) );
				$tile( $l['catseo'], 'category', __( 'Category SEO', 'ricoman' ), __( 'Range landing-page copy', 'ricoman' ) );
			}
			?>
		</div>

		<h2 class="rm-h2"><?php esc_html_e( 'Catalogue & media', 'ricoman' ); ?></h2>
		<div class="rm-grid">
			<?php
			$tile( $l['configimg'], 'images-alt2', __( 'Configurator Images', 'ricoman' ), __( 'Master option-tile images', 'ricoman' ) );
			$tile( $l['catorder'], 'sort', __( 'Reorder Categories', 'ricoman' ), __( 'Menu & grid order', 'ricoman' ) );
			$tile( $l['prodorder'], 'sort', __( 'Reorder Products', 'ricoman' ), __( 'Order within a category', 'ricoman' ) );
			$tile( $l['imagealt'], 'universal-access-alt', __( 'Image Alt Text', 'ricoman' ), __( 'SEO / accessibility backfill', 'ricoman' ) );
			$tile( $l['mediaclean'], 'admin-media', __( 'Media Cleanup', 'ricoman' ), __( 'Remove duplicate images', 'ricoman' ) );
			$tile( $l['ricobot'], 'rest-api', __( 'RICOBOT', 'ricoman' ), __( 'Live product data API', 'ricoman' ) );
			?>
		</div>

		<h2 class="rm-h2"><?php esc_html_e( 'Launch', 'ricoman' ); ?></h2>
		<div class="rm-grid">
			<?php
			$tile( $l['redirects'], 'randomize', __( 'Links & Redirects', 'ricoman' ), __( 'Old-URL 301 map', 'ricoman' ) );
			$tile( $l['transporter'], 'migrate', __( 'Content Transporter', 'ricoman' ), __( 'Import / convert content', 'ricoman' ) );
			$tile( $l['golive'], 'flag', __( 'Go Live', 'ricoman' ), __( 'Pre-flight & launch finalise', 'ricoman' ) );
			$tile( $l['pushlive'], 'superhero', __( 'Push to Live', 'ricoman' ), __( 'Deploy theme code to live', 'ricoman' ) );
			?>
		</div>
	</div>
	<?php
}

/* Control Center styling (scoped + a touch of global brand polish). */
add_action( 'admin_head', function () {
	$screen = get_current_screen();
	$on_hub = $screen && 'toplevel_page_ricoman-hub' === $screen->id;
	?>
	<style>
		#adminmenu .toplevel_page_ricoman-hub .wp-menu-image:before { color:#7eb0ff; }
		<?php if ( $on_hub ) : ?>
		.rm-cc { max-width:1180px; }
		.rm-cc .rm-hero { display:flex; align-items:center; justify-content:space-between; gap:24px; background:linear-gradient(120deg,#0b0b0c,#16335c 60%,#1d4ed8); color:#fff; border-radius:16px; padding:34px 38px; margin:18px 0 26px; }
		.rm-cc .rm-hero h1 { color:#fff; font-size:30px; margin:.1em 0 .15em; font-weight:600; letter-spacing:-.02em; }
		.rm-cc .rm-kick { text-transform:uppercase; letter-spacing:.22em; font-size:11px; font-weight:700; color:#9ec1ff; margin:0; }
		.rm-cc .rm-sub { color:rgba(255,255,255,.8); margin:0; }
		.rm-cc .rm-view { background:#fff !important; color:#16335c !important; border:0 !important; border-radius:999px !important; font-weight:600 !important; box-shadow:none !important; }
		.rm-card { background:#fff; border:1px solid #e3e3e1; border-radius:14px; padding:22px 24px; margin-bottom:26px; }
		.rm-setup-head { display:flex; align-items:center; justify-content:space-between; }
		.rm-setup h2 { margin:0; font-size:16px; }
		.rm-progress { font-weight:600; color:#1d4ed8; }
		.rm-setup-note { margin:0 0 14px; color:#555; font-size:13px; }
		.rm-bar { height:8px; background:#eef0f2; border-radius:99px; overflow:hidden; margin:14px 0 18px; }
		.rm-bar span { display:block; height:100%; background:linear-gradient(90deg,#16335c,#1d4ed8); border-radius:99px; transition:width .4s; }
		.rm-check { margin:0; display:grid; grid-template-columns:repeat(2,1fr); gap:10px 28px; }
		.rm-check li { margin:0; display:flex; align-items:center; gap:8px; color:#444; }
		.rm-check li .dashicons { font-size:18px; width:18px; height:18px; }
		.rm-check li.ok .dashicons { color:#1a9d4b; }
		.rm-check li.todo { color:#9a6a00; }
		.rm-check li.todo .dashicons { color:#d9a300; }
		.rm-check li a { margin-left:auto; text-decoration:none; font-weight:600; }
		.rm-h2 { font-size:13px; text-transform:uppercase; letter-spacing:.14em; color:#777; margin:24px 0 12px; }
		.rm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; }
		.rm-tile { display:flex; flex-direction:column; gap:4px; background:#fff; border:1px solid #e3e3e1; border-radius:14px; padding:20px; text-decoration:none; color:#16161a; transition:.18s; min-height:104px; }
		.rm-tile:hover { border-color:#1d4ed8; box-shadow:0 12px 30px -18px rgba(29,78,216,.55); transform:translateY(-2px); color:#16161a; }
		.rm-tile .dashicons { font-size:26px; width:26px; height:26px; color:#1d4ed8; margin-bottom:6px; }
		.rm-tile strong { font-size:15px; }
		.rm-tile-d { color:#777; font-size:12.5px; }
		<?php endif; ?>
	</style>
	<?php
} );

/* -------------------------------------------------------------------------
 * Dashboard widget — "Ricoman at a glance".
 * ---------------------------------------------------------------------- */

add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'ricoman_glance', __( 'Ricoman — at a glance', 'ricoman' ), 'ricoman_dashboard_widget' );
	// Float it to the top.
	global $wp_meta_boxes;
	$normal = &$wp_meta_boxes['dashboard']['normal']['core'];
	if ( isset( $normal['ricoman_glance'] ) ) {
		$w = array( 'ricoman_glance' => $normal['ricoman_glance'] );
		unset( $normal['ricoman_glance'] );
		$normal = $w + $normal;
	}
} );

function ricoman_dashboard_widget() {
	$l      = ricoman_admin_links();
	$status = ricoman_admin_status();
	$done   = count( array_filter( $status, function ( $s ) { return $s[1]; } ) );
	$total  = count( $status );
	echo '<p style="margin-top:0">' . esc_html__( 'Things to do', 'ricoman' ) . ': <strong>' . esc_html( $done . '/' . $total ) . '</strong> ' . esc_html__( 'set up.', 'ricoman' ) . '</p>';
	echo '<p>';
	$btn = function ( $href, $label ) {
		echo '<a class="button" style="margin:0 6px 6px 0" href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>';
	};
	$btn( admin_url( 'admin.php?page=ricoman-hub' ), __( 'Control Center', 'ricoman' ) );
	$btn( $l['home'], __( 'Edit Homepage', 'ricoman' ) );
	$btn( $l['tracking'], __( 'Tracking & Scripts', 'ricoman' ) );
	$btn( $l['speed'], __( 'SEO & Speed', 'ricoman' ) );
	echo '</p>';
}

/* -------------------------------------------------------------------------
 * Admin-bar shortcut (front + back).
 * ---------------------------------------------------------------------- */

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$l = ricoman_admin_links();
	$bar->add_node( array( 'id' => 'ricoman', 'title' => '💡 ' . __( 'Ricoman', 'ricoman' ), 'href' => admin_url( 'admin.php?page=ricoman-hub' ) ) );
	$kids = array(
		'rm-home'    => array( __( 'Edit Homepage', 'ricoman' ), $l['home'] ),
		'rm-prod'    => array( __( 'Products', 'ricoman' ), $l['products'] ),
		'rm-proj'    => array( __( 'Projects', 'ricoman' ), $l['projects'] ),
		'rm-speed'   => array( __( 'SEO & Speed', 'ricoman' ), $l['speed'] ),
		'rm-ricobot' => array( __( 'RICOBOT', 'ricoman' ), $l['ricobot'] ),
	);
	foreach ( $kids as $id => $node ) {
		$bar->add_node( array( 'parent' => 'ricoman', 'id' => $id, 'title' => $node[0], 'href' => $node[1] ) );
	}
}, 80 );

/* -------------------------------------------------------------------------
 * Login screen branding.
 * ---------------------------------------------------------------------- */

add_action( 'login_enqueue_scripts', function () {
	$logo = '';
	$id   = get_theme_mod( 'custom_logo' );
	if ( $id ) {
		$src = wp_get_attachment_image_src( $id, 'medium' );
		if ( $src ) {
			$logo = $src[0];
		}
	}
	?>
	<style>
		body.login { background:#0b0b0c; }
		.login h1 a {
			<?php if ( $logo ) : ?>background-image:url('<?php echo esc_url( $logo ); ?>');<?php else : ?>background:none;text-indent:0;width:auto;height:auto;font:600 26px/1.1 Poppins,system-ui,sans-serif;color:#fff;letter-spacing:.18em;<?php endif; ?>
			background-size:contain; background-position:center; width:100%;
		}
		<?php if ( ! $logo ) : ?>.login h1 a:after { content:"RICOMAN"; }<?php endif; ?>
		.login label { color:#cfd2d8; }
		.login #backtoblog a, .login #nav a { color:#9ec1ff; }
		.wp-core-ui .button-primary { background:#1d4ed8; border-color:#1d4ed8; }
		.wp-core-ui .button-primary:hover { background:#16335c; border-color:#16335c; }
		.login form { border-radius:12px; }
	</style>
	<?php
} );

add_filter( 'login_headerurl', function () { return home_url( '/' ); } );
add_filter( 'login_headertext', function () { return get_bloginfo( 'name' ); } );

/* -------------------------------------------------------------------------
 * Friendly admin footer.
 * ---------------------------------------------------------------------- */

add_filter( 'admin_footer_text', function () {
	return wp_kses_post( sprintf(
		/* translators: %s: control center link */
		__( 'Ricoman theme · need a hand? Open the %s.', 'ricoman' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=ricoman-hub' ) ) . '">' . esc_html__( 'Control Center', 'ricoman' ) . '</a>'
	) );
} );
