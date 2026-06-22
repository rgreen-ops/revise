<?php
/**
 * Go Live — a pre-flight checklist + a single "finalise launch" button for the
 * one-time staging→live launch. Read-only checks, plus safe one-click cleanup
 * (caches, permalinks, SEO seeders). No Plesk needed for this part.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	$parent = menu_page_url( 'ricoman-hub', false ) ? 'ricoman-hub' : '';
	$cb     = 'ricoman_go_live_screen';
	if ( $parent ) {
		add_submenu_page( $parent, __( 'Go Live', 'ricoman' ), __( '🚀 Go Live', 'ricoman' ), 'manage_options', 'ricoman-go-live', $cb );
	} else {
		add_menu_page( __( 'Go Live', 'ricoman' ), __( '🚀 Go Live', 'ricoman' ), 'manage_options', 'ricoman-go-live', $cb, 'dashicons-flag', 58 );
	}
}, 29 );

/** Read-only launch readiness checks: array of [ok(bool), label, hint]. */
function ricoman_go_live_checks() {
	$c = array();

	$indexable = '1' === (string) get_option( 'blog_public' );
	$c[] = array( $indexable, __( 'Search engines can find this site', 'ricoman' ), $indexable ? __( 'Good — visible to Google.', 'ricoman' ) : __( 'Settings → Reading → untick “Discourage search engines”. (Only do this on LIVE, not staging.)', 'ricoman' ) );

	$perma = '' !== (string) get_option( 'permalink_structure' );
	$c[] = array( $perma, __( 'Pretty permalinks are on', 'ricoman' ), $perma ? __( 'Good.', 'ricoman' ) : __( 'Settings → Permalinks → choose Post name, Save.', 'ricoman' ) );

	$free = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$free_ok = ( false === $free ) || $free > 500 * 1024 * 1024;
	$c[] = array( $free_ok, __( 'Disk space is healthy', 'ricoman' ), ( false === $free ) ? __( 'Could not read disk space.', 'ricoman' ) : sprintf( /* translators: %s size */ __( '%s free.', 'ricoman' ), size_format( $free ) ) . ( $free_ok ? '' : ' ' . __( 'Low — clear old Plesk backups before launch.', 'ricoman' ) ) );

	$seo = (bool) get_option( 'ricoman_seo_seeded_v1' );
	$c[] = array( $seo, __( 'SEO titles / descriptions seeded', 'ricoman' ), $seo ? __( 'Done.', 'ricoman' ) : __( 'Click “Run launch finalisation” to seed them.', 'ricoman' ) );

	$ver = (bool) get_option( 'rm_products_ver' );
	$c[] = array( $ver, __( 'Product catalogue caches built', 'ricoman' ), $ver ? __( 'Built.', 'ricoman' ) : __( 'Will build automatically after finalisation / first visits.', 'ricoman' ) );

	$home = (int) get_option( 'page_on_front' );
	$c[] = array( $home > 0, __( 'A homepage is set', 'ricoman' ), $home > 0 ? __( 'Set.', 'ricoman' ) : __( 'Settings → Reading → set a static homepage.', 'ricoman' ) );

	return $c;
}

function ricoman_go_live_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$notice = get_transient( 'ricoman_go_live_notice' );
	if ( $notice ) {
		delete_transient( 'ricoman_go_live_notice' );
	}
	?>
	<div class="wrap">
		<h1>🚀 <?php esc_html_e( 'Go Live', 'ricoman' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $notice ); ?></p></div>
		<?php endif; ?>

		<p style="max-width:680px"><?php esc_html_e( 'Use this after copying staging onto live (see the launch guide). The checklist shows what’s ready; the button does the WordPress-side cleanup in one go.', 'ricoman' ); ?></p>

		<h2><?php esc_html_e( 'Pre-flight checklist', 'ricoman' ); ?></h2>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
			<?php foreach ( ricoman_go_live_checks() as $row ) : ?>
				<tr>
					<td style="width:34px;font-size:18px"><?php echo $row[0] ? '✅' : '⚠️'; ?></td>
					<td><strong><?php echo esc_html( $row[1] ); ?></strong><br><span class="description"><?php echo esc_html( $row[2] ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:26px"><?php esc_html_e( 'Finalise launch', 'ricoman' ); ?></h2>
		<p class="description" style="max-width:680px"><?php esc_html_e( 'Safe to run any time (and after each big change): clears caches, refreshes permalinks and internal links, and seeds any missing SEO copy. It never deletes content.', 'ricoman' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ricoman_go_live_finalise">
			<?php wp_nonce_field( 'ricoman_go_live', 'ricoman_go_live_nonce' ); ?>
			<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Run launch finalisation', 'ricoman' ); ?></button></p>
		</form>

		<p style="margin-top:18px"><em><?php esc_html_e( 'Full step-by-step (Plesk copy + backup/rollback) is in the launch guide: reference/launch-guide.md.', 'ricoman' ); ?></em></p>
	</div>
	<?php
}

add_action( 'admin_post_ricoman_go_live_finalise', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['ricoman_go_live_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_go_live_nonce'] ) ), 'ricoman_go_live' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$done = array();

	// 1) OPcache.
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$done[] = __( 'cleared PHP OPcache', 'ricoman' );
	}
	// 2) Rewrite rules / permalinks.
	flush_rewrite_rules( false );
	$done[] = __( 'refreshed permalinks', 'ricoman' );
	// 3) Product-derived caches.
	if ( function_exists( 'ricoman_products_ver' ) ) {
		update_option( 'rm_products_ver', (string) time(), false );
		$done[] = __( 'refreshed product caches', 'ricoman' );
	}
	// 4) SEO seeders (fill empties only — never overwrite edits).
	if ( function_exists( 'ricoman_page_seo_seed_all' ) ) {
		ricoman_page_seo_seed_all();
		$done[] = __( 'seeded page SEO', 'ricoman' );
	}
	if ( function_exists( 'ricoman_cat_seo_seed_all' ) ) {
		ricoman_cat_seo_seed_all();
		$done[] = __( 'seeded category SEO', 'ricoman' );
	}
	if ( function_exists( 'ricoman_footer_links_topup' ) ) {
		ricoman_footer_links_topup();
		$done[] = __( 'topped up footer links', 'ricoman' );
	}

	set_transient( 'ricoman_go_live_notice', esc_html__( 'Launch finalisation done: ', 'ricoman' ) . esc_html( implode( ', ', $done ) ) . '.', 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-go-live' ) );
	exit;
} );
