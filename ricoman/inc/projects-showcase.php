<?php
/**
 * Projects showcase carousel + a hand-pick "Featured Projects" admin screen.
 *
 * Ricoman → Featured Projects: tick the projects to feature and drag them into
 * order (stored in the ricoman_featured_projects option). The [ricoman_projects_showcase]
 * shortcode renders them as a horizontally-scrolling card carousel (photo, name,
 * location, sector) with prev/next arrows — for the Lighting Design page (or any
 * page). Falls back to the most recent projects if nothing is picked.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Saved, ordered list of featured project IDs. */
function ricoman_featured_project_ids() {
	$ids = get_option( 'ricoman_featured_projects', array() );
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}
	return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

/* ----------------------------------------------------------- admin screen -- */
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	add_submenu_page(
		'ricoman-hub',
		__( 'Featured Projects', 'ricoman' ),
		__( 'Featured Projects', 'ricoman' ),
		'edit_pages',
		'ricoman-featured-projects',
		'ricoman_featured_projects_page'
	);
}, 28 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( isset( $_GET['page'] ) && 'ricoman-featured-projects' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_enqueue_script( 'jquery-ui-sortable' );
	}
} );

function ricoman_featured_projects_page() {
	$selected = ricoman_featured_project_ids();
	$sel_map  = array_flip( $selected );

	// All projects: selected first (in saved order), then the rest by title.
	$all = get_posts( array(
		'post_type'      => 'project',
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'posts_per_page' => 300,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$rest    = array_values( array_diff( $all, $selected ) );
	$ordered = array_merge( array_values( array_filter( $selected, function ( $id ) use ( $all ) { return in_array( (int) $id, $all, true ); } ) ), $rest );

	echo '<div class="wrap"><h1>' . esc_html__( 'Featured Projects', 'ricoman' ) . '</h1>';
	echo '<p class="description" style="max-width:720px">' . esc_html__( 'Tick the projects you want in the Lighting Design showcase, and drag them into the order they should appear. Then drop the "Projects showcase" pattern (or the [ricoman_projects_showcase] shortcode) onto the page.', 'ricoman' ) . '</p>';

	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Featured projects saved.', 'ricoman' ) . '</p></div>';
	}
	if ( ! $ordered ) {
		echo '<p><em>' . esc_html__( 'No projects found yet.', 'ricoman' ) . '</em></p></div>';
		return;
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ricoman_save_featured_projects">';
	wp_nonce_field( 'ricoman_featured_projects' );
	echo '<input type="hidden" name="fp_order" id="rm-fp-order" value="">';
	echo '<ul id="rm-fp-list" style="max-width:720px;margin:16px 0;padding:0;list-style:none">';
	foreach ( $ordered as $pid ) {
		$pid   = (int) $pid;
		$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
		$chk   = isset( $sel_map[ $pid ] ) ? ' checked' : '';
		echo '<li data-id="' . esc_attr( $pid ) . '" style="display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:8px 12px;margin:0 0 6px">';
		echo '<span class="rm-fp-handle dashicons dashicons-move" style="cursor:grab;color:#787c82"></span>';
		echo '<label style="display:flex;align-items:center;gap:12px;flex:1;margin:0;cursor:pointer">';
		echo '<input type="checkbox" class="rm-fp-cb"' . $chk . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $thumb ) {
			echo '<img src="' . esc_url( $thumb ) . '" alt="" width="46" height="46" style="width:46px;height:46px;object-fit:cover;border-radius:4px">';
		}
		echo '<span><strong>' . esc_html( get_the_title( $pid ) ) . '</strong>';
		$loc = trim( wp_strip_all_tags( (string) get_post_meta( $pid, 'area', true ) ) );
		$sec = function_exists( 'ricoman_first_term_name' ) ? ricoman_first_term_name( $pid, array( 'project-cat', 'application' ) ) : '';
		$meta = trim( implode( ' · ', array_filter( array( $loc, $sec ) ) ) );
		if ( '' !== $meta ) {
			echo '<br><span style="color:#787c82;font-size:12px">' . esc_html( $meta ) . '</span>';
		}
		echo '</span></label></li>';
	}
	echo '</ul>';
	submit_button( __( 'Save featured projects', 'ricoman' ) );
	echo '</form></div>';
	?>
	<script>
	jQuery(function($){
		$('#rm-fp-list').sortable({ handle: '.rm-fp-handle', axis: 'y' });
		$('#rm-fp-list').closest('form').on('submit', function(){
			var ids = [];
			$('#rm-fp-list > li').each(function(){
				if ($(this).find('.rm-fp-cb').is(':checked')) { ids.push($(this).data('id')); }
			});
			$('#rm-fp-order').val(ids.join(','));
		});
	});
	</script>
	<?php
}

add_action( 'admin_post_ricoman_save_featured_projects', function () {
	if ( ! current_user_can( 'edit_pages' ) || ! check_admin_referer( 'ricoman_featured_projects' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$raw = isset( $_POST['fp_order'] ) ? (string) wp_unslash( $_POST['fp_order'] ) : '';
	$ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
	$ids = array_values( array_filter( $ids, function ( $id ) { return 'project' === get_post_type( $id ); } ) );
	update_option( 'ricoman_featured_projects', $ids, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-featured-projects', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ------------------------------------------------------------- shortcode -- */
/** [ricoman_projects_showcase heading="…" link="/projects/" link_label="…" count="12" ids="1,2"] */
function ricoman_projects_showcase_sc( $atts ) {
	$a = shortcode_atts( array(
		'heading'    => 'Projects by our lighting design team',
		'link'       => '/projects/',
		'link_label' => 'Explore all projects',
		'count'      => 12,
		'ids'        => '',
	), $atts, 'ricoman_projects_showcase' );

	// Which projects: explicit ids > saved featured list > most recent.
	if ( '' !== $a['ids'] ) {
		$ids = array_values( array_filter( array_map( 'absint', explode( ',', $a['ids'] ) ) ) );
	} else {
		$ids = ricoman_featured_project_ids();
	}
	if ( ! $ids ) {
		$ids = get_posts( array(
			'post_type'      => 'project',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $a['count'] ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
	}
	$ids = array_values( array_filter( $ids, function ( $id ) { return 'project' === get_post_type( $id ) && 'publish' === get_post_status( $id ); } ) );
	if ( ! $ids ) {
		return '';
	}

	$src = get_theme_file_path( 'assets/js/projects-carousel.js' );
	wp_enqueue_script( 'ricoman-projects-carousel', get_theme_file_uri( 'assets/js/projects-carousel.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );

	$cards = '';
	foreach ( $ids as $pid ) {
		$pid   = (int) $pid;
		$img   = function_exists( 'ricoman_project_img' ) ? ricoman_project_img( $pid ) : (string) get_the_post_thumbnail_url( $pid, 'large' );
		$title = trim( (string) get_post_meta( $pid, 'lighting_project_title', true ) );
		if ( '' === $title ) {
			$title = get_the_title( $pid );
		}
		$loc = trim( wp_strip_all_tags( (string) get_post_meta( $pid, 'area', true ) ) );
		$sec = function_exists( 'ricoman_first_term_name' ) ? ricoman_first_term_name( $pid, array( 'project-cat', 'application' ) ) : '';
		$cards .= '<a class="rm-projshow-card" href="' . esc_url( get_permalink( $pid ) ) . '">'
			. '<span class="rm-projshow-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span>'
			. '<span class="rm-projshow-name">' . esc_html( $title ) . '</span>'
			. ( '' !== $loc ? '<span class="rm-projshow-loc">' . esc_html( $loc ) . '</span>' : '' )
			. ( '' !== $sec ? '<span class="rm-projshow-sector">' . esc_html( $sec ) . '</span>' : '' )
			. '</a>';
	}

	$head = '<div class="rm-projshow-head">'
		. '<h2 class="rm-projshow-title">' . esc_html( $a['heading'] ) . '</h2>'
		. ( $a['link'] ? '<a class="rm-projshow-all" href="' . esc_url( $a['link'] ) . '">' . esc_html( $a['link_label'] ) . ' &rsaquo;</a>' : '' )
		. '</div>';

	return '<div class="rm-section rm-projshow">' . $head
		. '<div class="rm-projshow-view">'
		. '<button type="button" class="rm-projshow-arrow rm-projshow-prev" aria-label="Previous projects" hidden>&lsaquo;</button>'
		. '<div class="rm-projshow-track">' . $cards . '</div>'
		. '<button type="button" class="rm-projshow-arrow rm-projshow-next" aria-label="More projects">&rsaquo;</button>'
		. '</div></div>';
}
add_shortcode( 'ricoman_projects_showcase', 'ricoman_projects_showcase_sc' );

/** Editable pattern. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/projects-showcase', array(
		'title'       => 'Projects showcase (carousel)',
		'description' => 'Horizontally-scrolling cards of your hand-picked projects (Ricoman → Featured Projects). Photo, name, location, sector.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">'
			. '<!-- wp:shortcode -->[ricoman_projects_showcase]<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->',
	) );
}, 14 );
