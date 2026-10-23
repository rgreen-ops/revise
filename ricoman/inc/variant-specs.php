<?php
/**
 * Variant Specifications hub.
 *
 * One screen to manage every variant axis (Wattage, Colour Temperature, IP,
 * Beam Angle, …) instead of digging through ~23 separate taxonomy screens. Each
 * axis stays its own taxonomy (so spec columns, the datasheet popup, filtering
 * and the configurator keep working) — this is just a single friendly surface
 * over them: see term counts, add a term inline, and jump to the full term
 * manager for rename/merge/delete.
 *
 * Variant Products → Spec Axes.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=variant-product',
		__( 'Variant Specifications', 'ricoman' ),
		__( 'Spec Axes', 'ricoman' ),
		'manage_categories',
		'ricoman-variant-specs',
		'ricoman_variant_specs_page'
	);
} );

/** Quick-add a term to an axis from the hub. */
add_action( 'admin_post_ricoman_specs_add_term', function () {
	if ( ! current_user_can( 'manage_categories' ) || ! check_admin_referer( 'ricoman_specs_add_term' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'ricoman' ) );
	}
	$tax  = isset( $_POST['tax'] ) ? sanitize_key( $_POST['tax'] ) : '';
	$name = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
	$axes = function_exists( 'ricoman_variant_axis_taxonomies' ) ? ricoman_variant_axis_taxonomies() : array();
	$back = admin_url( 'edit.php?post_type=variant-product&page=ricoman-variant-specs' );
	if ( isset( $axes[ $tax ] ) && '' !== $name && taxonomy_exists( $tax ) ) {
		wp_insert_term( $name, $tax );
		$back = add_query_arg( 'added', rawurlencode( $name ), $back ) . '#axis-' . $tax;
	}
	wp_safe_redirect( $back );
	exit;
} );

function ricoman_variant_specs_page() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$axes = function_exists( 'ricoman_variant_axis_taxonomies' ) ? ricoman_variant_axis_taxonomies() : array();
	?>
	<style>
		.rm-specs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:16px}
		.rm-spec-card{background:#fff;border:1px solid #e3e3e8;border-radius:12px;padding:16px 18px;box-shadow:0 1px 2px rgba(16,24,40,.06)}
		.rm-spec-card h2{font-size:15px;margin:0 0 2px;display:flex;align-items:center;justify-content:space-between;gap:8px}
		.rm-spec-card .cnt{font-size:11px;font-weight:600;color:#fff;background:#004899;border-radius:999px;padding:2px 9px}
		.rm-spec-card .cnt.zero{background:#c9ccd1}
		.rm-spec-terms{color:#646970;font-size:12.5px;margin:8px 0 12px;min-height:34px;line-height:1.5}
		.rm-spec-add{display:flex;gap:6px;margin:0 0 10px}
		.rm-spec-add input{flex:1;border:1px solid #d5d8dd;border-radius:7px;padding:6px 9px;font-size:13px}
		.rm-spec-foot{display:flex;justify-content:space-between;align-items:center}
		.rm-spec-foot a{font-size:13px;font-weight:600;text-decoration:none}
	</style>
	<div class="wrap">
		<h1><?php esc_html_e( 'Variant Specifications', 'ricoman' ); ?></h1>
		<p class="description" style="max-width:760px"><?php esc_html_e( 'Every spec axis used by the order codes. Add a value inline, or open a full list to rename, merge or delete. These power the Configure table columns, the variant datasheet popup, filtering and the configurator — they’re also bulk-editable via Import / Export.', 'ricoman' ); ?></p>
		<?php if ( isset( $_GET['added'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( __( 'Added “%s”.', 'ricoman' ), sanitize_text_field( wp_unslash( $_GET['added'] ) ) ) ); ?></p></div>
		<?php endif; ?>

		<div class="rm-specs-grid">
			<?php foreach ( $axes as $slug => $label ) :
				if ( ! taxonomy_exists( $slug ) ) {
					continue;
				}
				$terms   = get_terms( array( 'taxonomy' => $slug, 'hide_empty' => false, 'number' => 0 ) );
				$count   = is_wp_error( $terms ) ? 0 : count( $terms );
				$sample  = '';
				if ( $count ) {
					$names  = wp_list_pluck( array_slice( $terms, 0, 8 ), 'name' );
					$sample = esc_html( implode( ', ', $names ) );
					if ( $count > 8 ) {
						$sample .= ' <em>+' . ( $count - 8 ) . ' more</em>';
					}
				} else {
					$sample = '<em>' . esc_html__( 'No values yet.', 'ricoman' ) . '</em>';
				}
				$manage = admin_url( 'edit-tags.php?taxonomy=' . $slug . '&post_type=variant-product' );
				?>
				<div class="rm-spec-card" id="axis-<?php echo esc_attr( $slug ); ?>">
					<h2><?php echo esc_html( $label ); ?> <span class="cnt <?php echo $count ? '' : 'zero'; ?>"><?php echo (int) $count; ?></span></h2>
					<div class="rm-spec-terms"><?php echo $sample; // phpcs:ignore ?></div>
					<form class="rm-spec-add" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ricoman_specs_add_term">
						<input type="hidden" name="tax" value="<?php echo esc_attr( $slug ); ?>">
						<?php wp_nonce_field( 'ricoman_specs_add_term' ); ?>
						<input type="text" name="term" placeholder="<?php esc_attr_e( 'Add a value…', 'ricoman' ); ?>">
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Add', 'ricoman' ); ?></button>
					</form>
					<div class="rm-spec-foot">
						<a href="<?php echo esc_url( $manage ); ?>"><?php esc_html_e( 'Manage all values →', 'ricoman' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
