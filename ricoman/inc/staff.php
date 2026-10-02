<?php
/**
 * Ricoman staff / team profiles.
 *
 * A `staff` post type with name (title), photo (featured image), job title,
 * email, phone and an optional bio (editor). Staff can be tagged onto news
 * articles and projects, and dropped onto any page via [ricoman_staff] /
 * [ricoman_team] with a choice of which fields show. Each staff member has a
 * public profile page (/team/<name>/) that lists all their tagged content.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * Post type
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type( 'staff', array(
		'labels'        => array(
			'name'          => __( 'Team', 'ricoman' ),
			'singular_name' => __( 'Staff member', 'ricoman' ),
			'menu_name'     => __( 'Team', 'ricoman' ),
			'add_new_item'  => __( 'Add staff member', 'ricoman' ),
			'edit_item'     => __( 'Edit staff member', 'ricoman' ),
			'all_items'     => __( 'All staff', 'ricoman' ),
		),
		'public'        => true,
		'has_archive'   => false,
		'menu_icon'     => 'dashicons-groups',
		'menu_position' => 26,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		'rewrite'       => array( 'slug' => 'team', 'with_front' => false ),
		'show_in_rest'  => true,
	) );
} );

// One-time rewrite flush so /team/<name>/ resolves.
add_action( 'init', function () {
	if ( get_option( 'ricoman_staff_rewrite_v1' ) ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'ricoman_staff_rewrite_v1', '1' );
}, 11 );

/* ---------------------------------------------------------------------------
 * Profile fields (job title / email / phone). Photo = featured image.
 * ------------------------------------------------------------------------- */
add_action( 'add_meta_boxes_staff', function () {
	add_meta_box( 'ricoman_staff_fields', __( 'Profile details', 'ricoman' ), 'ricoman_staff_fields_box', 'staff', 'normal', 'high' );
} );

function ricoman_staff_fields_box( $post ) {
	wp_nonce_field( 'ricoman_staff_fields', 'ricoman_staff_fields_nonce' );
	$v = function ( $k ) use ( $post ) { return esc_attr( (string) get_post_meta( $post->ID, $k, true ) ); };
	echo '<style>.rms-fld{margin:0 0 14px}.rms-fld label{display:block;font-weight:600;margin:0 0 4px}.rms-fld input{width:100%;max-width:420px}</style>';
	echo '<p class="description">' . esc_html__( 'The name is the title above; the photo is the Featured image (right). The bio is optional (the editor).', 'ricoman' ) . '</p>';
	echo '<div class="rms-fld"><label>' . esc_html__( 'Job title', 'ricoman' ) . '</label><input type="text" name="_rms_role" value="' . $v( '_rms_role' ) . '" placeholder="e.g. Lighting Designer"></div>';
	echo '<div class="rms-fld"><label>' . esc_html__( 'Email', 'ricoman' ) . '</label><input type="email" name="_rms_email" value="' . $v( '_rms_email' ) . '" placeholder="name@ricoman.com"></div>';
	echo '<div class="rms-fld"><label>' . esc_html__( 'Phone', 'ricoman' ) . '</label><input type="text" name="_rms_phone" value="' . $v( '_rms_phone' ) . '" placeholder="0161 …"></div>';
}

add_action( 'save_post_staff', function ( $pid ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['ricoman_staff_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_staff_fields_nonce'] ) ), 'ricoman_staff_fields' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	$map = array( '_rms_role' => 'sanitize_text_field', '_rms_email' => 'sanitize_email', '_rms_phone' => 'sanitize_text_field' );
	foreach ( $map as $k => $san ) {
		if ( isset( $_POST[ $k ] ) ) {
			$val = call_user_func( $san, wp_unslash( $_POST[ $k ] ) );
			if ( '' !== $val ) {
				update_post_meta( $pid, $k, $val );
			} else {
				delete_post_meta( $pid, $k );
			}
		}
	}
} );

