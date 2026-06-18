<?php
/**
 * RICOBOT integration settings.
 *
 * RICOBOT is Ricoman's product/pricing engine. Public read-only API
 * (https://ricobot.ricoman.com), Authorization: Bearer <token>:
 *   GET /api/public/health                        -> { ok, version }
 *   GET /api/public/products                      -> { products:[...], total, page }
 *   GET /api/public/products/{code}               -> full detail (specs, finishes, price, datasheet)
 *   GET /api/public/products/{code}/datasheet.pdf -> PDF
 * Rate limit 60/min per token; responses are cached for 1 hour here so we never
 * hit it. The token is stored server-side only and is NEVER output to the front
 * end / JS — all calls go through PHP (wp_remote_get).
 *
 * Settings → RICOBOT.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read a RICOBOT option (constant overrides the stored value). */
function ricoman_ricobot_opt( $key, $default = '' ) {
	if ( 'url' === $key && defined( 'RICOMAN_RICOBOT_URL' ) && RICOMAN_RICOBOT_URL ) {
		return RICOMAN_RICOBOT_URL;
	}
	if ( 'key' === $key && defined( 'RICOMAN_RICOBOT_KEY' ) && RICOMAN_RICOBOT_KEY ) {
		return RICOMAN_RICOBOT_KEY;
	}
	$o = get_option( 'ricoman_ricobot', array() );
	return ( is_array( $o ) && isset( $o[ $key ] ) && '' !== $o[ $key ] ) ? $o[ $key ] : $default;
}

/** Is RICOBOT configured (at least a base URL)? */
function ricoman_ricobot_ready() {
	return (bool) ricoman_ricobot_opt( 'url' );
}

/**
 * Authenticated, cached GET against the RICOBOT API. Returns decoded data or
 * WP_Error. Results are cached for 1 hour (per path) to stay well under the
 * 60 req/min rate limit; pass $bypass_cache = true for the connection test.
 *
 * @param string $path        e.g. 'api/public/products/flow-plus'
 * @param bool   $bypass_cache Skip the transient cache for this call.
 * @return array|WP_Error
 */
