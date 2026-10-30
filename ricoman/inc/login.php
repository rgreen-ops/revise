<?php
/**
 * Ricoman-branded wp-login screen.
 *
 * Replaces the default WordPress login styling with the site's dark theme,
 * the Ricoman logo (linked home) and a brand-coloured button, so the back door
 * matches the front end. Pure CSS injected on the login page — no plugins.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'login_enqueue_scripts', function () {
	$logo = function_exists( 'ricoman_opt' ) ? ricoman_opt( 'brand_logo' ) : '';
	?>
	<style>
		body.login{background:#16161a;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
		body.login #login{width:340px;padding:6% 0 0}
		<?php if ( $logo ) : ?>
		body.login h1 a{background-image:url('<?php echo esc_url( $logo ); ?>');background-size:contain;background-position:center;width:210px;height:56px}
		<?php else : ?>
		/* No logo set — show the site name as a clean wordmark instead of the W mark. */
		body.login h1 a{background-image:none;width:auto;height:auto;text-indent:0;overflow:visible;font-size:1.7rem;font-weight:700;letter-spacing:.05em;line-height:1.1;color:#fff;text-transform:uppercase}
		<?php endif; ?>
		.login form{background:#fff;border:0;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.45);padding:26px 24px;margin-top:20px}
		.login form .input,.login input[type=text],.login input[type=password]{border:1px solid #d6d6da;border-radius:10px;padding:11px 12px;font-size:15px;background:#fff;color:#16161a}
		.login form .input:focus,.login input[type=text]:focus,.login input[type=password]:focus{border-color:#16161a;box-shadow:0 0 0 1px #16161a;outline:0}
		.login label{color:#16161a;font-size:14px}
		.wp-core-ui .button-primary{background:#16161a!important;border-color:#16161a!important;color:#fff!important;border-radius:10px;text-shadow:none;box-shadow:none;padding:7px 24px;font-weight:600}
		.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:#000!important;border-color:#000!important}
		.login .button.wp-hide-pw{color:#16161a}
		.login #nav,.login #backtoblog{text-align:center;padding:0;text-shadow:none}
		.login #nav a,.login #backtoblog a{color:rgba(255,255,255,.6)!important}
		.login #nav a:hover,.login #backtoblog a:hover{color:#fff!important}
		.login #login_error,.login .message,.login .success{border-radius:10px;border-left-color:#16161a;color:#16161a}
		/* Match the customer-type <select> to the text inputs (size, weight, font). */
		.login form select{display:block;width:100%;box-sizing:border-box;height:auto;margin:0;font-family:inherit;font-size:15px;font-weight:400;line-height:1.4;color:#16161a;background:#fff;border:1px solid #d6d6da;border-radius:10px;padding:11px 12px}
		.login form select:focus{border-color:#16161a;box-shadow:0 0 0 1px #16161a;outline:0}
		.login form label{font-weight:400}
		/* Email is the account identifier — hide the Username field on registration. */
		#registerform p:has(#user_login){display:none}
		/* Tidy: hide the language switcher on the login screen. */
		.login .language-switcher{display:none}
	</style>
	<?php
} );

/** Hide the Username field on the registration form (email is the identifier).
 *  CSS :has() handles modern browsers; this is the safe fallback. */
add_action( 'login_footer', function () {
	?>
	<script>(function(){var rf=document.getElementById('registerform');if(!rf)return;var u=rf.querySelector('#user_login');if(u){var p=u.closest('p');if(p)p.style.display='none';}})();</script>
	<?php
} );

/** Point the login logo + "back to" link at the live site, not wordpress.org. */
add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );
add_filter( 'login_headertext', function () {
	return get_bloginfo( 'name' );
} );