/* ---------------------------------------------------------------------------
 * Tag staff onto news + projects (multi-select). Stored as multiple
 * `_ricoman_staff` meta rows so we can query "all content for this person".
 * ------------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'news', 'project' ) as $pt ) {
		if ( post_type_exists( $pt ) ) {
			add_meta_box( 'ricoman_staff_tag', __( 'Ricoman staff', 'ricoman' ), 'ricoman_staff_tag_box', $pt, 'side', 'default' );
		}
	}
} );

function ricoman_staff_tag_box( $post ) {
	wp_nonce_field( 'ricoman_staff_tag', 'ricoman_staff_tag_nonce' );
	$people = get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
	if ( ! $people ) {
		echo '<p class="description">' . esc_html__( 'No staff yet — add people under Team.', 'ricoman' ) . '</p>';
		return;
	}
	$cur = array_map( 'intval', (array) get_post_meta( $post->ID, '_ricoman_staff', false ) );
	echo '<p class="description">' . esc_html__( 'Tag the people involved. They appear on this page and it shows on their profile.', 'ricoman' ) . '</p>';
	echo '<div style="max-height:200px;overflow:auto">';
	foreach ( $people as $p ) {
		echo '<label style="display:block;margin:3px 0"><input type="checkbox" name="ricoman_staff[]" value="' . (int) $p->ID . '"' . checked( in_array( (int) $p->ID, $cur, true ), true, false ) . '> ' . esc_html( get_the_title( $p ) ) . '</label>';
	}
	echo '</div>';
}

add_action( 'save_post', function ( $pid, $post ) {
	if ( ! in_array( $post->post_type, array( 'news', 'project' ), true ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $pid ) ) {
		return;
	}
	if ( ! isset( $_POST['ricoman_staff_tag_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_staff_tag_nonce'] ) ), 'ricoman_staff_tag' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	delete_post_meta( $pid, '_ricoman_staff' );
	$sel = isset( $_POST['ricoman_staff'] ) ? array_unique( array_map( 'absint', (array) wp_unslash( $_POST['ricoman_staff'] ) ) ) : array();
	foreach ( $sel as $sid ) {
		if ( $sid ) {
			add_post_meta( $pid, '_ricoman_staff', $sid );
		}
	}
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * Rendering
 * ------------------------------------------------------------------------- */

/** Normalise the requested field list (default = the lot). */
function ricoman_staff_fields_list( $fields ) {
	$all = array( 'photo', 'name', 'title', 'email', 'phone', 'bio', 'blurb' );
	$fields = trim( (string) $fields );
	if ( '' === $fields ) {
		return array( 'photo', 'name', 'title', 'email', 'phone' );
	}
	$out = array();
	foreach ( explode( ',', strtolower( $fields ) ) as $f ) {
		$f = trim( $f );
		if ( 'role' === $f || 'job' === $f ) {
			$f = 'title';
		}
		if ( in_array( $f, $all, true ) ) {
			$out[] = $f;
		}
	}
	return $out ? $out : array( 'photo', 'name', 'title' );
}

/** One staff card with a chosen set of fields; name + photo link to the profile. */
function ricoman_staff_card( $id, $fields = '', $linked = true ) {
	$id = (int) $id;
	if ( ! $id || 'staff' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		return '';
	}
	$show  = ricoman_staff_fields_list( $fields );
	$name  = get_the_title( $id );
	$link  = get_permalink( $id );
	$role  = (string) get_post_meta( $id, '_rms_role', true );
	$email = (string) get_post_meta( $id, '_rms_email', true );
	$phone = (string) get_post_meta( $id, '_rms_phone', true );
	$photo = has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'medium' ) : '';

	$h = '<div class="rm-staff-card">';
	if ( in_array( 'photo', $show, true ) && $photo ) {
		$img = '<span class="rm-staff-photo" style="background-image:url(' . esc_url( $photo ) . ')" role="img" aria-label="' . esc_attr( $name ) . '"></span>';
		$h  .= $linked ? '<a class="rm-staff-photolink" href="' . esc_url( $link ) . '">' . $img . '</a>' : $img;
	}
	$h .= '<div class="rm-staff-meta">';
	if ( in_array( 'name', $show, true ) ) {
		$h .= '<p class="rm-staff-name">' . ( $linked ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</p>';
	}
	if ( in_array( 'title', $show, true ) && '' !== $role ) {
		$h .= '<p class="rm-staff-role">' . esc_html( $role ) . '</p>';
	}
	if ( in_array( 'email', $show, true ) && '' !== $email ) {
		$h .= '<p class="rm-staff-contact"><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
	}
	if ( in_array( 'phone', $show, true ) && '' !== $phone ) {
		$h .= '<p class="rm-staff-contact"><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></p>';
	}
	if ( in_array( 'bio', $show, true ) ) {
		$bio = get_post_field( 'post_content', $id );
		if ( '' !== trim( (string) $bio ) ) {
			$h .= '<div class="rm-staff-bio">' . wp_kses_post( wpautop( $bio ) ) . '</div>';
		}
	}
	// A short one-line blurb (trimmed from the bio) for compact cards.
	if ( in_array( 'blurb', $show, true ) ) {
		$bio = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) );
		if ( '' !== $bio ) {
			$h .= '<p class="rm-staff-blurb">' . esc_html( wp_trim_words( $bio, 28, '…' ) ) . '</p>';
		}
	}
	$h .= '</div></div>';
	return $h;
}