function ricoman_ricobot_get( $path, $bypass_cache = false ) {
	$base = untrailingslashit( (string) ricoman_ricobot_opt( 'url' ) );
	if ( ! $base ) {
		return new WP_Error( 'ricobot_no_url', __( 'RICOBOT API URL is not set (Settings → RICOBOT).', 'ricoman' ) );
	}

	$cache_key = 'ricoman_rb_' . md5( $base . '|' . $path );
	if ( ! $bypass_cache ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$args = array( 'timeout' => 20, 'headers' => array( 'Accept' => 'application/json' ) );
	$key  = ricoman_ricobot_opt( 'key' );
	if ( $key ) {
		// Token is sent server-side only (Authorization: Bearer), never to JS.
		$args['headers']['Authorization'] = 'Bearer ' . $key;
	}
	$resp = wp_remote_get( $base . '/' . ltrim( $path, '/' ), $args );
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) {
		return new WP_Error( 'ricobot_http', sprintf( /* translators: %d: HTTP status */ __( 'RICOBOT returned HTTP %d.', 'ricoman' ), $code ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ricobot_json', __( 'RICOBOT did not return JSON.', 'ricoman' ) );
	}
	if ( ! $bypass_cache ) {
		set_transient( $cache_key, $data, HOUR_IN_SECONDS );
	}
	return $data;
}

/** Convenience wrappers matching the documented RICOBOT endpoints. */
function ricoman_ricobot_products() {
	return ricoman_ricobot_get( 'api/public/products' );
}
function ricoman_ricobot_product( $code ) {
	return ricoman_ricobot_get( 'api/public/products/' . rawurlencode( $code ) );
}

/** Page through the whole product catalogue (cached). Returns a flat array of products. */
function ricoman_ricobot_all_products() {
	$list  = array();
	$page  = 1;
	$guard = 0;
	do {
		$data = ricoman_ricobot_get( 'api/public/products?page=' . $page );
		if ( is_wp_error( $data ) ) {
			break;
		}
		$items = ( isset( $data['products'] ) && is_array( $data['products'] ) ) ? $data['products'] : ( is_array( $data ) ? $data : array() );
		$list  = array_merge( $list, $items );
		$total = isset( $data['total'] ) ? (int) $data['total'] : count( $list );
		$page++;
		$guard++;
	} while ( count( $items ) > 0 && count( $list ) < $total && $guard < 50 );
	return $list;
}

/* ---- Settings page ---- */
add_action( 'admin_menu', function () {
	add_options_page( __( 'RICOBOT', 'ricoman' ), __( 'RICOBOT', 'ricoman' ), 'manage_options', 'ricoman-ricobot', 'ricoman_ricobot_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'ricoman_ricobot_group', 'ricoman_ricobot', 'ricoman_ricobot_sanitize' );
} );

function ricoman_ricobot_sanitize( $input ) {
	return array(
		'url' => isset( $input['url'] ) ? esc_url_raw( trim( $input['url'] ) ) : '',
		'key' => isset( $input['key'] ) ? sanitize_text_field( trim( $input['key'] ) ) : '',
	);
}

function ricoman_ricobot_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Connection test.
	$test = null;
	if ( isset( $_POST['ricoman_ricobot_test'] ) && check_admin_referer( 'ricoman_ricobot_test' ) ) {
		$health = ricoman_ricobot_get( 'api/public/health', true );
		if ( is_wp_error( $health ) ) {
			$test = array( false, $health->get_error_message() );
		} else {
			$auth = ricoman_ricobot_get( 'api/public/products', true );
			$test = is_wp_error( $auth )
				? array( true, __( 'Reachable. API key not accepted yet (product endpoint needs a valid key): ', 'ricoman' ) . $auth->get_error_message() )
				: array( true, __( 'Connected — health OK and the products endpoint authenticated. ✓', 'ricoman' ) );
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'RICOBOT', 'ricoman' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Connect to the RICOBOT product engine so products can pull live specs and on-the-fly datasheets. Enter the API base URL and a key/token — no passwords are stored in the theme. Tip: use a read-only service token, not a personal login.', 'ricoman' ); ?></p>

		<?php if ( $test ) : ?>
			<div class="notice notice-<?php echo $test[0] ? 'success' : 'error'; ?>"><p><?php echo esc_html( $test[1] ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'ricoman_ricobot_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rb_url"><?php esc_html_e( 'API base URL', 'ricoman' ); ?></label></th>
					<td><input type="url" class="regular-text" id="rb_url" name="ricoman_ricobot[url]" value="<?php echo esc_attr( get_option( 'ricoman_ricobot' )['url'] ?? '' ); ?>" placeholder="https://ricobot.ricoman.com">
					<p class="description"><?php esc_html_e( 'No trailing slash (e.g. https://ricobot.ricoman.com). The theme calls /api/public/products and /api/public/products/{code}.', 'ricoman' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="rb_key"><?php esc_html_e( 'API key / token', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rb_key" name="ricoman_ricobot[key]" value="<?php echo esc_attr( get_option( 'ricoman_ricobot' )['key'] ?? '' ); ?>" autocomplete="off">
					<p class="description"><?php esc_html_e( 'Sent as both Authorization: Bearer and X-API-Key. Leave blank if the API is open.', 'ricoman' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<form method="post" style="margin-top:-8px">
			<?php wp_nonce_field( 'ricoman_ricobot_test' ); ?>
			<button type="submit" name="ricoman_ricobot_test" value="1" class="button"><?php esc_html_e( 'Test connection', 'ricoman' ); ?></button>
			<span class="description" style="margin-left:8px"><?php esc_html_e( 'Checks /api/public/health, then /api/public/products with your key.', 'ricoman' ); ?></span>
		</form>
	</div>
	<?php
}
