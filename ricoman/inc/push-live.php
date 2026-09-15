<?php
/**
 * Push to Live — a deliberate, hard-to-miss sign-off screen in WP admin that
 * publishes the approved theme code to the LIVE site. No Plesk, no GitHub UI.
 *
 * It triggers the manual "Deploy theme to LIVE" GitHub Action (deploy-live.yml)
 * via the GitHub API. CODE ONLY — it never touches live content / data / leads.
 *
 * Safety gates (so it never happens casually):
 *   - capability-gated (admins, filterable via ricoman_push_live_cap)
 *   - two confirmation tick-boxes + you must type PUBLISH
 *   - a final browser confirm() prompt
 *   - server re-checks all of the above + a nonce
 *
 * One-time setup (a developer/admin, once):
 *   define( 'RICOMAN_GH_TOKEN', 'github_pat_xxx' ); in wp-config.php
 *   (a fine-grained token with Actions: read/write on the repo) — OR paste a
 *   token on this screen. Repo + branch default below and are editable here.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Capability required to push to live. */
function ricoman_push_live_cap() {
	return apply_filters( 'ricoman_push_live_cap', 'manage_options' );
}

/** Config (constants win; else options; else sensible defaults). */
function ricoman_push_live_cfg() {
	$opt = get_option( 'ricoman_push_cfg', array() );
	return array(
		'owner'    => defined( 'RICOMAN_GH_OWNER' ) ? RICOMAN_GH_OWNER : ( $opt['owner'] ?? 'rgreen-ops' ),
		'repo'     => defined( 'RICOMAN_GH_REPO' ) ? RICOMAN_GH_REPO : ( $opt['repo'] ?? 'revise' ),
		'workflow' => 'deploy-live.yml',
		'ref'      => defined( 'RICOMAN_GH_REF' ) ? RICOMAN_GH_REF : ( $opt['ref'] ?? 'claude/wordpress-theme-s5u19q' ),
		'token'    => defined( 'RICOMAN_GH_TOKEN' ) ? RICOMAN_GH_TOKEN : ( $opt['token'] ?? '' ),
	);
}

add_action( 'admin_menu', function () {
	$cap = ricoman_push_live_cap();
	// Prefer the Ricoman hub; fall back to a top-level item if the hub isn't there.
	$parent = menu_page_url( 'ricoman-hub', false ) ? 'ricoman-hub' : '';
	if ( $parent ) {
		add_submenu_page( $parent, __( 'Push to Live', 'ricoman' ), __( '🚀 Push to Live', 'ricoman' ), $cap, 'ricoman-push-live', 'ricoman_push_live_screen' );
	} else {
		add_menu_page( __( 'Push to Live', 'ricoman' ), __( '🚀 Push to Live', 'ricoman' ), $cap, 'ricoman-push-live', 'ricoman_push_live_screen', 'dashicons-superhero', 59 );
	}
}, 30 );