/** [ricoman_staff id="123" fields="photo,name,title,email,phone,bio"] */
add_shortcode( 'ricoman_staff', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0, 'fields' => '' ), $atts, 'ricoman_staff' );
	$id   = (int) $atts['id'];
	if ( ! $id && 'staff' === get_post_type( get_the_ID() ) ) {
		$id = (int) get_the_ID();
	}
	return $id ? '<div class="rm-staff">' . ricoman_staff_card( $id, $atts['fields'] ) . '</div>' : '';
} );

/** [ricoman_team fields="photo,name,title" limit="24"] — a grid of all staff. */
add_shortcode( 'ricoman_team', function ( $atts ) {
	$atts = shortcode_atts( array( 'fields' => 'photo,name,title,email', 'limit' => 48 ), $atts, 'ricoman_team' );
	$ids  = get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'numberposts' => (int) $atts['limit'], 'orderby' => 'menu_order title', 'order' => 'ASC', 'fields' => 'ids' ) );
	if ( ! $ids ) {
		return '';
	}
	$cards = '';
	foreach ( $ids as $sid ) {
		$cards .= ricoman_staff_card( $sid, $atts['fields'] );
	}
	return '<div class="rm-team-grid">' . $cards . '</div>';
} );

/** Staff tagged on a given post (news/project) — a "People" credit row. */
function ricoman_staff_for_post( $pid, $fields = 'photo,name,title,email' ) {
	$ids = array_map( 'intval', (array) get_post_meta( $pid, '_ricoman_staff', false ) );
	$ids = array_values( array_filter( array_unique( $ids ) ) );
	if ( ! $ids ) {
		return '';
	}
	$cards = '';
	foreach ( $ids as $sid ) {
		$cards .= ricoman_staff_card( $sid, $fields );
	}
	if ( '' === $cards ) {
		return '';
	}
	return '<div class="rm-section rm-staff-credit"><div class="rm-pp-wrap"><h2 class="rm-shead">' . esc_html__( 'People on this', 'ricoman' ) . '</h2><div class="rm-team-grid">' . $cards . '</div></div></div>';
}

/** Show the People credit at the end of a news article / project. */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! ( is_singular( 'news' ) || is_singular( 'project' ) ) ) {
		return $content;
	}
	$credit = ricoman_staff_for_post( get_the_ID() );
	return $credit ? $content . $credit : $content;
}, 30 );

