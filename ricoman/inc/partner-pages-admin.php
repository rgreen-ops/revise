<?php
/**
 * wp-admin → Ricoman → Prospect pages.
 *
 * Lets Marketing create their own bespoke prospect landing pages (see
 * partner-pages.php) without code: company name, URL slug, intro line, logo and
 * the Ricoman contact the page (and its contact form) belongs to. Saved in the
 * `ricoman_partner_pages_custom` option; hard-coded pages are listed read-only.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'ricoman-hub', 'Prospect pages', 'Prospect pages', 'edit_pages', 'ricoman-prospect-pages', 'ricoman_prospect_pages_admin' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'ricoman_page_ricoman-prospect-pages' === $hook ) {
		wp_enqueue_media();
	}
} );

/** Why a slug can't be used, or '' if it's free. */
function ricoman_prospect_slug_problem( $slug, $editing = '' ) {
	if ( '' === $slug ) {
		return 'Please enter a web address (slug).';
	}
	$code = ricoman_partner_pages();
	$mine = get_option( 'ricoman_partner_pages_custom', array() );
	if ( $slug !== $editing && ( isset( $code[ $slug ] ) || isset( $mine[ $slug ] ) ) ) {
		return 'ricoman.com/' . $slug . ' is already a prospect page.';
	}
	if ( in_array( $slug, array( 'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'products', 'projects', 'news', 'contact', 'about', 'feed', 'sitemap', 'cart', 'checkout' ), true ) ) {
		return 'ricoman.com/' . $slug . ' is a reserved address.';
	}
	foreach ( get_post_types( array( 'public' => true ) ) as $pt ) {
		if ( get_page_by_path( $slug, OBJECT, $pt ) ) {
			return 'ricoman.com/' . $slug . ' is already a page on the website. Pick another.';
		}
	}
	return '';
}

function ricoman_prospect_pages_admin() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$pages  = get_option( 'ricoman_partner_pages_custom', array() );
	$pages  = is_array( $pages ) ? $pages : array();
	$notice = '';
	$error  = '';
	$form   = array( 'slug' => '', 'name' => '', 'intro' => '', 'logo' => '', 'rep' => '', 'rep_mob' => '', 'rep_email' => '' );
	$edit   = isset( $_GET['edit'] ) ? sanitize_title( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( isset( $_POST['rm_pp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['rm_pp_nonce'] ), 'rm_pp_save' ) ) {
		if ( ! empty( $_POST['rm_pp_delete'] ) ) {
			$del = sanitize_title( wp_unslash( $_POST['rm_pp_delete'] ) );
			unset( $pages[ $del ] );
			update_option( 'ricoman_partner_pages_custom', $pages, false );
			$notice = 'Deleted ricoman.com/' . $del;
			$edit   = '';
		} else {
			$orig = sanitize_title( wp_unslash( $_POST['rm_pp_orig'] ?? '' ) );
			$form = array(
				'slug'      => sanitize_title( wp_unslash( $_POST['rm_pp_slug'] ?? '' ) ),
				'name'      => sanitize_text_field( wp_unslash( $_POST['rm_pp_name'] ?? '' ) ),
				'intro'     => sanitize_textarea_field( wp_unslash( $_POST['rm_pp_intro'] ?? '' ) ),
				'logo'      => esc_url_raw( wp_unslash( $_POST['rm_pp_logo'] ?? '' ) ),
				'rep'       => sanitize_text_field( wp_unslash( $_POST['rm_pp_rep'] ?? '' ) ),
				'rep_mob'   => sanitize_text_field( wp_unslash( $_POST['rm_pp_rep_mob'] ?? '' ) ),
				'rep_email' => sanitize_email( wp_unslash( $_POST['rm_pp_rep_email'] ?? '' ) ),
			);
			$error = ricoman_prospect_slug_problem( $form['slug'], $orig );
			if ( ! $error && ( '' === $form['name'] || '' === $form['intro'] ) ) {
				$error = 'Company name and intro are both needed.';
			}
			if ( ! $error ) {
				if ( $orig && $orig !== $form['slug'] ) {
					unset( $pages[ $orig ] );
				}
				$row = $form;
				unset( $row['slug'] );
				$pages[ $form['slug'] ] = $row;
				update_option( 'ricoman_partner_pages_custom', $pages, false );
				$notice = 'Saved. Live now at ricoman.com/' . $form['slug'];
				$form   = array_fill_keys( array_keys( $form ), '' );
				$edit   = '';
			} else {
				$edit = $orig;
			}
		}
	} elseif ( $edit && isset( $pages[ $edit ] ) ) {
		$form         = array_merge( $form, $pages[ $edit ] );
		$form['slug'] = $edit;
	}
	$base = admin_url( 'admin.php?page=ricoman-prospect-pages' );
	?>
	<div class="wrap">
		<h1>Prospect pages</h1>
		<p style="max-width:760px">Bespoke "Prepared for &lt;company&gt;" landing pages (like <a href="<?php echo esc_url( home_url( '/obi' ) ); ?>" target="_blank">ricoman.com/obi</a>). Hidden from Google, only people you send the link to will see them. Add <code>?to=FirstName</code> to the end of the link to greet a person by name, e.g. <code><?php echo esc_html( home_url( '/tsk?to=John' ) ); ?></code></p>
		<?php if ( $notice ) : ?><div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

		<table class="widefat striped" style="max-width:1000px;margin:16px 0 28px">
			<thead><tr><th>Company</th><th>Link</th><th>Ricoman contact</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( ricoman_partner_pages() as $slug => $p ) : $r = ricoman_partner_rep( $p ); $custom = isset( $pages[ $slug ] ); ?>
				<tr>
					<td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
					<td><a href="<?php echo esc_url( home_url( '/' . $slug ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/' . $slug ) ); ?></a></td>
					<td><?php echo esc_html( $r['name'] ); ?></td>
					<td><?php if ( $custom ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'edit', $slug, $base ) ); ?>">Edit</a>
						<form method="post" style="display:inline" onsubmit="return confirm('Delete this page? The link will stop working.');">
							<?php wp_nonce_field( 'rm_pp_save', 'rm_pp_nonce' ); ?>
							<button class="button-link-delete button" name="rm_pp_delete" value="<?php echo esc_attr( $slug ); ?>">Delete</button>
						</form>
					<?php else : ?><span style="color:#888">Built in</span><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php echo $edit ? 'Edit page' : 'Add a new page'; ?></h2>
		<form method="post" style="max-width:760px">
			<?php wp_nonce_field( 'rm_pp_save', 'rm_pp_nonce' ); ?>
			<input type="hidden" name="rm_pp_orig" value="<?php echo esc_attr( $edit ); ?>">
			<table class="form-table" role="presentation">
				<tr><th><label for="rm_pp_name">Company name</label></th><td><input class="regular-text" id="rm_pp_name" name="rm_pp_name" value="<?php echo esc_attr( $form['name'] ); ?>" placeholder="e.g. TSK" required><p class="description">Shown in the headline: "Lighting that finishes TSK's fit-outs".</p></td></tr>
				<tr><th><label for="rm_pp_slug">Web address</label></th><td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="rm_pp_slug" name="rm_pp_slug" value="<?php echo esc_attr( $form['slug'] ); ?>" placeholder="tsk" required style="width:220px"><p class="description">Short, lower-case, no spaces.</p></td></tr>
				<tr><th><label for="rm_pp_intro">Intro</label></th><td><textarea class="large-text" rows="4" id="rm_pp_intro" name="rm_pp_intro" required placeholder="One or two sentences about THEM, then: We make the lighting that finishes them, engineered in-house in Manchester, right on your doorstep."><?php echo esc_textarea( $form['intro'] ); ?></textarea><p class="description">Sits under "Made round the corner, not shipped round the world." Look at their website's About page and say what they do in their words.</p></td></tr>
				<tr><th>Their logo</th><td>
					<input type="hidden" id="rm_pp_logo" name="rm_pp_logo" value="<?php echo esc_attr( $form['logo'] ); ?>">
					<img id="rm_pp_logo_prev" src="<?php echo esc_url( $form['logo'] ); ?>" style="max-height:60px;<?php echo $form['logo'] ? '' : 'display:none;'; ?>background:#fff;padding:6px;border:1px solid #ddd;margin-bottom:6px"><br>
					<button type="button" class="button" id="rm_pp_logo_btn">Choose / upload logo</button>
					<button type="button" class="button-link" id="rm_pp_logo_clear">Remove</button>
					<p class="description">Dark logo on a transparent or white background works best. No logo = their name in bold.</p>
				</td></tr>
				<tr><th><label for="rm_pp_rep">Ricoman contact</label></th><td><input class="regular-text" id="rm_pp_rep" name="rm_pp_rep" value="<?php echo esc_attr( $form['rep'] ); ?>" placeholder="Richard Green"><p class="description">Who the page says to talk to. Leave blank for Richard.</p></td></tr>
				<tr><th><label for="rm_pp_rep_mob">Their mobile</label></th><td><input id="rm_pp_rep_mob" name="rm_pp_rep_mob" value="<?php echo esc_attr( $form['rep_mob'] ); ?>" placeholder="07802 832 849"><p class="description">Also used for the WhatsApp button.</p></td></tr>
				<tr><th><label for="rm_pp_rep_email">Their email</label></th><td><input class="regular-text" type="email" id="rm_pp_rep_email" name="rm_pp_rep_email" value="<?php echo esc_attr( $form['rep_email'] ); ?>" placeholder="rgreen@ricoman.com"><p class="description">The page's contact form goes here (Richard copied in).</p></td></tr>
			</table>
			<p><button class="button button-primary button-large"><?php echo $edit ? 'Save changes' : 'Create page'; ?></button>
			<?php if ( $edit ) : ?> <a class="button" href="<?php echo esc_url( $base ); ?>">Cancel</a><?php endif; ?></p>
		</form>
	</div>
	<script>
	(function($){
		var frame;
		$('#rm_pp_logo_btn').on('click',function(e){e.preventDefault();if(!frame){frame=wp.media({title:'Prospect logo',library:{type:'image'},multiple:false});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#rm_pp_logo').val(a.url);$('#rm_pp_logo_prev').attr('src',a.url).show();});}frame.open();});
		$('#rm_pp_logo_clear').on('click',function(){$('#rm_pp_logo').val('');$('#rm_pp_logo_prev').hide();});
	})(jQuery);
	</script>
	<?php
}