/** The sign-off screen. */
function ricoman_push_live_screen() {
	if ( ! current_user_can( ricoman_push_live_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to push to live.', 'ricoman' ) );
	}
	$cfg     = ricoman_push_live_cfg();
	$has_tok = '' !== trim( (string) $cfg['token'] );
	$notice  = get_transient( 'ricoman_push_live_notice' );
	if ( $notice ) {
		delete_transient( 'ricoman_push_live_notice' );
	}
	$actions_url = 'https://github.com/' . rawurlencode( $cfg['owner'] ) . '/' . rawurlencode( $cfg['repo'] ) . '/actions/workflows/deploy-live.yml';
	?>
	<div class="wrap">
		<h1>🚀 <?php esc_html_e( 'Push to Live', 'ricoman' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo wp_kses_post( $notice['msg'] ); ?></p></div>
		<?php endif; ?>

		<div style="max-width:720px;border:2px solid #d63638;border-radius:12px;padding:22px 26px;background:#fff;margin:18px 0">
			<p style="font-size:15px;margin-top:0"><strong><?php esc_html_e( 'This publishes the approved website updates to the LIVE site (ricoman.com).', 'ricoman' ); ?></strong></p>
			<p style="color:#50575e"><?php esc_html_e( 'Visitors will see the changes within a minute. This updates the website’s code only — your products, pages, news, customer accounts and captured leads are NOT affected and NOT overwritten.', 'ricoman' ); ?></p>
			<p style="color:#50575e"><?php esc_html_e( 'Only do this once the changes have been checked and approved on staging.', 'ricoman' ); ?></p>

			<?php if ( ! $has_tok ) : ?>
				<div class="notice notice-warning inline" style="margin:14px 0"><p><?php esc_html_e( 'Push to Live isn’t connected yet. An admin needs to add the live connection token once (see “Connection” below).', 'ricoman' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rm-pushform" onsubmit="return confirm('<?php echo esc_js( __( 'Final check — publish these updates to the LIVE website now?', 'ricoman' ) ); ?>');">
				<input type="hidden" name="action" value="ricoman_push_live">
				<?php wp_nonce_field( 'ricoman_push_live', 'ricoman_push_live_nonce' ); ?>

				<label style="display:block;margin:14px 0"><input type="checkbox" class="rm-pchk"> <?php esc_html_e( 'I have checked and approved these changes on staging.', 'ricoman' ); ?></label>
				<label style="display:block;margin:14px 0"><input type="checkbox" class="rm-pchk"> <?php esc_html_e( 'I understand this updates the live website that customers see.', 'ricoman' ); ?></label>

				<p style="margin:18px 0 6px"><label for="rm-pconfirm"><strong><?php esc_html_e( 'Type PUBLISH to enable the button:', 'ricoman' ); ?></strong></label></p>
				<input type="text" id="rm-pconfirm" name="confirm_text" autocomplete="off" placeholder="PUBLISH" style="font-size:16px;letter-spacing:.08em;width:220px">

				<p style="margin-top:22px">
					<button type="submit" id="rm-pbtn" class="button button-primary button-hero" disabled <?php disabled( ! $has_tok ); ?> style="background:#d63638;border-color:#b32d2e">
						<?php esc_html_e( 'Publish to LIVE now', 'ricoman' ); ?>
					</button>
				</p>
			</form>
		</div>

		<p><a href="<?php echo esc_url( $actions_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View deployment history / status on GitHub →', 'ricoman' ); ?></a></p>

		<details style="max-width:720px;margin-top:18px">
			<summary style="cursor:pointer;font-weight:600"><?php esc_html_e( 'Connection (one-time admin setup)', 'ricoman' ); ?></summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;padding:16px;border:1px solid #dcdcde;border-radius:10px;background:#fff">
				<input type="hidden" name="action" value="ricoman_push_live_cfg">
				<?php wp_nonce_field( 'ricoman_push_live_cfg', 'ricoman_push_live_cfg_nonce' ); ?>
				<p class="description"><?php esc_html_e( 'Best practice: define RICOMAN_GH_TOKEN in wp-config.php instead of storing it here. Repo + branch can be left as the defaults unless they change.', 'ricoman' ); ?></p>
				<p><label><?php esc_html_e( 'Repo owner', 'ricoman' ); ?><br><input type="text" name="owner" value="<?php echo esc_attr( $cfg['owner'] ); ?>" class="regular-text"></label></p>
				<p><label><?php esc_html_e( 'Repo name', 'ricoman' ); ?><br><input type="text" name="repo" value="<?php echo esc_attr( $cfg['repo'] ); ?>" class="regular-text"></label></p>
				<p><label><?php esc_html_e( 'Branch (ref to deploy from)', 'ricoman' ); ?><br><input type="text" name="ref" value="<?php echo esc_attr( $cfg['ref'] ); ?>" class="regular-text"></label></p>
				<?php if ( ! defined( 'RICOMAN_GH_TOKEN' ) ) : ?>
					<p><label><?php esc_html_e( 'GitHub token (Actions: read/write)', 'ricoman' ); ?><br><input type="password" name="token" value="" placeholder="<?php echo $has_tok ? '••••••••（saved）' : 'github_pat_…'; ?>" class="regular-text" autocomplete="off"></label></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Token is set via RICOMAN_GH_TOKEN in wp-config.php (recommended).', 'ricoman' ); ?></p>
				<?php endif; ?>
				<p><button type="submit" class="button"><?php esc_html_e( 'Save connection', 'ricoman' ); ?></button></p>
			</form>
		</details>
	</div>

	<script>
	( function () {
		var form = document.getElementById( 'rm-pushform' );
		if ( ! form ) { return; }
		var btn = document.getElementById( 'rm-pbtn' );
		var conf = document.getElementById( 'rm-pconfirm' );
		var chks = form.querySelectorAll( '.rm-pchk' );
		var tokenReady = <?php echo $has_tok ? 'true' : 'false'; ?>;
		function refresh() {
			var allTicked = true;
			chks.forEach( function ( c ) { if ( ! c.checked ) { allTicked = false; } } );
			var typed = ( conf.value || '' ).trim().toUpperCase() === 'PUBLISH';
			btn.disabled = ! ( tokenReady && allTicked && typed );
		}
		chks.forEach( function ( c ) { c.addEventListener( 'change', refresh ); } );
		conf.addEventListener( 'input', refresh );
		refresh();
	} )();
	</script>
	<?php
}

/** Save the connection config. */
add_action( 'admin_post_ricoman_push_live_cfg', function () {
	if ( ! current_user_can( ricoman_push_live_cap() ) || ! isset( $_POST['ricoman_push_live_cfg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_push_live_cfg_nonce'] ) ), 'ricoman_push_live_cfg' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$cur = get_option( 'ricoman_push_cfg', array() );
	$cur['owner'] = isset( $_POST['owner'] ) ? sanitize_text_field( wp_unslash( $_POST['owner'] ) ) : '';
	$cur['repo']  = isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '';
	$cur['ref']   = isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '';
	if ( ! defined( 'RICOMAN_GH_TOKEN' ) && ! empty( $_POST['token'] ) ) {
		$cur['token'] = sanitize_text_field( wp_unslash( $_POST['token'] ) );
	}
	update_option( 'ricoman_push_cfg', $cur, false );
	set_transient( 'ricoman_push_live_notice', array( 'type' => 'success', 'msg' => __( 'Connection saved.', 'ricoman' ) ), 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-push-live' ) );
	exit;
} );

/** Trigger the live deploy after the full sign-off. */
add_action( 'admin_post_ricoman_push_live', function () {
	if ( ! current_user_can( ricoman_push_live_cap() ) || ! isset( $_POST['ricoman_push_live_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_push_live_nonce'] ) ), 'ricoman_push_live' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$typed = isset( $_POST['confirm_text'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['confirm_text'] ) ) ) ) : '';
	$fail  = function ( $msg ) {
		set_transient( 'ricoman_push_live_notice', array( 'type' => 'error', 'msg' => $msg ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=ricoman-push-live' ) );
		exit;
	};
	if ( 'PUBLISH' !== $typed ) {
		$fail( __( 'You must type PUBLISH to confirm. Nothing was pushed.', 'ricoman' ) );
	}
	$cfg = ricoman_push_live_cfg();
	if ( '' === trim( (string) $cfg['token'] ) ) {
		$fail( __( 'No live connection token is set. Ask an admin to add RICOMAN_GH_TOKEN. Nothing was pushed.', 'ricoman' ) );
	}
	$url  = 'https://api.github.com/repos/' . rawurlencode( $cfg['owner'] ) . '/' . rawurlencode( $cfg['repo'] ) . '/actions/workflows/' . rawurlencode( $cfg['workflow'] ) . '/dispatches';
	$resp = wp_remote_post( $url, array(
		'timeout' => 25,
		'headers' => array(
			'Authorization'        => 'Bearer ' . $cfg['token'],
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'Ricoman-PushToLive',
			'Content-Type'         => 'application/json',
		),
		'body'    => wp_json_encode( array( 'ref' => $cfg['ref'], 'inputs' => array( 'confirm' => 'DEPLOY' ) ) ),
	) );
	if ( is_wp_error( $resp ) ) {
		$fail( sprintf( /* translators: %s: error */ __( 'Could not reach the deploy service: %s. Nothing was pushed.', 'ricoman' ), esc_html( $resp->get_error_message() ) ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 204 === $code ) {
		set_transient( 'ricoman_push_live_notice', array( 'type' => 'success', 'msg' => __( '✅ Push to live started. The site will update within a minute or two — you can watch progress via the GitHub link below.', 'ricoman' ) ), 60 );
	} else {
		$body = wp_remote_retrieve_body( $resp );
		$fail( sprintf( /* translators: 1: HTTP code 2: response */ __( 'The deploy service refused the request (HTTP %1$d). %2$s', 'ricoman' ), $code, esc_html( wp_trim_words( (string) $body, 30 ) ) ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-push-live' ) );
	exit;
} );