/* ---------------------------------------------------------------------------
 * Staff profile page: profile + all their tagged content.
 * ------------------------------------------------------------------------- */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'staff' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$pid = get_the_ID();

	// Profile header (photo + name + role + contact + bio = the editor content).
	$crumb = function_exists( 'shortcode_exists' ) && shortcode_exists( 'ricoman_breadcrumbs' ) ? do_shortcode( '[ricoman_breadcrumbs]' ) : '';
	$role  = (string) get_post_meta( $pid, '_rms_role', true );
	$email = (string) get_post_meta( $pid, '_rms_email', true );
	$phone = (string) get_post_meta( $pid, '_rms_phone', true );
	$photo = has_post_thumbnail( $pid ) ? get_the_post_thumbnail_url( $pid, 'large' ) : '';

	$info  = '<h1 class="rm-staffp-name">' . esc_html( get_the_title( $pid ) ) . '</h1>';
	if ( '' !== $role ) {
		$info .= '<p class="rm-staffp-role">' . esc_html( $role ) . '</p>';
	}
	$contact = '';
	if ( '' !== $email ) {
		$contact .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	}
	if ( '' !== $phone ) {
		$contact .= '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( '' !== $contact ) {
		$info .= '<p class="rm-staffp-contact">' . $contact . '</p>';
	}
	$photo_h = $photo ? '<span class="rm-staffp-photo" style="background-image:url(' . esc_url( $photo ) . ')" role="img" aria-label="' . esc_attr( get_the_title( $pid ) ) . '"></span>' : '';

	$out  = '<div class="rm-section rm-staffp-head"><div class="rm-pp-wrap">';
	$out .= '<div class="rm-pp-crumb">' . $crumb . '</div>';
	$out .= '<div class="rm-staffp-top">' . $photo_h . '<div class="rm-staffp-info">' . $info . '</div></div>';
	$out .= '</div></div>';

	// Editable body — whatever the team builds in the block editor for this person
	// (about, images, quotes, patterns) renders full-width as the profile body.
	if ( '' !== trim( (string) $content ) ) {
		$out .= '<div class="rm-section rm-staffp-body"><div class="rm-pp-wrap rm-staffp-bodyin">' . $content . '</div></div>';
	}

	// Their content — news + projects tagged with this person.
	$q = new WP_Query( array(
		'post_type'      => array_values( array_filter( array( 'project', 'news' ), 'post_type_exists' ) ),
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( array( 'key' => '_ricoman_staff', 'value' => $pid ) ),
	) );
	if ( $q->have_posts() ) {
		$cards = '';
		while ( $q->have_posts() ) {
			$q->the_post();
			$cid   = get_the_ID();
			$img   = '';
			if ( 'project' === get_post_type( $cid ) && function_exists( 'ricoman_project_img' ) ) {
				$img = ricoman_project_img( $cid );
			} elseif ( 'news' === get_post_type( $cid ) && function_exists( 'ricoman_news_img' ) ) {
				$img = ricoman_news_img( $cid, 'large' );
			}
			if ( ! $img ) {
				$img = (string) get_the_post_thumbnail_url( $cid, 'large' );
			}
			$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
			$label  = 'project' === get_post_type( $cid ) ? __( 'Project', 'ricoman' ) : __( 'News', 'ricoman' );
			$cards .= '<a class="rm-projcard" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov"><span class="rm-eyebrow">' . esc_html( $label ) . '</span><span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span></span></a>';
		}
		wp_reset_postdata();
		$out .= '<div class="rm-section"><div class="rm-pp-wrap"><h2 class="rm-shead">' . esc_html__( 'Projects & articles', 'ricoman' ) . '</h2><div class="rm-projgrid rm-prodgrid">' . $cards . '</div></div></div>';
	}

	return $out;
}, 9 );

/* ---------------------------------------------------------------------------
 * Insertable patterns.
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	$one = '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">'
		. '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Meet the team</h2><!-- /wp:heading -->'
		. '<!-- wp:shortcode -->[ricoman_team fields="photo,name,title,email"]<!-- /wp:shortcode -->'
		. '</div><!-- /wp:group -->';
	register_block_pattern( 'ricoman/team-grid', array( 'title' => __( 'Team · Grid (all staff)', 'ricoman' ), 'categories' => array( 'ricoman-page' ), 'content' => $one ) );
	register_block_pattern( 'ricoman/staff-one', array(
		'title'      => __( 'Team · Single member', 'ricoman' ),
		'categories' => array( 'ricoman-page' ),
		'content'    => '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section"><!-- wp:paragraph --><p>Replace 0 with the staff member ID, and choose fields:</p><!-- /wp:paragraph --><!-- wp:shortcode -->[ricoman_staff id="0" fields="photo,name,title,email,phone"]<!-- /wp:shortcode --></div><!-- /wp:group -->',
	) );
}, 13 );
